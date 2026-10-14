<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Exceptions\Highlight;
use Shikiphp\Highlighter;
use Shikiphp\Shikiphp;
use Shikiphp\Tests\Oracle\OracleCapture;

final class PlainTextTest extends TestCase
{
    /** @return list<array{string}> */
    public static function languages(): array
    {
        return [['text'], ['txt'], ['plain'], ['plaintext']];
    }

    #[Test]
    #[DataProvider('languages')]
    public function plain_text_renders_with_root_theme_colors(string $lang): void
    {
        self::assertSame(
            '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line"><span>hello &#x3C;b></span></span></code></pre>',
            Shikiphp::codeToHtml('hello <b>', ['lang' => $lang, 'theme' => 'github-dark']),
        );
    }

    #[Test]
    public function empty_lines_and_original_utf16_offsets_are_preserved(): void
    {
        $hl = Highlighter::createBundled();
        $result = $hl->codeToTokensResult("a\n\n😀\r\nz\n", ['lang' => 'text', 'theme' => 'github-dark']);
        self::assertNull($result->grammarState);
        self::assertSame(['a', '', '😀', 'z', ''], array_map(static fn($line) => $line[0]->content, $result->tokens));
        self::assertSame([0, 2, 3, 7, 9], array_map(static fn($line) => $line[0]->offset, $result->tokens));
        foreach ($result->tokens as $line) {
            self::assertCount(1, $line);
            self::assertNull($line[0]->color);
            self::assertNull($line[0]->bgColor);
            self::assertSame(0, $line[0]->fontStyle);
        }

        $empty = $hl->codeToTokensBase('', ['lang' => 'plain', 'theme' => 'github-dark']);
        self::assertCount(1, $empty[0]);
        self::assertSame('', $empty[0][0]->content);
    }

    #[Test]
    public function plain_text_has_no_grammar_state(): void
    {
        $this->expectException(Highlight::class);
        $this->expectExceptionMessage('Plain language does not have grammar state.');
        Highlighter::createBundled()->getLastGrammarState('hello', ['lang' => 'txt', 'theme' => 'github-dark']);
    }

    #[Test]
    public function single_and_dual_theme_output_matches_shiki(): void
    {
        if (!OracleCapture::isAvailable()) {
            self::markTestSkipped('Shiki oracle (node_modules) is not available.');
        }

        $file = tempnam(sys_get_temp_dir(), 'shikiphp-plain-');
        self::assertIsString($file);
        $source = "hello <b> & 😀\n\nlast\n";
        file_put_contents($file, $source);
        $hl = Highlighter::createBundled();
        try {
            foreach (self::languages() as [$lang]) {
                self::assertSame(
                    OracleCapture::html($lang, 'github-dark', $file),
                    $hl->codeToHtml($source, ['lang' => $lang, 'theme' => 'github-dark']),
                );
            }

            $themes = ['light' => 'github-light', 'dark' => 'github-dark'];
            $expected = OracleCapture::tokensWithThemes('text', $file, $themes);
            $actual = $hl->codeToTokensWithThemes($source, ['lang' => 'text', 'themes' => $themes]);
            foreach ($actual as $index => $line) {
                self::assertCount(1, $line);
                self::assertSame($expected[$index][0]['content'], $line[0]->content);
                self::assertSame($expected[$index][0]['offset'], $line[0]->offset);
                foreach ($line[0]->variants as $key => $style) {
                    self::assertSame($expected[$index][0]['variants'][$key], [
                        'color' => $style->color,
                        'fontStyle' => $style->fontStyle,
                        'bgColor' => $style->bgColor,
                    ]);
                }
            }
            self::assertStringNotContainsString('<span style=', $hl->codeToHtml($source, ['lang' => 'text', 'themes' => $themes]));
        } finally {
            unlink($file);
        }
    }
}
