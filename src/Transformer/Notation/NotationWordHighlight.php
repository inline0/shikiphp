<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Node;
use Shikiphp\Hast\Text;
use Shikiphp\Transformer\TransformerContext;

/**
 * `[!code word:xxx]` (optionally `:<count>`) → wraps each occurrence of `xxx`
 * in the following line(s) in a span carrying the "highlighted-word" class.
 */
final class NotationWordHighlight extends CommentNotationTransformer
{
    public function __construct(
        private readonly string $classActiveWord = 'highlighted-word',
        private readonly ?string $classActivePre = null,
    ) {
        parent::__construct(
            '@shikijs/transformers:notation-highlight-word',
            '/\s*\[!code word:((?:\\\\.|[^:\]])+)(:\d+)?\]/',
        );
    }

    protected function onMatch(
        array $match,
        Element $line,
        Element $token,
        array $lines,
        int $lineIndex,
        TransformerContext $context,
    ): bool {
        $word = $match[1] ?? '';
        $range = $match[2] ?? null;
        $lineNum = ($range !== null && $range !== '')
            ? (int) substr($range, 1)
            : count($lines);

        $word = preg_replace('/\\\\(.)/', '$1', $word) ?? $word;

        $end = min($lineIndex + $lineNum, count($lines));
        for ($i = $lineIndex; $i < $end; $i++) {
            if ($i < 0) {
                continue;
            }
            $this->highlightWordInLine($lines[$i], $token, $word, $this->classActiveWord, $context);
        }

        if ($this->classActivePre !== null && $context->pre !== null) {
            $context->addClassToHast($context->pre, $this->classActivePre);
        }

        return true;
    }

    private function highlightWordInLine(
        Element $line,
        Element $ignored,
        string $word,
        string $className,
        TransformerContext $context,
    ): void {
        $content = self::getTextContent($line);
        $wordLen = self::utf16Length($word);
        if ($wordLen === 0) {
            return;
        }

        $index = self::utf16IndexOf($content, $word, 0);
        while ($index !== -1) {
            $this->highlightRange($line, $ignored, $index, $wordLen, $className, $context);
            $index = self::utf16IndexOf($content, $word, $index + 1);
        }
    }

    private function highlightRange(
        Element $line,
        Element $ignored,
        int $index,
        int $len,
        string $className,
        TransformerContext $context,
    ): void {
        $elements = $line->children;
        $currentIdx = 0;
        for ($i = 0; $i < count($elements); $i++) {
            $element = $elements[$i];
            if (!$element instanceof Element || $element->tag !== 'span' || $element === $ignored) {
                if ($element instanceof Element) {
                    $currentIdx += self::utf16Length(self::getTextContent($element));
                }
                continue;
            }
            $textNode = $element->children[0] ?? null;
            if (!$textNode instanceof Text) {
                continue;
            }
            $textLen = self::utf16Length($textNode->value);

            if (self::hasOverlap([$currentIdx, $currentIdx + $textLen - 1], [$index, $index + $len])) {
                $start = max(0, $index - $currentIdx);
                $length = $len - max(0, $currentIdx - $index);
                if ($length !== 0) {
                    $separated = self::separateToken($element, $textNode, $start, $length);
                    $context->addClassToHast($separated[1], $className);
                    $output = array_values(array_filter($separated, static fn (?Element $e): bool => $e !== null));
                    array_splice($elements, $i, 1, $output);
                    $i += count($output) - 1;
                }
            }
            $currentIdx += $textLen;
        }
        $line->children = $elements;
    }

    /**
     * @param array{0:int,1:int} $range1
     * @param array{0:int,1:int} $range2
     */
    private static function hasOverlap(array $range1, array $range2): bool
    {
        return $range1[0] <= $range2[1] && $range1[1] >= $range2[0];
    }

    /**
     * @return array{0:?Element,1:Element,2:?Element}
     */
    private static function separateToken(Element $span, Text $textNode, int $index, int $len): array
    {
        $text = $textNode->value;
        $before = self::utf16Slice($text, 0, $index);
        $mid = self::utf16Slice($text, $index, $index + $len);
        $after = self::utf16Slice($text, $index + $len, self::utf16Length($text));

        return [
            $index > 0 ? self::inheritElement($span, $before) : null,
            self::inheritElement($span, $mid),
            ($index + $len < self::utf16Length($text)) ? self::inheritElement($span, $after) : null,
        ];
    }

    private static function inheritElement(Element $original, string $value): Element
    {
        return new Element($original->tag, $original->properties, [new Text($value)]);
    }

    private static function getTextContent(Node $node): string
    {
        if ($node instanceof Text) {
            return $node->value;
        }
        if ($node instanceof Element && $node->tag === 'span') {
            $out = '';
            foreach ($node->children as $child) {
                $out .= self::getTextContent($child);
            }
            return $out;
        }
        return '';
    }

    private static function utf16IndexOf(string $haystack, string $needle, int $fromCodeUnit): int
    {
        $hLen = self::utf16Length($haystack);
        $nLen = self::utf16Length($needle);
        for ($i = $fromCodeUnit; $i + $nLen <= $hLen; $i++) {
            if (self::utf16Slice($haystack, $i, $i + $nLen) === $needle) {
                return $i;
            }
        }
        return -1;
    }

    private static function utf16Slice(string $utf8, int $start, int $end): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $start * 2, ($end - $start) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }
}
