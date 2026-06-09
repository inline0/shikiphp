<?php

declare(strict_types=1);

namespace Shikiphp\Transformer\Notation;

/**
 * `[!code error]` / `[!code warning]` / `[!code info]` → "highlighted error" /
 * "highlighted warning" / "highlighted info" on the line; adds "has-highlighted"
 * to `<pre>`.
 */
final class NotationErrorLevel extends NotationMap
{
    /**
     * @param array<string,list<string>>|null $classMap
     */
    public function __construct(
        ?array $classMap = null,
        ?string $classActivePre = 'has-highlighted',
        ?string $classActiveCode = null,
    ) {
        parent::__construct(
            $classMap ?? [
                'error' => ['highlighted', 'error'],
                'warning' => ['highlighted', 'warning'],
                'info' => ['highlighted', 'info'],
            ],
            $classActivePre,
            $classActiveCode,
            '@shikijs/transformers:notation-error-level',
        );
    }
}
