<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * Maps between absolute UTF-16 offsets and `{line, character}` positions over a
 * source string, mirroring Shiki's `createPositionConverter`. Line lengths
 * include their trailing line ending, so offsets are absolute into the source.
 */
final class PositionConverter
{
    /** @var list<string> per-line text including its `\n`/`\r\n` terminator */
    private readonly array $lines;

    private readonly int $length;

    public function __construct(string $code)
    {
        $this->length = self::utf16Length($code);

        $parts = preg_split('/(\r?\n)/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = $parts === false ? [$code] : $parts;

        $lines = [];
        for ($i = 0; $i < count($parts); $i += 2) {
            $lines[] = $parts[$i] . ($parts[$i + 1] ?? '');
        }
        $this->lines = $lines === [] ? [''] : $lines;
    }

    public function length(): int
    {
        return $this->length;
    }

    /** @return list<int> per-line UTF-16 length including the terminator */
    public function lineLengths(): array
    {
        return array_map(self::utf16Length(...), $this->lines);
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    /** @return array{line:int, character:int} */
    public function indexToPos(int $index): array
    {
        $last = count($this->lines) - 1;
        if ($index === $this->length) {
            return ['line' => $last, 'character' => self::utf16Length($this->lines[$last])];
        }

        $character = $index;
        $line = 0;
        foreach ($this->lines as $text) {
            $len = self::utf16Length($text);
            if ($character < $len) {
                break;
            }
            $character -= $len;
            $line++;
        }

        return ['line' => $line, 'character' => $character];
    }

    public function posToIndex(int $line, int $character): int
    {
        $index = 0;
        for ($i = 0; $i < $line; $i++) {
            $index += self::utf16Length($this->lines[$i] ?? '');
        }

        return $index + $character;
    }

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
