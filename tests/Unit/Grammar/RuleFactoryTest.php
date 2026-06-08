<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\Registry;
use Shikiphp\Grammar\Rule\BeginEndRule;
use Shikiphp\Grammar\Rule\CaptureRule;
use Shikiphp\Grammar\Rule\IncludeOnlyRule;
use Shikiphp\Grammar\Rule\MatchRule;

final class RuleFactoryTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sampleGrammar(): array
    {
        return [
            'scopeName' => 'source.sample',
            'patterns' => [
                ['include' => '#keywords'],
                ['include' => '#string'],
            ],
            'repository' => [
                'keywords' => [
                    'match' => '\\b(if|else)\\b',
                    'name' => 'keyword.control.sample',
                ],
                'string' => [
                    'name' => 'string.quoted.double.sample',
                    'begin' => '"',
                    'end' => '"',
                    'beginCaptures' => [
                        '0' => ['name' => 'punctuation.begin.sample'],
                    ],
                    'patterns' => [
                        ['include' => '#keywords'],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function root_is_an_include_only_rule_with_two_patterns(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw($this->sampleGrammar());

        $root = $registry->getRule($grammar->rootRuleId());

        self::assertInstanceOf(IncludeOnlyRule::class, $root);
        self::assertCount(2, $root->patterns);
    }

    #[Test]
    public function repository_match_compiles_to_a_match_rule(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw($this->sampleGrammar());

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        $keywords = $registry->getRule($root->patterns[0]);

        self::assertInstanceOf(MatchRule::class, $keywords);
        self::assertSame('keyword.control.sample', $keywords->name);
        self::assertSame('\\b(if|else)\\b', $keywords->match->source);
    }

    #[Test]
    public function begin_end_rule_carries_captures_and_child_patterns(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw($this->sampleGrammar());

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        $string = $registry->getRule($root->patterns[1]);

        self::assertInstanceOf(BeginEndRule::class, $string);
        self::assertSame('string.quoted.double.sample', $string->name);
        self::assertSame('"', $string->begin->source);
        self::assertSame('"', $string->end->source);

        $beginCaptureId = $string->beginCaptures[0];
        self::assertNotNull($beginCaptureId);
        $beginCapture = $registry->getRule($beginCaptureId);
        self::assertInstanceOf(CaptureRule::class, $beginCapture);
        self::assertSame('punctuation.begin.sample', $beginCapture->name);

        self::assertCount(1, $string->patterns);
    }

    #[Test]
    public function repeated_repository_reference_resolves_to_the_same_rule_id(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw($this->sampleGrammar());

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        $string = $registry->getRule($root->patterns[1]);
        self::assertInstanceOf(BeginEndRule::class, $string);

        self::assertSame($root->patterns[0], $string->patterns[0]);
    }

    #[Test]
    public function missing_repository_reference_is_dropped_from_patterns(): void
    {
        $registry = new Registry();
        $grammar = $registry->loadGrammarFromRaw([
            'scopeName' => 'source.holey',
            'patterns' => [
                ['include' => '#exists'],
                ['include' => '#missing'],
            ],
            'repository' => [
                'exists' => ['match' => 'x', 'name' => 'x.holey'],
            ],
        ]);

        $root = $registry->getRule($grammar->rootRuleId());
        self::assertInstanceOf(IncludeOnlyRule::class, $root);

        self::assertCount(1, $root->patterns);
    }
}
