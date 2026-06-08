<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

use Closure;

/**
 * One compiled grammar injection: a selector matcher (with priority), the rule
 * id to run, and whether the selector ends in `$` (matching against the prefix
 * of the scope path). Mirrors vscode-textmate's `Injection`.
 */
final readonly class Injection
{
    /** @var Closure(array<array-key, mixed>): bool */
    private Closure $matcher;

    /** @param callable(array<array-key, mixed>): bool $matcher */
    public function __construct(
        public string $debugSelector,
        callable $matcher,
        public int $priority,
        public int $ruleId,
        public bool $matchesAnyScope,
    ) {
        $this->matcher = Closure::fromCallable($matcher);
    }

    /** @param list<string> $scopes */
    public function matches(array $scopes): bool
    {
        return ($this->matcher)($scopes);
    }
}
