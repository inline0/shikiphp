<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/** A PCRE runtime error during fast-path matching; the scanner falls back to the VM. */
final class PcreMatchError extends \RuntimeException
{
}
