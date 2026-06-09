<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

/**
 * `[!code highlight]` / `[!code hl]` → "highlighted" on the line; adds
 * "has-highlighted" to `<pre>`. Supports the `:<count>` range form.
 */
final class NotationHighlight extends NotationMap
{
    public function __construct(
        string $classActiveLine = 'highlighted',
        ?string $classActivePre = 'has-highlighted',
        ?string $classActiveCode = null,
    ) {
        parent::__construct(
            ['highlight' => $classActiveLine, 'hl' => $classActiveLine],
            $classActivePre,
            $classActiveCode,
            '@shikijs/transformers:notation-highlight',
        );
    }
}
