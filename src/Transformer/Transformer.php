<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Render\ThemedToken;

/**
 * A Shiki-compatible transformer. Every hook is optional; returning a value
 * replaces the input, while null keeps it. Implementations should extend
 * {@see AbstractTransformer} to inherit the no-op defaults.
 *
 * @phpstan-import-type Options from \Shikiphp\Highlighter
 */
interface Transformer
{
    public function name(): string;

    /** Invocation tier: 'pre' runs first, 'post' last, null in between. */
    public function enforce(): ?string;

    /**
     * @param Options $options
     */
    public function preprocess(string $code, array $options, TransformerContext $context): ?string;

    /**
     * @param list<list<ThemedToken>> $tokens
     * @return list<list<ThemedToken>>|null
     */
    public function tokens(array $tokens, TransformerContext $context): ?array;

    public function root(Element $element, TransformerContext $context): ?Element;

    public function pre(Element $element, TransformerContext $context): ?Element;

    public function code(Element $element, TransformerContext $context): ?Element;

    /** @param int $line 1-based line number. */
    public function line(Element $element, int $line, TransformerContext $context): ?Element;

    public function span(
        Element $element,
        int $line,
        int $col,
        Element $lineElement,
        ThemedToken $token,
        TransformerContext $context,
    ): ?Element;

    /**
     * @param Options $options
     */
    public function postprocess(string $html, array $options, TransformerContext $context): ?string;
}
