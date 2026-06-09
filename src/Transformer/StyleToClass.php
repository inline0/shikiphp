<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Render\ThemedToken;

/**
 * Port of Shiki's `transformerStyleToClass`: replaces inline `style` on the `pre`
 * element and (in dual-theme mode) on token spans with generated CSS classes,
 * collecting a stylesheet retrievable via {@see getCSS()}. Class names hash the
 * stringified style with Shiki's `cyrb53`, so output is byte-identical.
 */
final class StyleToClass extends AbstractTransformer
{
    private const MASK = 0xFFFFFFFF;

    /** @var array<string,string> className → stringified style */
    private array $classToStyle = [];

    private readonly string $classPrefix;
    private readonly string $classSuffix;

    /** @var callable(string):string */
    private $classReplacer;

    /**
     * @param (callable(string):string)|null $classReplacer
     */
    public function __construct(
        string $classPrefix = '__shiki_',
        string $classSuffix = '',
        ?callable $classReplacer = null,
    ) {
        $this->classPrefix = $classPrefix;
        $this->classSuffix = $classSuffix;
        $this->classReplacer = $classReplacer ?? static fn (string $className): string => $className;
    }

    public function name(): string
    {
        return '@shikijs/transformers:style-to-class';
    }

    public function pre(Element $element, TransformerContext $context): ?Element
    {
        $style = $element->properties['style'] ?? null;
        if (!is_array($style) || $style === []) {
            return null;
        }

        $className = $this->registerStyle($this->stringifyStyle($style));
        unset($element->properties['style']);
        $context->addClassToHast($element, $className);

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
        if ($token->htmlStyle === null || $token->htmlStyle === '') {
            return null;
        }

        $className = $this->registerStyle($token->htmlStyle);
        unset($element->properties['style']);
        $context->addClassToHast($element, $className);

        return null;
    }

    /**
     * The generated stylesheet, in insertion order, mirroring Shiki's `getCSS()`.
     */
    public function getCSS(): string
    {
        $css = '';
        foreach ($this->classToStyle as $className => $style) {
            $css .= '.' . $className . '{' . $style . '}';
        }

        return $css;
    }

    /**
     * @return array<string,string> className → stringified style
     */
    public function getClassRegistry(): array
    {
        return $this->classToStyle;
    }

    public function clearRegistry(): void
    {
        $this->classToStyle = [];
    }

    private function registerStyle(string $style): string
    {
        $className = ($this->classReplacer)($this->classPrefix . self::cyrb53($style) . $this->classSuffix);
        if (!array_key_exists($className, $this->classToStyle)) {
            $this->classToStyle[$className] = $style;
        }

        return $className;
    }

    /**
     * @param array<array-key,mixed> $style
     */
    private function stringifyStyle(array $style): string
    {
        $parts = [];
        foreach ($style as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = $key . ':' . (string) $value;
            }
        }

        return implode(';', $parts);
    }

    /**
     * Shiki's `cyrb53` string hash, computed in 32-bit space over UTF-16 code
     * units to stay byte-identical with the JS implementation.
     */
    private static function cyrb53(string $str, int $seed = 0): string
    {
        $h1 = 0xDEADBEEF ^ $seed;
        $h2 = 0x41C6CE57 ^ $seed;

        foreach (self::codeUnits($str) as $ch) {
            $h1 = self::imul($h1 ^ $ch, 2654435761);
            $h2 = self::imul($h2 ^ $ch, 1597334677);
        }

        $h1 = self::imul($h1 ^ ($h1 >> 16), 2246822507);
        $h1 ^= self::imul($h2 ^ ($h2 >> 13), 3266489909);
        $h2 = self::imul($h2 ^ ($h2 >> 16), 2246822507);
        $h2 ^= self::imul($h1 ^ ($h1 >> 13), 3266489909);

        $value = (4294967296 * (2097151 & $h2)) + ($h1 & self::MASK);

        return substr(self::toBase36($value), 0, 6);
    }

    /**
     * 32-bit integer multiply matching JavaScript's `Math.imul`.
     */
    private static function imul(int $a, int $b): int
    {
        $a &= self::MASK;
        $b &= self::MASK;

        $aHi = ($a >> 16) & 0xFFFF;
        $aLo = $a & 0xFFFF;
        $bHi = ($b >> 16) & 0xFFFF;
        $bLo = $b & 0xFFFF;

        $lo = $aLo * $bLo;
        $mid = (($aHi * $bLo) + ($aLo * $bHi)) & self::MASK;

        return ($lo + (($mid << 16) & self::MASK)) & self::MASK;
    }

    /**
     * @return list<int> UTF-16 code units
     */
    private static function codeUnits(string $str): array
    {
        $utf16 = mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
        $units = [];
        $length = strlen($utf16);
        for ($i = 0; $i < $length; $i += 2) {
            $units[] = (ord($utf16[$i]) << 8) | ord($utf16[$i + 1]);
        }

        return $units;
    }

    private static function toBase36(int $value): string
    {
        if ($value === 0) {
            return '0';
        }

        $digits = '0123456789abcdefghijklmnopqrstuvwxyz';
        $out = '';
        while ($value > 0) {
            $out = $digits[$value % 36] . $out;
            $value = intdiv($value, 36);
        }

        return $out;
    }
}
