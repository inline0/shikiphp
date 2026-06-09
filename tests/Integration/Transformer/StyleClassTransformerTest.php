<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Transformer\RemoveLineBreak;
use Shikiphp\Transformer\StyleToClass;

/**
 * Byte-exact parity for the remove-line-break and style-to-class transformers
 * against output captured from the installed `@shikijs/transformers`
 * (`bin/.oracle-tools/style-class-parity.mjs`).
 */
final class StyleClassTransformerTest extends TestCase
{
    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    #[Test]
    public function remove_line_break_drops_newline_text_nodes(): void
    {
        $code = "const a = 1\nconst b = 2\nconst c = 3";
        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code>'
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>'
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>'
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>'
            . '</code></pre>';

        $this->assertSame($expected, self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [new RemoveLineBreak()],
        ]));
    }

    #[Test]
    public function style_to_class_single_theme_moves_pre_style_to_class(): void
    {
        $code = "const a = 1\nconst b = 2\nconst c = 3";
        $transformer = new StyleToClass();
        $expectedHtml = '<pre class="shiki github-dark __shiki_1wf9g5" tabindex="0"><code>'
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>'
            . '</code></pre>';

        $html = self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$transformer],
        ]);

        $this->assertSame($expectedHtml, $html);
        $this->assertSame('.__shiki_1wf9g5{background-color:#24292e;color:#e1e4e8}', $transformer->getCSS());
    }

    #[Test]
    public function style_to_class_dual_theme_moves_token_styles_to_classes(): void
    {
        $code = "const a = 1\nconst b = 2\nconst c = 3";
        $transformer = new StyleToClass();
        $expectedHtml = '<pre class="shiki shiki-themes github-light github-dark __shiki_18d7xz" tabindex="0"><code>'
            . '<span class="line"><span class="__shiki_2zefwf">const</span><span class="__shiki_18al46"> a</span><span class="__shiki_2zefwf"> =</span><span class="__shiki_18al46"> 1</span></span>' . "\n"
            . '<span class="line"><span class="__shiki_2zefwf">const</span><span class="__shiki_18al46"> b</span><span class="__shiki_2zefwf"> =</span><span class="__shiki_18al46"> 2</span></span>' . "\n"
            . '<span class="line"><span class="__shiki_2zefwf">const</span><span class="__shiki_18al46"> c</span><span class="__shiki_2zefwf"> =</span><span class="__shiki_18al46"> 3</span></span>'
            . '</code></pre>';
        $expectedCss = '.__shiki_2zefwf{color:#D73A49;--shiki-dark:#F97583}'
            . '.__shiki_18al46{color:#005CC5;--shiki-dark:#79B8FF}'
            . '.__shiki_18d7xz{background-color:#fff;--shiki-dark-bg:#24292e;color:#24292e;--shiki-dark:#e1e4e8}';

        $html = self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
            'transformers' => [$transformer],
        ]);

        $this->assertSame($expectedHtml, $html);
        $this->assertSame($expectedCss, $transformer->getCSS());
    }

    #[Test]
    public function style_to_class_custom_prefix_and_suffix(): void
    {
        $code = 'const a = 1';
        $transformer = new StyleToClass('cls-', '-x');

        $html = self::$highlighter->codeToHtml($code, [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$transformer],
        ]);

        $this->assertStringContainsString('class="shiki github-dark cls-1wf9g5-x"', $html);
        $this->assertSame('.cls-1wf9g5-x{background-color:#24292e;color:#e1e4e8}', $transformer->getCSS());
    }
}
