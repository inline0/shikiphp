<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Render\ThemedToken;

/**
 * Mirrors Shiki's `ShikiTransformerContext`: carries the call options, source
 * meta, and — once the HAST build begins — the live tree references the
 * structural hooks operate on.
 *
 * @phpstan-import-type Options from \Shikiphp\Highlighter
 */
final class TransformerContext
{
    /**
     * @param Options $options
     * @param array<string,string> $themes color key → theme name
     * @param list<list<ThemedToken>> $tokens
     * @param list<Element> $lines
     * @param array<string,mixed> $meta per-render scratch bag shared across hooks (Shiki's `this.meta`)
     */
    public function __construct(
        public array $options,
        public string $source,
        public readonly ?string $lang,
        public readonly array $themes,
        public string $structure = 'classic',
        public array $tokens = [],
        public ?Element $root = null,
        public ?Element $pre = null,
        public ?Element $code = null,
        public array $lines = [],
        public array $meta = [],
    ) {
    }

    /**
     * Append a class (or classes) to a hast element, splitting on whitespace and
     * de-duplicating — the PHP equivalent of Shiki's `addClassToHast`.
     *
     * @param string|list<string> $className
     */
    public function addClassToHast(Element $element, string|array $className): Element
    {
        $targets = is_array($className) ? $className : (preg_split('/\s+/', $className) ?: []);

        $existing = $element->properties['className'] ?? [];
        if (is_string($existing)) {
            $existing = preg_split('/\s+/', $existing) ?: [];
        }
        if (!is_array($existing)) {
            $existing = [];
        }

        $classes = [];
        foreach ($existing as $class) {
            if (is_string($class) && $class !== '') {
                $classes[] = $class;
            }
        }

        foreach ($targets as $class) {
            if ($class !== '' && !in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }

        $element->properties['className'] = $classes;

        return $element;
    }
}
