<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\Token;

final class BundledGrammarTokenizeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!BundledRegistry::hasBundledGrammars()) {
            self::markTestSkipped('Bundled grammars are not present.');
        }
    }

    /**
     * @param list<Token> $tokens
     * @return list<string> the scope path of the token covering $needle
     */
    private function scopesAt(array $tokens, string $line, string $needle): array
    {
        $offset = mb_strpos($line, $needle);
        self::assertNotFalse($offset, "Needle '{$needle}' not found in line.");

        foreach ($tokens as $token) {
            if ($token->startIndex <= $offset && $offset < $token->endIndex) {
                return $token->scopes;
            }
        }

        self::fail("No token covers offset {$offset}.");
    }

    /** @param list<Token> $tokens */
    private function assertGapFree(array $tokens, string $line): void
    {
        $expected = 0;
        foreach ($tokens as $token) {
            self::assertSame($expected, $token->startIndex);
            $expected = $token->endIndex;
        }
        self::assertSame(mb_strlen($line), $expected);
    }

    #[Test]
    public function json_string_number_and_punctuation(): void
    {
        $grammar = BundledRegistry::create()->loadGrammar('source.json');
        $line = '{"id": 42}';
        $result = $grammar->tokenizeLine($line, null);

        $this->assertGapFree($result->tokens, $line);

        self::assertContains(
            'punctuation.definition.dictionary.begin.json',
            $this->scopesAt($result->tokens, $line, '{'),
        );
        self::assertContains(
            'support.type.property-name.json',
            $this->scopesAt($result->tokens, $line, 'id'),
        );
        self::assertContains(
            'constant.numeric.json',
            $this->scopesAt($result->tokens, $line, '42'),
        );
        self::assertContains(
            'punctuation.definition.dictionary.end.json',
            $this->scopesAt($result->tokens, $line, '}'),
        );
    }

    #[Test]
    public function javascript_keyword_string_and_comment(): void
    {
        $grammar = BundledRegistry::create()->loadGrammar('source.js');
        $line = 'const s = "x"; // c';
        $result = $grammar->tokenizeLine($line, null);

        $this->assertGapFree($result->tokens, $line);

        self::assertContains('storage.type.js', $this->scopesAt($result->tokens, $line, 'const'));
        self::assertContains('string.quoted.double.js', $this->scopesAt($result->tokens, $line, 'x'));
        self::assertContains('comment.line.double-slash.js', $this->scopesAt($result->tokens, $line, '//'));
    }

    #[Test]
    public function javascript_block_comment_spans_multiple_lines(): void
    {
        $grammar = BundledRegistry::create()->loadGrammar('source.js');

        $first = $grammar->tokenizeLine('/* start', null);
        self::assertContains('comment.block.js', $first->tokens[0]->scopes);

        $middle = $grammar->tokenizeLine('still inside', $first->ruleStack);
        self::assertContains('comment.block.js', $middle->tokens[0]->scopes);

        $last = $grammar->tokenizeLine('end */ x', $middle->ruleStack);
        self::assertContains('comment.block.js', $this->scopesAt($last->tokens, 'end */ x', '*/'));
        self::assertNotContains('comment.block.js', $this->scopesAt($last->tokens, 'end */ x', 'x'));
    }
}
