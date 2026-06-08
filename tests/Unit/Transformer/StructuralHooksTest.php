<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Hast\Element;
use Shikiphp\Hast\HastSerializer;
use Shikiphp\Render\HastBuilder;
use Shikiphp\Render\RenderOptions;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Transformer\AbstractTransformer;
use Shikiphp\Transformer\TransformerContext;
use Shikiphp\Transformer\TransformerPipeline;

final class StructuralHooksTest extends TestCase
{
    #[Test]
    public function span_line_code_pre_root_fire_in_order_with_correct_args(): void
    {
        $log = [];
        $recorder = new class ($log) extends AbstractTransformer {
            /** @param list<string> $log */
            public function __construct(private array &$log)
            {
            }

            public function span(Element $element, int $line, int $col, Element $lineElement, ThemedToken $token, TransformerContext $context): ?Element
            {
                $this->log[] = "span L{$line} C{$col} '{$token->content}'";
                return null;
            }

            public function line(Element $element, int $line, TransformerContext $context): ?Element
            {
                $this->log[] = "line {$line}";
                return null;
            }

            public function code(Element $element, TransformerContext $context): ?Element
            {
                $this->log[] = 'code';
                return null;
            }

            public function pre(Element $element, TransformerContext $context): ?Element
            {
                $this->log[] = 'pre';
                return null;
            }

            public function root(Element $element, TransformerContext $context): ?Element
            {
                $this->log[] = 'root';
                return null;
            }
        };

        $this->build([
            [new ThemedToken('ab', '#111', FontStyle::NONE), new ThemedToken('cd', '#222', FontStyle::NONE)],
            [new ThemedToken('ef', '#333', FontStyle::NONE)],
        ], [$recorder]);

        $this->assertSame([
            "span L1 C0 'ab'",
            "span L1 C2 'cd'",
            'line 1',
            "span L2 C0 'ef'",
            'line 2',
            'code',
            'pre',
            'root',
        ], $log);
    }

    #[Test]
    public function line_hook_can_add_a_class(): void
    {
        $addClass = new class extends AbstractTransformer {
            public function line(Element $element, int $line, TransformerContext $context): ?Element
            {
                return $context->addClassToHast($element, 'highlighted');
            }
        };

        $root = $this->build([[new ThemedToken('x', '#111', FontStyle::NONE)]], [$addClass]);
        $pre = $root->children[0];
        $this->assertInstanceOf(Element::class, $pre);
        $code = $pre->children[0];
        $this->assertInstanceOf(Element::class, $code);
        $line = $code->children[0];
        $this->assertInstanceOf(Element::class, $line);
        $this->assertSame(['line', 'highlighted'], $line->properties['className']);
    }

    #[Test]
    public function span_hook_replacement_element_takes_effect(): void
    {
        $replace = new class extends AbstractTransformer {
            public function span(Element $element, int $line, int $col, Element $lineElement, ThemedToken $token, TransformerContext $context): ?Element
            {
                return new Element('mark', [], $element->children);
            }
        };

        $html = HastSerializer::toHtml($this->build([[new ThemedToken('x', '#111', FontStyle::NONE)]], [$replace]));
        $this->assertStringContainsString('<span class="line"><mark>x</mark></span>', $html);
    }

    #[Test]
    public function pre_hook_replacement_propagates_to_returned_element(): void
    {
        $replace = new class extends AbstractTransformer {
            public function pre(Element $element, TransformerContext $context): ?Element
            {
                return $context->addClassToHast($element, 'replaced-pre');
            }
        };

        $root = $this->build([[new ThemedToken('x', '#111', FontStyle::NONE)]], [$replace]);
        $pre = $root->children[0];
        $this->assertInstanceOf(Element::class, $pre);
        $this->assertContains('replaced-pre', $pre->properties['className']);
    }

    #[Test]
    public function col_is_utf16_code_units_for_astral_content(): void
    {
        $cols = [];
        $recorder = new class ($cols) extends AbstractTransformer {
            /** @param list<int> $cols */
            public function __construct(private array &$cols)
            {
            }

            public function span(Element $element, int $line, int $col, Element $lineElement, ThemedToken $token, TransformerContext $context): ?Element
            {
                $this->cols[] = $col;
                return null;
            }
        };

        $this->build([[
            new ThemedToken('😀', '#111', FontStyle::NONE),
            new ThemedToken('x', '#222', FontStyle::NONE),
        ]], [$recorder]);

        $this->assertSame([0, 2], $cols);
    }

    /**
     * @param list<list<ThemedToken>> $lines
     * @param list<\Shikiphp\Transformer\Transformer> $transformers
     */
    private function build(array $lines, array $transformers): Element
    {
        $pipeline = new TransformerPipeline($transformers);
        $context = new TransformerContext(options: ['lang' => 'txt'], source: '', lang: 'txt', themes: ['default' => 't']);

        return (new HastBuilder())->build(
            $lines,
            new RenderOptions(themeName: 't', fg: '#fff', bg: '#000'),
            $pipeline,
            $context,
        );
    }
}
