<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * A `begin`/`while` rule: matches `begin`, pushes itself, and stays active as
 * long as `while` matches at the start of each subsequent line. `while` may carry
 * back-references resolved against the begin match.
 */
final class BeginWhileRule extends Rule
{
    /**
     * @param array<int, ?int> $beginCaptures group number → CaptureRule id
     * @param array<int, ?int> $whileCaptures group number → CaptureRule id
     * @param list<int> $patterns sub-rule ids
     */
    public function __construct(
        int $id,
        ?string $name,
        ?string $contentName,
        public readonly RegExpSource $begin,
        public readonly array $beginCaptures,
        public readonly RegExpSource $while,
        public readonly array $whileCaptures,
        public readonly array $patterns,
    ) {
        parent::__construct($id, $name, $contentName);
    }

    private ?RegExpSourceList $cachedCompiled = null;

    /** @var array<string, RegExpSourceList> while-list keyed by the resolved while source */
    private array $cachedWhile = [];

    public function whileHasBackReferences(): bool
    {
        return $this->while->hasBackReferences;
    }

    public function collectPatterns(array $rulesById, RegExpSourceList $out): void
    {
        $out->push($this->begin);
    }

    public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        if ($this->cachedCompiled === null) {
            $this->cachedCompiled = new RegExpSourceList();
            foreach ($this->patterns as $ruleId) {
                $rule = $rulesById[$ruleId] ?? null;
                $rule?->collectPatterns($rulesById, $this->cachedCompiled);
            }
        }

        return $this->cachedCompiled;
    }

    public function compileWhile(?string $whileRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        $key = $whileRegexSource ?? "\0";
        if (isset($this->cachedWhile[$key])) {
            return $this->cachedWhile[$key];
        }

        $out = new RegExpSourceList();
        $out->push($whileRegexSource !== null ? new RegExpSource($whileRegexSource, $this->id) : $this->while);

        return $this->cachedWhile[$key] = $out;
    }
}
