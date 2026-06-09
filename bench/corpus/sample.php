<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\Repository;
use App\Events\UserRegistered;
use Psr\Log\LoggerInterface;

/**
 * Handles user lifecycle: registration, lookup, and soft deletion.
 */
final class UserService
{
    private const MAX_ATTEMPTS = 5;

    /** @var array<int, User> */
    private array $cache = [];

    public function __construct(
        private readonly Repository $repository,
        private readonly LoggerInterface $logger,
        private string $defaultRole = 'member',
    ) {
    }

    public function register(string $email, string $password, array $meta = []): User
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 1 << 17,
            'time_cost' => 4,
            'threads' => 2,
        ]);

        $user = new User(
            email: strtolower($email),
            passwordHash: $hash,
            role: $meta['role'] ?? $this->defaultRole,
            createdAt: new \DateTimeImmutable('now'),
        );

        $id = $this->repository->save($user);
        $this->cache[$id] = $user;

        $this->logger->info('user.registered', ['email' => $email, 'id' => $id]);
        event(new UserRegistered($user));

        return $user;
    }

    public function find(int $id): ?User
    {
        return $this->cache[$id] ??= $this->repository->findById($id);
    }

    /**
     * @param list<int> $ids
     * @return array<int, User>
     */
    public function findMany(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $user = $this->find($id);
            if ($user !== null) {
                $result[$id] = $user;
            }
        }

        return $result;
    }

    public function attemptLogin(string $email, string $password): bool
    {
        $user = $this->repository->findByEmail(strtolower($email));
        if ($user === null) {
            return false;
        }

        $ok = password_verify($password, $user->passwordHash);
        $this->logger->info('user.login', ['email' => $email, 'ok' => $ok]);

        return $ok;
    }

    public function softDelete(int $id): void
    {
        $user = $this->find($id);
        if ($user === null) {
            throw new \RuntimeException("No user #{$id}");
        }

        $user->deletedAt = new \DateTimeImmutable();
        $this->repository->save($user);
        unset($this->cache[$id]);
    }
}

$service = new UserService($repo, $logger);
$names = array_map(fn (User $u) => $u->email, $service->findMany([1, 2, 3]));
printf("Loaded %d users: %s\n", count($names), implode(', ', $names));
