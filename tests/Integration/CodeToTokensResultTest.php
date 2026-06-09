<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Exceptions\Highlight;
use Shikiphp\GrammarState;
use Shikiphp\Highlighter;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Tests\Integration\Grammar\BundledRegistry;

final class CodeToTokensResultTest extends TestCase
{
    private const CODE = <<<'JS'
        function f() {
          return /* a
        comment */ 1;
        }
        JS;

    protected function setUp(): void
    {
        if (!BundledRegistry::hasBundledGrammars()) {
            self::markTestSkipped('Bundled grammars are not present.');
        }
    }

    #[Test]
    public function code_to_tokens_base_is_alias_of_code_to_tokens(): void
    {
        $hl = Highlighter::createBundled();
        $opts = ['lang' => 'javascript', 'theme' => 'github-dark'];

        self::assertEquals(
            self::flatten($hl->codeToTokens(self::CODE, $opts)),
            self::flatten($hl->codeToTokensBase(self::CODE, $opts)),
        );
    }

    #[Test]
    public function rich_result_single_theme_fields(): void
    {
        $result = Highlighter::createBundled()
            ->codeToTokensResult(self::CODE, ['lang' => 'javascript', 'theme' => 'github-dark']);

        self::assertSame('#24292e', $result->bg);
        self::assertSame('#e1e4e8', $result->fg);
        self::assertSame('github-dark', $result->themeName);
        self::assertNull($result->rootStyle);
        self::assertNotNull($result->grammarState);
        self::assertSame('javascript', $result->grammarState->lang);
        self::assertSame('github-dark', $result->grammarState->theme());
    }

    #[Test]
    public function rich_result_dual_theme_fg_bg_use_css_vars(): void
    {
        $themes = ['light' => 'github-light', 'dark' => 'github-dark'];

        $result = Highlighter::createBundled()
            ->codeToTokensResult('const x = 1;', ['lang' => 'javascript', 'themes' => $themes]);

        self::assertSame('#24292e;--shiki-dark:#e1e4e8', $result->fg);
        self::assertSame('#fff;--shiki-dark-bg:#24292e', $result->bg);
        self::assertSame('shiki-themes github-light github-dark', $result->themeName);
        self::assertNull($result->rootStyle);
    }

    #[Test]
    public function rich_result_dual_theme_default_color_false_sets_root_style(): void
    {
        $themes = ['light' => 'github-light', 'dark' => 'github-dark'];

        $result = Highlighter::createBundled()->codeToTokensResult('const x = 1;', [
            'lang' => 'javascript',
            'themes' => $themes,
            'defaultColor' => false,
        ]);

        self::assertSame('--shiki-light:#24292e;--shiki-dark:#e1e4e8', $result->fg);
        self::assertSame('--shiki-light-bg:#fff;--shiki-dark-bg:#24292e', $result->bg);
        self::assertSame(
            '--shiki-light:#24292e;--shiki-dark:#e1e4e8;--shiki-light-bg:#fff;--shiki-dark-bg:#24292e',
            $result->rootStyle,
        );
    }

    #[Test]
    public function grammar_state_round_trip_matches_tokenizing_together(): void
    {
        $hl = Highlighter::createBundled();
        $opts = ['lang' => 'javascript', 'theme' => 'github-dark'];

        $lines = explode("\n", self::CODE);
        $head = $lines[0] . "\n" . $lines[1];
        $tail = $lines[2] . "\n" . $lines[3];

        $whole = $hl->codeToTokens(self::CODE, $opts);

        $state = $hl->getLastGrammarState($head, $opts);
        self::assertInstanceOf(GrammarState::class, $state);
        $resumed = $hl->codeToTokens($tail, [...$opts, 'grammarState' => $state]);

        self::assertEquals(
            self::styles(array_slice($whole, 2, 2)),
            self::styles($resumed),
        );
    }

    #[Test]
    public function rich_result_grammar_state_round_trip(): void
    {
        $hl = Highlighter::createBundled();
        $opts = ['lang' => 'javascript', 'theme' => 'github-dark'];

        $lines = explode("\n", self::CODE);
        $head = $lines[0] . "\n" . $lines[1];
        $tail = $lines[2] . "\n" . $lines[3];

        $whole = $hl->codeToTokens(self::CODE, $opts);

        $headResult = $hl->codeToTokensResult($head, $opts);
        self::assertNotNull($headResult->grammarState);
        $resumed = $hl->codeToTokensResult($tail, [...$opts, 'grammarState' => $headResult->grammarState]);

        self::assertEquals(
            self::styles(array_slice($whole, 2, 2)),
            self::styles($resumed->tokens),
        );
    }

    #[Test]
    public function get_last_grammar_state_exposes_scopes_inside_block_comment(): void
    {
        $hl = Highlighter::createBundled();
        $opts = ['lang' => 'javascript', 'theme' => 'github-dark'];

        $state = $hl->getLastGrammarState("function f() {\n  return /* still open", $opts);

        self::assertContains('comment.block.js', $state->getScopes());
    }

    #[Test]
    public function grammar_state_language_mismatch_throws(): void
    {
        $hl = Highlighter::createBundled();
        $state = $hl->getLastGrammarState('const x = 1', ['lang' => 'javascript', 'theme' => 'github-dark']);

        $this->expectException(Highlight::class);
        $hl->codeToTokens('echo 1', ['lang' => 'php', 'theme' => 'github-dark', 'grammarState' => $state]);
    }

    #[Test]
    public function ansi_language_has_no_grammar_state(): void
    {
        $this->expectException(Highlight::class);
        Highlighter::createBundled()->getLastGrammarState("\u{001b}[31mred", ['lang' => 'ansi', 'theme' => 'github-dark']);
    }

    /**
     * @param list<list<ThemedToken>> $lines
     * @return list<array{content: string, color: ?string, fontStyle: int, offset: int}>
     */
    private static function flatten(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            foreach ($line as $token) {
                $out[] = [
                    'content' => $token->content,
                    'color' => $token->color,
                    'fontStyle' => $token->fontStyle,
                    'offset' => $token->offset,
                ];
            }
        }

        return $out;
    }

    /**
     * Content + style, ignoring offsets (resuming a fresh substring restarts
     * offsets at 0, like Shiki).
     *
     * @param list<list<ThemedToken>> $lines
     * @return list<array{content: string, color: ?string, fontStyle: int}>
     */
    private static function styles(array $lines): array
    {
        return array_map(
            static fn (array $token): array => [
                'content' => $token['content'],
                'color' => $token['color'],
                'fontStyle' => $token['fontStyle'],
            ],
            self::flatten($lines),
        );
    }
}
