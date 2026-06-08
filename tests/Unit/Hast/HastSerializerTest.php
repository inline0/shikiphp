<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Hast;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Hast\Element;
use Shikiphp\Hast\HastSerializer;
use Shikiphp\Hast\Text;

final class HastSerializerTest extends TestCase
{
    #[Test]
    public function serializes_element_with_text_child(): void
    {
        $tree = new Element('span', [], [new Text('hello')]);

        $this->assertSame('<span>hello</span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function joins_classname_list_with_spaces(): void
    {
        $tree = new Element('pre', ['className' => ['shiki', 'github-dark']], []);

        $this->assertSame('<pre class="shiki github-dark"></pre>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function joins_style_map_in_insertion_order_without_trailing_semicolon(): void
    {
        $tree = new Element('span', ['style' => ['color' => '#fff', 'font-weight' => 'bold']], []);

        $this->assertSame('<span style="color:#fff;font-weight:bold"></span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function omits_empty_classname_and_style(): void
    {
        $tree = new Element('span', ['className' => [], 'style' => []], [new Text('x')]);

        $this->assertSame('<span>x</span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function void_elements_have_no_closing_tag(): void
    {
        $tree = new Element('br', []);

        $this->assertSame('<br>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function void_element_renders_attributes(): void
    {
        $tree = new Element('img', ['src' => 'a.png']);

        $this->assertSame('<img src="a.png">', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function escapes_text_ampersand_and_lt_only(): void
    {
        $tree = new Text('<a> & "b"');

        $this->assertSame('&#x3C;a> &#x26; "b"', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function escapes_attribute_ampersand_lt_and_quote(): void
    {
        $tree = new Element('span', ['title' => '<a> & "b"'], []);

        $this->assertSame('<span title="&#x3C;a> &#x26; &#x22;b&#x22;"></span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function boolean_true_attribute_renders_bare_name(): void
    {
        $tree = new Element('span', ['hidden' => true, 'disabled' => false], []);

        $this->assertSame('<span hidden></span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function integer_and_string_attributes_render(): void
    {
        $tree = new Element('span', ['tabindex' => 0, 'data-x' => 'y'], []);

        $this->assertSame('<span tabindex="0" data-x="y"></span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function null_attribute_is_omitted(): void
    {
        $tree = new Element('span', ['data-x' => null], []);

        $this->assertSame('<span></span>', HastSerializer::toHtml($tree));
    }

    #[Test]
    public function nested_tree_serializes_recursively(): void
    {
        $tree = new Element('pre', ['className' => ['shiki']], [
            new Element('code', [], [
                new Element('span', ['className' => ['line']], [
                    new Element('span', ['style' => ['color' => '#abc']], [new Text('x')]),
                ]),
                new Text("\n"),
                new Element('span', ['className' => ['line']], []),
            ]),
        ]);

        $this->assertSame(
            '<pre class="shiki"><code><span class="line">'
            . '<span style="color:#abc">x</span></span>' . "\n"
            . '<span class="line"></span></code></pre>',
            HastSerializer::toHtml($tree),
        );
    }
}
