<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Oniguruma;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Oniguruma\PatternConverter;
use Shikiphp\Regex\Matcher;
use Shikiphp\Regex\Parser;

final class PatternConverterTest extends TestCase
{
    private PatternConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PatternConverter();
    }

    /** @return array{pattern: string, flags: string} */
    private function convert(string $onig): array
    {
        $result = $this->converter->convert($onig);
        (new Parser($result['pattern'], $result['flags']))->parse();
        return $result;
    }

    #[Test]
    public function emits_unicode_flag_by_default(): void
    {
        $this->assertSame('u', $this->convert('abc')['flags']);
    }

    #[Test]
    public function posix_alpha_becomes_unicode_letter_property(): void
    {
        $this->assertSame('[\p{L}]+', $this->convert('[[:alpha:]]+')['pattern']);
    }

    #[Test]
    public function posix_digit_and_word_expand(): void
    {
        $this->assertSame('[\p{Nd}]', $this->convert('[[:digit:]]')['pattern']);
        $this->assertSame(
            '[\p{L}\p{Nd}\p{Pc}\p{Mn}\p{Mc}]',
            $this->convert('[[:word:]]')['pattern'],
        );
    }

    #[Test]
    public function posix_xdigit_and_space(): void
    {
        $this->assertSame('[0-9A-Fa-f]', $this->convert('[[:xdigit:]]')['pattern']);
        $this->assertSame('[\s]', $this->convert('[[:space:]]')['pattern']);
    }

    #[Test]
    public function hex_digit_escapes(): void
    {
        $this->assertSame('\p{AHex}+', $this->convert('\h+')['pattern']);
        $this->assertSame('\P{AHex}', $this->convert('\H')['pattern']);
    }

    #[Test]
    public function hex_digit_escape_inside_class(): void
    {
        $this->assertSame('[a\p{AHex}b]', $this->convert('[a\hb]')['pattern']);
    }

    #[Test]
    public function absolute_anchors(): void
    {
        $this->assertSame('(?<![\s\S])foo(?![\s\S])', $this->convert('\Afoo\z')['pattern']);
    }

    #[Test]
    public function end_of_string_with_optional_newline(): void
    {
        $this->assertSame('foo(?=\n?(?![\s\S]))', $this->convert('foo\Z')['pattern']);
    }

    #[Test]
    public function scan_anchor_drops_token_and_marks_sticky(): void
    {
        $result = $this->convert('\Gfoo');
        $this->assertSame('foo', $result['pattern']);
        $this->assertStringContainsString('y', $result['flags']);
    }

    #[Test]
    public function leading_scan_anchor_after_zero_width_anchors_is_sticky(): void
    {
        $this->assertStringContainsString('y', $this->convert('^\Gfoo')['flags']);
        $this->assertStringContainsString('y', $this->convert('\A\Gfoo')['flags']);
        $this->assertStringContainsString('y', $this->convert('\Gfoo|bar')['flags']);
    }

    #[Test]
    public function non_leading_scan_anchor_emits_real_anchor_without_marking_sticky(): void
    {
        foreach (['foo\Gbar', '(?:\G|^)x', '(a)\G', '(?!\G)', 'foo|\Gbar'] as $pattern) {
            $result = $this->convert($pattern);
            $this->assertStringNotContainsString('y', $result['flags'], $pattern);
            $this->assertStringContainsString('\G', $result['pattern'], $pattern);
        }
    }

    #[Test]
    public function non_leading_scan_anchor_matches_only_at_scan_start(): void
    {
        // `(^|\G)foo`: the `^` branch matches at any line start; the `\G` branch
        // only at the scan origin. From offset 0, `\G` succeeds; from offset 1,
        // the `\G` branch must fail (and `^` does not apply mid-line).
        $result = $this->converter->convert('(^|\G)foo');
        $matcher = new Matcher(
            (new Parser($result['pattern'], $result['flags']))->parse(),
            $result['flags'],
        );

        $hit = $matcher->match('foofoo', 0);
        $this->assertNotNull($hit);
        $this->assertSame(0, $hit['index']);

        // Starting the scan at 1: `\G` only matches at 1; `foo` is at 3, so the
        // `\G` branch cannot reach it and `^` does not apply -> no match.
        $this->assertNull($matcher->match('foofoo', 1));
    }

    #[Test]
    public function sticky_matcher_matches_only_at_start_offset(): void
    {
        $result = $this->converter->convert('\Gfoo');
        $matcher = new Matcher(
            (new Parser($result['pattern'], $result['flags']))->parse(),
            $result['flags'],
        );

        $this->assertNotNull($matcher->match('foo', 0));
        $this->assertNotNull($matcher->match('xxfoo', 2));
        // The token sits at offset 2 but the scan starts at 0: a sticky pattern
        // must not advance to find it.
        $this->assertNull($matcher->match('xxfoo', 0));
        $this->assertFalse($matcher->matchTest('xxfoo', 0));
        $this->assertTrue($matcher->matchTest('xxfoo', 2));
    }

    #[Test]
    public function line_break_escape(): void
    {
        $result = $this->convert('a\Rb');
        $this->assertStringContainsString('\r\n', $result['pattern']);
    }

    #[Test]
    public function possessive_plus_becomes_atomic_emulation(): void
    {
        $this->assertSame('(?:(?=(?<atomic1>a+))\k<atomic1>)', $this->convert('a++')['pattern']);
    }

    #[Test]
    public function possessive_star_and_interval(): void
    {
        $this->assertSame('(?:(?=(?<atomic1>a*))\k<atomic1>)', $this->convert('a*+')['pattern']);
        $this->assertSame('(?:(?=(?<atomic1>a{2,5}))\k<atomic1>)', $this->convert('a{2,5}+')['pattern']);
    }

    #[Test]
    public function atomic_group_emulation(): void
    {
        $this->assertSame(
            '(?:(?=(?<atomic1>foo|bar))\k<atomic1>)',
            $this->convert('(?>foo|bar)')['pattern'],
        );
    }

    #[Test]
    public function lazy_quantifier_is_preserved(): void
    {
        $this->assertSame('a*?', $this->convert('a*?')['pattern']);
    }

    #[Test]
    public function global_inline_case_insensitive_flag(): void
    {
        $result = $this->convert('(?i)hello');
        $this->assertSame('hello', $result['pattern']);
        $this->assertSame('ui', $result['flags']);
    }

    #[Test]
    public function global_inline_multiline_flag_implies_dotall(): void
    {
        $this->assertSame('us', $this->convert('(?m)a.b')['flags']);
    }

    #[Test]
    public function scoped_case_insensitive_group(): void
    {
        $this->assertSame('(?i:Hello)', $this->convert('(?i:Hello)')['pattern']);
    }

    #[Test]
    public function extended_mode_strips_whitespace_and_comments(): void
    {
        $this->assertSame('foobar', $this->convert("(?x) foo  # a comment\n bar")['pattern']);
    }

    #[Test]
    public function named_group_angle_with_backref(): void
    {
        $this->assertSame('(?<name>\w+)\k<name>', $this->convert('(?<name>\w+)\k<name>')['pattern']);
    }

    #[Test]
    public function named_group_quoted_form_normalises_to_angle(): void
    {
        $this->assertSame('(?<n>\d+)', $this->convert("(?'n'\\d+)")['pattern']);
    }

    #[Test]
    public function quoted_named_backref_normalises_to_angle(): void
    {
        $this->assertSame(
            '(?<n>\w+)\k<n>',
            $this->convert("(?<n>\\w+)\\k'n'")['pattern'],
        );
    }

    #[Test]
    public function numbered_backref_passthrough(): void
    {
        $this->assertSame('(\w+)\1', $this->convert('(\w+)\1')['pattern']);
    }

    #[Test]
    public function subroutine_ref_inlines_group_body(): void
    {
        $this->assertSame('(\w+)(?:\w+)', $this->convert('(\w+)\g<1>')['pattern']);
    }

    #[Test]
    public function unicode_property_passthrough(): void
    {
        $this->assertSame('\p{L}+', $this->convert('\p{L}+')['pattern']);
        $this->assertSame('\P{L}', $this->convert('\P{L}')['pattern']);
    }

    #[Test]
    public function negated_unicode_property_caret_form(): void
    {
        $this->assertSame('\P{L}', $this->convert('\p{^L}')['pattern']);
    }

    #[Test]
    public function posix_property_name_in_brace_expands_to_class(): void
    {
        $this->assertSame('[\p{L}\p{Nd}\p{Pc}\p{Mn}\p{Mc}]*', $this->convert('\p{word}*')['pattern']);
        $this->assertSame('\p{L}', $this->convert('\p{alpha}')['pattern']);
    }

    #[Test]
    public function posix_property_name_in_brace_uses_negated_complement(): void
    {
        $this->assertSame('\W', $this->convert('\p{^word}')['pattern']);
        $this->assertSame('\W', $this->convert('\P{word}')['pattern']);
    }

    #[Test]
    public function posix_property_name_inside_class_is_unbracketed(): void
    {
        $this->assertSame('[a\p{L}\p{Nd}\p{Pc}\p{Mn}\p{Mc}b]', $this->convert('[a\p{word}b]')['pattern']);
    }

    #[Test]
    public function double_quoted_string_pattern(): void
    {
        $this->assertSame('"(?:\.|[^"\\\\])*"', $this->convert('"(?:\.|[^"\\\\])*"')['pattern']);
    }

    #[Test]
    public function lookahead_and_lookbehind_passthrough(): void
    {
        $this->assertSame('(?=a)(?!b)(?<=c)(?<!d)', $this->convert('(?=a)(?!b)(?<=c)(?<!d)')['pattern']);
    }

    #[Test]
    public function nested_class_is_flattened(): void
    {
        $this->assertSame('[abc]', $this->convert('[a[bc]]')['pattern']);
    }

    #[Test]
    public function negated_posix_uses_specific_complement(): void
    {
        $this->assertSame('[\P{Lu}]+', $this->convert('[[:^upper:]]+')['pattern']);
        $this->assertSame('[\P{Nd}]', $this->convert('[[:^digit:]]')['pattern']);
    }

    #[Test]
    public function brace_hex_escape_becomes_unicode_brace(): void
    {
        $this->assertSame('\u{48}\u{49}', $this->convert('\x{48}\x{49}')['pattern']);
    }

    #[Test]
    public function two_digit_hex_escape_is_preserved(): void
    {
        $this->assertSame('\x48', $this->convert('\x48')['pattern']);
    }

    #[Test]
    public function intersection_emulated_with_lookahead(): void
    {
        $this->assertSame('(?:(?=[\w])[^0-9])+', $this->convert('[\w&&[^0-9]]+')['pattern']);
    }

    #[Test]
    public function nested_negated_class_treats_leading_bracket_as_literal(): void
    {
        $this->assertSame(
            '(?:(?=[\p{S}\p{P}])[^\]"\'(),;\[_`{}])',
            $this->convert('[[\p{S}\p{P}]&&[^]"\'(),;\[_`{}]]')['pattern'],
        );
    }

    #[Test]
    public function atomic_group_reports_its_injected_slot(): void
    {
        $this->assertSame([1], $this->convert('(?>a+)(b+)')['atomicSlots']);
    }

    #[Test]
    public function out_of_order_interval_is_literal(): void
    {
        $this->assertSame('\s+\{1,0\}', $this->convert('\s+{1,0}')['pattern']);
    }

    #[Test]
    public function out_of_order_interval_matches_literally(): void
    {
        $result = $this->convert('\s+{1,0}');
        $matcher = new Matcher((new Parser($result['pattern'], $result['flags']))->parse(), $result['flags']);
        $this->assertNotNull($matcher->match(' {1,0}', 0));
        $this->assertNull($matcher->match(' ', 0));
    }

    #[Test]
    public function well_formed_intervals_remain_quantifiers(): void
    {
        $this->assertSame('a{2,5}', $this->convert('a{2,5}')['pattern']);
        $this->assertSame('a{3}', $this->convert('a{3}')['pattern']);
        $this->assertSame('a{2,}', $this->convert('a{2,}')['pattern']);
    }

    #[Test]
    public function absence_operator_expands_to_negated_run(): void
    {
        $this->assertSame('(?:(?!\*\/)[\s\S])*', $this->convert('(?~\*/)')['pattern']);
    }

    #[Test]
    public function absence_operator_matches_run_without_pattern(): void
    {
        $result = $this->convert('(?~\*/)');
        $matcher = new Matcher((new Parser($result['pattern'], $result['flags']))->parse(), $result['flags']);

        $stop = $matcher->match('abc*/def', 0);
        $this->assertNotNull($stop);
        $this->assertSame(0, $stop['index']);
        $this->assertSame(3, $stop['end']);

        $all = $matcher->match('abcdef', 0);
        $this->assertNotNull($all);
        $this->assertSame(6, $all['end']);
    }
}
