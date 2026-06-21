<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * A rule that only groups sub-patterns (the grammar's top level, repository
 * entries, or a bare `{ "patterns": [...] }`). It contributes no regex of its
 * own; its patterns are expanded recursively when building a scanner.
 */
final class IncludeOnlyRule extends Rule
{
    public readonly bool $hasMissingPatterns;

    private ?RegExpSourceList $cachedCompiled = null;

    /** @param list<int> $patterns sub-rule ids (Include rules are resolved at compile time) */
    public function __construct(
        int $id,
        ?string $name,
        ?string $contentName,
        public readonly array $patterns,
        bool $hasMissingPatterns,
    ) {
        parent::__construct($id, $name, $contentName);
        $this->hasMissingPatterns = $hasMissingPatterns;
    }

    public function collectPatterns(array $rulesById, RegExpSourceList $out): void
    {
        foreach ($this->patterns as $ruleId) {
            $rule = $rulesById[$ruleId] ?? null;
            $rule?->collectPatterns($rulesById, $out);
        }
    }

    public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        // The expanded pattern list is static (rulesById is fixed for the rule's
        // grammar) and independent of the anchor flags, which the returned list's
        // own compile() resolves per variant. Build it once.
        $cached = $this->cachedCompiled;
        if ($cached === null) {
            $cached = new RegExpSourceList();
            $this->collectPatterns($rulesById, $cached);
            $this->cachedCompiled = $cached;
        }

        return $cached;
    }
}
