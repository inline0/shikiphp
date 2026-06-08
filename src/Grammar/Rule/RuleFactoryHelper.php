<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

use Shikiphp\Grammar\RawGrammar;

/**
 * Seam the RuleFactory uses to mint rule ids, fetch already-compiled rules, and
 * resolve `include` references — both inside the current grammar (`#name`,
 * `$self`, `$base`) and into external grammars by scope name. Implemented by the
 * Registry.
 */
interface RuleFactoryHelper
{
    public function nextRuleId(): int;

    public function registerRule(Rule $rule): Rule;

    public function getRule(int $id): Rule;

    /** Resolve a `#name` reference against the grammar currently being compiled. */
    public function resolveRepositoryReference(string $name): ?int;

    /** Resolve `$self` for the grammar currently being compiled. */
    public function resolveSelf(): ?int;

    /** Resolve `$base` (the root grammar of the current include chain). */
    public function resolveBase(): ?int;

    /**
     * Resolve an external `scope` or `scope#name` include, lazily loading the
     * external grammar through the registry's resolver. Returns the root rule id.
     */
    public function resolveExternalInclude(string $scopeName, ?string $ruleName): ?int;

    public function currentGrammar(): RawGrammar;
}
