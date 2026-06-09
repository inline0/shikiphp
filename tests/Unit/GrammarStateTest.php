<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Exceptions\Highlight;
use Shikiphp\GrammarState;
use Shikiphp\Grammar\ScopeStack;
use Shikiphp\Grammar\StateStack;

final class GrammarStateTest extends TestCase
{
    #[Test]
    public function exposes_lang_theme_and_themes(): void
    {
        $stack = StateStack::root(0, ScopeStack::from('source.js'));
        $state = new GrammarState(['github-light' => $stack, 'github-dark' => $stack], 'javascript');

        self::assertSame('javascript', $state->lang);
        self::assertSame('github-light', $state->theme());
        self::assertSame(['github-light', 'github-dark'], $state->themes());
        self::assertSame($stack, $state->getInternalStack());
        self::assertSame($stack, $state->getInternalStack('github-dark'));
    }

    #[Test]
    public function get_scopes_walks_the_stack_innermost_first(): void
    {
        $base = StateStack::root(0, ScopeStack::from('source.js'));
        $nested = $base->push(1, -1, false, null, ScopeStack::from('string.quoted.js'), null);
        $state = new GrammarState(['t' => $nested], 'javascript');

        self::assertSame(['string.quoted.js', 'source.js'], $state->getScopes());
    }

    #[Test]
    public function with_theme_narrows_to_one_theme(): void
    {
        $stack = StateStack::root(0, ScopeStack::from('source.js'));
        $state = new GrammarState(['light' => $stack, 'dark' => $stack], 'javascript');

        $narrowed = $state->withTheme('dark');

        self::assertSame(['dark'], $narrowed->themes());
        self::assertSame('javascript', $narrowed->lang);
    }

    #[Test]
    public function with_theme_rejects_unknown_theme(): void
    {
        $stack = StateStack::root(0, ScopeStack::from('source.js'));
        $state = new GrammarState(['light' => $stack], 'javascript');

        $this->expectException(Highlight::class);
        $state->withTheme('dark');
    }

    #[Test]
    public function get_scopes_rejects_unknown_theme(): void
    {
        $stack = StateStack::root(0, ScopeStack::from('source.js'));
        $state = new GrammarState(['light' => $stack], 'javascript');

        $this->expectException(Highlight::class);
        $state->getScopes('dark');
    }
}
