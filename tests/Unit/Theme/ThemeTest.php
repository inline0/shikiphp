<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\Theme;

final class ThemeTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sampleTheme(): array
    {
        return [
            'name' => 'sample-dark',
            'type' => 'dark',
            'colors' => [
                'editor.foreground' => '#e0e0e0',
                'editor.background' => '#101010',
            ],
            'tokenColors' => [
                ['settings' => ['foreground' => '#abcdef', 'fontStyle' => 'italic bold']],
                ['scope' => 'comment', 'settings' => ['foreground' => '#777777', 'fontStyle' => 'italic']],
                ['scope' => 'string', 'settings' => ['foreground' => '#22aa22']],
                ['scope' => 'string.quoted.double', 'settings' => ['foreground' => '#33bb33']],
                ['scope' => 'keyword', 'settings' => ['foreground' => '#cc4444', 'fontStyle' => 'bold']],
                ['scope' => 'meta.embedded string', 'settings' => ['foreground' => '#0000ff']],
            ],
        ];
    }

    #[Test]
    public function name_and_type_come_from_raw(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        self::assertSame('sample-dark', $theme->name());
        self::assertSame('dark', $theme->type());
    }

    #[Test]
    public function defaults_come_from_editor_colors(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        self::assertSame('#abcdef', $theme->foreground());
        self::assertSame('#101010', $theme->background());
    }

    #[Test]
    public function default_rule_supplies_global_foreground_and_font_style(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match([]);

        self::assertSame('#abcdef', $style->foreground);
        self::assertSame(FontStyle::ITALIC | FontStyle::BOLD, $style->fontStyle);
    }

    #[Test]
    public function exact_scope_matches(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match(['source.js', 'comment']);

        self::assertSame('#777777', $style->foreground);
        self::assertSame(FontStyle::ITALIC, $style->fontStyle);
    }

    #[Test]
    public function prefix_scope_matches_more_specific_descendant(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match(['source.js', 'string.quoted.single']);

        self::assertSame('#22aa22', $style->foreground);
    }

    #[Test]
    public function more_specific_scope_wins_over_prefix(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match(['source.js', 'string.quoted.double']);

        self::assertSame('#33bb33', $style->foreground);
    }

    #[Test]
    public function unmatched_scope_falls_back_to_defaults(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match(['source.js', 'variable.other']);

        self::assertSame('#abcdef', $style->foreground);
        self::assertSame(FontStyle::ITALIC | FontStyle::BOLD, $style->fontStyle);
    }

    #[Test]
    public function parent_scope_selector_matches_when_ancestor_present(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $matched = $theme->match(['meta.embedded.block', 'string']);
        $unmatched = $theme->match(['meta.other', 'string']);

        self::assertSame('#0000ff', $matched->foreground);
        self::assertSame('#22aa22', $unmatched->foreground);
    }

    #[Test]
    public function font_style_bold_only(): void
    {
        $theme = Theme::fromRaw($this->sampleTheme());

        $style = $theme->match(['keyword']);

        self::assertSame(FontStyle::BOLD, $style->fontStyle);
        self::assertSame('#cc4444', $style->foreground);
    }

    #[Test]
    public function legacy_settings_key_is_supported(): void
    {
        $raw = [
            'name' => 'legacy',
            'type' => 'light',
            'settings' => [
                ['settings' => ['foreground' => '#000000', 'background' => '#ffffff']],
                ['scope' => 'keyword', 'settings' => ['foreground' => '#0000aa']],
            ],
        ];

        $theme = Theme::fromRaw($raw);

        self::assertSame('light', $theme->type());
        self::assertSame('#000000', $theme->foreground());
        self::assertSame('#ffffff', $theme->background());
        self::assertSame('#0000aa', $theme->match(['keyword'])->foreground);
    }

    #[Test]
    public function comma_separated_scope_string_applies_to_each(): void
    {
        $raw = [
            'name' => 'commas',
            'type' => 'dark',
            'colors' => [],
            'tokenColors' => [
                ['scope' => 'keyword, storage.type', 'settings' => ['foreground' => '#ff00ff']],
            ],
        ];

        $theme = Theme::fromRaw($raw);

        self::assertSame('#ff00ff', $theme->match(['keyword'])->foreground);
        self::assertSame('#ff00ff', $theme->match(['storage.type'])->foreground);
    }

    #[Test]
    public function later_rule_breaks_specificity_tie(): void
    {
        $raw = [
            'name' => 'ties',
            'type' => 'dark',
            'colors' => [],
            'tokenColors' => [
                ['scope' => 'constant', 'settings' => ['foreground' => '#111111']],
                ['scope' => 'constant', 'settings' => ['foreground' => '#222222']],
            ],
        ];

        $theme = Theme::fromRaw($raw);

        self::assertSame('#222222', $theme->match(['constant'])->foreground);
    }

    #[Test]
    public function empty_font_style_string_means_none(): void
    {
        $raw = [
            'name' => 'fs',
            'type' => 'dark',
            'colors' => [],
            'tokenColors' => [
                ['settings' => ['foreground' => '#cccccc', 'fontStyle' => 'italic']],
                ['scope' => 'keyword', 'settings' => ['fontStyle' => '']],
            ],
        ];

        $theme = Theme::fromRaw($raw);

        self::assertSame(FontStyle::NONE, $theme->match(['keyword'])->fontStyle);
    }

    #[Test]
    public function absent_font_style_inherits_default(): void
    {
        $raw = [
            'name' => 'fs',
            'type' => 'dark',
            'colors' => [],
            'tokenColors' => [
                ['settings' => ['foreground' => '#cccccc', 'fontStyle' => 'bold']],
                ['scope' => 'keyword', 'settings' => ['foreground' => '#dddddd']],
            ],
        ];

        $theme = Theme::fromRaw($raw);

        self::assertSame(FontStyle::BOLD, $theme->match(['keyword'])->fontStyle);
        self::assertSame('#dddddd', $theme->match(['keyword'])->foreground);
    }

    #[Test]
    public function missing_editor_colors_fall_back_to_type_defaults(): void
    {
        $theme = Theme::fromRaw(['name' => 'bare', 'type' => 'light', 'tokenColors' => []]);

        self::assertSame('#333333', $theme->foreground());
        self::assertSame('#ffffff', $theme->background());
    }
}
