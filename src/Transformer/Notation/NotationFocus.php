<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

/**
 * `[!code focus]` → "focused" on the line; adds "has-focused" to `<pre>`.
 */
final class NotationFocus extends NotationMap
{
    public function __construct(
        string $classActiveLine = 'focused',
        ?string $classActivePre = 'has-focused',
        ?string $classActiveCode = null,
    ) {
        parent::__construct(
            ['focus' => $classActiveLine],
            $classActivePre,
            $classActiveCode,
            '@shikijs/transformers:notation-focus',
        );
    }
}
