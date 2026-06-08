<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * A capture entry: a scope name and/or nested `patterns` (whose rule is then
 * applied to the captured span). Captures never match on their own, so the
 * scanner-collection methods are no-ops.
 */
final class CaptureRule extends Rule
{
    public function __construct(
        int $id,
        ?string $name,
        ?string $contentName,
        public readonly ?int $retokenizeCapturedWithRuleId,
    ) {
        parent::__construct($id, $name, $contentName);
    }

    public function collectPatterns(array $rulesById, RegExpSourceList $out): void
    {
        throw new \LogicException('CaptureRule has no patterns to collect.');
    }

    public function compile(array $rulesById, ?string $endRegexSource, bool $allowA, bool $allowG): RegExpSourceList
    {
        throw new \LogicException('CaptureRule cannot be compiled into a scanner.');
    }
}
