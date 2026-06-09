<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

/**
 * `[!code ++]` / `[!code --]` → "diff add" / "diff remove" on the line; adds
 * "has-diff" to `<pre>`.
 */
final class NotationDiff extends NotationMap
{
    public function __construct(
        string $classLineAdd = 'diff add',
        string $classLineRemove = 'diff remove',
        ?string $classActivePre = 'has-diff',
        ?string $classActiveCode = null,
    ) {
        parent::__construct(
            ['++' => $classLineAdd, '--' => $classLineRemove],
            $classActivePre,
            $classActiveCode,
            '@shikijs/transformers:notation-diff',
        );
    }
}
