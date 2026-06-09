<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;

/**
 * Port of Shiki's `transformerCompactLineOptions`: applies per-line classes from
 * a list of `{line, classes}` entries (Shiki's legacy `lineOptions`).
 */
final class CompactLineOptions extends AbstractTransformer
{
    /**
     * @param list<array{line:int, classes?:string|list<string>}> $lineOptions
     */
    public function __construct(
        private readonly array $lineOptions = [],
    ) {
    }

    public function name(): string
    {
        return '@shikijs/transformers:compact-line-options';
    }

    public function line(Element $element, int $line, TransformerContext $context): ?Element
    {
        foreach ($this->lineOptions as $option) {
            if ($option['line'] !== $line) {
                continue;
            }
            $classes = $option['classes'] ?? null;
            if ($classes !== null && $classes !== []) {
                $context->addClassToHast($element, $classes);
            }
            return $element;
        }

        return null;
    }
}
