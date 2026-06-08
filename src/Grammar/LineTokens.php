<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * Accumulates gap-free {@see Token} spans for one line. Each produced token
 * carries the scope path that was active when it was opened, mirroring
 * vscode-textmate's `LineTokens`.
 */
final class LineTokens
{
    /** @var list<Token> */
    private array $tokens = [];

    private int $lastTokenEndIndex = 0;

    public function produce(StateStack $stack, int $endIndex): void
    {
        $this->produceFromScopes($stack->contentNameScopesList, $endIndex);
    }

    public function produceFromScopes(?ScopeStack $scopesList, int $endIndex): void
    {
        if ($this->lastTokenEndIndex >= $endIndex) {
            return;
        }

        $scopes = $scopesList?->toArray() ?? [];
        $this->tokens[] = new Token($this->lastTokenEndIndex, $endIndex, $scopes);
        $this->lastTokenEndIndex = $endIndex;
    }

    /**
     * The line was tokenized with a trailing `\n` appended; drop the token that
     * covers only that newline and guarantee at least one token spanning the line.
     *
     * @return list<Token>
     */
    public function getResult(StateStack $stack, int $lineLength): array
    {
        $count = count($this->tokens);
        if ($count !== 0 && $this->tokens[$count - 1]->startIndex === $lineLength - 1) {
            array_pop($this->tokens);
        }

        if ($this->tokens === []) {
            $this->lastTokenEndIndex = -1;
            $this->produce($stack, $lineLength);
            $first = $this->tokens[0];
            $this->tokens[0] = new Token(0, $first->endIndex, $first->scopes);
        }

        return $this->tokens;
    }
}
