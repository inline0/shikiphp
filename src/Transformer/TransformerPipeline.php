<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Render\ThemedToken;

/**
 * Holds the transformers for a single render, stably ordered by enforce tier
 * (`pre`, then unset, then `post`), and drives the non-structural hooks. The
 * structural hooks (root/pre/code/line/span) are fed to {@see \Shikiphp\Render\HastBuilder}.
 *
 * @phpstan-import-type Options from \Shikiphp\Highlighter
 */
final class TransformerPipeline
{
    /** @var list<Transformer> */
    public readonly array $transformers;

    /**
     * @param list<Transformer> $transformers
     */
    public function __construct(array $transformers)
    {
        $pre = [];
        $normal = [];
        $post = [];
        foreach ($transformers as $transformer) {
            match ($transformer->enforce()) {
                'pre' => $pre[] = $transformer,
                'post' => $post[] = $transformer,
                default => $normal[] = $transformer,
            };
        }

        $this->transformers = [...$pre, ...$normal, ...$post];
    }

    public function isEmpty(): bool
    {
        return $this->transformers === [];
    }

    /**
     * @param Options $options mutated in place by transformers (Shiki's `this.options`)
     */
    public function preprocess(string $code, array &$options, TransformerContext $context): string
    {
        foreach ($this->transformers as $transformer) {
            $code = $transformer->preprocess($code, $options, $context) ?? $code;
        }

        return $code;
    }

    /**
     * @param list<list<ThemedToken>> $tokens
     * @return list<list<ThemedToken>>
     */
    public function tokens(array $tokens, TransformerContext $context): array
    {
        foreach ($this->transformers as $transformer) {
            $tokens = $transformer->tokens($tokens, $context) ?? $tokens;
        }

        return $tokens;
    }

    /**
     * @param Options $options
     */
    public function postprocess(string $html, array $options, TransformerContext $context): string
    {
        foreach ($this->transformers as $transformer) {
            $html = $transformer->postprocess($html, $options, $context) ?? $html;
        }

        return $html;
    }
}
