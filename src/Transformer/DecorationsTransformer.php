<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

use Shikiphp\Exceptions\Highlight;
use Shikiphp\Hast\Element;
use Shikiphp\Render\PositionConverter;
use Shikiphp\Render\ThemedToken;

/**
 * Port of Shiki's built-in `transformerDecorations`: splits tokens at decoration
 * boundaries (absolute UTF-16 offsets) in the `tokens` hook, then in the `code`
 * hook wraps the covered spans in an element carrying the decoration's tag and
 * properties — applying to the line element when it covers a whole line, to the
 * single token when it covers exactly one, otherwise a new wrapper span. Nested
 * decorations (one fully containing another) are allowed; partial overlaps throw.
 *
 * @phpstan-import-type Options from \Shikiphp\Highlighter
 * @phpstan-type ResolvedDecoration array{
 *     start: array{line:int, character:int, offset:int},
 *     end: array{line:int, character:int, offset:int},
 *     tagName: string,
 *     properties: array<string,mixed>,
 *     alwaysWrap: bool
 * }
 */
final class DecorationsTransformer extends AbstractTransformer
{
    private readonly PositionConverter $converter;

    /** @var list<ResolvedDecoration> */
    private readonly array $decorations;

    /**
     * @param list<array<string,mixed>> $decorations raw decoration items
     */
    public function __construct(string $source, array $decorations)
    {
        $this->converter = new PositionConverter($source);
        $this->decorations = self::resolve($this->converter, $decorations);
    }

    public function enforce(): string
    {
        return 'post';
    }

    /**
     * @param list<list<ThemedToken>> $tokens
     * @return list<list<ThemedToken>>|null
     */
    public function tokens(array $tokens, TransformerContext $context): ?array
    {
        if ($this->decorations === []) {
            return null;
        }

        $breakpoints = [];
        foreach ($this->decorations as $d) {
            $breakpoints[$d['start']['offset']] = true;
            $breakpoints[$d['end']['offset']] = true;
        }

        return self::splitTokens($tokens, array_keys($breakpoints));
    }

    public function code(Element $element, TransformerContext $context): ?Element
    {
        if ($this->decorations === []) {
            return null;
        }

        $lines = [];
        foreach ($element->children as $child) {
            if ($child instanceof Element && $child->tag === 'span') {
                $lines[] = $child;
            }
        }

        if (count($lines) !== $this->converter->lineCount()) {
            throw new \RuntimeException(sprintf(
                'Number of lines in code element (%d) does not match the number of lines in the source (%d). Failed to apply decorations.',
                count($lines),
                $this->converter->lineCount(),
            ));
        }

        $sorted = $this->decorations;
        usort($sorted, static fn (array $a, array $b): int =>
            ($b['start']['offset'] - $a['start']['offset']) ?: ($a['end']['offset'] - $b['end']['offset']));

        $lineApplies = [];
        foreach ($sorted as $decoration) {
            $start = $decoration['start'];
            $end = $decoration['end'];

            if ($start['line'] === $end['line']) {
                self::applyLineSection($lines, $start['line'], $start['character'], $end['character'], $decoration);
                continue;
            }

            if ($start['line'] < $end['line']) {
                self::applyLineSection($lines, $start['line'], $start['character'], PHP_INT_MAX, $decoration);
                for ($i = $start['line'] + 1; $i < $end['line']; $i++) {
                    $lineEl = $lines[$i];
                    array_unshift($lineApplies, static function () use ($lineEl, $decoration): void {
                        self::applyDecoration($lineEl, $decoration);
                    });
                }
                self::applyLineSection($lines, $end['line'], 0, $end['character'], $decoration);
            }
        }

        foreach ($lineApplies as $apply) {
            $apply();
        }

        return $element;
    }

    /**
     * @param list<Element> $lines
     * @param ResolvedDecoration $decoration
     */
    private static function applyLineSection(array $lines, int $lineIndex, int $start, int $end, array $decoration): void
    {
        $lineEl = $lines[$lineIndex];
        $children = $lineEl->children;

        $text = '';
        $startIndex = $start === 0 ? 0 : -1;
        $endIndex = -1;
        if ($end === 0) {
            $endIndex = 0;
        }
        if ($end === PHP_INT_MAX) {
            $endIndex = count($children);
        }

        if ($startIndex === -1 || $endIndex === -1) {
            foreach ($children as $i => $child) {
                $text .= self::stringify($child);
                $len = self::utf16Length($text);
                if ($startIndex === -1 && $len === $start) {
                    $startIndex = $i + 1;
                }
                if ($endIndex === -1 && $len === $end) {
                    $endIndex = $i + 1;
                }
            }
        }

        if ($startIndex === -1 || $endIndex === -1) {
            throw new \RuntimeException('Failed to find decoration boundary index.');
        }

        $covered = array_slice($children, $startIndex, $endIndex - $startIndex);

        if (!$decoration['alwaysWrap'] && count($covered) === count($children)) {
            self::applyDecoration($lineEl, $decoration);
            return;
        }

        $first = $covered[0] ?? null;
        if (!$decoration['alwaysWrap'] && count($covered) === 1 && $first instanceof Element) {
            self::applyDecoration($first, $decoration);
            return;
        }

        $wrapper = new Element('span', [], $covered);
        self::applyDecoration($wrapper, $decoration);
        array_splice($children, $startIndex, count($covered), [$wrapper]);
        $lineEl->children = $children;
    }

    /**
     * Mutates the element in place — sets the decoration's tag, merges its
     * properties (preserving the element's existing class), and appends classes.
     *
     * @param ResolvedDecoration $decoration
     */
    private static function applyDecoration(Element $element, array $decoration): void
    {
        $existingClass = $element->properties['className'] ?? null;

        $properties = $element->properties;
        foreach ($decoration['properties'] as $key => $value) {
            if ($key === 'className' || $key === 'class') {
                continue;
            }
            $properties[$key] = $value;
        }
        if ($existingClass !== null) {
            $properties['className'] = $existingClass;
        }

        $element->tag = $decoration['tagName'];
        $element->properties = $properties;

        $class = $decoration['properties']['class'] ?? $decoration['properties']['className'] ?? null;
        if ($class !== null) {
            self::addClass($element, $class);
        }
    }

    private static function addClass(Element $element, mixed $class): void
    {
        if (is_array($class)) {
            $targets = $class;
        } elseif (is_string($class)) {
            $split = preg_split('/\s+/', $class);
            $targets = $split === false ? [] : $split;
        } else {
            $targets = [];
        }

        $existing = $element->properties['className'] ?? [];
        if (is_string($existing)) {
            $split = preg_split('/\s+/', $existing);
            $existing = $split === false ? [] : $split;
        }
        if (!is_array($existing)) {
            $existing = [];
        }

        $classes = [];
        foreach ($existing as $c) {
            if (is_string($c) && $c !== '') {
                $classes[] = $c;
            }
        }
        foreach ($targets as $c) {
            if (is_string($c) && $c !== '' && !in_array($c, $classes, true)) {
                $classes[] = $c;
            }
        }

        $element->properties['className'] = $classes;
    }

    /**
     * @param list<list<ThemedToken>> $tokens
     * @param list<int> $breakpoints absolute offsets
     * @return list<list<ThemedToken>>
     */
    private static function splitTokens(array $tokens, array $breakpoints): array
    {
        sort($breakpoints);
        if ($breakpoints === []) {
            return $tokens;
        }

        $out = [];
        foreach ($tokens as $line) {
            $newLine = [];
            foreach ($line as $token) {
                $tokenLen = self::utf16Length($token->content);
                $tokenStart = $token->offset;
                $tokenEnd = $tokenStart + $tokenLen;

                $inner = [];
                foreach ($breakpoints as $bp) {
                    if ($bp > $tokenStart && $bp < $tokenEnd) {
                        $inner[] = $bp - $tokenStart;
                    }
                }

                if ($inner === []) {
                    $newLine[] = $token;
                    continue;
                }

                foreach (self::splitToken($token, $inner) as $piece) {
                    $newLine[] = $piece;
                }
            }
            $out[] = $newLine;
        }

        return $out;
    }

    /**
     * @param list<int> $offsets relative (within the token), ascending
     * @return list<ThemedToken>
     */
    private static function splitToken(ThemedToken $token, array $offsets): array
    {
        $pieces = [];
        $last = 0;
        $len = self::utf16Length($token->content);

        foreach ($offsets as $offset) {
            if ($offset > $last) {
                $pieces[] = $token->withContent(
                    self::utf16Slice($token->content, $last, $offset),
                    $token->offset + $last,
                );
            }
            $last = $offset;
        }
        if ($last < $len) {
            $pieces[] = $token->withContent(
                self::utf16Slice($token->content, $last, $len),
                $token->offset + $last,
            );
        }

        return $pieces;
    }

    /**
     * @param list<array<string,mixed>> $raw
     * @return list<ResolvedDecoration>
     */
    private static function resolve(PositionConverter $converter, array $raw): array
    {
        $resolved = [];
        foreach ($raw as $item) {
            $start = self::normalizePosition($converter, $item['start'] ?? 0);
            $end = self::normalizePosition($converter, $item['end'] ?? 0);

            $tagName = $item['tagName'] ?? 'span';

            $properties = [];
            $rawProperties = $item['properties'] ?? [];
            if (is_array($rawProperties)) {
                foreach ($rawProperties as $key => $value) {
                    $properties[(string) $key] = $value;
                }
            }

            $resolved[] = [
                'start' => $start,
                'end' => $end,
                'tagName' => is_string($tagName) ? $tagName : 'span',
                'properties' => $properties,
                'alwaysWrap' => (bool) ($item['alwaysWrap'] ?? false),
            ];
        }

        self::verifyIntersections($resolved);

        return $resolved;
    }

    /**
     * @return array{line:int, character:int, offset:int}
     */
    private static function normalizePosition(PositionConverter $converter, mixed $position): array
    {
        if (is_int($position)) {
            if ($position < 0 || $position > $converter->length()) {
                throw Highlight::invalidDecorationOffset($position, $converter->length());
            }
            $pos = $converter->indexToPos($position);
            return ['line' => $pos['line'], 'character' => $pos['character'], 'offset' => $position];
        }

        if (
            !is_array($position)
            || !isset($position['line'], $position['character'])
            || !is_int($position['line'])
            || !is_int($position['character'])
        ) {
            throw Highlight::invalidDecorationPosition(json_encode($position) ?: 'null');
        }

        $line = $position['line'];
        $lineLengths = $converter->lineLengths();
        if (!isset($lineLengths[$line])) {
            throw Highlight::invalidDecorationPosition(json_encode($position) ?: 'null');
        }

        $character = $position['character'];
        $lineLen = $lineLengths[$line];
        if ($character < 0) {
            $character = $lineLen + $character;
        }
        if ($character < 0 || $character > $lineLen) {
            throw Highlight::invalidDecorationPosition(json_encode($position) ?: 'null');
        }

        return [
            'line' => $line,
            'character' => $character,
            'offset' => $converter->posToIndex($line, $character),
        ];
    }

    /**
     * @param list<ResolvedDecoration> $items
     */
    private static function verifyIntersections(array $items): void
    {
        $count = count($items);
        for ($i = 0; $i < $count; $i++) {
            $foo = $items[$i];
            if ($foo['start']['offset'] > $foo['end']['offset']) {
                throw Highlight::invalidDecorationRange(
                    json_encode($foo['start']) ?: '',
                    json_encode($foo['end']) ?: '',
                );
            }
            for ($j = $i + 1; $j < $count; $j++) {
                $bar = $items[$j];
                $fooHasBarStart = $foo['start']['offset'] <= $bar['start']['offset'] && $bar['start']['offset'] < $foo['end']['offset'];
                $fooHasBarEnd = $foo['start']['offset'] < $bar['end']['offset'] && $bar['end']['offset'] <= $foo['end']['offset'];
                $barHasFooStart = $bar['start']['offset'] <= $foo['start']['offset'] && $foo['start']['offset'] < $bar['end']['offset'];
                $barHasFooEnd = $bar['start']['offset'] < $foo['end']['offset'] && $foo['end']['offset'] <= $bar['end']['offset'];

                if (!$fooHasBarStart && !$fooHasBarEnd && !$barHasFooStart && !$barHasFooEnd) {
                    continue;
                }
                if ($fooHasBarStart && $fooHasBarEnd) {
                    continue;
                }
                if ($barHasFooStart && $barHasFooEnd) {
                    continue;
                }
                if ($barHasFooStart && $foo['start']['offset'] === $foo['end']['offset']) {
                    continue;
                }
                if ($fooHasBarEnd && $bar['start']['offset'] === $bar['end']['offset']) {
                    continue;
                }

                throw Highlight::decorationsIntersect(
                    json_encode($foo['start']) ?: '',
                    json_encode($bar['start']) ?: '',
                );
            }
        }
    }

    private static function stringify(mixed $node): string
    {
        if ($node instanceof Element) {
            $out = '';
            foreach ($node->children as $child) {
                $out .= self::stringify($child);
            }
            return $out;
        }
        if ($node instanceof \Shikiphp\Hast\Text) {
            return $node->value;
        }
        return '';
    }

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }

    private static function utf16Slice(string $utf8, int $start, int $end): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $start * 2, ($end - $start) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }
}
