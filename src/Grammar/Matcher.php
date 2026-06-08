<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * Compiles a TextMate scope selector (e.g. `source.js -comment, L:text`) into a
 * set of predicates over a scope path with priorities, a port of
 * vscode-textmate's `matcher.ts`.
 */
final class Matcher
{
    /** @var list<string> */
    private array $tokens;

    private int $pos = 0;

    private ?string $token;

    private function __construct(string $expression)
    {
        $this->tokens = self::tokenize($expression);
        $this->token = $this->tokens[0] ?? null;
        $this->pos = 1;
    }

    /**
     * @return list<array{matcher: callable(array<array-key, mixed>): bool, priority: int}>
     */
    public static function create(string $selector): array
    {
        $parser = new self($selector);
        $results = [];

        while ($parser->token !== null) {
            $priority = 0;
            if (strlen($parser->token) === 2 && $parser->token[1] === ':') {
                $priority = match ($parser->token[0]) {
                    'R' => 1,
                    'L' => -1,
                    default => 0,
                };
                $parser->advance();
            }

            $matcher = $parser->parseConjunction();
            $results[] = ['matcher' => $matcher, 'priority' => $priority];

            if ($parser->token !== ',') {
                break;
            }
            $parser->advance();
        }

        return $results;
    }

    /** @return callable(array<array-key, mixed>): bool */
    private function parseConjunction(): callable
    {
        $matchers = [];
        $matcher = $this->parseOperand();
        while ($matcher !== null) {
            $matchers[] = $matcher;
            $matcher = $this->parseOperand();
        }

        return static fn (array $names): bool => self::all($matchers, $names);
    }

    /** @return (callable(array<array-key, mixed>): bool)|null */
    private function parseOperand(): ?callable
    {
        if ($this->token === '-') {
            $this->advance();
            $expression = $this->parseOperand();
            return static fn (array $names): bool => $expression !== null && !$expression($names);
        }

        if ($this->token === '(') {
            $this->advance();
            $expressionInParens = $this->parseInnerExpression();
            if ($this->token === ')') {
                $this->advance();
            }

            return $expressionInParens;
        }

        if ($this->isIdentifier($this->token)) {
            $identifiers = [];
            do {
                assert($this->token !== null);
                $identifiers[] = $this->token;
                $this->advance();
            } while ($this->isIdentifier($this->token));

            return static fn (array $names): bool => self::nameMatcher($identifiers, $names);
        }

        return null;
    }

    /** @return callable(array<array-key, mixed>): bool */
    private function parseInnerExpression(): callable
    {
        $matchers = [];
        $matcher = $this->parseConjunction();
        $matchers[] = $matcher;
        while ($this->token === '|' || $this->token === ',') {
            do {
                $this->advance();
            } while ($this->token === '|' || $this->token === ',');
            $matcher = $this->parseConjunction();
            $matchers[] = $matcher;
        }

        return static fn (array $names): bool => self::any($matchers, $names);
    }

    /**
     * @param list<callable(array<array-key, mixed>): bool> $matchers
     * @param array<array-key, mixed> $names
     */
    private static function all(array $matchers, array $names): bool
    {
        foreach ($matchers as $matcher) {
            if (!$matcher($names)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<callable(array<array-key, mixed>): bool> $matchers
     * @param array<array-key, mixed> $names
     */
    private static function any(array $matchers, array $names): bool
    {
        foreach ($matchers as $matcher) {
            if ($matcher($names)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $identifiers
     * @param array<array-key, mixed> $scopes
     */
    private static function nameMatcher(array $identifiers, array $scopes): bool
    {
        $scopes = array_values($scopes);
        if (count($identifiers) > count($scopes)) {
            return false;
        }

        $lastIndex = 0;
        foreach ($identifiers as $identifier) {
            $matched = false;
            for (; $lastIndex < count($scopes); $lastIndex++) {
                $scope = $scopes[$lastIndex];
                if (is_string($scope) && self::scopesAreMatching($scope, $identifier)) {
                    $lastIndex++;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private static function scopesAreMatching(string $thisScopeName, string $scopeName): bool
    {
        if ($thisScopeName === $scopeName) {
            return true;
        }

        $len = strlen($scopeName);
        return strlen($thisScopeName) > $len
            && substr($thisScopeName, 0, $len) === $scopeName
            && $thisScopeName[$len] === '.';
    }

    private function isIdentifier(?string $token): bool
    {
        return $token !== null && preg_match('/^[\w\.:]+$/', $token) === 1;
    }

    private function advance(): void
    {
        $this->token = $this->tokens[$this->pos] ?? null;
        $this->pos++;
    }

    /** @return list<string> */
    private static function tokenize(string $expression): array
    {
        preg_match_all('/([LR]:|[\w\.:][\w\.:\-]*|[\,\|\-\(\)])/', $expression, $matches);
        return $matches[0];
    }
}
