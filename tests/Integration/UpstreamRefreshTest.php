<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Transformer\Notation\NotationDiff;
use Shikiphp\Transformer\Notation\NotationFocus;
use Shikiphp\Transformer\Notation\NotationHighlight;
use Shikiphp\Transformer\Notation\NotationWordHighlight;
use Shikiphp\Transformer\RenderLineNumber;

final class UpstreamRefreshTest extends TestCase
{
    #[Test]
    public function matches_pinned_shiki_45_features(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/upstream-4.5.json'), true, 512, JSON_THROW_ON_ERROR);
        $highlighter = Highlighter::createBundled();
        foreach ($fixture['html'] as $index => $case) {
            $transformer = match ($case['kind']) {
                'RenderLineNumber' => new RenderLineNumber(
                    classLineNumber: $case['options']['classLineNumber'] ?? 'line-number',
                    start: $case['options']['start'] ?? 1,
                ),
                'NotationHighlight' => new NotationHighlight(),
                'NotationDiff' => new NotationDiff(),
                'NotationFocus' => new NotationFocus(),
                'NotationWordHighlight' => new NotationWordHighlight(
                    classActivePre: $case['options']['classActivePre'] ?? null,
                    classActiveCode: $case['options']['classActiveCode'] ?? null,
                ),
            };
            self::assertSame($case['output'], $highlighter->codeToHtml($case['code'], [
                'lang' => $case['lang'], 'theme' => $case['theme'],
                ...$case['extra'], 'transformers' => [$transformer],
            ]), 'HTML case ' . $index);
        }
        foreach ($fixture['ansi'] as $index => $case) {
            self::assertSame($case['output'], $highlighter->codeToANSI($case['code'], [
                'lang' => $case['lang'], 'theme' => $case['theme'],
            ], $case['colorLevel']), 'ANSI case ' . $index);
        }
        $tokens = $highlighter->codeToTokensBase($fixture['tokenTypes']['code'], [
            'lang' => 'javascript', 'theme' => 'github-dark', 'includeExplanation' => 'tokenType',
        ]);
        $actual = array_map(static fn (array $line): array => array_map(
            static fn (ThemedToken $token): array => ['content' => $token->content, 'type' => $token->type],
            $line,
        ), $tokens);
        self::assertSame($fixture['tokenTypes']['output'], $actual);
    }
}
