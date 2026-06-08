<?php

declare(strict_types=1);

namespace Shikiphp\Hast;

/**
 * Serializes a HAST {@see Node} to an HTML string, reproducing the byte-exact
 * output Shiki emits: void-element aware, `className` space-joined into `class`,
 * `style` joined as `k:v;` in insertion order, attribute and text escaping.
 */
final class HastSerializer
{
    private const VOID_ELEMENTS = [
        'area' => true, 'base' => true, 'br' => true, 'col' => true,
        'embed' => true, 'hr' => true, 'img' => true, 'input' => true,
        'link' => true, 'meta' => true, 'param' => true, 'source' => true,
        'track' => true, 'wbr' => true,
    ];

    public static function toHtml(Node $node): string
    {
        if ($node instanceof Text) {
            return self::escapeText($node->value);
        }

        if ($node instanceof Element) {
            return self::element($node);
        }

        return '';
    }

    private static function element(Element $element): string
    {
        if ($element->tag === 'root') {
            $html = '';
            foreach ($element->children as $child) {
                $html .= self::toHtml($child);
            }
            return $html;
        }

        $html = '<' . $element->tag . self::attributes($element->properties);

        if (isset(self::VOID_ELEMENTS[$element->tag])) {
            return $html . '>';
        }

        $html .= '>';
        foreach ($element->children as $child) {
            $html .= self::toHtml($child);
        }

        return $html . '</' . $element->tag . '>';
    }

    /**
     * @param array<string,mixed> $properties
     */
    private static function attributes(array $properties): string
    {
        $out = '';
        foreach ($properties as $name => $value) {
            $rendered = self::attribute($name, $value);
            if ($rendered !== null) {
                $out .= $rendered;
            }
        }

        return $out;
    }

    private static function attribute(string $name, mixed $value): ?string
    {
        if ($name === 'className') {
            if (!is_array($value) || $value === []) {
                return null;
            }
            $classes = [];
            foreach ($value as $class) {
                if (is_scalar($class)) {
                    $classes[] = (string) $class;
                }
            }
            if ($classes === []) {
                return null;
            }
            return ' class="' . self::escapeAttr(implode(' ', $classes)) . '"';
        }

        if ($name === 'style') {
            if (!is_array($value) || $value === []) {
                return null;
            }
            $parts = [];
            foreach ($value as $key => $declaration) {
                if (!is_scalar($declaration)) {
                    continue;
                }
                $parts[] = $key . ':' . (string) $declaration;
            }
            return ' style="' . self::escapeAttr(implode(';', $parts)) . '"';
        }

        if ($value === null || $value === false) {
            return null;
        }

        if ($value === true) {
            return ' ' . $name;
        }

        if (!is_scalar($value)) {
            return null;
        }

        return ' ' . $name . '="' . self::escapeAttr((string) $value) . '"';
    }

    private static function escapeText(string $value): string
    {
        return str_replace(
            ['&', '<'],
            ['&#x26;', '&#x3C;'],
            $value,
        );
    }

    private static function escapeAttr(string $value): string
    {
        return str_replace(
            ['&', '<', '"'],
            ['&#x26;', '&#x3C;', '&#x22;'],
            $value,
        );
    }
}
