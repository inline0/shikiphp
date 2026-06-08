<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

use Shikiphp\Grammar\Exceptions\Grammar as GrammarException;
use Shikiphp\Grammar\Rule\IncludeOnlyRule;
use Shikiphp\Grammar\Rule\Rule;
use Shikiphp\Grammar\Rule\RuleFactory;
use Shikiphp\Grammar\Rule\RuleFactoryHelper;

/**
 * Owns grammars keyed by scope name, mints and caches rule ids globally, and
 * resolves `include` references — repository (`#name`), `$self`, `$base`, and
 * external (`scope` / `scope#name`) — lazily pulling unknown grammars through a
 * resolver callback. Mirrors vscode-textmate's `SyncRegistry` + grammar wiring.
 */
final class Registry implements RuleFactoryHelper
{
    /** @var array<string, RawGrammar> */
    private array $rawGrammars = [];

    /** @var array<string, Grammar> */
    private array $grammars = [];

    /** @var array<int, Rule> */
    private array $rulesById = [];

    private int $lastRuleId = 0;

    /** @var array<string, array<string, int>> per-scope repository name → root rule id */
    private array $repositoryCache = [];

    /** @var array<string, int> per-scope `$self` root rule id */
    private array $selfCache = [];

    /** @var list<RawGrammar> compilation context stack (innermost last) */
    private array $compileStack = [];

    /** @var list<array<array-key, mixed>> nested-repository context stack (innermost last) */
    private array $repoStack = [];

    private ?RawGrammar $baseGrammar = null;

    /** @var (callable(string): ?array<string, mixed>)|null */
    private $resolver;

    /** @param (callable(string): ?array<string, mixed>)|null $resolver returns raw grammar JSON for a scope */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /** @param array<string, mixed> $raw */
    public function loadGrammarFromRaw(array $raw): Grammar
    {
        return $this->loadRawGrammar(RawGrammar::fromArray($raw));
    }

    public function loadRawGrammar(RawGrammar $raw): Grammar
    {
        $this->rawGrammars[$raw->scopeName] = $raw;
        if (isset($this->grammars[$raw->scopeName])) {
            return $this->grammars[$raw->scopeName];
        }

        $grammar = new Grammar($raw, $this);
        $this->grammars[$raw->scopeName] = $grammar;
        return $grammar;
    }

    public function loadGrammar(string $scopeName): Grammar
    {
        if (isset($this->grammars[$scopeName])) {
            return $this->grammars[$scopeName];
        }

        $raw = $this->lookupRawGrammar($scopeName);
        if ($raw === null) {
            throw GrammarException::notLoaded($scopeName);
        }

        return $this->loadRawGrammar($raw);
    }

    public function hasGrammar(string $scopeName): bool
    {
        return isset($this->grammars[$scopeName]) || isset($this->rawGrammars[$scopeName]);
    }

    public function getRawGrammar(string $scopeName): ?RawGrammar
    {
        return $this->lookupRawGrammar($scopeName);
    }

    /** @return array<int, Rule> */
    public function rulesById(): array
    {
        return $this->rulesById;
    }

    /**
     * Compile a grammar's top-level `patterns` (with `$self`/`$base` bound to it)
     * into a root IncludeOnlyRule and return its id.
     */
    public function compileGrammarRoot(RawGrammar $raw): int
    {
        if (isset($this->selfCache[$raw->scopeName])) {
            return $this->selfCache[$raw->scopeName];
        }

        $rootId = $this->nextRuleId();
        $this->selfCache[$raw->scopeName] = $rootId;

        $this->pushCompile($raw);
        $patterns = RuleFactory::compileRootPatterns($raw->patterns, $this);
        $this->popCompile();

        $this->registerRule(new IncludeOnlyRule($rootId, $raw->scopeName, null, $patterns['ids'], $patterns['hasMissing']));

        return $rootId;
    }

    public function nextRuleId(): int
    {
        return ++$this->lastRuleId;
    }

    public function registerRule(Rule $rule): Rule
    {
        $this->rulesById[$rule->id] = $rule;
        return $rule;
    }

    public function getRule(int $id): Rule
    {
        return $this->rulesById[$id] ?? throw new \OutOfBoundsException("No rule with id {$id}.");
    }

    public function currentGrammar(): RawGrammar
    {
        $current = $this->compileStack[count($this->compileStack) - 1] ?? null;
        assert($current !== null);
        return $current;
    }

    public function resolveRepositoryReference(string $name): ?int
    {
        for ($i = count($this->repoStack) - 1; $i >= 0; $i--) {
            if (!isset($this->repoStack[$i][$name]) || !is_array($this->repoStack[$i][$name])) {
                continue;
            }
            $entry = &$this->repoStack[$i][$name];
            if (isset($entry['__ruleId']) && is_int($entry['__ruleId'])) {
                return $entry['__ruleId'];
            }
            $id = $this->nextRuleId();
            $entry['__ruleId'] = $id;
            RuleFactory::getCompiledRuleId($entry, $this, $id);
            return $id;
        }

        $current = $this->currentGrammar();
        return $this->resolveRepositoryFor($current, $name);
    }

    /** @param array<array-key, mixed>|null $repository a rule's own nested repository, or null. */
    public function pushRepository(?array $repository): void
    {
        if ($repository !== null) {
            $this->repoStack[] = $repository;
        }
    }

    /** @param array<array-key, mixed>|null $repository the repository pushed by the matching pushRepository call. */
    public function popRepository(?array $repository): void
    {
        if ($repository !== null) {
            array_pop($this->repoStack);
        }
    }

    public function resolveSelf(): int
    {
        return $this->compileGrammarRoot($this->currentGrammar());
    }

    public function resolveBase(): int
    {
        $base = $this->baseGrammar ?? $this->currentGrammar();
        return $this->compileGrammarRoot($base);
    }

    public function resolveExternalInclude(string $scopeName, ?string $ruleName): ?int
    {
        $raw = $this->lookupRawGrammar($scopeName);
        if ($raw === null) {
            return null;
        }

        if ($ruleName === null) {
            return $this->compileGrammarRoot($raw);
        }

        $this->pushCompile($raw);
        $id = $this->resolveRepositoryFor($raw, $ruleName);
        $this->popCompile();
        return $id;
    }

    /**
     * Parse an `injectionSelector` into its comma-separated scope-path selectors.
     *
     * @return list<string>
     */
    public static function parseInjectionSelector(string $selector): array
    {
        $parts = explode(',', $selector);
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * The scope names of grammars that inject into the given scope (via
     * `injectionSelector` or the grammar's own `injections` map).
     *
     * @return list<string>
     */
    public function injectionScopesFor(string $scopeName): array
    {
        $out = [];
        foreach ($this->rawGrammars as $raw) {
            if ($raw->injectionSelector === null) {
                continue;
            }
            foreach (self::parseInjectionSelector($raw->injectionSelector) as $selector) {
                if (self::selectorTargetsScope($selector, $scopeName)) {
                    $out[] = $raw->scopeName;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Compile every injection that applies to a grammar: the grammar's own
     * `injections` map plus any other grammar whose `injectionSelector` targets
     * its scope. Ordered most-specific selector first (descending priority).
     *
     * @return list<Injection>
     */
    public function collectInjections(RawGrammar $raw): array
    {
        $injections = [];

        foreach ($raw->injections as $selector => $desc) {
            $this->pushCompile($raw);
            $ruleId = RuleFactory::getCompiledRuleId($desc, $this);
            $this->popCompile();
            $this->appendInjection($injections, $selector, $ruleId);
        }

        foreach ($this->injectionScopesFor($raw->scopeName) as $injectionScope) {
            $injectionRaw = $this->lookupRawGrammar($injectionScope);
            if ($injectionRaw === null || $injectionRaw->injectionSelector === null) {
                continue;
            }
            $rootId = $this->compileGrammarRoot($injectionRaw);
            $this->appendInjection($injections, $injectionRaw->injectionSelector, $rootId);
        }

        usort($injections, static fn (Injection $a, Injection $b): int => $b->priority <=> $a->priority);

        return $injections;
    }

    /** @param list<Injection> $injections */
    private function appendInjection(array &$injections, string $selector, int $ruleId): void
    {
        foreach (Matcher::create($selector) as $matcherWithPriority) {
            $injections[] = new Injection(
                $selector,
                $matcherWithPriority['matcher'],
                $matcherWithPriority['priority'],
                $ruleId,
                str_ends_with(trim($selector), '$'),
            );
        }
    }

    private static function selectorTargetsScope(string $selector, string $scopeName): bool
    {
        foreach (preg_split('/\s+/', trim($selector)) ?: [] as $token) {
            $token = ltrim($token, 'L:R:-');
            if ($token === $scopeName) {
                return true;
            }
            if (str_starts_with($scopeName, $token . '.')) {
                return true;
            }
        }

        return false;
    }

    private function resolveRepositoryFor(RawGrammar $raw, string $name): ?int
    {
        $cache = $this->repositoryCache[$raw->scopeName] ?? [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        if ($name === '$self') {
            return $this->compileGrammarRoot($raw);
        }
        if ($name === '$base') {
            return $this->resolveBase();
        }

        $entry = $raw->repository[$name] ?? null;
        if ($entry === null) {
            return null;
        }

        $id = $this->nextRuleId();
        $this->repositoryCache[$raw->scopeName][$name] = $id;

        $this->pushCompile($raw);
        RuleFactory::getCompiledRuleId($entry, $this, $id);
        $this->popCompile();

        return $id;
    }

    private function lookupRawGrammar(string $scopeName): ?RawGrammar
    {
        if (isset($this->rawGrammars[$scopeName])) {
            return $this->rawGrammars[$scopeName];
        }

        if ($this->resolver === null) {
            return null;
        }

        $raw = ($this->resolver)($scopeName);
        if ($raw === null) {
            return null;
        }

        $parsed = RawGrammar::fromArray($raw);
        $this->rawGrammars[$parsed->scopeName] = $parsed;
        return $parsed;
    }

    private function pushCompile(RawGrammar $raw): void
    {
        if ($this->compileStack === []) {
            $this->baseGrammar = $raw;
        }
        $this->compileStack[] = $raw;
    }

    private function popCompile(): void
    {
        array_pop($this->compileStack);
        if ($this->compileStack === []) {
            $this->baseGrammar = null;
        }
    }
}
