<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Oniguruma;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Oniguruma\PcreMatcher;
use Shikiphp\Oniguruma\PcreTranslator;
use Shikiphp\Regex\Matcher;
use Shikiphp\Regex\Parser;

/**
 * Guards the equivalence contract: every pattern the PcreTranslator classifies
 * PCRE-SAFE must produce byte-identical results (index, end, every capture span)
 * to the vendored Matcher. Divergent constructs must be rejected (translate →
 * null) so they stay on the VM.
 */
final class PcreFastPathTest extends TestCase
{
    /** @return iterable<string, array{string, string, list<string>}> */
    public static function safePatterns(): iterable
    {
        $inputs = ['', 'abc', 'ABC123', 'foo_bar baz', '  spaced  ', "line\n", 'café', '😀x', '/path/to', 'a.b.c', 'x+y-z'];
        yield 'identifier' => ['[A-Za-z_][A-Za-z0-9_]*', 'u', $inputs];
        yield 'digits' => ['\\d+', 'u', $inputs];
        yield 'word' => ['\\w+', 'u', $inputs];
        yield 'whitespace' => ['\\s+', 'u', $inputs];
        yield 'non-digit' => ['\\D+', 'u', $inputs];
        yield 'dot-star' => ['.*', 'u', $inputs];
        yield 'class-with-slash' => ['[-%+/]', 'u', $inputs];
        yield 'anchor-start' => ['(?<=^|\\n(?!$))\\s*', 'u', $inputs];
        yield 'anchor-end' => ['foo(?=$|\\n)', 'u', $inputs];
        yield 'alternation' => ['(foo|bar|baz)', 'u', $inputs];
        yield 'lookbehind-no-capture' => ['(?<!:)\\*', 'u', $inputs];
        yield 'lookahead' => ['x(?=y)', 'u', ['xy', 'xz', 'axy']];
        yield 'case-insensitive' => ['hello', 'ui', ['HELLO', 'Hello', 'hella']];
        yield 'interval' => ['a{2,4}', 'u', ['a', 'aa', 'aaaaa']];
        yield 'lazy' => ['<.*?>', 'u', ['<a><b>']];
        yield 'hex' => ['\\x41', 'u', ['A', 'B']];
        yield 'unicode-escape' => ['\\u0041', 'u', ['A', 'B']];
        yield 'optional-group' => ['colou?r', 'u', ['color', 'colour']];
        yield 'nested-noncapture' => ['(?:(?:a|b)c)+', 'u', ['acbc', 'ac', 'x']];
    }

    /**
     * @param list<string> $inputs
     */
    #[Test]
    #[DataProvider('safePatterns')]
    public function fast_path_matches_vm_for_safe_patterns(string $js, string $flags, array $inputs): void
    {
        $t = (new PcreTranslator())->translate($js, $flags);
        self::assertNotNull($t, "expected PCRE-safe: {$js}");
        self::assertNotFalse(@preg_match($t['pcre'], ''), "PCRE compile failed: {$t['pcre']}");

        $matcher = new Matcher((new Parser($js, $flags))->parse(), $flags);
        $pcre = new PcreMatcher($t['pcre']);

        foreach ($inputs as $input) {
            for ($start = 0; $start <= self::utf16Len($input); $start++) {
                $vm = $matcher->match($input, $start);
                $fp = $pcre->match($input, $start);
                self::assertSame(
                    self::normalize($vm),
                    self::normalize($fp),
                    sprintf('pattern %s input %s start %d', $js, json_encode($input), $start),
                );
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsafePatterns(): iterable
    {
        yield 'word-boundary' => ['\\bword\\b', 'u'];
        yield 'unicode-property' => ['\\p{L}+', 'u'];
        yield 'numbered-backref' => ['(a)\\1', 'u'];
        yield 'named-group' => ['(?<n>a)', 'u'];
        yield 'named-backref' => ['(?<n>a)\\k<n>', 'u'];
        yield 'quantified-capture' => ['(ab)+', 'u'];
        yield 'lookbehind-capture' => ['(?<=(a))b', 'u'];
        yield 'atomic-emulation' => ['(?:(?=(?<atomic1>a+))\\k<atomic1>)', 'u'];
        yield 'scan-anchor' => ['\\Gfoo', 'u'];
        // The Matcher mis-backtracks across alternatives in a scoped-flag group
        // when a trailing atom rejects the shorter branch; PCRE would diverge.
        yield 'inline-flag-group' => ['(?i:add|address)(?![a-z])', 'u'];
        yield 'unbounded-lookbehind' => ['(?<=\\s*\\))x', 'u'];
        // A lookahead opening a lookbehind branch is evaluated at a different
        // anchor in PCRE than in ES.
        yield 'leading-lookahead-in-lookbehind' => ['(?<=(?=$|\\n)|[ ])x', 'u'];
    }

    #[Test]
    #[DataProvider('unsafePatterns')]
    public function rejects_divergent_constructs(string $js, string $flags): void
    {
        self::assertNull((new PcreTranslator())->translate($js, $flags), "expected VM fallback: {$js}");
    }

    /**
     * @param array{index:int,end:int,captures:list<?array{0:int,1:int,2?:string}>}|null $r
     * @return array{index:int,end:int,captures:list<?array{0:int,1:int}>}|null
     */
    private static function normalize(?array $r): ?array
    {
        if ($r === null) {
            return null;
        }
        $caps = [];
        foreach ($r['captures'] as $c) {
            $caps[] = $c === null ? null : [$c[0], $c[1]];
        }
        return ['index' => $r['index'], 'end' => $r['end'], 'captures' => $caps];
    }

    private static function utf16Len(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
