<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Render;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Render\PositionConverter;

final class PositionConverterTest extends TestCase
{
    #[Test]
    public function index_to_pos_counts_newlines_as_line_length(): void
    {
        $c = new PositionConverter("ab\ncd");

        $this->assertSame(['line' => 0, 'character' => 0], $c->indexToPos(0));
        $this->assertSame(['line' => 0, 'character' => 2], $c->indexToPos(2));
        $this->assertSame(['line' => 1, 'character' => 0], $c->indexToPos(3));
        $this->assertSame(['line' => 1, 'character' => 1], $c->indexToPos(4));
    }

    #[Test]
    public function index_equal_to_length_maps_to_end_of_last_line(): void
    {
        $c = new PositionConverter("ab\ncd");
        $this->assertSame(['line' => 1, 'character' => 2], $c->indexToPos(5));
    }

    #[Test]
    public function pos_to_index_includes_newline_offsets(): void
    {
        $c = new PositionConverter("ab\ncd");

        $this->assertSame(0, $c->posToIndex(0, 0));
        $this->assertSame(2, $c->posToIndex(0, 2));
        $this->assertSame(3, $c->posToIndex(1, 0));
        $this->assertSame(5, $c->posToIndex(1, 2));
    }

    #[Test]
    public function line_lengths_include_terminators(): void
    {
        $c = new PositionConverter("ab\ncd");
        $this->assertSame([3, 2], $c->lineLengths());
        $this->assertSame(2, $c->lineCount());
        $this->assertSame(5, $c->length());
    }

    #[Test]
    public function astral_characters_count_as_two_code_units(): void
    {
        $c = new PositionConverter("a\u{1F600}b");
        $this->assertSame(4, $c->length());
        $this->assertSame(['line' => 0, 'character' => 3], $c->indexToPos(3));
    }
}
