<?php

declare(strict_types=1);

namespace Shikiphp\Regex;

/**
 * Thrown when a regular expression source cannot be parsed.
 *
 * The vendored ECMAScript regex engine reports invalid patterns by
 * throwing this exception during {@see Parser::parse()}.
 */
final class RegexSyntaxError extends \RuntimeException
{
}
