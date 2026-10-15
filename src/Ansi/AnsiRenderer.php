<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

use InvalidArgumentException;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\Theme;

/** Shiki CLI's codeToANSI, including the explicit Ansis colour levels. */
final class AnsiRenderer
{
    /** @param list<list<ThemedToken>> $lines */
    public function render(array $lines, Theme $theme, int $colorLevel = 3): string
    {
        if ($colorLevel < 0 || $colorLevel > 3) {
            throw new InvalidArgumentException('ANSI colorLevel must be between 0 and 3.');
        }
        $output = '';
        foreach ($lines as $line) {
            foreach ($line as $token) {
                $text = $token->content;
                if ($colorLevel !== 0) {
                    [$r, $g, $b] = self::color($token->color ?: $theme->foreground(), $theme->type());
                    $color = match ($colorLevel) {
                        3 => "38;2;{$r};{$g};{$b}",
                        2 => '38;5;' . self::paletteIndex($r, $g, $b),
                        default => (string) self::basicColor(self::paletteIndex($r, $g, $b)),
                    };
                    $text = self::wrap($text, $color, 39);
                    foreach (
                        [[FontStyle::BOLD, 1, 22], [FontStyle::ITALIC, 3, 23],
                        [FontStyle::UNDERLINE, 4, 24], [FontStyle::STRIKETHROUGH, 9, 29]] as [$flag, $open, $close]
                    ) {
                        if (($token->fontStyle & $flag) !== 0) {
                            $text = self::wrap($text, (string) $open, $close);
                        }
                    }
                }
                $output .= $text;
            }
            $output .= "\n";
        }

        return $output;
    }

    private static function wrap(string $text, string $code, int $close): string
    {
        if ($text === '') {
            return '';
        }
        $open = "\e[{$code}m";
        $end = "\e[{$close}m";
        $text = str_replace($end, $open, $text);
        $text = preg_replace('/(\r?\n)/', $end . '$1' . $open, $text) ?? $text;

        return $open . $text . $end;
    }

    /** @return array{int, int, int} */
    private static function color(string $hex, string $type): array
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = implode('', array_map(static fn (string $c): string => $c . $c, str_split($hex)));
        }
        if (strlen($hex) === 6) {
            $hex .= 'ff';
        }
        $alpha = hexdec(substr($hex, 6, 2)) / 255;
        $flattened = '';
        for ($i = 0; $i < 6; $i += 2) {
            $value = hexdec(substr($hex, $i, 2)) * $alpha + ($type === 'light' ? 255 * (1 - $alpha) : 0);
            // Match the upstream helper's Number.toString(16), including fractions.
            $digits = dechex((int) $value);
            $fraction = $value - floor($value);
            if ($fraction > 0) {
                $digits .= '.';
                for ($j = 0; $fraction > 0 && $j < 20; $j++) {
                    $fraction *= 16;
                    $digits .= dechex((int) $fraction);
                    $fraction -= floor($fraction);
                }
            }
            $flattened .= strlen($digits) === 1 ? '0' . $digits : $digits;
        }
        preg_match('/([a-f\d]{3,6})/i', $flattened, $match);
        $color = $match[1] ?? '';
        if (strlen($color) === 3) {
            $color = implode('', array_map(static fn (string $c): string => $c . $c, str_split($color)));
        }
        $rgb = strlen($color) === 6 ? (int) hexdec($color) : 0;

        return [$rgb >> 16, ($rgb >> 8) & 255, $rgb & 255];
    }

    private static function paletteIndex(int $r, int $g, int $b): int
    {
        if ($r === $g && $g === $b) {
            return $r < 8 ? 16 : ($r > 248 ? 231 : (int) round(24 * ($r - 8) / 247) + 232);
        }

        return 16 + 36 * (int) round($r / 51) + 6 * (int) round($g / 51) + (int) round($b / 51);
    }

    private static function basicColor(int $index): int
    {
        if ($index < 8) {
            return 30 + $index;
        }
        if ($index < 16) {
            return 82 + $index;
        }
        if ($index > 231) {
            return $index > 243 ? 37 : 30;
        }
        $index -= 16;
        $r = intdiv($index, 36);
        $g = intdiv($index % 36, 6);
        $b = $index % 6;

        return 30 + (int) ($r > 2) + ((int) ($g > 2) << 1) + ((int) ($b > 2) << 2)
            + (max($r, $g, $b) > 4 ? 60 : 0);
    }
}
