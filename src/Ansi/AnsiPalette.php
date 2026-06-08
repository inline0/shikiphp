<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

use Shikiphp\Theme\Theme;

/**
 * Resolves parsed {@see AnsiColor}s to hex strings using a named-colour map (the
 * theme's `terminal.ansi*` entries when present, else Shiki's default ANSI
 * palette) plus the 256-colour cube + grayscale ramp and 24-bit truecolor.
 * Port of @shikijs/core's `createColorPalette`.
 */
final class AnsiPalette
{
    /** @var list<string> */
    public const NAMED_COLORS = [
        'black',
        'red',
        'green',
        'yellow',
        'blue',
        'magenta',
        'cyan',
        'white',
        'brightBlack',
        'brightRed',
        'brightGreen',
        'brightYellow',
        'brightBlue',
        'brightMagenta',
        'brightCyan',
        'brightWhite',
    ];

    /** @var array<string, string> */
    private const DEFAULT_ANSI_COLORS = [
        'black' => '#000000',
        'red' => '#cd3131',
        'green' => '#0DBC79',
        'yellow' => '#E5E510',
        'blue' => '#2472C8',
        'magenta' => '#BC3FBC',
        'cyan' => '#11A8CD',
        'white' => '#E5E5E5',
        'brightBlack' => '#666666',
        'brightRed' => '#F14C4C',
        'brightGreen' => '#23D18B',
        'brightYellow' => '#F5F543',
        'brightBlue' => '#3B8EEA',
        'brightMagenta' => '#D670D6',
        'brightCyan' => '#29B8DB',
        'brightWhite' => '#FFFFFF',
    ];

    /** @var array<string, string> name → hex */
    private array $namedColors;

    /** @var list<string>|null */
    private ?array $colorTable = null;

    /**
     * @param array<string, string> $namedColors
     */
    private function __construct(array $namedColors)
    {
        $this->namedColors = $namedColors;
    }

    public static function fromTheme(Theme $theme): self
    {
        $namedColors = [];
        foreach (self::NAMED_COLORS as $name) {
            $key = 'terminal.ansi' . strtoupper($name[0]) . substr($name, 1);
            $namedColors[$name] = $theme->color($key) ?? self::DEFAULT_ANSI_COLORS[$name];
        }

        return new self($namedColors);
    }

    public function value(AnsiColor $color): string
    {
        return match ($color->type) {
            AnsiColor::TYPE_RGB => self::rgbColor($color->rgb),
            AnsiColor::TYPE_TABLE => $this->tableColor($color->index),
            default => $this->namedColors[(string) $color->name],
        };
    }

    private function tableColor(int $index): string
    {
        return $this->colorTable()[$index];
    }

    /**
     * @return list<string>
     */
    private function colorTable(): array
    {
        if ($this->colorTable !== null) {
            return $this->colorTable;
        }

        $table = [];
        foreach (self::NAMED_COLORS as $name) {
            $table[] = $this->namedColors[$name];
        }

        $levels = [0, 95, 135, 175, 215, 255];
        for ($r = 0; $r < 6; $r++) {
            for ($g = 0; $g < 6; $g++) {
                for ($b = 0; $b < 6; $b++) {
                    $table[] = self::rgbColor([$levels[$r], $levels[$g], $levels[$b]]);
                }
            }
        }

        $level = 8;
        for ($i = 0; $i < 24; $i++, $level += 10) {
            $table[] = self::rgbColor([$level, $level, $level]);
        }

        return $this->colorTable = $table;
    }

    /**
     * @param list<int> $rgb
     */
    private static function rgbColor(array $rgb): string
    {
        $hex = '';
        foreach ($rgb as $x) {
            $x = max(0, min($x, 255));
            $hex .= str_pad(dechex($x), 2, '0', STR_PAD_LEFT);
        }

        return '#' . $hex;
    }
}
