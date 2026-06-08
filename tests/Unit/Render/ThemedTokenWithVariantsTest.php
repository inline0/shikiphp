<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Render;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Render\ThemedTokenStyle;
use Shikiphp\Render\ThemedTokenWithVariants;
use Shikiphp\Tests\Integration\Grammar\BundledRegistry;
use Shikiphp\Theme\FontStyle;

final class ThemedTokenWithVariantsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!BundledRegistry::hasBundledGrammars()) {
            self::markTestSkipped('Bundled grammars are not present.');
        }
    }

    #[Test]
    public function each_token_carries_a_variant_per_theme_in_declaration_order(): void
    {
        $lines = Highlighter::createBundled()->codeToTokensWithThemes('const x = 1;', [
            'lang' => 'javascript',
            'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
        ]);

        self::assertCount(1, $lines);
        $first = $lines[0][0];

        self::assertInstanceOf(ThemedTokenWithVariants::class, $first);
        self::assertSame('const', $first->content);
        self::assertSame(0, $first->offset);
        self::assertSame(['light', 'dark'], array_keys($first->variants));

        self::assertInstanceOf(ThemedTokenStyle::class, $first->variants['light']);
        self::assertSame('#D73A49', $first->variants['light']->color);
        self::assertSame('#F97583', $first->variants['dark']->color);
        self::assertSame(FontStyle::NONE, $first->variants['light']->fontStyle);
        self::assertNull($first->variants['light']->bgColor);
    }

    #[Test]
    public function utf16_offsets_track_surrogate_pairs(): void
    {
        $lines = Highlighter::createBundled()->codeToTokensWithThemes("const a = '😀';\nconst b = 2;", [
            'lang' => 'javascript',
            'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
        ]);

        self::assertCount(2, $lines);

        $firstLineEnd = $lines[0][count($lines[0]) - 1];
        $secondLineStart = $lines[1][0];

        self::assertSame('const', $secondLineStart->content);
        self::assertSame(
            $firstLineEnd->offset + self::utf16Length($firstLineEnd->content) + 1,
            $secondLineStart->offset,
        );
    }

    #[Test]
    public function fontstyle_is_carried_per_variant(): void
    {
        $lines = Highlighter::createBundled()->codeToTokensWithThemes('function f(a) { return a; }', [
            'lang' => 'javascript',
            'themes' => ['plain' => 'github-dark', 'fancy' => 'dracula'],
        ]);

        $param = null;
        foreach ($lines[0] as $token) {
            if ($token->content === 'a' && $token->offset === 11) {
                $param = $token;
                break;
            }
        }

        self::assertNotNull($param);
        self::assertSame(FontStyle::NONE, $param->variants['plain']->fontStyle);
        self::assertSame(FontStyle::ITALIC, $param->variants['fancy']->fontStyle);
    }

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen((string) mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
