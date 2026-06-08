<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

use Shikiphp\Oniguruma\OnigScanner;

/**
 * An ordered list of RegExpSource plus a cache of compiled OnigScanners keyed by
 * the four `\A`/`\G` anchor variants, mirroring vscode-textmate's
 * `RegExpSourceList` / `CompiledRule`.
 */
final class RegExpSourceList
{
    /** @var list<RegExpSource> */
    private array $sources = [];

    private bool $hasAnchors = false;

    private ?CompiledRule $cached = null;

    /** @var array<string, CompiledRule> */
    private array $anchorCache = [];

    public function push(RegExpSource $source): void
    {
        $this->sources[] = $source;
        $this->hasAnchors = $this->hasAnchors || $source->hasAnchor;
    }

    public function unshift(RegExpSource $source): void
    {
        array_unshift($this->sources, $source);
        $this->hasAnchors = $this->hasAnchors || $source->hasAnchor;
    }

    public function length(): int
    {
        return count($this->sources);
    }

    /** @return list<RegExpSource> */
    public function sources(): array
    {
        return $this->sources;
    }

    public function compile(bool $allowA, bool $allowG): CompiledRule
    {
        if (!$this->hasAnchors) {
            return $this->cached ??= $this->resolveCompiled(false, false);
        }

        $key = ($allowA ? 'A' : '-') . ($allowG ? 'G' : '-');
        return $this->anchorCache[$key] ??= $this->resolveCompiled($allowA, $allowG);
    }

    private function resolveCompiled(bool $allowA, bool $allowG): CompiledRule
    {
        $patterns = [];
        $ruleIds = [];
        foreach ($this->sources as $source) {
            $patterns[] = $source->resolveAnchors($allowA, $allowG);
            $ruleIds[] = $source->ruleId;
        }

        return new CompiledRule(new OnigScanner($patterns), $ruleIds);
    }
}
