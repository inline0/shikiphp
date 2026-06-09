<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Hast\Element;
use Shikiphp\Highlighter;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Transformer\AbstractTransformer;
use Shikiphp\Transformer\TransformerContext;

final class HighlighterTransformerTest extends TestCase
{
    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    #[Test]
    public function no_transformers_is_byte_identical_to_plain_options(): void
    {
        $code = "const x = 1\nconst y = 2";
        $base = ['lang' => 'javascript', 'theme' => 'github-dark'];

        $plain = self::$highlighter->codeToHtml($code, $base);
        $withEmpty = self::$highlighter->codeToHtml($code, [...$base, 'transformers' => []]);

        $this->assertSame($plain, $withEmpty);
    }

    #[Test]
    public function preprocess_feeds_modified_code_into_tokenizer(): void
    {
        $replace = new class extends AbstractTransformer {
            public function preprocess(string $code, array &$options, TransformerContext $context): ?string
            {
                return str_replace('REPLACE_ME', 'const', $code);
            }
        };

        $html = self::$highlighter->codeToHtml('REPLACE_ME x = 1', [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$replace],
        ]);

        $direct = self::$highlighter->codeToHtml('const x = 1', [
            'lang' => 'javascript',
            'theme' => 'github-dark',
        ]);

        $this->assertSame($direct, $html);
    }

    #[Test]
    public function line_hook_adds_class_to_every_line(): void
    {
        $addClass = new class extends AbstractTransformer {
            public function line(Element $element, int $line, TransformerContext $context): ?Element
            {
                return $context->addClassToHast($element, 'line-' . $line);
            }
        };

        $html = self::$highlighter->codeToHtml("a\nb", [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$addClass],
        ]);

        $this->assertStringContainsString('class="line line-1"', $html);
        $this->assertStringContainsString('class="line line-2"', $html);
    }

    #[Test]
    public function context_exposes_source_lang_and_theme_during_hooks(): void
    {
        $seen = [];
        $probe = new class ($seen) extends AbstractTransformer {
            /** @param array<string,mixed> $seen */
            public function __construct(private array &$seen)
            {
            }

            public function tokens(array $tokens, TransformerContext $context): ?array
            {
                $this->seen['source'] = $context->source;
                $this->seen['lang'] = $context->lang;
                $this->seen['themes'] = $context->themes;
                $this->seen['lineCount'] = count($tokens);
                return null;
            }
        };

        self::$highlighter->codeToHtml("a\nb", [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$probe],
        ]);

        $this->assertSame("a\nb", $seen['source']);
        $this->assertSame('javascript', $seen['lang']);
        $this->assertSame(['default' => 'github-dark'], $seen['themes']);
        $this->assertSame(2, $seen['lineCount']);
    }

    #[Test]
    public function postprocess_only_runs_for_code_to_html(): void
    {
        $wrap = new class extends AbstractTransformer {
            public function postprocess(string $html, array $options, TransformerContext $context): ?string
            {
                return "<figure>{$html}</figure>";
            }
        };

        $html = self::$highlighter->codeToHtml('x', [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$wrap],
        ]);
        $this->assertStringStartsWith('<figure><pre', $html);
        $this->assertStringEndsWith('</pre></figure>', $html);

        $hast = self::$highlighter->codeToHast('x', [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$wrap],
        ]);
        $this->assertSame('root', $hast->tag);
    }

    #[Test]
    public function tokens_hook_can_rewrite_token_content(): void
    {
        $rewrite = new class extends AbstractTransformer {
            public function tokens(array $tokens, TransformerContext $context): ?array
            {
                $out = [];
                foreach ($tokens as $line) {
                    $newLine = [];
                    foreach ($line as $token) {
                        $newLine[] = new ThemedToken(strtoupper($token->content), $token->color, FontStyle::NONE);
                    }
                    $out[] = $newLine;
                }
                return $out;
            }
        };

        $html = self::$highlighter->codeToHtml('abc', [
            'lang' => 'javascript',
            'theme' => 'github-dark',
            'transformers' => [$rewrite],
        ]);

        $this->assertStringContainsString('>ABC<', $html);
    }
}
