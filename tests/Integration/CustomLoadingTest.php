<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Exceptions\Highlight as HighlightException;
use Shikiphp\Highlighter;
use Shikiphp\Tests\Integration\Grammar\BundledRegistry;

final class CustomLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!BundledRegistry::hasBundledGrammars()) {
            self::markTestSkipped('Bundled grammars are not present.');
        }
    }

    /** @return array<string, mixed> */
    private function toyGrammar(): array
    {
        return [
            'name' => 'toylang',
            'scopeName' => 'source.toy',
            'patterns' => [
                ['match' => '\\b(let|fn)\\b', 'name' => 'keyword.control.toy'],
                ['match' => '\\b\\d+\\b', 'name' => 'constant.numeric.toy'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function toyTheme(string $keywordColor = '#ff0000'): array
    {
        return [
            'name' => 'toy-theme',
            'type' => 'dark',
            'colors' => ['editor.foreground' => '#ffffff', 'editor.background' => '#000000'],
            'tokenColors' => [
                ['scope' => 'keyword.control.toy', 'settings' => ['foreground' => $keywordColor]],
                ['scope' => 'constant.numeric.toy', 'settings' => ['foreground' => '#00ff00']],
            ],
        ];
    }

    #[Test]
    public function custom_grammar_and_theme_produce_expected_scopes_and_colors(): void
    {
        $hl = Highlighter::createBundled();
        $hl->loadGrammar($this->toyGrammar());
        $hl->loadTheme($this->toyTheme());

        $lines = $hl->codeToTokens('let 42', ['lang' => 'toylang', 'theme' => 'toy-theme']);

        self::assertCount(1, $lines);
        $byContent = [];
        foreach ($lines[0] as $token) {
            $byContent[trim($token->content)] = $token->color;
        }

        self::assertSame('#FF0000', $byContent['let']);
        self::assertSame('#00FF00', $byContent['42']);
    }

    #[Test]
    public function derives_lang_id_from_name(): void
    {
        $hl = Highlighter::createBundled();
        $hl->loadGrammar($this->toyGrammar());

        self::assertContains('toylang', $hl->loadedLanguages());
        self::assertNotContains('toylang', $hl->bundledLanguages());
    }

    #[Test]
    public function explicit_lang_id_and_aliases_resolve(): void
    {
        $hl = Highlighter::createBundled();
        $hl->loadGrammar($this->toyGrammar(), 'mylang', ['ml', 'toy']);

        $viaAlias = $hl->codeToTokens('fn', ['lang' => 'toy', 'theme' => 'nord']);
        self::assertSame('fn', trim($viaAlias[0][0]->content));
    }

    #[Test]
    public function custom_theme_overrides_bundled_same_name(): void
    {
        $hl = Highlighter::createBundled();
        $hl->loadGrammar($this->toyGrammar());

        $bundledName = $hl->bundledThemes()[0];
        $override = $this->toyTheme('#123456');
        $override['name'] = $bundledName;
        $hl->loadTheme($override);

        $lines = $hl->codeToTokens('let', ['lang' => 'toylang', 'theme' => $bundledName]);
        $color = $lines[0][0]->color;

        self::assertSame('#123456', $color);
    }

    #[Test]
    public function embedded_include_resolves_against_registered_grammar(): void
    {
        $hl = Highlighter::createBundled();
        $hl->loadGrammar($this->toyGrammar());

        $hl->loadGrammar([
            'name' => 'wrapper',
            'scopeName' => 'source.wrapper',
            'patterns' => [
                ['match' => '\\bwrap\\b', 'name' => 'keyword.other.wrapper'],
                ['include' => 'source.toy'],
            ],
        ], embedded: ['toylang']);

        $lines = $hl->codeToTokens('wrap let 7', ['lang' => 'wrapper', 'theme' => 'nord']);

        $scopes = [];
        foreach ($lines[0] as $token) {
            $scopes[trim($token->content)] = true;
        }
        self::assertArrayHasKey('wrap', $scopes);
        self::assertArrayHasKey('let', $scopes);
        self::assertArrayHasKey('7', $scopes);
    }

    #[Test]
    public function bundled_accessors_return_sensible_counts(): void
    {
        $hl = Highlighter::createBundled();

        self::assertGreaterThan(50, count($hl->bundledLanguages()));
        self::assertGreaterThan(10, count($hl->bundledThemes()));
        self::assertContains('php', $hl->bundledLanguages());
        self::assertContains('nord', $hl->bundledThemes());
    }

    #[Test]
    public function unknown_language_still_throws(): void
    {
        $hl = Highlighter::createBundled();

        $this->expectException(HighlightException::class);
        $hl->codeToTokens('x', ['lang' => 'definitely-not-a-language', 'theme' => 'nord']);
    }

    #[Test]
    public function unknown_theme_still_throws(): void
    {
        $hl = Highlighter::createBundled();

        $this->expectException(HighlightException::class);
        $hl->codeToTokens('x', ['lang' => 'php', 'theme' => 'definitely-not-a-theme']);
    }

    #[Test]
    public function grammar_without_scope_name_is_rejected(): void
    {
        $hl = Highlighter::createBundled();

        $this->expectException(HighlightException::class);
        $hl->loadGrammar(['name' => 'broken', 'patterns' => []]);
    }

    #[Test]
    public function theme_without_name_is_rejected(): void
    {
        $hl = Highlighter::createBundled();

        $this->expectException(HighlightException::class);
        $hl->loadTheme(['type' => 'dark', 'colors' => []]);
    }
}
