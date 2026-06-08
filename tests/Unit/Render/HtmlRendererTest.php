<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Render;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Render\HtmlRenderer;
use Shikiphp\Render\RenderOptions;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;

final class HtmlRendererTest extends TestCase
{
    #[Test]
    public function single_theme_single_line(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(
            themeName: 'github-dark',
            fg: '#e1e4e8',
            bg: '#24292e',
        );

        $html = $renderer->render([
            [
                new ThemedToken('echo', '#F97583', FontStyle::NONE),
                new ThemedToken(' ', '#E1E4E8', FontStyle::NONE),
            ],
        ], $options);

        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0">'
            . '<code><span class="line">'
            . '<span style="color:#F97583">echo</span>'
            . '<span style="color:#E1E4E8"> </span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function multiline_joins_lines_with_newline(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 'nord', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('a', '#111', FontStyle::NONE)],
            [new ThemedToken('b', '#222', FontStyle::NONE)],
        ], $options);

        $expected = '<pre class="shiki nord" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line"><span style="color:#111">a</span></span>' . "\n"
            . '<span class="line"><span style="color:#222">b</span></span>'
            . '</code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function empty_line_renders_empty_span(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('x', '#abc', FontStyle::NONE)],
            [],
            [new ThemedToken('y', '#def', FontStyle::NONE)],
        ], $options);

        $expected = '<pre class="shiki t" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line"><span style="color:#abc">x</span></span>' . "\n"
            . '<span class="line"></span>' . "\n"
            . '<span class="line"><span style="color:#def">y</span></span>'
            . '</code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function escapes_special_characters(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('<a href="x" & y>', '#000', FontStyle::NONE)],
        ], $options);

        $expected = '<pre class="shiki t" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line">'
            . '<span style="color:#000">&#x3C;a href="x" &#x26; y></span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function bold_and_italic_font_styles(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('x', '#abc', FontStyle::BOLD | FontStyle::ITALIC)],
        ], $options);

        $expected = '<pre class="shiki t" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line">'
            . '<span style="color:#abc;font-style:italic;font-weight:bold">x</span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function underline_and_strikethrough_combine_into_text_decoration(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('x', '#abc', FontStyle::UNDERLINE | FontStyle::STRIKETHROUGH)],
        ], $options);

        $expected = '<pre class="shiki t" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line">'
            . '<span style="color:#abc;text-decoration:underline line-through">x</span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function token_without_color_has_no_style_attribute(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $html = $renderer->render([
            [new ThemedToken('plain', null, FontStyle::NONE)],
        ], $options);

        $expected = '<pre class="shiki t" style="background-color:#000;color:#fff" tabindex="0">'
            . '<code><span class="line"><span>plain</span></span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function dual_theme_with_default_color_uses_plain_default_and_css_var(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(
            themes: ['light' => 'github-light', 'dark' => 'github-dark'],
            fgByKey: ['light' => '#24292e', 'dark' => '#e1e4e8'],
            bgByKey: ['light' => '#fff', 'dark' => '#24292e'],
            defaultColor: 'light',
        );

        $html = $renderer->render([
            [new ThemedToken('x', null, FontStyle::NONE, null, 'color:#005CC5;--shiki-dark:#79B8FF')],
        ], $options);

        $expected = '<pre class="shiki shiki-themes github-light github-dark" '
            . 'style="background-color:#fff;--shiki-dark-bg:#24292e;color:#24292e;--shiki-dark:#e1e4e8" '
            . 'tabindex="0"><code><span class="line">'
            . '<span style="color:#005CC5;--shiki-dark:#79B8FF">x</span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }

    #[Test]
    public function dual_theme_without_default_color_uses_only_css_vars(): void
    {
        $renderer = new HtmlRenderer();
        $options = new RenderOptions(
            themes: ['light' => 'github-light', 'dark' => 'github-dark'],
            fgByKey: ['light' => '#24292e', 'dark' => '#e1e4e8'],
            bgByKey: ['light' => '#fff', 'dark' => '#24292e'],
            defaultColor: false,
        );

        $html = $renderer->render([
            [new ThemedToken('x', null, FontStyle::NONE, null, '--shiki-light:#005CC5;--shiki-dark:#79B8FF')],
        ], $options);

        $expected = '<pre class="shiki shiki-themes github-light github-dark" '
            . 'style="--shiki-light-bg:#fff;--shiki-dark-bg:#24292e;--shiki-light:#24292e;--shiki-dark:#e1e4e8" '
            . 'tabindex="0"><code><span class="line">'
            . '<span style="--shiki-light:#005CC5;--shiki-dark:#79B8FF">x</span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $html);
    }
}
