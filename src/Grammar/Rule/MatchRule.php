<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * A `match` rule: a single regex that, when matched, emits its scope and applies
 * its captures. Has no sub-patterns and never pushes onto the rule stack.
 */
final class MatchRule extends Rule
{
    private ?RegExpSourceList $cachedCompiled = null;

    /** @param array<int, ?int> $captures group number → CaptureRule id (or null) */
    public function __construct(
        int $id,
        ?string $name,
        public readonly RegExpSource $match,
        public readonly array $captures,
    ) {
        parent::__construct($id, $name, null);
    }

    public function collectPatterns(array $rulesById, RegExpSourceList $out): void
    {
        $out->push($this->match);
    }

    public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        $compiled = $this->cachedCompiled;
        if ($compiled === null) {
            $compiled = new RegExpSourceList();
            $this->collectPatterns($rulesById, $compiled);
            $this->cachedCompiled = $compiled;
        }

        return $compiled;
    }
}
