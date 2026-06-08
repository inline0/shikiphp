<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * A `begin`/`end` rule: matches `begin`, pushes itself, tokenizes its `patterns`
 * until `end` matches. `end` may carry back-references resolved against the begin
 * match; `applyEndPatternLast` controls whether `end` is tried after the
 * sub-patterns.
 */
final class BeginEndRule extends Rule
{
    /**
     * @param array<int, ?int> $beginCaptures group number → CaptureRule id
     * @param array<int, ?int> $endCaptures group number → CaptureRule id
     * @param list<int> $patterns sub-rule ids
     */
    public function __construct(
        int $id,
        ?string $name,
        ?string $contentName,
        public readonly RegExpSource $begin,
        public readonly array $beginCaptures,
        public readonly RegExpSource $end,
        public readonly array $endCaptures,
        public readonly array $patterns,
        public readonly bool $applyEndPatternLast,
    ) {
        parent::__construct($id, $name, $contentName);
    }

    public function endHasBackReferences(): bool
    {
        return $this->end->hasBackReferences;
    }

    public function collectPatterns(array $rulesById, RegExpSourceList $out): void
    {
        $out->push($this->begin);
    }

    public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        $out = new RegExpSourceList();

        $endSource = $endRegexSource !== null
            ? new RegExpSource($endRegexSource, self::END_RULE_ID)
            : $this->end;

        if ($this->applyEndPatternLast) {
            $this->collectChildPatterns($rulesById, $out);
            $out->push($endSource);
        } else {
            $out->push($endSource);
            $this->collectChildPatterns($rulesById, $out);
        }

        return $out;
    }

    /** @param array<int, Rule> $rulesById */
    private function collectChildPatterns(array $rulesById, RegExpSourceList $out): void
    {
        foreach ($this->patterns as $ruleId) {
            $rule = $rulesById[$ruleId] ?? null;
            $rule?->collectPatterns($rulesById, $out);
        }
    }
}
