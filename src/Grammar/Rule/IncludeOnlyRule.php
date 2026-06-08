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
        $out = new RegExpSourceList();
        $this->collectPatterns($rulesById, $out);
        return $out;
    }
}
