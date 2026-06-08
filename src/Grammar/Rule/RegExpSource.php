<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

use Shikiphp\Oniguruma\OnigCaptureIndex;

/**
 * A single Oniguruma source string tied to its owning rule id, plus the metadata
 * vscode-textmate needs to anchor (`\A`/`\G`) and to substitute back-references
 * (`\N`) into dynamic end/while patterns from captured text.
 */
final class RegExpSource
{
    private const HAS_BACK_REFERENCES = '/\\\\(\d+)/';
    private const BACK_REFERENCING = '/\\\\(\d+)/';

    public readonly string $source;
    public readonly int $ruleId;
    public readonly bool $hasAnchor;
    public readonly bool $hasBackReferences;

    /** @var array{A0_G0: string, A0_G1: string, A1_G0: string, A1_G1: string}|null */
    private ?array $anchorCache = null;

    public function __construct(string $source, int $ruleId)
    {
        $this->ruleId = $ruleId;
        $this->hasAnchor = $this->detectAnchor($source);
        if ($this->hasAnchor) {
            $this->anchorCache = $this->buildAnchorCache($source);
        }
        $this->hasBackReferences = preg_match(self::HAS_BACK_REFERENCES, $source) === 1;
        $this->source = $source;
    }

    /**
     * Substitute `\N` references in this source with the (regex-escaped) text of
     * capture group N from the begin/while match, producing a concrete pattern.
     *
     * @param list<OnigCaptureIndex> $captureIndices
     */
    public function resolveBackReferences(string $lineText, array $captureIndices): string
    {
        $capturedValues = [];
        foreach ($captureIndices as $capture) {
            $capturedValues[] = self::utf16Substr($lineText, $capture->start, $capture->end);
        }

        return (string) preg_replace_callback(
            self::BACK_REFERENCING,
            static function (array $m) use ($capturedValues): string {
                $index = (int) $m[1];
                $value = $capturedValues[$index] ?? '';
                return self::escapeRegExpCharacters($value);
            },
            $this->source,
        );
    }

    /**
     * Pick the variant of this source with `\A`/`\G` anchors enabled or disabled
     * for the current scan position, matching vscode-textmate's anchor cache.
     */
    public function resolveAnchors(bool $allowA, bool $allowG): string
    {
        if (!$this->hasAnchor || $this->anchorCache === null) {
            return $this->source;
        }

        if ($allowA) {
            return $allowG ? $this->anchorCache['A1_G1'] : $this->anchorCache['A1_G0'];
        }

        return $allowG ? $this->anchorCache['A0_G1'] : $this->anchorCache['A0_G0'];
    }

    private function detectAnchor(string $source): bool
    {
        $length = strlen($source);
        $escaped = false;
        for ($i = 0; $i < $length; $i++) {
            $ch = $source[$i];
            if ($escaped) {
                if ($ch === 'A' || $ch === 'G') {
                    return true;
                }
                $escaped = false;
                continue;
            }
            if ($ch === '\\') {
                $escaped = true;
            }
        }

        return false;
    }

    /**
     * @return array{A0_G0: string, A0_G1: string, A1_G0: string, A1_G1: string}
     */
    private function buildAnchorCache(string $source): array
    {
        $a0g0 = '';
        $a0g1 = '';
        $a1g0 = '';
        $a1g1 = '';

        $length = strlen($source);
        for ($i = 0; $i < $length; $i++) {
            $ch = $source[$i];
            $a0g0 .= $ch;
            $a0g1 .= $ch;
            $a1g0 .= $ch;
            $a1g1 .= $ch;

            if ($ch !== '\\' || $i + 1 >= $length) {
                continue;
            }

            $next = $source[$i + 1];
            if ($next === 'A') {
                $a0g0 .= "\u{ffff}";
                $a0g1 .= "\u{ffff}";
                $a1g0 .= 'A';
                $a1g1 .= 'A';
            } elseif ($next === 'G') {
                $a0g0 .= "\u{ffff}";
                $a0g1 .= 'G';
                $a1g0 .= "\u{ffff}";
                $a1g1 .= 'G';
            } else {
                $a0g0 .= $next;
                $a0g1 .= $next;
                $a1g0 .= $next;
                $a1g1 .= $next;
            }
            $i++;
        }

        return ['A0_G0' => $a0g0, 'A0_G1' => $a0g1, 'A1_G0' => $a1g0, 'A1_G1' => $a1g1];
    }

    private static function escapeRegExpCharacters(string $value): string
    {
        return (string) preg_replace('/[\-\\\\\{\}\*\+\?\|\^\$\.\,\[\]\(\)\#\s]/', '\\\\$0', $value);
    }

    private static function utf16Substr(string $utf8, int $startCodeUnit, int $endCodeUnit): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $startCodeUnit * 2, ($endCodeUnit - $startCodeUnit) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }
}
