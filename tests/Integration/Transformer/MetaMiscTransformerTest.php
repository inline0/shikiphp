<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Transformer\CompactLineOptions;
use Shikiphp\Transformer\MetaHighlight;
use Shikiphp\Transformer\MetaWordHighlight;
use Shikiphp\Transformer\RemoveNotationEscape;
use Shikiphp\Transformer\RenderWhitespace;

/**
 * Byte-exact parity for the meta-string and misc transformers against strings
 * captured from the installed `@shikijs/transformers`
 * (`bin/.oracle-tools/meta-misc-parity.mjs`).
 */
final class MetaMiscTransformerTest extends TestCase
{
    private const PRE_OPEN = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code>';

    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    #[Test]
    public function meta_highlight_marks_lines_from_range(): void
    {
        $code = "const a = 1\nconst b = 2\nconst c = 3\nconst d = 4\nconst e = 5";
        $expected = self::PRE_OPEN
            . '<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>' . "\n"
            . '<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> d</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 4</span></span>' . "\n"
            . '<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> e</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 5</span></span></code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new MetaHighlight()],
            'meta' => ['__raw' => '{1,3-5}'],
        ]));
    }

    #[Test]
    public function meta_highlight_noop_without_raw(): void
    {
        $code = "const a = 1";
        $html = self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new MetaHighlight()],
        ]);

        $this->assertStringNotContainsString('highlighted', $html);
    }

    #[Test]
    public function meta_word_highlight_wraps_each_occurrence(): void
    {
        $code = "const apple = 1\nconst banana = apple + apple";
        $expected = self::PRE_OPEN
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> </span><span style="color:#79B8FF" class="highlighted-word">apple</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> banana</span><span style="color:#F97583"> =</span><span style="color:#E1E4E8"> </span><span style="color:#E1E4E8" class="highlighted-word">apple</span><span style="color:#E1E4E8"> </span><span style="color:#F97583">+</span><span style="color:#E1E4E8"> </span><span style="color:#E1E4E8" class="highlighted-word">apple</span></span></code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new MetaWordHighlight()],
            'meta' => ['__raw' => '/apple/'],
        ]));
    }

    #[Test]
    public function render_whitespace_all_emits_space_spans(): void
    {
        $code = "a + b";
        $expected = self::PRE_OPEN
            . '<span class="line"><span style="color:#E1E4E8">a</span><span class="space"> </span><span style="color:#F97583">+</span><span class="space"> </span><span style="color:#E1E4E8">b</span></span></code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new RenderWhitespace()],
        ]));
    }

    #[Test]
    public function compact_line_options_applies_per_line_classes(): void
    {
        $code = "const a = 1\nconst b = 2\nconst c = 3\nconst d = 4\nconst e = 5";
        $expected = self::PRE_OPEN
            . '<span class="line a b"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line c"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> d</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 4</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> e</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 5</span></span></code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new CompactLineOptions([
                ['line' => 1, 'classes' => ['a', 'b']],
                ['line' => 3, 'classes' => 'c'],
            ])],
        ]));
    }

    #[Test]
    public function remove_notation_escape_unescapes_code_marker(): void
    {
        $code = "// [\\!code highlight]\nconst a = 1";
        $expected = self::PRE_OPEN
            . '<span class="line"><span style="color:#6A737D">// [!code highlight]</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span></code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new RemoveNotationEscape()],
        ]));
    }
}
