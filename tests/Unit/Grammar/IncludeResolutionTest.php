<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\Registry;
use Shikiphp\Grammar\Rule\BeginEndRule;
use Shikiphp\Grammar\Rule\IncludeOnlyRule;
use Shikiphp\Grammar\Rule\MatchRule;

final class IncludeResolutionTest extends TestCase
{
    #[Test]
    public function self_include_resolves_to_the_grammar_root(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw([
            'scopeName' => 'source.s',
            'patterns' => [
                [
                    'begin' => '\\(',
                    'end' => '\\)',
                    'patterns' => [
                        ['include' => '$self'],
                    ],
                ],
            ],
        ]);

        $rootId = $grammar->rootRuleId();
        $root = $registry->getRule($rootId);
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        $group = $registry->getRule($root->patterns[0]);
        self::assertInstanceOf(BeginEndRule::class, $group);

        self::assertSame($rootId, $group->patterns[0]);
    }

    #[Test]
    public function base_include_resolves_to_the_outermost_grammar_root(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw([
            'scopeName' => 'source.b',
            'patterns' => [
                ['include' => '$base'],
                ['match' => 'x', 'name' => 'x.b'],
            ],
        ]);

        $rootId = $grammar->rootRuleId();
        $root = $registry->getRule($rootId);
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        self::assertSame($rootId, $root->patterns[0]);
    }

    #[Test]
    public function cross_scope_include_pulls_an_external_grammar_via_resolver(): void
    {
        $injected = [
            'scopeName' => 'source.css',
            'patterns' => [
                ['match' => 'color', 'name' => 'support.property.css'],
            ],
            'repository' => [
                'value' => ['match' => '\\d+', 'name' => 'constant.numeric.css'],
            ],
        ];

        $resolver = static function (string $scope) use ($injected): ?array {
            return $scope === 'source.css' ? $injected : null;
        };

        $registry = new Registry($resolver);
        $grammar = $registry->loadGrammarFromRaw([
            'scopeName' => 'text.html',
            'patterns' => [
                ['include' => 'source.css'],
                ['include' => 'source.css#value'],
            ],
        ]);

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);
        self::assertCount(2, $root->patterns);

        $cssRoot = $registry->getRule($root->patterns[0]);
        self::assertInstanceOf(IncludeOnlyRule::class, $cssRoot);
        self::assertSame('source.css', $cssRoot->name);

        $cssValue = $registry->getRule($root->patterns[1]);
        self::assertInstanceOf(MatchRule::class, $cssValue);
        self::assertSame('constant.numeric.css', $cssValue->name);
    }

    #[Test]
    public function unresolvable_external_include_is_dropped(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw([
            'scopeName' => 'text.html',
            'patterns' => [
                ['include' => 'source.unknown'],
                ['match' => 'y', 'name' => 'y.html'],
            ],
        ]);

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);
        self::assertCount(1, $root->patterns);
    }

    #[Test]
    public function injection_selector_targets_matching_scope(): void
    {
        $registry = new Registry();
        $registry->loadGrammarFromRaw([
            'scopeName' => 'comment.todo',
            'injectionSelector' => 'L:comment.line, L:comment.block',
            'patterns' => [
                ['match' => 'TODO', 'name' => 'keyword.todo'],
            ],
        ]);

        $scopes = $registry->injectionScopesFor('comment.line.double-slash');

        self::assertSame(['comment.todo'], $scopes);
        self::assertSame([], $registry->injectionScopesFor('source.php'));
    }
}
