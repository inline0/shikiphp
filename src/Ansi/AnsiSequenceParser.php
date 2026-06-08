<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

/**
 * Stateful parser over a string containing ANSI CSI SGR (`\x1b[ … m`) escape
 * sequences, splitting it into {@see AnsiToken} runs that carry the active
 * foreground/background colour and decoration set. Faithful port of
 * ansi-sequence-parser as bundled in @shikijs/core.
 *
 * One instance carries state across multiple {@see parse()} calls so styles
 * persist across lines, matching Shiki's single-parser-per-document behaviour.
 */
final class AnsiSequenceParser
{
    /** @var array<int, string> SGR code → decoration name */
    private const DECORATIONS = [
        1 => 'bold',
        2 => 'dim',
        3 => 'italic',
        4 => 'underline',
        7 => 'reverse',
        8 => 'hidden',
        9 => 'strikethrough',
    ];

    private ?AnsiColor $foreground = null;
    private ?AnsiColor $background = null;

    /** @var array<string, true> */
    private array $decorations = [];

    /**
     * @return list<AnsiToken>
     */
    public function parse(string $value): array
    {
        $tokens = [];
        $position = 0;
        $length = self::utf16Length($value);

        do {
            $findResult = self::findSequence($value, $position);
            $text = $findResult['sequence'] !== null
                ? self::utf16Substr($value, $position, $findResult['startPosition'])
                : self::utf16Substr($value, $position, $length);

            if ($text !== '') {
                $tokens[] = new AnsiToken(
                    $text,
                    $this->foreground,
                    $this->background,
                    $this->decorations,
                );
            }

            if ($findResult['sequence'] !== null) {
                $commands = self::parseSequence($findResult['sequence']);
                foreach ($commands as $command) {
                    if ($command['type'] === 'resetAll') {
                        $this->foreground = null;
                        $this->background = null;
                        $this->decorations = [];
                    } elseif ($command['type'] === 'resetForegroundColor') {
                        $this->foreground = null;
                    } elseif ($command['type'] === 'resetBackgroundColor') {
                        $this->background = null;
                    } elseif ($command['type'] === 'resetDecoration' && isset($command['value'])) {
                        unset($this->decorations[$command['value']]);
                    }
                }
                foreach ($commands as $command) {
                    if ($command['type'] === 'setForegroundColor' && isset($command['color'])) {
                        $this->foreground = $command['color'];
                    } elseif ($command['type'] === 'setBackgroundColor' && isset($command['color'])) {
                        $this->background = $command['color'];
                    } elseif ($command['type'] === 'setDecoration' && isset($command['value'])) {
                        $this->decorations[$command['value']] = true;
                    }
                }
            }

            $position = $findResult['position'];
        } while ($position < $length);

        return $tokens;
    }

    /**
     * @return array{sequence: list<string>|null, startPosition: int, position: int}
     */
    private static function findSequence(string $value, int $position): array
    {
        $nextEscape = self::utf16IndexOf($value, "\x1b", $position);
        if ($nextEscape !== -1) {
            if (self::utf16CharAt($value, $nextEscape + 1) === '[') {
                $nextClose = self::utf16IndexOf($value, 'm', $nextEscape);
                if ($nextClose !== -1) {
                    $raw = self::utf16Substr($value, $nextEscape + 2, $nextClose);
                    return [
                        'sequence' => explode(';', $raw),
                        'startPosition' => $nextEscape,
                        'position' => $nextClose + 1,
                    ];
                }
            }
        }

        return [
            'sequence' => null,
            'startPosition' => 0,
            'position' => self::utf16Length($value),
        ];
    }

    /**
     * @param list<string> $sequence
     */
    private static function parseColor(array &$sequence): ?AnsiColor
    {
        $colorMode = array_shift($sequence);
        if ($colorMode === '2') {
            $rgbParts = array_splice($sequence, 0, 3);
            $rgb = array_map(static fn (string $x): int => (int) $x, $rgbParts);
            if (count($rgb) !== 3) {
                return null;
            }
            foreach ($rgbParts as $part) {
                if (!self::isIntegerString($part)) {
                    return null;
                }
            }

            return AnsiColor::rgb($rgb);
        }

        if ($colorMode === '5') {
            $index = array_shift($sequence);
            if ($index !== null && $index !== '') {
                return AnsiColor::table((int) $index);
            }
        }

        return null;
    }

    /**
     * @param list<string> $sequence
     * @return list<array{type: string, value?: string, color?: AnsiColor}>
     */
    private static function parseSequence(array $sequence): array
    {
        $commands = [];
        while ($sequence !== []) {
            $code = array_shift($sequence);
            if ($code === '' || $code === null) {
                continue;
            }
            if (!self::isIntegerString($code)) {
                continue;
            }
            $codeInt = (int) $code;

            if ($codeInt === 0) {
                $commands[] = ['type' => 'resetAll'];
            } elseif ($codeInt <= 9) {
                $decoration = self::DECORATIONS[$codeInt] ?? null;
                if ($decoration !== null) {
                    $commands[] = ['type' => 'setDecoration', 'value' => $decoration];
                }
            } elseif ($codeInt <= 29) {
                $decoration = self::DECORATIONS[$codeInt - 20] ?? null;
                if ($decoration !== null) {
                    $commands[] = ['type' => 'resetDecoration', 'value' => $decoration];
                    if ($decoration === 'dim') {
                        $commands[] = ['type' => 'resetDecoration', 'value' => 'bold'];
                    }
                }
            } elseif ($codeInt <= 37) {
                $commands[] = [
                    'type' => 'setForegroundColor',
                    'color' => AnsiColor::named(AnsiPalette::NAMED_COLORS[$codeInt - 30]),
                ];
            } elseif ($codeInt === 38) {
                $color = self::parseColor($sequence);
                if ($color !== null) {
                    $commands[] = ['type' => 'setForegroundColor', 'color' => $color];
                }
            } elseif ($codeInt === 39) {
                $commands[] = ['type' => 'resetForegroundColor'];
            } elseif ($codeInt <= 47) {
                $commands[] = [
                    'type' => 'setBackgroundColor',
                    'color' => AnsiColor::named(AnsiPalette::NAMED_COLORS[$codeInt - 40]),
                ];
            } elseif ($codeInt === 48) {
                $color = self::parseColor($sequence);
                if ($color !== null) {
                    $commands[] = ['type' => 'setBackgroundColor', 'color' => $color];
                }
            } elseif ($codeInt === 49) {
                $commands[] = ['type' => 'resetBackgroundColor'];
            } elseif ($codeInt === 53) {
                $commands[] = ['type' => 'setDecoration', 'value' => 'overline'];
            } elseif ($codeInt === 55) {
                $commands[] = ['type' => 'resetDecoration', 'value' => 'overline'];
            } elseif ($codeInt >= 90 && $codeInt <= 97) {
                $commands[] = [
                    'type' => 'setForegroundColor',
                    'color' => AnsiColor::named(AnsiPalette::NAMED_COLORS[$codeInt - 90 + 8]),
                ];
            } elseif ($codeInt >= 100 && $codeInt <= 107) {
                $commands[] = [
                    'type' => 'setBackgroundColor',
                    'color' => AnsiColor::named(AnsiPalette::NAMED_COLORS[$codeInt - 100 + 8]),
                ];
            }
        }

        return $commands;
    }

    private static function isIntegerString(string $value): bool
    {
        return preg_match('/^[+-]?\d+$/', $value) === 1;
    }

    private static function utf16IndexOf(string $haystack, string $needle, int $fromCodeUnit): int
    {
        $haystack16 = mb_convert_encoding($haystack, 'UTF-16LE', 'UTF-8');
        $needle16 = mb_convert_encoding($needle, 'UTF-16LE', 'UTF-8');
        $byteOffset = strpos($haystack16, $needle16, $fromCodeUnit * 2);
        if ($byteOffset === false) {
            return -1;
        }

        return intdiv($byteOffset, 2);
    }

    private static function utf16CharAt(string $value, int $codeUnit): ?string
    {
        $value16 = mb_convert_encoding($value, 'UTF-16LE', 'UTF-8');
        $slice = substr($value16, $codeUnit * 2, 2);
        if ($slice === '' || strlen($slice) < 2) {
            return null;
        }

        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }

    private static function utf16Substr(string $utf8, int $startCodeUnit, int $endCodeUnit): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $startCodeUnit * 2, ($endCodeUnit - $startCodeUnit) * 2);

        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
