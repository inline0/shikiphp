<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Render;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Hast\Element;
use Shikiphp\Hast\HastSerializer;
use Shikiphp\Hast\Text;
use Shikiphp\Render\HastBuilder;
use Shikiphp\Render\RenderOptions;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;

final class HastBuilderTest extends TestCase
{
    #[Test]
    public function builds_pre_code_line_span_structure(): void
    {
        $options = new RenderOptions(themeName: 'github-dark', fg: '#e1e4e8', bg: '#24292e');

        $root = (new HastBuilder())->build([
            [new ThemedToken('echo', '#F97583', FontStyle::NONE)],
        ], $options);

        $this->assertSame('root', $root->tag);
        $pre = $root->children[0];
        $this->assertInstanceOf(Element::class, $pre);
        $this->assertSame('pre', $pre->tag);
        $this->assertSame(['shiki', 'github-dark'], $pre->properties['className']);
        $this->assertSame(['background-color' => '#24292e', 'color' => '#e1e4e8'], $pre->properties['style']);
        $this->assertSame('0', $pre->properties['tabindex']);

        $code = $pre->children[0];
        $this->assertInstanceOf(Element::class, $code);
        $this->assertSame('code', $code->tag);
        $this->assertSame([], $code->properties);

        $line = $code->children[0];
        $this->assertInstanceOf(Element::class, $line);
        $this->assertSame('span', $line->tag);
        $this->assertSame(['line'], $line->properties['className']);

        $token = $line->children[0];
        $this->assertInstanceOf(Element::class, $token);
        $this->assertSame(['style' => ['color' => '#F97583']], $token->properties);
        $this->assertEquals([new Text('echo')], $token->children);
    }

    #[Test]
    public function joins_lines_with_newline_text_nodes(): void
    {
        $options = new RenderOptions(themeName: 't', fg: '#fff', bg: '#000');

        $root = (new HastBuilder())->build([
            [new ThemedToken('a', '#111', FontStyle::NONE)],
            [],
            [new ThemedToken('b', '#222', FontStyle::NONE)],
        ], $options);

        $pre = $root->children[0];
        $this->assertInstanceOf(Element::class, $pre);
        $code = $pre->children[0];
        $this->assertInstanceOf(Element::class, $code);

        $this->assertCount(5, $code->children);
        $this->assertInstanceOf(Element::class, $code->children[0]);
        $this->assertEquals(new Text("\n"), $code->children[1]);
        $this->assertInstanceOf(Element::class, $code->children[2]);
        $this->assertEquals(new Text("\n"), $code->children[3]);
        $this->assertInstanceOf(Element::class, $code->children[4]);
    }

    #[Test]
    public function serialized_hast_matches_legacy_html(): void
    {
        $options = new RenderOptions(themeName: 'github-dark', fg: '#e1e4e8', bg: '#24292e');
        $lines = [
            [
                new ThemedToken('echo', '#F97583', FontStyle::NONE),
                new ThemedToken(' ', '#E1E4E8', FontStyle::NONE),
            ],
        ];

        $html = HastSerializer::toHtml((new HastBuilder())->build($lines, $options));

        $this->assertSame(
            '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0">'
            . '<code><span class="line">'
            . '<span style="color:#F97583">echo</span>'
            . '<span style="color:#E1E4E8"> </span>'
            . '</span></code></pre>',
            $html,
        );
    }

    #[Test]
    public function dual_theme_pre_style_uses_default_plus_css_vars(): void
    {
        $options = new RenderOptions(
            themes: ['light' => 'github-light', 'dark' => 'github-dark'],
            fgByKey: ['light' => '#24292e', 'dark' => '#e1e4e8'],
            bgByKey: ['light' => '#fff', 'dark' => '#24292e'],
            defaultColor: 'light',
        );

        $root = (new HastBuilder())->build([
            [new ThemedToken('x', null, FontStyle::NONE, null, 'color:#005CC5;--shiki-dark:#79B8FF')],
        ], $options);

        $pre = $root->children[0];
        $this->assertInstanceOf(Element::class, $pre);
        $this->assertSame(['shiki', 'shiki-themes', 'github-light', 'github-dark'], $pre->properties['className']);
        $this->assertSame([
            'background-color' => '#fff',
            '--shiki-dark-bg' => '#24292e',
            'color' => '#24292e',
            '--shiki-dark' => '#e1e4e8',
        ], $pre->properties['style']);

        $code = $pre->children[0];
        $this->assertInstanceOf(Element::class, $code);
        $line = $code->children[0];
        $this->assertInstanceOf(Element::class, $line);
        $token = $line->children[0];
        $this->assertInstanceOf(Element::class, $token);
        $this->assertSame(
            ['style' => ['color' => '#005CC5', '--shiki-dark' => '#79B8FF']],
            $token->properties,
        );
    }
}
