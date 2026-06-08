<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * An immutable linked stack of scope names (outermost at the bottom). Pushing a
 * space-separated scope string adds each segment as its own frame, mirroring
 * vscode-textmate's `ScopeStack`.
 */
final readonly class ScopeStack
{
    public function __construct(
        public ?ScopeStack $parent,
        public string $scopeName,
    ) {
    }

    public static function from(string ...$scopeNames): ?self
    {
        $stack = null;
        foreach ($scopeNames as $scopeName) {
            $stack = new self($stack, $scopeName);
        }

        return $stack;
    }

    public function push(string $scopeName): self
    {
        $stack = $this;
        foreach (self::splitScopes($scopeName) as $segment) {
            $stack = new self($stack, $segment);
        }

        return $stack;
    }

    /** Push a (possibly space-separated, possibly empty) scope onto a nullable stack. */
    public static function pushScopes(?self $stack, string $scopeName): ?self
    {
        foreach (self::splitScopes($scopeName) as $segment) {
            $stack = new self($stack, $segment);
        }

        return $stack;
    }

    /** @return list<string> outermost first */
    public function toArray(): array
    {
        $result = [];
        for ($node = $this; $node !== null; $node = $node->parent) {
            if ($node->scopeName !== '') {
                array_unshift($result, $node->scopeName);
            }
        }

        return $result;
    }

    public function equals(?self $other): bool
    {
        $a = $this;
        $b = $other;
        while (true) {
            if ($a === $b) {
                return true;
            }
            if ($a === null || $b === null) {
                return false;
            }
            if ($a->scopeName !== $b->scopeName) {
                return false;
            }
            $a = $a->parent;
            $b = $b->parent;
        }
    }

    /** @return list<string> */
    private static function splitScopes(string $scopeName): array
    {
        $segments = preg_split('/ +/', trim($scopeName)) ?: [];
        return array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));
    }
}
