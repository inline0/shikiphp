<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\Theme;

/**
 * Shiki's dedicated `lang: 'ansi'` tokenizer: rather than a TextMate grammar it
 * parses ANSI SGR escape sequences directly and paints each run with the theme's
 * default fg/bg and ANSI palette. Port of @shikijs/core's `tokenizeAnsiWithTheme`.
 */
final class AnsiTokenizer
{
    /**
     * @param array<string, string> $replacements lowercased-hex colour remap
     * @return list<list<ThemedToken>>
     */
    public function tokenize(string $code, Theme $theme, array $replacements): array
    {
        $palette = AnsiPalette::fromTheme($theme);
        $parser = new AnsiSequenceParser();
        $themeFg = $theme->foreground();
        $themeBg = $theme->background();

        $lines = self::splitLines($code);

        $out = [];
        foreach ($lines as [$lineText, $offset]) {
            $tokens = [];
            foreach ($parser->parse($lineText) as $token) {
                $reverse = isset($token->decorations['reverse']);
                if ($reverse) {
                    $color = $token->background !== null ? $palette->value($token->background) : $themeBg;
                    $bgColor = $token->foreground !== null ? $palette->value($token->foreground) : $themeFg;
                } else {
                    $color = $token->foreground !== null ? $palette->value($token->foreground) : $themeFg;
                    $bgColor = $token->background !== null ? $palette->value($token->background) : null;
                }

                $color = self::applyColorReplacements($color, $replacements);
                $bgColor = self::applyColorReplacements($bgColor, $replacements);

                if (isset($token->decorations['dim']) && $color !== null) {
                    $color = self::dimColor($color);
                }

                $fontStyle = FontStyle::NONE;
                if (isset($token->decorations['bold'])) {
                    $fontStyle |= FontStyle::BOLD;
                }
                if (isset($token->decorations['italic'])) {
                    $fontStyle |= FontStyle::ITALIC;
                }
                if (isset($token->decorations['underline'])) {
                    $fontStyle |= FontStyle::UNDERLINE;
                }
                if (isset($token->decorations['strikethrough'])) {
                    $fontStyle |= FontStyle::STRIKETHROUGH;
                }

                $tokens[] = new ThemedToken($token->value, $color, $fontStyle, $bgColor, null, $offset);
            }

            $out[] = $tokens;
        }

        return $out;
    }

    /**
     * @param array<string, string> $replacements
     */
    private static function applyColorReplacements(?string $color, array $replacements): ?string
    {
        if ($color === null) {
            return null;
        }

        return $replacements[strtolower($color)] ?? $color;
    }

    private static function dimColor(string $color): string
    {
        if (preg_match('/#([0-9a-f]{3,8})/i', $color, $m) === 1) {
            $hex = $m[1];
            $len = strlen($hex);
            if ($len === 8) {
                $alpha = str_pad(dechex((int) round(intval(substr($hex, 6, 2), 16) / 2)), 2, '0', STR_PAD_LEFT);
                return '#' . substr($hex, 0, 6) . $alpha;
            }
            if ($len === 6) {
                return '#' . $hex . '80';
            }
            if ($len === 4) {
                $r = $hex[0];
                $g = $hex[1];
                $b = $hex[2];
                $a = $hex[3];
                $alpha = str_pad(dechex((int) round(intval($a . $a, 16) / 2)), 2, '0', STR_PAD_LEFT);
                return '#' . $r . $r . $g . $g . $b . $b . $alpha;
            }
            if ($len === 3) {
                $r = $hex[0];
                $g = $hex[1];
                $b = $hex[2];
                return '#' . $r . $r . $g . $g . $b . $b . '80';
            }
        }

        if (preg_match('/var\((--[\w-]+-ansi-[\w-]+)\)/', $color, $m) === 1) {
            return 'var(' . $m[1] . '-dim)';
        }

        return $color;
    }

    /**
     * @return list<array{0: string, 1: int}> lines with UTF-16 start offsets
     */
    private static function splitLines(string $code): array
    {
        if ($code === '') {
            return [['', 0]];
        }

        $parts = preg_split('/(\r?\n)/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [[$code, 0]];
        }

        $lines = [];
        $index = 0;
        for ($i = 0; $i < count($parts); $i += 2) {
            $line = $parts[$i];
            $lines[] = [$line, $index];
            $index += self::utf16Length($line);
            $index += isset($parts[$i + 1]) ? self::utf16Length($parts[$i + 1]) : 0;
        }

        return $lines;
    }

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
