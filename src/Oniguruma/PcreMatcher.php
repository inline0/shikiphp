<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/**
 * Runs a PcreTranslator-produced PCRE pattern via native preg_match and returns
 * results in the exact shape the vendored Matcher produces: UTF-16 code-unit
 * offsets for the whole match and every capture group. Used only for patterns
 * the translator proved equivalent (see PcreTranslator).
 *
 * preg_match reports byte offsets; this class maps offsets to/from UTF-16 code
 * units so the scanner stays in UTF-16 space throughout. A start offset given in
 * UTF-16 units is converted to a byte offset for the search; sticky (`y`) is
 * already baked into the pattern as the `A` modifier by PcreTranslator.
 */
final class PcreMatcher
{
    public function __construct(
        private readonly string $pcre,
    ) {
    }

    /**
     * @return array{index:int, end:int, captures:list<?array{0:int,1:int}>}|null
     */
    public function match(string $inputUtf8, int $startCodeUnit): ?array
    {
        $byteOffset = self::utf16ToByteOffset($inputUtf8, $startCodeUnit);
        if ($byteOffset === null) {
            return null;
        }

        $flags = PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL;
        $matches = [];
        $r = @preg_match($this->pcre, $inputUtf8, $matches, $flags, $byteOffset);
        if ($r === false) {
            // A PCRE runtime error (e.g. backtrack/recursion limit) is not a
            // clean no-match; signal the scanner to fall back to the VM so the
            // result still matches the source-of-truth Matcher.
            throw new PcreMatchError(preg_last_error_msg());
        }
        if ($r !== 1) {
            return null;
        }

        // Byte → UTF-16 conversion is monotonic in offset; cache the running
        // (byte, codeUnit) cursor so the per-capture conversions stay linear.
        $cursorByte = 0;
        $cursorCu = 0;

        $convert = function (int $byte) use ($inputUtf8, &$cursorByte, &$cursorCu): int {
            if ($byte < $cursorByte) {
                $cursorByte = 0;
                $cursorCu = 0;
            }
            $cursorCu += self::utf16LenRange($inputUtf8, $cursorByte, $byte);
            $cursorByte = $byte;
            return $cursorCu;
        };

        $whole = $matches[0];
        $index = $convert($whole[1]);
        $end = $convert($whole[1] + strlen((string) $whole[0]));

        $captures = [[$index, $end]];
        $n = count($matches);
        for ($i = 1; $i < $n; $i++) {
            $cap = $matches[$i];
            // PREG_UNMATCHED_AS_NULL reports a non-participating group as
            // [null, -1]: null text, -1 offset.
            if ($cap[0] === null || $cap[1] < 0) {
                $captures[] = null;
                continue;
            }
            $cs = $convert($cap[1]);
            $ce = $convert($cap[1] + strlen((string) $cap[0]));
            $captures[] = [$cs, $ce];
        }

        return ['index' => $index, 'end' => $end, 'captures' => $captures];
    }

    /**
     * UTF-16 code-unit count of the UTF-8 byte range [from, to). Walks UTF-8 lead
     * bytes: a 4-byte sequence (astral) is two UTF-16 code units, all others one.
     */
    private static function utf16LenRange(string $utf8, int $from, int $to): int
    {
        $count = 0;
        $i = $from;
        while ($i < $to) {
            $b = ord($utf8[$i]);
            if ($b < 0x80) {
                $i += 1;
                $count += 1;
            } elseif ($b < 0xE0) {
                $i += 2;
                $count += 1;
            } elseif ($b < 0xF0) {
                $i += 3;
                $count += 1;
            } else {
                $i += 4;
                $count += 2;
            }
        }
        return $count;
    }

    /**
     * Convert a UTF-16 code-unit offset to a UTF-8 byte offset. Returns null if
     * the offset falls inside a surrogate pair (no byte boundary there) — the
     * Matcher returns null in that case too.
     */
    private static function utf16ToByteOffset(string $utf8, int $codeUnit): ?int
    {
        if ($codeUnit <= 0) {
            return 0;
        }
        $cu = 0;
        $i = 0;
        $len = strlen($utf8);
        while ($i < $len) {
            if ($cu === $codeUnit) {
                return $i;
            }
            $b = ord($utf8[$i]);
            if ($b < 0x80) {
                $i += 1;
                $cu += 1;
            } elseif ($b < 0xE0) {
                $i += 2;
                $cu += 1;
            } elseif ($b < 0xF0) {
                $i += 3;
                $cu += 1;
            } else {
                $i += 4;
                $cu += 2;
                if ($cu > $codeUnit) {
                    // Offset lands between the surrogate halves of an astral char.
                    return null;
                }
            }
        }
        return $cu === $codeUnit ? $len : ($cu < $codeUnit ? $len : null);
    }
}
