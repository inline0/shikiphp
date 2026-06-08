<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

use Shikiphp\Oniguruma\OnigCaptureIndex;

/**
 * Base for a compiled grammar rule. Each rule has a unique int id minted by the
 * RuleFactory; subclasses carry their own match/begin/end sources and the ids of
 * any sub-patterns. Mirrors vscode-textmate's `Rule`.
 */
abstract class Rule
{
    public const END_RULE_ID = -1;
    public const WHILE_RULE_ID = -2;

    private const CAPTURING = '/\$(\d+)|\$\{(\d+):\/(downcase|upcase)\}/';

    private readonly bool $nameHasCaptures;

    private readonly bool $contentNameHasCaptures;

    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $contentName,
    ) {
        $this->nameHasCaptures = $name !== null && preg_match(self::CAPTURING, $name) === 1;
        $this->contentNameHasCaptures = $contentName !== null && preg_match(self::CAPTURING, $contentName) === 1;
    }

    /**
     * @param list<OnigCaptureIndex> $captureIndices
     */
    public function nameScope(?string $lineText = null, array $captureIndices = []): ?string
    {
        if (!$this->nameHasCaptures || $lineText === null) {
            return $this->name;
        }
        return self::replaceCaptures((string) $this->name, $lineText, $captureIndices);
    }

    /**
     * @param list<OnigCaptureIndex> $captureIndices
     */
    public function contentNameScope(?string $lineText = null, array $captureIndices = []): ?string
    {
        if (!$this->contentNameHasCaptures || $lineText === null) {
            return $this->contentName;
        }
        return self::replaceCaptures((string) $this->contentName, $lineText, $captureIndices);
    }

    /**
     * Substitute `$n` / `${n:/downcase}` / `${n:/upcase}` references in a scope
     * name with the captured text from the match (mirrors vscode-textmate's
     * `RegexSource.replaceCaptures`).
     *
     * @param list<OnigCaptureIndex> $captureIndices
     */
    private static function replaceCaptures(string $scope, string $lineText, array $captureIndices): string
    {
        return (string) preg_replace_callback(
            self::CAPTURING,
            static function (array $m) use ($lineText, $captureIndices): string {
                $index = (int) ($m[1] !== '' ? $m[1] : $m[2]);
                $capture = $captureIndices[$index] ?? null;
                if ($capture === null) {
                    return $m[0];
                }
                $value = self::utf16Substr($lineText, $capture->start, $capture->end);
                $command = $m[3] ?? '';
                return match ($command) {
                    'downcase' => mb_strtolower($value),
                    'upcase' => mb_strtoupper($value),
                    default => $value,
                };
            },
            $scope,
        );
    }

    private static function utf16Substr(string $utf8, int $startCodeUnit, int $endCodeUnit): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $startCodeUnit * 2, ($endCodeUnit - $startCodeUnit) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }

    /**
     * Append this rule's match/begin sources into a scanner's source list so the
     * tokenizer can compile a combined OnigScanner for the active state.
     *
     * @param array<int, Rule> $rulesById
     */
    abstract public function collectPatterns(array $rulesById, RegExpSourceList $out): void;

    /**
     * Recursively expand this rule's `patterns` (resolving Include rules) into a
     * scanner source list.
     *
     * @param array<int, Rule> $rulesById
     */
    abstract public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList;
}
