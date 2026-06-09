<?php

declare(strict_types=1);

namespace App;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

final class User
{
    public function __construct(
        public readonly string $name,
        public readonly Status $status = Status::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }
}

$user = new User('Alice');
echo $user->name . ': ' . ($user->isActive() ? 'on' : 'off') . PHP_EOL;
