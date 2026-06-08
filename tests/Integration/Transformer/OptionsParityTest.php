<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Exceptions\Highlight;
use Shikiphp\Highlighter;

/**
 * Verifies the codeToHtml option surface against byte-exact strings captured
 * from the installed Shiki (`bin/.oracle-tools/oracle-options.mjs`).
 */
final class OptionsParityTest extends TestCase
{
    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    /**
     * @param array<string,mixed> $options
     */
    private function html(string $code, array $options): string
    {
        return self::$highlighter->codeToHtml($code, ['lang' => 'javascript', 'theme' => 'github-dark', ...$options]);
    }

    #[Test]
    public function structure_inline_emits_token_spans_and_br_no_wrappers(): void
    {
        $expected = '<span style="color:#F97583">const</span><span style="color:#79B8FF"> x</span>'
            . '<span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span>'
            . '<br><span style="color:#F97583">const</span><span style="color:#79B8FF"> y</span>'
            . '<span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span>';

        $this->assertSame($expected, $this->html("const x = 1\nconst y = 2", ['structure' => 'inline']));
    }

    #[Test]
    public function tabindex_false_omits_the_attribute(): void
    {
        $html = $this->html('const x = 1', ['tabindex' => false]);
        $this->assertStringContainsString('<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8"><code>', $html);
        $this->assertStringNotContainsString('tabindex', $html);
    }

    #[Test]
    public function tabindex_int_renders_its_value(): void
    {
        $this->assertStringContainsString('tabindex="-1"', $this->html('const x = 1', ['tabindex' => -1]));
    }

    #[Test]
    public function color_replacements_remap_token_and_background_colours(): void
    {
        $html = $this->html('const x = 1', ['colorReplacements' => ['#f97583' => '#ff0000', '#24292e' => '#000000']]);
        $this->assertStringContainsString('background-color:#000000', $html);
        $this->assertStringContainsString('<span style="color:#ff0000">const</span>', $html);
    }

    #[Test]
    public function color_replacements_per_theme_apply_only_to_the_named_theme(): void
    {
        $html = $this->html('const x = 1', ['colorReplacements' => ['github-dark' => ['#f97583' => '#00ff00']]]);
        $this->assertStringContainsString('<span style="color:#00ff00">const</span>', $html);

        $other = $this->html('const x = 1', ['colorReplacements' => ['some-other-theme' => ['#f97583' => '#00ff00']]]);
        $this->assertStringContainsString('<span style="color:#F97583">const</span>', $other);
    }

    #[Test]
    public function css_variable_prefix_changes_dual_theme_variables(): void
    {
        $html = self::$highlighter->codeToHtml('const x = 1', [
            'lang' => 'javascript',
            'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
            'cssVariablePrefix' => '--p-',
        ]);
        $this->assertStringContainsString('--p-dark:', $html);
        $this->assertStringNotContainsString('--shiki-', $html);
    }

    #[Test]
    public function merge_whitespaces_false_keeps_whitespace_tokens_separate(): void
    {
        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0">'
            . '<code><span class="line"><span style="color:#F97583">const</span>'
            . '<span style="color:#E1E4E8"> </span><span style="color:#79B8FF">x</span>'
            . '<span style="color:#E1E4E8"> </span><span style="color:#F97583">=</span>'
            . '<span style="color:#E1E4E8"> </span><span style="color:#79B8FF">1</span>'
            . '</span></code></pre>';

        $this->assertSame($expected, $this->html('const x = 1', ['mergeWhitespaces' => false]));
        $this->assertSame($expected, $this->html('const x = 1', ['mergeWhitespaces' => 'never']));
    }

    #[Test]
    public function merge_whitespaces_default_folds_whitespace_into_next_token(): void
    {
        $html = $this->html('const x = 1', []);
        $this->assertStringContainsString('<span style="color:#79B8FF"> x</span>', $html);
    }

    #[Test]
    public function tokenize_max_line_length_skips_long_lines(): void
    {
        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0">'
            . '<code><span class="line"><span>const x = 1</span></span></code></pre>';

        $this->assertSame($expected, $this->html('const x = 1', ['tokenizeMaxLineLength' => 5]));
    }

    #[Test]
    public function decoration_on_a_single_token_adds_its_class(): void
    {
        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0">'
            . '<code><span class="line"><span style="color:#F97583" class="hl">const</span>'
            . '<span style="color:#79B8FF"> x</span><span style="color:#F97583"> =</span>'
            . '<span style="color:#79B8FF"> 1</span></span></code></pre>';

        $this->assertSame($expected, $this->html('const x = 1', [
            'decorations' => [['start' => 0, 'end' => 5, 'properties' => ['class' => 'hl']]],
        ]));
    }

    #[Test]
    public function decoration_mid_token_splits_and_decorates_the_middle_piece(): void
    {
        $html = $this->html('const x = 1', [
            'decorations' => [['start' => 1, 'end' => 4, 'properties' => ['class' => 'hl']]],
        ]);
        $this->assertStringContainsString('<span style="color:#F97583">c</span>'
            . '<span style="color:#F97583" class="hl">ons</span>'
            . '<span style="color:#F97583">t</span>', $html);
    }

    #[Test]
    public function decoration_spanning_multiple_tokens_wraps_them(): void
    {
        $html = $this->html('const x = 1', [
            'decorations' => [['start' => 4, 'end' => 7, 'properties' => ['class' => 'hl']]],
        ]);
        $this->assertStringContainsString('<span class="hl"><span style="color:#F97583">t</span>'
            . '<span style="color:#79B8FF"> x</span></span>', $html);
    }

    #[Test]
    public function decoration_by_position_resolves_line_and_character(): void
    {
        $html = $this->html('const x = 1', [
            'decorations' => [[
                'start' => ['line' => 0, 'character' => 6],
                'end' => ['line' => 0, 'character' => 7],
                'properties' => ['class' => 'hl'],
            ]],
        ]);
        $this->assertStringContainsString('<span style="color:#79B8FF" class="hl">x</span>', $html);
    }

    #[Test]
    public function nested_decorations_are_allowed(): void
    {
        $html = $this->html('const x = 1', [
            'decorations' => [
                ['start' => 0, 'end' => 5, 'properties' => ['class' => 'outer']],
                ['start' => 1, 'end' => 4, 'properties' => ['class' => 'inner']],
            ],
        ]);
        $this->assertStringContainsString('<span class="outer"><span style="color:#F97583">c</span>'
            . '<span style="color:#F97583" class="inner">ons</span>'
            . '<span style="color:#F97583">t</span></span>', $html);
    }

    #[Test]
    public function partially_overlapping_decorations_throw(): void
    {
        $this->expectException(Highlight::class);
        $this->expectExceptionMessage('intersect');

        $this->html('const x = 1', [
            'decorations' => [
                ['start' => 0, 'end' => 5, 'properties' => ['class' => 'a']],
                ['start' => 3, 'end' => 7, 'properties' => ['class' => 'b']],
            ],
        ]);
    }

    #[Test]
    public function decoration_tag_name_and_always_wrap(): void
    {
        $mark = $this->html('const x = 1', [
            'decorations' => [['start' => 0, 'end' => 5, 'tagName' => 'mark', 'properties' => ['class' => 'hl']]],
        ]);
        $this->assertStringContainsString('<mark style="color:#F97583" class="hl">const</mark>', $mark);

        $wrapped = $this->html('const x = 1', [
            'decorations' => [['start' => 0, 'end' => 5, 'alwaysWrap' => true, 'properties' => ['class' => 'hl']]],
        ]);
        $this->assertStringContainsString('<span class="hl"><span style="color:#F97583">const</span></span>', $wrapped);
    }

    #[Test]
    public function multi_line_decoration_decorates_full_lines_and_partial_end(): void
    {
        $code = "const x = 1\nlet y = 2\nvar z = 3";
        $html = $this->html($code, [
            'decorations' => [[
                'start' => ['line' => 0, 'character' => 0],
                'end' => ['line' => 2, 'character' => 3],
                'properties' => ['class' => 'block'],
            ]],
        ]);

        $this->assertSame(2, substr_count($html, 'class="line block"'));
        $this->assertStringContainsString('<span style="color:#F97583" class="block">var</span>', $html);
    }
}
