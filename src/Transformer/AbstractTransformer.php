<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Render\ThemedToken;

/**
 * No-op base for {@see Transformer}: every hook keeps its input. Override only
 * the hooks a transformer needs.
 *
 * @phpstan-import-type Options from \Shikiphp\Highlighter
 */
abstract class AbstractTransformer implements Transformer
{
    public function name(): string
    {
        return static::class;
    }

    public function enforce(): ?string
    {
        return null;
    }

    /**
     * @param Options $options
     */
    public function preprocess(string $code, array $options, TransformerContext $context): ?string
    {
        return null;
    }

    /**
     * @param list<list<ThemedToken>> $tokens
     * @return list<list<ThemedToken>>|null
     */
    public function tokens(array $tokens, TransformerContext $context): ?array
    {
        return null;
    }

    public function root(Element $element, TransformerContext $context): ?Element
    {
        return null;
    }

    public function pre(Element $element, TransformerContext $context): ?Element
    {
        return null;
    }

    public function code(Element $element, TransformerContext $context): ?Element
    {
        return null;
    }

    public function line(Element $element, int $line, TransformerContext $context): ?Element
    {
        return null;
    }

    public function span(
        Element $element,
        int $line,
        int $col,
        Element $lineElement,
        ThemedToken $token,
        TransformerContext $context,
    ): ?Element {
        return null;
    }

    /**
     * @param Options $options
     */
    public function postprocess(string $html, array $options, TransformerContext $context): ?string
    {
        return null;
    }
}
