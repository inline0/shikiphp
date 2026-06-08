<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Exceptions;

/**
 * Raised when a grammar cannot be loaded or an include cannot be resolved.
 */
final class Grammar extends \RuntimeException
{
    public static function notLoaded(string $scopeName): self
    {
        return new self("Grammar for scope \"{$scopeName}\" is not loaded.");
    }

    public static function unresolvableInclude(string $include, string $scopeName): self
    {
        return new self("Cannot resolve include \"{$include}\" in grammar \"{$scopeName}\".");
    }

    public static function missingRepositoryEntry(string $name, string $scopeName): self
    {
        return new self("Repository entry \"{$name}\" not found in grammar \"{$scopeName}\".");
    }
}
