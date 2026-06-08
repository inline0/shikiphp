<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

use Shikiphp\Oniguruma\OnigScanner;

/**
 * A compiled OnigScanner paired with the rule id each of its patterns belongs to,
 * so a scanner hit at pattern index N maps back to the matched rule.
 */
final readonly class CompiledRule
{
    /** @param list<int> $ruleIds */
    public function __construct(
        public OnigScanner $scanner,
        public array $ruleIds,
    ) {
    }
}
