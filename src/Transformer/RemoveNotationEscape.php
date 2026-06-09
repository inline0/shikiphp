<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Text;

/**
 * Port of Shiki's `transformerRemoveNotationEscape`: turns `[\!code` escapes back
 * into `[!code`, so authors can write `// [\!code ...]` to keep the literal text.
 */
final class RemoveNotationEscape extends AbstractTransformer
{
    public function name(): string
    {
        return '@shikijs/transformers:remove-notation-escape';
    }

    public function code(Element $element, TransformerContext $context): ?Element
    {
        self::replace($element);

        return null;
    }

    private static function replace(Element $element): void
    {
        foreach ($element->children as $i => $child) {
            if ($child instanceof Text) {
                $pos = strpos($child->value, '[\\!code');
                if ($pos !== false) {
                    $element->children[$i] = new Text(
                        substr_replace($child->value, '[!code', $pos, strlen('[\\!code')),
                    );
                }
                continue;
            }
            if ($child instanceof Element) {
                self::replace($child);
            }
        }
    }
}
