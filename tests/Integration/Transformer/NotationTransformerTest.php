<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Transformer\Notation\NotationDiff;
use Shikiphp\Transformer\Notation\NotationErrorLevel;
use Shikiphp\Transformer\Notation\NotationFocus;
use Shikiphp\Transformer\Notation\NotationHighlight;
use Shikiphp\Transformer\Notation\NotationWordHighlight;
use Shikiphp\Transformer\Transformer;

/**
 * Byte-exact parity for the `// [!code ...]` notation transformers against
 * strings captured from the installed `@shikijs/transformers`
 * (`bin/.oracle-tools/notation-parity.mjs`).
 */
final class NotationTransformerTest extends TestCase
{
    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    private function render(string $code, string $lang, Transformer $transformer): string
    {
        return self::$highlighter->codeToHtml($code, [
            'lang' => $lang,
            'theme' => 'github-dark',
            'transformers' => [$transformer],
        ]);
    }

    #[Test]
    public function highlight_marks_lines_and_strips_notation(): void
    {
        $code = "const a = 1 // [!code highlight]\nconst b = 2\nconst c = 3 // [!code hl]\n";
        $expected = '<pre class="shiki github-dark has-highlighted" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>' . "\n"
            . '<span class="line"></span></code></pre>';

        $this->assertSame($expected, $this->render($code, 'javascript', new NotationHighlight()));
    }

    #[Test]
    public function diff_marks_add_and_remove_and_tags_pre(): void
    {
        $code = "const a = 1 // [!code --]\nconst b = 2 // [!code ++]\nconst c = 3\n";
        $expected = '<pre class="shiki github-dark has-diff" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line diff remove"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line diff add"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> c</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 3</span></span>' . "\n"
            . '<span class="line"></span></code></pre>';

        $this->assertSame($expected, $this->render($code, 'javascript', new NotationDiff()));
    }

    #[Test]
    public function focus_marks_line_and_tags_pre(): void
    {
        $code = "const a = 1\nconst b = 2 // [!code focus]\n";
        $expected = '<pre class="shiki github-dark has-focused" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line focused"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line"></span></code></pre>';

        $this->assertSame($expected, $this->render($code, 'javascript', new NotationFocus()));
    }

    #[Test]
    public function error_level_marks_error_and_warning(): void
    {
        $code = "const a = 1 // [!code error]\nconst b = 2 // [!code warning]\n";
        $expected = '<pre class="shiki github-dark has-highlighted" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line highlighted error"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line highlighted warning"><span style="color:#F97583">const</span><span style="color:#79B8FF"> b</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 2</span></span>' . "\n"
            . '<span class="line"></span></code></pre>';

        $this->assertSame($expected, $this->render($code, 'javascript', new NotationErrorLevel()));
    }

    #[Test]
    public function word_highlight_wraps_matching_words(): void
    {
        $code = "const apple = 1 // [!code word:apple]\nconst banana = apple\n";
        $expected = '<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> </span><span style="color:#79B8FF" class="highlighted-word">apple</span><span style="color:#F97583"> =</span><span style="color:#79B8FF"> 1</span></span>' . "\n"
            . '<span class="line"><span style="color:#F97583">const</span><span style="color:#79B8FF"> banana</span><span style="color:#F97583"> =</span><span style="color:#E1E4E8"> </span><span style="color:#E1E4E8" class="highlighted-word">apple</span></span>' . "\n"
            . '<span class="line"></span></code></pre>';

        $this->assertSame($expected, $this->render($code, 'javascript', new NotationWordHighlight()));
    }

    #[Test]
    public function own_line_comment_applies_to_next_line(): void
    {
        $code = "// [!code highlight]\nconst a = 1\nconst b = 2\n";
        $html = $this->render($code, 'javascript', new NotationHighlight());

        $this->assertStringNotContainsString('[!code', $html);
        $this->assertStringContainsString('<span class="line highlighted"><span style="color:#F97583">const</span><span style="color:#79B8FF"> a</span>', $html);
    }

    #[Test]
    public function highlight_works_for_python_hash_comments(): void
    {
        $code = "a = 1  # [!code highlight]\nb = 2\n";
        $html = $this->render($code, 'python', new NotationHighlight());

        $this->assertStringNotContainsString('[!code', $html);
        $this->assertStringContainsString('class="line highlighted"', $html);
    }

    #[Test]
    public function range_form_highlights_multiple_lines(): void
    {
        $code = "const a = 1 // [!code highlight:2]\nconst b = 2\nconst c = 3\n";
        $html = $this->render($code, 'javascript', new NotationHighlight());

        $this->assertSame(2, substr_count($html, 'class="line highlighted"'));
    }
}
