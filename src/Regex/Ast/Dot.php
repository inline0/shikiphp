<?php

declare(strict_types=1);

namespace Shikiphp\Regex\Ast;

/**
 * `.` — any character placeholder. Matcher decides whether to
 * exclude line terminators based on the current dotAll flag (which
 * may be flipped by an enclosing inline-modifier group).
 */
class Dot extends Node
{
}
