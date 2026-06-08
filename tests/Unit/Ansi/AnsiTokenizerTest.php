<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Ansi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Ansi\AnsiTokenizer;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\Theme;

final class AnsiTokenizerTest extends TestCase
{
    private function theme(): Theme
    {
        return Theme::fromRaw([
            'name' => 'plain',
            'type' => 'dark',
            'colors' => [
                'editor.foreground' => '#ffffff',
                'editor.background' => '#000000',
            ],
            'tokenColors' => [],
        ]);
    }

    /**
     * @param array<string, string> $extraColors
     */
    private function themeWith(array $extraColors): Theme
    {
        return Theme::fromRaw([
            'name' => 'plain',
            'type' => 'dark',
            'colors' => array_merge([
                'editor.foreground' => '#ffffff',
                'editor.background' => '#000000',
            ], $extraColors),
            'tokenColors' => [],
        ]);
    }

    #[Test]
    public function named_foreground_uses_default_ansi_palette(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[31mred\x1b[0m end", $this->theme(), []);

        $this->assertCount(1, $lines);
        $this->assertSame('red', $lines[0][0]->content);
        $this->assertSame('#cd3131', $lines[0][0]->color);
        $this->assertSame(' end', $lines[0][1]->content);
        $this->assertSame('#ffffff', $lines[0][1]->color);
    }

    #[Test]
    public function truecolor_and_256_color_resolve(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;2;10;20;30mtc\x1b[0m", $this->theme(), []);
        $this->assertSame('#0a141e', $lines[0][0]->color);

        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;5;208mc\x1b[0m", $this->theme(), []);
        $this->assertSame('#ff8700', $lines[0][0]->color);
    }

    #[Test]
    public function decorations_map_to_font_style(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[1;3;4;9mx\x1b[0m", $this->theme(), []);
        $expected = FontStyle::BOLD | FontStyle::ITALIC | FontStyle::UNDERLINE | FontStyle::STRIKETHROUGH;
        $this->assertSame($expected, $lines[0][0]->fontStyle);
    }

    #[Test]
    public function reverse_swaps_foreground_and_background(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[7mrev\x1b[0m", $this->theme(), []);
        $this->assertSame('#000000', $lines[0][0]->color);
        $this->assertSame('#ffffff', $lines[0][0]->bgColor);
    }

    #[Test]
    public function dim_halves_the_alpha_channel(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[2;31mdim\x1b[0m", $this->theme(), []);
        $this->assertSame('#cd313180', $lines[0][0]->color);
    }

    #[Test]
    public function state_persists_across_lines(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[32mgreen\nstill", $this->theme(), []);
        $this->assertSame('#0DBC79', $lines[0][0]->color);
        $this->assertSame('#0DBC79', $lines[1][0]->color);
    }

    #[Test]
    public function bright_foreground_codes_map_to_bright_palette(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[90mbb\x1b[0m", $this->theme(), []);
        $this->assertSame('#666666', $lines[0][0]->color);

        $lines = (new AnsiTokenizer())->tokenize("\x1b[97mbw\x1b[0m", $this->theme(), []);
        $this->assertSame('#FFFFFF', $lines[0][0]->color);
    }

    #[Test]
    public function named_background_codes_set_bg_color(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[41mr\x1b[0m", $this->theme(), []);
        $this->assertSame('#cd3131', $lines[0][0]->bgColor);
        $this->assertSame('#ffffff', $lines[0][0]->color);

        $lines = (new AnsiTokenizer())->tokenize("\x1b[101mr\x1b[0m", $this->theme(), []);
        $this->assertSame('#F14C4C', $lines[0][0]->bgColor);
    }

    #[Test]
    public function table_color_cube_and_grayscale(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;5;16ma\x1b[0m", $this->theme(), []);
        $this->assertSame('#000000', $lines[0][0]->color);

        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;5;231ma\x1b[0m", $this->theme(), []);
        $this->assertSame('#ffffff', $lines[0][0]->color);

        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;5;232ma\x1b[0m", $this->theme(), []);
        $this->assertSame('#080808', $lines[0][0]->color);
    }

    #[Test]
    public function table_color_uses_named_palette_for_first_sixteen(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;5;1ma\x1b[0m", $this->theme(), []);
        $this->assertSame('#cd3131', $lines[0][0]->color);
    }

    #[Test]
    public function truecolor_clamps_out_of_range_components(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[38;2;300;0;255mtc\x1b[0m", $this->theme(), []);
        $this->assertSame('#ff00ff', $lines[0][0]->color);
    }

    #[Test]
    public function theme_terminal_ansi_overrides_default_palette(): void
    {
        $theme = $this->themeWith(['terminal.ansiRed' => '#abcdef']);
        $lines = (new AnsiTokenizer())->tokenize("\x1b[31mr\x1b[0m", $theme, []);
        $this->assertSame('#abcdef', $lines[0][0]->color);

        $blue = (new AnsiTokenizer())->tokenize("\x1b[34mb\x1b[0m", $theme, []);
        $this->assertSame('#2472C8', $blue[0][0]->color);
    }

    #[Test]
    public function reset_foreground_keeps_decorations_and_background(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[1;31;41ma\x1b[39mb\x1b[0m", $this->theme(), []);

        $this->assertSame('#cd3131', $lines[0][0]->color);
        $this->assertSame('#cd3131', $lines[0][0]->bgColor);
        $this->assertSame(FontStyle::BOLD, $lines[0][0]->fontStyle);

        $this->assertSame('#ffffff', $lines[0][1]->color);
        $this->assertSame('#cd3131', $lines[0][1]->bgColor);
        $this->assertSame(FontStyle::BOLD, $lines[0][1]->fontStyle);
    }

    #[Test]
    public function reset_dim_also_clears_bold(): void
    {
        $lines = (new AnsiTokenizer())->tokenize("\x1b[1;2mx\x1b[22my\x1b[0m", $this->theme(), []);

        $this->assertSame(FontStyle::BOLD, $lines[0][0]->fontStyle);
        $this->assertSame(FontStyle::NONE, $lines[0][1]->fontStyle);
    }

    #[Test]
    public function color_replacements_remap_resolved_hex(): void
    {
        $lines = (new AnsiTokenizer())->tokenize(
            "\x1b[31mr\x1b[0m",
            $this->theme(),
            ['#cd3131' => '#00ff00'],
        );
        $this->assertSame('#00ff00', $lines[0][0]->color);
    }

    #[Test]
    public function plain_text_before_any_sequence_uses_theme_defaults(): void
    {
        $lines = (new AnsiTokenizer())->tokenize('plain', $this->theme(), []);
        $this->assertCount(1, $lines[0]);
        $this->assertSame('plain', $lines[0][0]->content);
        $this->assertSame('#ffffff', $lines[0][0]->color);
        $this->assertNull($lines[0][0]->bgColor);
        $this->assertSame(FontStyle::NONE, $lines[0][0]->fontStyle);
    }
}
