<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\ScopeStack;
use Shikiphp\Grammar\StateStack;

final class StateStackTest extends TestCase
{
    #[Test]
    public function root_starts_at_depth_one(): void
    {
        $scopes = ScopeStack::from('source.php');
        $root = StateStack::root(1, $scopes);

        self::assertSame(1, $root->depth);
        self::assertSame(1, $root->ruleId);
        self::assertNull($root->parent);
        self::assertSame($root->nameScopesList, $root->contentNameScopesList);
    }

    #[Test]
    public function push_increments_depth_and_pop_restores_parent(): void
    {
        $scopes = ScopeStack::from('source.php');
        $root = StateStack::root(1, $scopes);

        $pushed = $root->push(7, 0, false, '\\1end', $scopes, $scopes);

        self::assertSame(2, $pushed->depth);
        self::assertSame(7, $pushed->ruleId);
        self::assertSame('\\1end', $pushed->endRule);
        self::assertSame($root, $pushed->pop());
        self::assertNull($root->pop());
        self::assertSame($root, $root->safePop());
    }

    #[Test]
    public function equals_distinguishes_rule_id_and_end_rule(): void
    {
        $scopes = ScopeStack::from('source.php');
        $root = StateStack::root(1, $scopes);

        $a = $root->push(7, 0, false, 'end', $scopes, $scopes);
        $b = $root->push(7, 0, false, 'end', $scopes, $scopes);
        $differentRule = $root->push(8, 0, false, 'end', $scopes, $scopes);
        $differentEnd = $root->push(7, 0, false, 'OTHER', $scopes, $scopes);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($differentRule));
        self::assertFalse($a->equals($differentEnd));
        self::assertFalse($a->equals(null));
    }

    #[Test]
    public function with_end_rule_returns_same_instance_when_unchanged(): void
    {
        $scopes = ScopeStack::from('source.php');
        $pushed = StateStack::root(1, $scopes)->push(2, 0, false, 'end', $scopes, $scopes);

        self::assertSame($pushed, $pushed->withEndRule('end'));
        self::assertSame('new', $pushed->withEndRule('new')->endRule);
    }
}
