<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Registry;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Registry\BundleLoader;

final class BundleLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shikiphp-bundle-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/grammars', 0777, true);
        file_put_contents($this->dir . '/manifest.json', json_encode([
            'languages' => [
                'demo' => [
                    'file' => 'grammars/demo.json',
                    'scopeName' => 'source.demo',
                    'aliases' => ['dm'],
                    'embedded' => [],
                ],
            ],
            'themes' => [],
        ], JSON_THROW_ON_ERROR));
        $this->writeGrammar('first');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/grammars/demo.json');
        @unlink($this->dir . '/manifest.json');
        @rmdir($this->dir . '/grammars');
        @rmdir($this->dir);
    }

    #[Test]
    public function repeat_grammar_lookups_reuse_the_first_decode(): void
    {
        $loader = new BundleLoader($this->dir);
        self::assertSame('first', $loader->rawGrammar('demo')['name']);

        file_put_contents($this->dir . '/grammars/demo.json', '{not json');

        self::assertSame('first', $loader->rawGrammar('demo')['name']);
        self::assertSame('first', $loader->rawGrammar('dm')['name']);
        $resolved = ($loader->grammarResolver())('source.demo');
        self::assertIsArray($resolved);
        self::assertSame('first', $resolved['name']);
    }

    #[Test]
    public function a_new_loader_decodes_the_file_again(): void
    {
        (new BundleLoader($this->dir))->rawGrammar('demo');
        $this->writeGrammar('second');

        self::assertSame('second', (new BundleLoader($this->dir))->rawGrammar('demo')['name']);
    }

    private function writeGrammar(string $name): void
    {
        file_put_contents($this->dir . '/grammars/demo.json', json_encode([
            'name' => $name,
            'scopeName' => 'source.demo',
            'patterns' => [],
        ], JSON_THROW_ON_ERROR));
    }
}
