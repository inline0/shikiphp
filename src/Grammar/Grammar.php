<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

use Shikiphp\Grammar\Rule\Rule;

/**
 * A loaded TextMate grammar bound to its Registry. Holds the root scope and
 * compiled rule graph and is the entry point for tokenization.
 *
 * `tokenizeLine` is intentionally a stub: the tokenizer is a separate subsystem
 * (ARCHITECTURE §4) that fills it in against this seam.
 */
final class Grammar
{
    private ?int $rootRuleId = null;

    /** @var list<Injection>|null */
    private ?array $injections = null;

    public function __construct(
        private readonly RawGrammar $raw,
        private readonly Registry $registry,
    ) {
    }

    public function scopeName(): string
    {
        return $this->raw->scopeName;
    }

    public function raw(): RawGrammar
    {
        return $this->raw;
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    public function rootScopeName(): string
    {
        return $this->raw->scopeName;
    }

    public function rootRuleId(): int
    {
        return $this->rootRuleId ??= $this->registry->compileGrammarRoot($this->raw);
    }

    public function getRule(int $id): Rule
    {
        return $this->registry->getRule($id);
    }

    /** @return array<int, Rule> */
    public function rulesById(): array
    {
        return $this->registry->rulesById();
    }

    /**
     * Build the initial rule stack for tokenizing from the top of this grammar.
     */
    public function initialState(): StateStack
    {
        $rootId = $this->rootRuleId();
        $scopeStack = ScopeStack::from($this->raw->scopeName);
        return StateStack::root($rootId, $scopeStack);
    }

    /** @return list<Injection> */
    public function injections(): array
    {
        return $this->injections ??= $this->registry->collectInjections($this->raw);
    }

    public function tokenizeLine(string $line, ?StateStack $prevState): TokenizeLineResult
    {
        $this->rootRuleId();
        return (new Tokenizer($this))->tokenizeLine($line, $prevState);
    }
}
