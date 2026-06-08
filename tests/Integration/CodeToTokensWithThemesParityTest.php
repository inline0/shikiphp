<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Render\ThemedTokenWithVariants;
use Shikiphp\Tests\Integration\Grammar\BundledRegistry;
use Shikiphp\Tests\Oracle\OracleCapture;

final class CodeToTokensWithThemesParityTest extends TestCase
{
    private const SNIPPET = <<<'JS'
        const x = 1;
        // a comment
        function f(a) { return a + 1; }
        const s = "hi";
        JS;

    /** @var array<string, string> */
    private const THEMES = [
        'light' => 'github-light',
        'dark' => 'github-dark',
        'fancy' => 'dracula',
    ];

    protected function setUp(): void
    {
        if (!BundledRegistry::hasBundledGrammars()) {
            self::markTestSkipped('Bundled grammars are not present.');
        }
        if (!OracleCapture::isAvailable()) {
            self::markTestSkipped('Shiki oracle (node_modules) is not available.');
        }
    }

    #[Test]
    public function matches_shiki_codeToTokensWithThemes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'shikiphp-tokens-themes') . '.js';
        file_put_contents($file, self::SNIPPET);

        try {
            $expected = OracleCapture::tokensWithThemes('javascript', $file, self::THEMES);
        } finally {
            @unlink($file);
        }

        $lines = Highlighter::createBundled()->codeToTokensWithThemes(self::SNIPPET, [
            'lang' => 'javascript',
            'themes' => self::THEMES,
        ]);

        self::assertSame($expected, self::toArray($lines));
    }

    /**
     * @param list<list<ThemedTokenWithVariants>> $lines
     * @return list<list<array{content: string, offset: int, variants: array<string, array{color: ?string, fontStyle: int, bgColor: ?string}>}>>
     */
    private static function toArray(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $row = [];
            foreach ($line as $token) {
                $variants = [];
                foreach ($token->variants as $key => $style) {
                    $variants[$key] = [
                        'color' => $style->color,
                        'fontStyle' => $style->fontStyle,
                        'bgColor' => $style->bgColor,
                    ];
                }
                $row[] = [
                    'content' => $token->content,
                    'offset' => $token->offset,
                    'variants' => $variants,
                ];
            }
            $out[] = $row;
        }

        return $out;
    }
}
