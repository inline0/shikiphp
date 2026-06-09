<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;

/**
 * Port of Shiki's `transformerMetaHighlight`: highlights lines named in the meta
 * string like `{1,3-5}` by adding a class (default "highlighted") to each.
 */
final class MetaHighlight extends AbstractTransformer
{
    private const RE_HIGHLIGHT_LINES = '/\{([\d,-]+)\}/';
    private const META_KEY = '@shikijs/transformers:meta-highlight:lines';

    public function __construct(
        private readonly string $className = 'highlighted',
        private readonly bool $zeroIndexed = false,
    ) {
    }

    public function name(): string
    {
        return '@shikijs/transformers:meta-highlight';
    }

    public function line(Element $element, int $line, TransformerContext $context): ?Element
    {
        $raw = self::metaRaw($context);
        if ($raw === null) {
            return null;
        }

        if (!array_key_exists(self::META_KEY, $context->meta)) {
            $context->meta[self::META_KEY] = self::parse($raw);
        }
        /** @var list<int> $highlighted */
        $highlighted = $context->meta[self::META_KEY] ?? [];

        $effectiveLine = $this->zeroIndexed ? $line - 1 : $line;
        if (in_array($effectiveLine, $highlighted, true)) {
            $context->addClassToHast($element, $this->className);
        }

        return $element;
    }

    /**
     * @return list<int>
     */
    private static function parse(string $meta): array
    {
        if (preg_match(self::RE_HIGHLIGHT_LINES, $meta, $m) !== 1) {
            return [];
        }

        $lines = [];
        foreach (explode(',', $m[1]) as $part) {
            $range = array_map(static fn (string $n): int => (int) $n, explode('-', $part));
            if (count($range) === 1) {
                $lines[] = $range[0];
                continue;
            }
            for ($i = $range[0]; $i <= $range[1]; $i++) {
                $lines[] = $i;
            }
        }

        return $lines;
    }

    private static function metaRaw(TransformerContext $context): ?string
    {
        $meta = $context->options['meta'] ?? null;
        if (!is_array($meta)) {
            return null;
        }
        $raw = $meta['__raw'] ?? null;
        return is_string($raw) && $raw !== '' ? $raw : null;
    }
}
