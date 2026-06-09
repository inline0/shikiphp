<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Node;
use Shikiphp\Hast\Text;
use Shikiphp\Transformer\AbstractTransformer;
use Shikiphp\Transformer\TransformerContext;

/**
 * Shared base for the `// [!code ...]` notation transformers. Ports Shiki's
 * `createCommentNotationTransformer` + `parseComments` (v3 algorithm): in the
 * `code` hook it locates notation comments per language, lets the subclass
 * apply classes for each match, then strips the matched notation (and the whole
 * comment token / line when it becomes empty).
 *
 * @phpstan-type Comment array{
 *     info: array{0:string,1:string,2:?string},
 *     line: Element,
 *     token: Element,
 *     isLineCommentOnly: bool,
 *     isJsxStyle: bool,
 *     additionalTokens: list<Element>
 * }
 */
abstract class CommentNotationTransformer extends AbstractTransformer
{
    private const RE_SPLIT_COMMENT = '/(\s+\/\/)/';
    private const RE_V3_END_COMMENT_PREFIX = '/(?:\/\/|#|;{1,2}|%{1,2}|--)(\s*)$/';

    /**
     * @param non-empty-string $regex PCRE pattern (with delimiters and flags)
     *     matched against the comment body; capture groups are passed to onMatch
     */
    public function __construct(
        private readonly string $name,
        private readonly string $regex,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Applies the transformer to one regex match. Return true to consume (strip)
     * the matched notation, false to leave it in place.
     *
     * @param list<?string> $match full match + capture groups (null = not participating)
     * @param list<Element> $lines
     */
    abstract protected function onMatch(
        array $match,
        Element $line,
        Element $token,
        array $lines,
        int $lineIndex,
        TransformerContext $context,
    ): bool;

    public function code(Element $element, TransformerContext $context): ?Element
    {
        $lines = [];
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                $lines[] = $child;
            }
        }

        $jsx = in_array($context->lang, ['jsx', 'tsx'], true);
        $parsed = $this->parseComments($lines, $jsx);

        $linesToRemove = [];
        foreach ($parsed as $comment) {
            if ($comment['info'][1] === '') {
                continue;
            }

            $lineIdx = array_search($comment['line'], $lines, true);
            $lineIdx = $lineIdx === false ? -1 : $lineIdx;
            if ($comment['isLineCommentOnly']) {
                $lineIdx++;
            }

            $replaced = false;
            $comment['info'][1] = $this->replaceMatches(
                $this->regex,
                $comment['info'][1],
                function (array $match) use (&$replaced, $comment, $lines, $lineIdx, $context): string {
                    if ($this->onMatch($match, $comment['line'], $comment['token'], $lines, $lineIdx, $context)) {
                        $replaced = true;
                        return '';
                    }
                    return $match[0] ?? '';
                },
            );

            if (!$replaced) {
                continue;
            }

            $comment['info'][1] = self::v3ClearEndCommentPrefix($comment['info'][1]);
            $isEmpty = trim($comment['info'][1]) === '';
            if ($isEmpty) {
                $comment['info'][1] = '';
            }

            if ($isEmpty && $comment['isLineCommentOnly']) {
                $linesToRemove[] = $comment['line'];
            } elseif ($isEmpty && $comment['isJsxStyle']) {
                $idx = array_search($comment['token'], $comment['line']->children, true);
                if ($idx !== false) {
                    array_splice($comment['line']->children, $idx - 1, 3);
                }
            } elseif ($isEmpty) {
                for ($j = count($comment['additionalTokens']) - 1; $j >= 0; $j--) {
                    $tokenIndex = array_search($comment['additionalTokens'][$j], $comment['line']->children, true);
                    if ($tokenIndex !== false) {
                        array_splice($comment['line']->children, $tokenIndex, 1);
                    }
                }
                $tokenIndex = array_search($comment['token'], $comment['line']->children, true);
                if ($tokenIndex !== false) {
                    array_splice($comment['line']->children, $tokenIndex, 1);
                }
            } else {
                $head = $comment['token']->children[0] ?? null;
                if ($head instanceof Text) {
                    $comment['token']->children[0] = new Text(implode('', array_map(
                        static fn ($p): string => $p ?? '',
                        $comment['info'],
                    )));
                    foreach ($comment['additionalTokens'] as $additionalToken) {
                        $additionalHead = $additionalToken->children[0] ?? null;
                        if ($additionalHead instanceof Text) {
                            $additionalToken->children[0] = new Text('');
                        }
                    }
                }
            }
        }

        foreach ($linesToRemove as $line) {
            $index = array_search($line, $element->children, true);
            if ($index === false) {
                continue;
            }
            $next = $element->children[$index + 1] ?? null;
            $removeLength = ($next instanceof Text && $next->value === "\n") ? 2 : 1;
            array_splice($element->children, $index, $removeLength);
        }

        return $element;
    }

    /**
     * @param list<Element> $lines
     * @return list<Comment>
     */
    private function parseComments(array $lines, bool $jsx): array
    {
        $out = [];
        foreach ($lines as $line) {
            $this->v3SplitElements($line);

            $elements = $line->children;
            $count = count($elements);
            $start = $count - 1;
            if ($jsx) {
                $start = $count - 2;
            }

            for ($i = max($start, 0); $i < $count; $i++) {
                $token = $elements[$i];
                if (!$token instanceof Element) {
                    continue;
                }
                $head = $token->children[0] ?? null;
                if (!$head instanceof Text) {
                    continue;
                }
                $isLast = $i === $count - 1;
                $match = self::matchToken($head->value, $isLast);

                $additionalTokens = [];
                if ($match === null && $i > 0 && str_starts_with(ltrim($head->value), '[!code')) {
                    $prevToken = $elements[$i - 1];
                    if ($prevToken instanceof Element) {
                        $prevHead = $prevToken->children[0] ?? null;
                        if ($prevHead instanceof Text && str_contains($prevHead->value, '//')) {
                            $combined = self::matchToken($prevHead->value . $head->value, $isLast);
                            if ($combined !== null) {
                                $out[] = [
                                    'info' => $combined,
                                    'line' => $line,
                                    'token' => $prevToken,
                                    'isLineCommentOnly' => $count === 2
                                        && count($prevToken->children) === 1
                                        && count($token->children) === 1,
                                    'isJsxStyle' => false,
                                    'additionalTokens' => [$token],
                                ];
                                continue;
                            }
                        }
                    }
                }

                if ($match === null) {
                    continue;
                }

                if ($jsx && !$isLast && $i !== 0) {
                    $isJsxStyle = self::isValue($elements[$i - 1] ?? null, '{')
                        && self::isValue($elements[$i + 1] ?? null, '}');
                    $out[] = [
                        'info' => $match,
                        'line' => $line,
                        'token' => $token,
                        'isLineCommentOnly' => $count === 3 && count($token->children) === 1,
                        'isJsxStyle' => $isJsxStyle,
                        'additionalTokens' => $additionalTokens,
                    ];
                } else {
                    $out[] = [
                        'info' => $match,
                        'line' => $line,
                        'token' => $token,
                        'isLineCommentOnly' => $count === 1 && count($token->children) === 1,
                        'isJsxStyle' => false,
                        'additionalTokens' => $additionalTokens,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * v3 pre-split: split a trailing comment token on ` //` boundaries so a
     * notation following code in the same token is detected.
     */
    private function v3SplitElements(Element $line): void
    {
        $children = $line->children;
        $count = count($children);
        $result = [];
        $changed = false;

        foreach ($children as $idx => $element) {
            if (!$element instanceof Element) {
                $result[] = $element;
                continue;
            }
            $token = $element->children[0] ?? null;
            if (!$token instanceof Text) {
                $result[] = $element;
                continue;
            }
            $isLast = $idx === $count - 1;
            if (self::matchToken($token->value, $isLast) === null) {
                $result[] = $element;
                continue;
            }

            $rawSplits = preg_split(self::RE_SPLIT_COMMENT, $token->value, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($rawSplits === false || count($rawSplits) <= 1) {
                $result[] = $element;
                continue;
            }

            $splits = [$rawSplits[0]];
            for ($i = 1; $i < count($rawSplits); $i += 2) {
                $splits[] = $rawSplits[$i] . ($rawSplits[$i + 1] ?? '');
            }
            $splits = array_values(array_filter($splits, static fn (string $s): bool => $s !== ''));
            if (count($splits) <= 1) {
                $result[] = $element;
                continue;
            }

            $changed = true;
            foreach ($splits as $split) {
                $result[] = new Element($element->tag, $element->properties, [new Text($split)]);
            }
        }

        if ($changed) {
            $line->children = $result;
        }
    }

    private static function isValue(?Node $element, string $value): bool
    {
        if (!$element instanceof Element) {
            return false;
        }
        $text = $element->children[0] ?? null;
        if (!$text instanceof Text) {
            return false;
        }
        return trim($text->value) === $value;
    }

    /**
     * Port of Shiki's `matchToken`. Returns `[prefix, body, suffix?]` or null.
     *
     * @return array{0:string,1:string,2:?string}|null
     */
    private static function matchToken(string $text, bool $isLast): ?array
    {
        $matchers = [
            ['/^(<!--)(.+)(-->)$/s', false],
            ['/^(\/\*)(.+)(\*\/)$/s', false],
            ['/^(\/\/|["\'#]|;{1,2}|%{1,2}|--)(.*)$/s', true],
            ['/^(\*)(.+)$/s', true],
        ];

        $trimmed = ltrim($text);
        $spaceFront = self::utf16Length($text) - self::utf16Length($trimmed);
        $trimmed = rtrim($trimmed);
        $spaceEnd = self::utf16Length($text) - self::utf16Length($trimmed) - $spaceFront;

        foreach ($matchers as [$matcher, $endOfLine]) {
            if ($endOfLine && !$isLast) {
                continue;
            }
            if (preg_match($matcher, $trimmed, $m) !== 1) {
                continue;
            }
            $suffix = $m[3] ?? '';
            return [
                str_repeat(' ', $spaceFront) . $m[1],
                $m[2],
                $suffix !== '' ? $suffix . str_repeat(' ', $spaceEnd) : null,
            ];
        }

        return null;
    }

    private static function v3ClearEndCommentPrefix(string $text): string
    {
        if (preg_match(self::RE_V3_END_COMMENT_PREFIX, $text, $m, PREG_OFFSET_CAPTURE) === 1) {
            if (trim($m[1][0]) === '') {
                $byteOffset = $m[0][1];
                return rtrim(substr($text, 0, $byteOffset));
            }
        }
        return $text;
    }

    /**
     * Replace each match of a global PCRE pattern via a callback that receives
     * the raw capture-group array (mirrors JS `String.replace(re, fn)`).
     *
     * @param callable(list<?string>):string $callback
     */
    private function replaceMatches(string $regex, string $subject, callable $callback): string
    {
        $out = '';
        $offset = 0;
        while ($offset <= strlen($subject)) {
            if (preg_match($regex, $subject, $m, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                break;
            }
            $matchStart = $m[0][1];
            $matchText = $m[0][0];
            $out .= substr($subject, $offset, $matchStart - $offset);

            $groups = [];
            foreach ($m as $g) {
                $groups[] = ($g[1] === -1) ? null : $g[0];
            }
            $out .= $callback($groups);

            $advance = strlen($matchText);
            $offset = $matchStart + ($advance > 0 ? $advance : 1);
            if ($advance === 0 && $matchStart < strlen($subject)) {
                $out .= $subject[$matchStart];
            }
        }
        $out .= substr($subject, min($offset, strlen($subject)));

        return $out;
    }

    protected static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
