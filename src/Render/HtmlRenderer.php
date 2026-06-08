<?php

declare(strict_types=1);

namespace Shikiphp\Render;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\HastSerializer;
use Shikiphp\Transformer\TransformerContext;
use Shikiphp\Transformer\TransformerPipeline;

/**
 * Renders themed token lines into Shiki-compatible `<pre class="shiki">` HTML
 * by building a HAST tree ({@see HastBuilder}) and serializing it
 * ({@see HastSerializer}), in single-theme or dual-theme (CSS-variable) mode.
 */
final class HtmlRenderer
{
    /**
     * @param list<list<ThemedToken>> $lines
     */
    public function render(array $lines, RenderOptions $options): string
    {
        return HastSerializer::toHtml($this->renderToHast($lines, $options));
    }

    /**
     * @param list<list<ThemedToken>> $lines
     */
    public function renderToHast(
        array $lines,
        RenderOptions $options,
        ?TransformerPipeline $pipeline = null,
        ?TransformerContext $context = null,
    ): Element {
        return (new HastBuilder())->build($lines, $options, $pipeline, $context);
    }
}
