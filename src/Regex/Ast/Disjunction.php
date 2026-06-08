<?php

declare(strict_types=1);

namespace Shikiphp\Regex\Ast;

/** A | B | C */
class Disjunction extends Node
{
    /** @param list<Node> $alternatives */
    public function __construct(public readonly array $alternatives)
    {
    }
}
