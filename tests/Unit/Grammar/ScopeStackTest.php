<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\ScopeStack;

final class ScopeStackTest extends TestCase
{
    #[Test]
    public function from_builds_outermost_first(): void
    {
        $stack = ScopeStack::from('source.php', 'meta.function');

        self::assertNotNull($stack);
        self::assertSame(['source.php', 'meta.function'], $stack->toArray());
    }

    #[Test]
    public function push_splits_space_separated_scopes(): void
    {
        $stack = ScopeStack::from('source.php');
        self::assertNotNull($stack);

        $pushed = $stack->push('meta.embedded string.quoted');

        self::assertSame(['source.php', 'meta.embedded', 'string.quoted'], $pushed->toArray());
    }

    #[Test]
    public function push_does_not_mutate_the_original(): void
    {
        $stack = ScopeStack::from('a');
        self::assertNotNull($stack);

        $stack->push('b');

        self::assertSame(['a'], $stack->toArray());
    }

    #[Test]
    public function push_scopes_handles_null_base_and_empty_string(): void
    {
        self::assertNull(ScopeStack::pushScopes(null, ''));

        $stack = ScopeStack::pushScopes(null, 'a b');
        self::assertNotNull($stack);
        self::assertSame(['a', 'b'], $stack->toArray());
    }

    #[Test]
    public function equals_is_structural(): void
    {
        $a = ScopeStack::from('source.php', 'keyword');
        $b = ScopeStack::from('source.php', 'keyword');
        $c = ScopeStack::from('source.php', 'string');
        self::assertNotNull($a);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals(null));
    }
}
