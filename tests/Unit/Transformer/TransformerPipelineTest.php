<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Hast\Element;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Transformer\AbstractTransformer;
use Shikiphp\Transformer\TransformerContext;
use Shikiphp\Transformer\TransformerPipeline;

final class TransformerPipelineTest extends TestCase
{
    #[Test]
    public function orders_by_enforce_pre_then_normal_then_post(): void
    {
        $log = [];
        $make = function (string $id, ?string $enforce) use (&$log): AbstractTransformer {
            return new class ($id, $enforce, $log) extends AbstractTransformer {
                /** @param array<int,string> $log */
                public function __construct(private string $id, private ?string $enforce, private array &$log)
                {
                }

                public function enforce(): ?string
                {
                    return $this->enforce;
                }

                public function preprocess(string $code, array $options, TransformerContext $context): ?string
                {
                    $this->log[] = $this->id;
                    return null;
                }
            };
        };

        $pipeline = new TransformerPipeline([
            $make('post1', 'post'),
            $make('normal1', null),
            $make('pre1', 'pre'),
            $make('normal2', null),
            $make('pre2', 'pre'),
            $make('post2', 'post'),
        ]);

        $pipeline->preprocess('x', ['lang' => 'txt'], $this->context());

        $this->assertSame(['pre1', 'pre2', 'normal1', 'normal2', 'post1', 'post2'], $log);
    }

    #[Test]
    public function preprocess_replaces_when_returning_a_value_and_keeps_on_null(): void
    {
        $upper = new class extends AbstractTransformer {
            public function preprocess(string $code, array $options, TransformerContext $context): ?string
            {
                return strtoupper($code);
            }
        };
        $noop = new class extends AbstractTransformer {
        };

        $pipeline = new TransformerPipeline([$upper, $noop]);

        $this->assertSame('ABC', $pipeline->preprocess('abc', ['lang' => 'txt'], $this->context()));
    }

    #[Test]
    public function tokens_hook_can_replace_the_token_grid(): void
    {
        $replace = new class extends AbstractTransformer {
            public function tokens(array $tokens, TransformerContext $context): ?array
            {
                return [[new ThemedToken('replaced', '#fff', FontStyle::NONE)]];
            }
        };

        $pipeline = new TransformerPipeline([$replace]);
        $out = $pipeline->tokens([[new ThemedToken('orig', '#000', FontStyle::NONE)]], $this->context());

        $this->assertCount(1, $out);
        $this->assertSame('replaced', $out[0][0]->content);
    }

    #[Test]
    public function postprocess_runs_on_the_html_string(): void
    {
        $wrap = new class extends AbstractTransformer {
            public function postprocess(string $html, array $options, TransformerContext $context): ?string
            {
                return "<!--x-->{$html}";
            }
        };

        $pipeline = new TransformerPipeline([$wrap]);
        $this->assertSame('<!--x--><pre></pre>', $pipeline->postprocess('<pre></pre>', ['lang' => 'txt'], $this->context()));
    }

    #[Test]
    public function add_class_to_hast_splits_dedups_and_normalises_string(): void
    {
        $context = $this->context();
        $el = new Element('span', ['className' => 'line']);

        $context->addClassToHast($el, 'a a b');
        $context->addClassToHast($el, ['b', 'c']);

        $this->assertSame(['line', 'a', 'b', 'c'], $el->properties['className']);
    }

    private function context(): TransformerContext
    {
        return new TransformerContext(options: ['lang' => 'txt'], source: '', lang: 'txt', themes: ['default' => 't']);
    }
}
