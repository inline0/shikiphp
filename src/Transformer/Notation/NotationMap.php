<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

use Shikiphp\Hast\Element;
use Shikiphp\Transformer\TransformerContext;

/**
 * Port of Shiki's `transformerNotationMap`: matches `[!code <key>]` (optionally
 * `:<count>`) notation and adds the mapped class(es) to the affected line(s),
 * optionally tagging `<pre>`/`<code>` with an "active" class. The line-based
 * notation transformers (highlight, diff, focus, error-level) build on this.
 */
class NotationMap extends CommentNotationTransformer
{
    /**
     * @param array<string,string|list<string>> $classMap key → class(es)
     */
    public function __construct(
        private readonly array $classMap,
        private readonly ?string $classActivePre = null,
        private readonly ?string $classActiveCode = null,
        string $name = '@shikijs/transformers:notation-map',
    ) {
        $keys = implode('|', array_map(self::escapeRegExp(...), array_keys($classMap)));
        parent::__construct($name, '/#?\s*\[!code (' . $keys . ')(:\d+)?\]/i');
    }

    protected function onMatch(
        array $match,
        Element $line,
        Element $token,
        array $lines,
        int $lineIndex,
        TransformerContext $context,
    ): bool {
        $key = $match[1] ?? '';
        $range = $match[2] ?? '';
        if ($range === '') {
            $range = ':1';
        }
        $lineNum = (int) substr($range, 1);

        $end = min($lineIndex + $lineNum, count($lines));
        for ($i = $lineIndex; $i < $end; $i++) {
            if ($i < 0) {
                continue;
            }
            $context->addClassToHast($lines[$i], $this->classMap[$key]);
        }

        if ($this->classActivePre !== null && $context->pre !== null) {
            $context->addClassToHast($context->pre, $this->classActivePre);
        }
        if ($this->classActiveCode !== null && $context->code !== null) {
            $context->addClassToHast($context->code, $this->classActiveCode);
        }

        return true;
    }

    private static function escapeRegExp(string $str): string
    {
        return preg_replace('/[.*+?^${}()|[\]\\\\]/', '\\\\$0', $str) ?? $str;
    }
}
