<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

/**
 * Port of Shiki's `transformerMetaWordHighlight`: highlights words named in the
 * meta string like `/foo/` by emitting decorations (default class
 * "highlighted-word") for every occurrence in the source.
 */
final class MetaWordHighlight extends AbstractTransformer
{
    private const RE_WORD_MATCH = '/\/((?:\\\\.|[^\/])+)\//';
    private const RE_ESCAPE_BACKSLASH = '/\\\\(.)/';

    public function __construct(
        private readonly string $className = 'highlighted-word',
    ) {
    }

    public function name(): string
    {
        return '@shikijs/transformers:meta-word-highlight';
    }

    /**
     * @param array<string,mixed> $options
     */
    public function preprocess(string $code, array &$options, TransformerContext $context): ?string
    {
        $raw = self::metaRaw($context);
        if ($raw === null) {
            return null;
        }

        $words = self::parseWords($raw);

        $decorations = $options['decorations'] ?? [];
        if (!is_array($decorations)) {
            $decorations = [];
        }

        foreach ($words as $word) {
            $wordLen = self::utf16Length($word);
            foreach (self::findAllSubstringIndexes($code, $word) as $index) {
                $decorations[] = [
                    'start' => $index,
                    'end' => $index + $wordLen,
                    'properties' => ['class' => $this->className],
                ];
            }
        }

        $options['decorations'] = $decorations;

        return null;
    }

    /**
     * @return list<string>
     */
    private static function parseWords(string $meta): array
    {
        if (preg_match_all(self::RE_WORD_MATCH, $meta, $matches) === false) {
            return [];
        }

        $words = [];
        foreach ($matches[1] as $word) {
            $words[] = preg_replace(self::RE_ESCAPE_BACKSLASH, '$1', $word) ?? $word;
        }

        return $words;
    }

    /**
     * @return list<int> UTF-16 code-unit offsets
     */
    private static function findAllSubstringIndexes(string $str, string $substr): array
    {
        $indexes = [];
        $strLen = self::utf16Length($str);
        $subLen = self::utf16Length($substr);
        if ($subLen === 0) {
            return [];
        }

        $cursor = 0;
        while (true) {
            $index = self::utf16IndexOf($str, $substr, $cursor);
            if ($index === -1 || $index >= $strLen) {
                break;
            }
            if ($index < $cursor) {
                break;
            }
            $indexes[] = $index;
            $cursor = $index + $subLen;
        }

        return $indexes;
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

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
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
