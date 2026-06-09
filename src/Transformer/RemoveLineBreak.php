<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Text;

/**
 * Port of Shiki's `transformerRemoveLineBreak`: drops the `\n` text nodes between
 * line spans. Useful when `.line` is styled `display:block` in CSS.
 */
final class RemoveLineBreak extends AbstractTransformer
{
    public function name(): string
    {
        return '@shikijs/transformers:remove-line-break';
    }

    public function code(Element $element, TransformerContext $context): ?Element
    {
        $element->children = array_values(array_filter(
            $element->children,
            static fn ($child): bool => !($child instanceof Text && $child->value === "\n"),
        ));

        return null;
    }
}
