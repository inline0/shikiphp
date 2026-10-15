<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;

final class GrammarCacheTest extends TestCase
{
    #[Test]
    public function incremental_state_keeps_its_rules_after_other_languages_are_loaded(): void
    {
        $highlighter = Highlighter::createBundled();
        for ($i = 0; $i < 20; $i++) {
            $id = 'cache-test-' . $i;
            $highlighter->loadGrammar([
                'scopeName' => 'source.' . $id,
                'patterns' => [['match' => '\\w+', 'name' => 'string.test']],
            ], $id);
        }
        $options = ['lang' => 'javascript', 'theme' => 'github-dark'];
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/upstream-4.5.json'), true, 512, JSON_THROW_ON_ERROR)['resume'];
        $state = $highlighter->getLastGrammarState($fixture['head'], $options)->withTheme('github-dark');
        self::assertSame($fixture['output'], $highlighter->codeToHtml($fixture['tail'], [...$options, 'grammarState' => $state]));
        for ($i = 0; $i < 20; $i++) {
            $highlighter->codeToHtml('text', ['lang' => 'cache-test-' . $i, 'theme' => 'github-dark']);
        }
        self::assertSame($fixture['output'], $highlighter->codeToHtml($fixture['tail'], [...$options, 'grammarState' => $state]));
    }

    #[Test]
    public function reloading_an_embedded_grammar_invalidates_compiled_parents(): void
    {
        $highlighter = Highlighter::createBundled();
        $highlighter->loadGrammar([
            'scopeName' => 'source.cache-child',
            'patterns' => [['match' => 'word', 'name' => 'string.test']],
        ], 'cache-child');
        $highlighter->loadGrammar([
            'scopeName' => 'source.cache-parent',
            'patterns' => [['include' => 'source.cache-child']],
        ], 'cache-parent');
        $options = ['lang' => 'cache-parent', 'theme' => 'github-dark'];
        self::assertStringContainsString('color:#9ECBFF', $highlighter->codeToHtml('word', $options));
        $highlighter->loadGrammar([
            'scopeName' => 'source.cache-child',
            'patterns' => [['match' => 'word', 'name' => 'keyword.test']],
        ], 'cache-child');
        self::assertStringContainsString('color:#F97583', $highlighter->codeToHtml('word', $options));
    }
}
