<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Text;

/**
 * Port of Shiki's `transformerRenderWhitespace`: renders space and tab whitespace
 * as separate spans carrying classes (default "space"/"tab") so it can be styled.
 * The `position` option ("all"|"boundary"|"trailing"|"leading") limits which
 * tokens are processed.
 */
final class RenderWhitespace extends AbstractTransformer
{
    private const RE_SPACE_OR_TAB = '/([ \t])/';

    /** @var array<string,string> */
    private readonly array $classMap;
    private readonly string $position;

    public function __construct(
        string $classSpace = 'space',
        string $classTab = 'tab',
        string $position = 'all',
    ) {
        $this->classMap = [' ' => $classSpace, "\t" => $classTab];
        $this->position = $position;
    }

    public function name(): string
    {
        return '@shikijs/transformers:render-whitespace';
    }

    public function root(Element $element, TransformerContext $context): ?Element
    {
        $pre = $element->children[0] ?? null;

        if ($pre instanceof Element && $pre->tag === 'pre') {
            $code = $pre->children[0] ?? null;
            $lines = $code instanceof Element ? $code->children : [];
            foreach ($lines as $line) {
                if ($line instanceof Element) {
                    $this->processLine($line, $context);
                }
            }
        } else {
            $this->processLine($element, $context);
        }

        return null;
    }

    private function processLine(Element $line, TransformerContext $context): void
    {
        $elements = [];
        foreach ($line->children as $child) {
            if ($child instanceof Element) {
                $elements[] = $child;
            }
        }
        $last = count($elements) - 1;

        $result = [];
        foreach ($line->children as $token) {
            if (!$token instanceof Element) {
                $result[] = $token;
                continue;
            }

            $index = array_search($token, $elements, true);
            $index = $index === false ? -1 : $index;

            if ($this->position === 'boundary' && $index !== 0 && $index !== $last) {
                $result[] = $token;
                continue;
            }
            if ($this->position === 'trailing' && $index !== $last) {
                $result[] = $token;
                continue;
            }
            if ($this->position === 'leading' && $index !== 0) {
                $result[] = $token;
                continue;
            }

            $node = $token->children[0] ?? null;
            if (!$node instanceof Text || $node->value === '') {
                $result[] = $token;
                continue;
            }

            $rawParts = preg_split(self::RE_SPACE_OR_TAB, $node->value, -1, PREG_SPLIT_DELIM_CAPTURE);
            $parts = [];
            foreach ($rawParts === false ? [] : $rawParts as $part) {
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            $splitType = ($this->position === 'boundary' && $index === $last && $last !== 0)
                ? 'trailing'
                : $this->position;
            $renderContinuous = $this->position !== 'trailing' && $this->position !== 'leading';

            $parts = self::splitSpaces($parts, $splitType, $renderContinuous);

            if (count($parts) <= 1) {
                $result[] = $token;
                continue;
            }

            foreach ($parts as $part) {
                $properties = $token->properties;
                if (isset($this->classMap[$part])) {
                    unset($properties['style']);
                    $clone = new Element($token->tag, $properties, [new Text($part)]);
                    $context->addClassToHast($clone, $this->classMap[$part]);
                } else {
                    $clone = new Element($token->tag, $properties, [new Text($part)]);
                }
                $result[] = $clone;
            }
        }

        $line->children = $result;
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    private static function splitSpaces(array $parts, string $type, bool $renderContinuousSpaces): array
    {
        if ($type === 'all') {
            return $parts;
        }

        $count = count($parts);
        $leftCount = 0;
        $rightCount = 0;

        if ($type === 'boundary' || $type === 'leading') {
            for ($i = 0; $i < $count; $i++) {
                if (self::isSpace($parts[$i])) {
                    $leftCount++;
                } else {
                    break;
                }
            }
        }
        if ($type === 'boundary' || $type === 'trailing') {
            for ($i = $count - 1; $i >= 0; $i--) {
                if (self::isSpace($parts[$i])) {
                    $rightCount++;
                } else {
                    break;
                }
            }
        }

        $middle = array_slice($parts, $leftCount, $count - $rightCount - $leftCount);

        $middlePart = $renderContinuousSpaces
            ? self::separateContinuousSpaces($middle)
            : [implode('', $middle)];

        return [
            ...array_slice($parts, 0, $leftCount),
            ...$middlePart,
            ...array_slice($parts, $count - $rightCount),
        ];
    }

    /**
     * @param list<string> $inputs
     * @return list<string>
     */
    private static function separateContinuousSpaces(array $inputs): array
    {
        $result = [];
        $current = '';

        foreach ($inputs as $idx => $part) {
            if (self::isTab($part)) {
                if ($current !== '') {
                    $result[] = $current;
                    $current = '';
                }
                $result[] = $part;
            } elseif (
                self::isSpace($part)
                && (self::isSpace($inputs[$idx - 1] ?? null) || self::isSpace($inputs[$idx + 1] ?? null))
            ) {
                if ($current !== '') {
                    $result[] = $current;
                    $current = '';
                }
                $result[] = $part;
            } else {
                $current .= $part;
            }
        }
        if ($current !== '') {
            $result[] = $current;
        }

        return $result;
    }

    private static function isTab(?string $part): bool
    {
        return $part === "\t";
    }

    private static function isSpace(?string $part): bool
    {
        return $part === ' ' || $part === "\t";
    }
}
