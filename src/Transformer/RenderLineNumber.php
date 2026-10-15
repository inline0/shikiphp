<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Text;

/** Port of Shiki 4.5's transformerRenderLineNumber. */
final class RenderLineNumber extends AbstractTransformer
{
    public function __construct(
        private readonly string $classLineNumber = 'line-number',
        private readonly int $start = 1,
    ) {
    }

    public function name(): string
    {
        return '@shikijs/transformers:render-line-number';
    }

    public function line(Element $element, int $line, TransformerContext $context): ?Element
    {
        if ($element->tag === 'span') {
            array_unshift($element->children, new Element(
                'span',
                ['class' => $this->classLineNumber],
                [new Text((string) ($this->start + $line - 1))],
            ));
        }

        return null;
    }
}
