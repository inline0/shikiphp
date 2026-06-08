<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma\Exceptions;

/**
 * Raised when an Oniguruma pattern cannot be converted to a JS-RegExp
 * source the vendored engine accepts.
 */
final class ConversionFailed extends \RuntimeException
{
    public static function unbalanced(string $onig): self
    {
        return new self("Unbalanced group in Oniguruma pattern: {$onig}");
    }

    public static function unsupported(string $construct, string $onig): self
    {
        return new self("Unsupported Oniguruma construct {$construct} in: {$onig}");
    }
}
