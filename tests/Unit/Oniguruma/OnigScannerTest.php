<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Oniguruma;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Oniguruma\OnigScanner;
use Shikiphp\Oniguruma\OnigString;

final class OnigScannerTest extends TestCase
{
    /** @param list<string> $patterns */
    private function scan(array $patterns, string $content, int $start = 0): ?\Shikiphp\Oniguruma\OnigMatch
    {
        return (new OnigScanner($patterns))->findNextMatch(new OnigString($content), $start);
    }

    /**
     * @param \Shikiphp\Oniguruma\OnigMatch $match
     * @return list<array{int, int}>
     */
    private function spans(\Shikiphp\Oniguruma\OnigMatch $match): array
    {
        return array_map(
            static fn ($ci): array => [$ci->start, $ci->end],
            $match->captureIndices,
        );
    }

    #[Test]
    public function returns_null_when_no_pattern_matches(): void
    {
        $this->assertNull($this->scan(['\bnope\b'], 'nothing here'));
    }

    #[Test]
    public function double_quoted_string_with_escapes(): void
    {
        $match = $this->scan(['"(?:\\\\.|[^"\\\\])*"'], 'x = "a\\"b" ;');
        $this->assertNotNull($match);
        $this->assertSame(0, $match->index);
        $this->assertSame([[4, 10]], $this->spans($match));
    }

    #[Test]
    public function line_comment_to_end_of_line(): void
    {
        $match = $this->scan(['//.*$'], 'code // trailing');
        $this->assertNotNull($match);
        $this->assertSame([[5, 16]], $this->spans($match));
    }

    #[Test]
    public function word_keyword_with_boundaries(): void
    {
        $match = $this->scan(['\breturn\b'], '  return x');
        $this->assertNotNull($match);
        $this->assertSame([[2, 8]], $this->spans($match));
    }

    #[Test]
    public function leftmost_match_wins_across_patterns(): void
    {
        $match = $this->scan(['bar', 'baz', 'foo'], 'foo bar baz');
        $this->assertNotNull($match);
        $this->assertSame(2, $match->index);
        $this->assertSame([[0, 3]], $this->spans($match));
    }

    #[Test]
    public function tie_at_same_position_breaks_to_lowest_index(): void
    {
        $match = $this->scan(['a.', 'abc', 'ab'], 'abc');
        $this->assertNotNull($match);
        $this->assertSame(0, $match->index);
        $this->assertSame([[0, 2]], $this->spans($match));
    }

    #[Test]
    public function whole_match_is_capture_zero_then_groups(): void
    {
        $match = $this->scan(['(\w+):\s*(\w+)'], 'key: value');
        $this->assertNotNull($match);
        $this->assertCount(3, $match->captureIndices);
        $this->assertSame([[0, 10], [0, 3], [5, 10]], $this->spans($match));
    }

    #[Test]
    public function non_participating_group_is_empty_span(): void
    {
        $match = $this->scan(['(a)|(b)'], 'a');
        $this->assertNotNull($match);
        $this->assertSame([0, 1], $this->spans($match)[0]);
        $this->assertSame([0, 1], $this->spans($match)[1]);
        $span = $this->spans($match)[2];
        $this->assertSame($span[0], $span[1]);
    }

    #[Test]
    public function search_respects_start_position(): void
    {
        $match = $this->scan(['foo'], 'foo foo', 1);
        $this->assertNotNull($match);
        $this->assertSame([[4, 7]], $this->spans($match));
    }

    #[Test]
    public function bad_pattern_is_skipped_not_fatal(): void
    {
        $match = $this->scan(['(unclosed', 'good'], 'good');
        $this->assertNotNull($match);
        $this->assertSame(1, $match->index);
        $this->assertSame([[0, 4]], $this->spans($match));
    }

    #[Test]
    public function posix_class_pattern_matches(): void
    {
        $match = $this->scan(['[[:alpha:]]+'], '123 abc');
        $this->assertNotNull($match);
        $this->assertSame([[4, 7]], $this->spans($match));
    }

    #[Test]
    public function utf16_offsets_for_astral_input(): void
    {
        $match = $this->scan(['b'], "\u{1F600}b");
        $this->assertNotNull($match);
        $this->assertSame([[2, 3]], $this->spans($match));
    }

    #[Test]
    public function g_anchor_pins_match_to_start_position(): void
    {
        $this->assertNull($this->scan(['\Gfoo'], 'xfoo', 0));
        $hit = $this->scan(['\Gfoo'], 'xxfoo', 2);
        $this->assertNotNull($hit);
        $this->assertSame([[2, 5]], $this->spans($hit));
    }

    #[Test]
    public function g_anchored_pattern_does_not_match_past_the_anchor(): void
    {
        // The token only appears later in the line; a `\G`-anchored pattern
        // must refuse it (it would over-match without proper anchoring).
        $this->assertNull($this->scan(['\Gbar'], 'foo bar', 0));
    }

    #[Test]
    public function g_anchored_loses_to_forward_searching_pattern_in_same_scanner(): void
    {
        // Pattern 0 is `\G`-anchored and cannot match at offset 0 ("xx..."),
        // so the non-anchored pattern 1 wins by searching forward to "bar".
        $match = $this->scan(['\Gfoo', 'bar'], 'xxbar', 0);
        $this->assertNotNull($match);
        $this->assertSame(1, $match->index);
        $this->assertSame([[2, 5]], $this->spans($match));
    }

    #[Test]
    public function non_g_pattern_still_searches_forward(): void
    {
        $match = $this->scan(['foo'], 'xxxfoo', 0);
        $this->assertNotNull($match);
        $this->assertSame([[3, 6]], $this->spans($match));
    }

    #[Test]
    public function non_leading_g_in_alternation_anchors_to_scan_start(): void
    {
        // Markdown fenced-code `while`: `(^|\G)(?!\s*```...$)`. On a closing-fence
        // line the negative lookahead fails at every valid `^`/`\G` position, so
        // the scanner must report no match (the block pops). A naive `\G`->empty
        // would let the empty alternative match mid-line and wrongly continue.
        $while = '(^|\G)(?!\s*([`~]{3,})\s*$)';
        $this->assertNull($this->scan([$while], "```\n", 0));

        // On a content line the lookahead passes, so the `while` matches (the
        // block continues).
        $cont = $this->scan([$while], "code line\n", 0);
        $this->assertNotNull($cont);
        $this->assertSame(0, $cont->index);
    }

    #[Test]
    public function atomic_group_does_not_leak_a_phantom_capture(): void
    {
        $match = $this->scan(['(?>a+)(b+)'], 'aabb');
        $this->assertNotNull($match);
        $this->assertSame([[0, 4], [2, 4]], $this->spans($match));
    }

    #[Test]
    public function possessive_quantifier_reports_no_extra_group(): void
    {
        $match = $this->scan(['a++'], 'aaab');
        $this->assertNotNull($match);
        $this->assertSame([[0, 3]], $this->spans($match));
    }

    #[Test]
    public function intersection_class_stops_at_excluded_char(): void
    {
        $match = $this->scan(['[a-z&&[^aeiou]]+'], 'hello');
        $this->assertNotNull($match);
        $this->assertSame([[0, 1]], $this->spans($match));
    }

    #[Test]
    public function negated_posix_class_matches_complement(): void
    {
        $match = $this->scan(['[[:^upper:]]+'], 'abcD');
        $this->assertNotNull($match);
        $this->assertSame([[0, 3]], $this->spans($match));
    }

    #[Test]
    public function brace_hex_escape_matches(): void
    {
        $match = $this->scan(['\x{48}\x{49}'], 'HI');
        $this->assertNotNull($match);
        $this->assertSame([[0, 2]], $this->spans($match));
    }

    #[Test]
    public function hex_digit_escape_matches_only_hex(): void
    {
        $this->assertNull($this->scan(['a\hb'], "a\tb"));
        $match = $this->scan(['\H+'], 'ab cd');
        $this->assertNotNull($match);
        $this->assertSame([[2, 3]], $this->spans($match));
    }
}
