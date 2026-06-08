<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * The tokens produced for one line plus the rule stack to carry into the next
 * line. `stoppedEarly` is set when tokenization bailed on a runaway line.
 */
final readonly class TokenizeLineResult
{
    /** @param list<Token> $tokens */
    public function __construct(
        public array $tokens,
        public StateStack $ruleStack,
        public bool $stoppedEarly = false,
    ) {
    }
}
