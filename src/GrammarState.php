<?php

declare(strict_types=1);

namespace Shikiphp;

use Shikiphp\Exceptions\Highlight;
use Shikiphp\Grammar\StateStack;

/**
 * Holds the tokenizer's rule stack after a tokenization, keyed by theme name,
 * so highlighting can resume from an intermediate point (incremental
 * highlighting). Port of @shikijs/core's `GrammarState`.
 */
final class GrammarState
{
    /**
     * @param array<string, StateStack> $stacks theme name → final rule stack
     */
    public function __construct(
        private readonly array $stacks,
        public readonly string $lang,
    ) {
    }

    public function theme(): string
    {
        return array_key_first($this->stacks) ?? '';
    }

    /** @return list<string> */
    public function themes(): array
    {
        return array_keys($this->stacks);
    }

    public function getInternalStack(?string $theme = null): ?StateStack
    {
        return $this->stacks[$theme ?? $this->theme()] ?? null;
    }

    /** @return list<string> the scope names in effect, innermost first */
    public function getScopes(?string $theme = null): array
    {
        $stack = $this->getInternalStack($theme);
        if ($stack === null) {
            throw Highlight::invalidGrammarState();
        }

        return self::scopesOf($stack);
    }

    public function withTheme(string $theme): self
    {
        if (!isset($this->stacks[$theme])) {
            throw Highlight::invalidGrammarState();
        }

        return new self([$theme => $this->stacks[$theme]], $this->lang);
    }

    /** @return list<string> */
    private static function scopesOf(StateStack $stack): array
    {
        $scopes = [];
        $node = $stack;
        while ($node !== null) {
            $name = $node->nameScopesList?->scopeName;
            if ($name !== null && $name !== '') {
                $scopes[] = $name;
            }
            $node = $node->parent;
        }

        return $scopes;
    }
}
