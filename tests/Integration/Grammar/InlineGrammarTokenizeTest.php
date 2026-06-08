<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\Grammar;
use Shikiphp\Grammar\Registry;
use Shikiphp\Grammar\Token;

final class InlineGrammarTokenizeTest extends TestCase
{
    private function grammar(): Grammar
    {
        $registry = new Registry();
        return $registry->loadGrammarFromRaw([
            'scopeName' => 'source.toy',
            'patterns' => [
                ['include' => '#keyword'],
                ['include' => '#string'],
                ['include' => '#comment'],
            ],
            'repository' => [
                'keyword' => [
                    'match' => '\\b(let|fn)\\b',
                    'name' => 'keyword.control.toy',
                ],
                'string' => [
                    'begin' => '"',
                    'beginCaptures' => ['0' => ['name' => 'punctuation.definition.string.begin.toy']],
                    'end' => '"',
                    'endCaptures' => ['0' => ['name' => 'punctuation.definition.string.end.toy']],
                    'name' => 'string.quoted.double.toy',
                ],
                'comment' => [
                    'begin' => '/\\*',
                    'end' => '\\*/',
                    'name' => 'comment.block.toy',
                ],
            ],
        ]);
    }

    /** @param list<Token> $tokens */
    private function spans(array $tokens, string $line): string
    {
        $parts = [];
        foreach ($tokens as $token) {
            $text = mb_substr($line, $token->startIndex, $token->endIndex - $token->startIndex);
            $parts[] = sprintf('%d:%d=%s', $token->startIndex, $token->endIndex, $text);
        }

        return implode('|', $parts);
    }

    #[Test]
    public function keyword_match_rule_emits_its_scope(): void
    {
        $line = 'let x';
        $result = $this->grammar()->tokenizeLine($line, null);

        self::assertCount(2, $result->tokens);
        self::assertSame('0:3=let|3:5= x', $this->spans($result->tokens, $line));
        self::assertSame(['source.toy', 'keyword.control.toy'], $result->tokens[0]->scopes);
        self::assertSame(['source.toy'], $result->tokens[1]->scopes);
    }

    #[Test]
    public function string_begin_end_pushes_and_applies_captures(): void
    {
        $line = 'let "hi"';
        $result = $this->grammar()->tokenizeLine($line, null);

        self::assertSame('0:3=let|3:4= |4:5="|5:7=hi|7:8="', $this->spans($result->tokens, $line));

        self::assertSame(
            ['source.toy', 'string.quoted.double.toy', 'punctuation.definition.string.begin.toy'],
            $result->tokens[2]->scopes,
        );
        self::assertSame(['source.toy', 'string.quoted.double.toy'], $result->tokens[3]->scopes);
        self::assertSame(
            ['source.toy', 'string.quoted.double.toy', 'punctuation.definition.string.end.toy'],
            $result->tokens[4]->scopes,
        );
    }

    #[Test]
    public function block_comment_carries_state_across_lines(): void
    {
        $grammar = $this->grammar();

        $first = $grammar->tokenizeLine('a /* open', null);
        $lastFirst = $first->tokens[count($first->tokens) - 1];
        self::assertSame(['source.toy', 'comment.block.toy'], $lastFirst->scopes);

        $second = $grammar->tokenizeLine('still */ b', $first->ruleStack);
        self::assertSame('0:6=still |6:8=*/|8:10= b', $this->spans($second->tokens, 'still */ b'));
        self::assertSame(['source.toy', 'comment.block.toy'], $second->tokens[0]->scopes);
        self::assertSame(['source.toy'], $second->tokens[2]->scopes);
    }

    #[Test]
    public function whole_line_is_covered_without_gaps(): void
    {
        $line = 'let "a" fn /* c */';
        $result = $this->grammar()->tokenizeLine($line, null);

        $expectedStart = 0;
        foreach ($result->tokens as $token) {
            self::assertSame($expectedStart, $token->startIndex);
            $expectedStart = $token->endIndex;
        }
        self::assertSame(mb_strlen($line), $expectedStart);
    }
}
