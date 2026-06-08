<?php

declare(strict_types=1);

namespace App;

final class Greeter
{
    public function __construct(
        private readonly string $name = 'world',
    ) {
    }

    public function greet(): string
    {
        return sprintf('Hello, %s! %d', $this->name, 42);
    }
}

$greeter = new Greeter('shikiphp');
echo $greeter->greet(), PHP_EOL;
