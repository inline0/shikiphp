<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the live-oracle retry path: transient hiccups are absorbed, but a real,
 * stable divergence must still fail after retries are exhausted.
 */
final class ScenarioRunnerRetryTest extends TestCase
{
    #[Test]
    public function comparator_rejects_a_real_diff(): void
    {
        $oracle = '<pre class="shiki"><code><span class="line"><span style="color:#fff">a</span></span></code></pre>';
        $wrong = '<pre class="shiki"><code><span class="line"><span style="color:#000">a</span></span></code></pre>';

        $result = ScenarioComparator::compare($oracle, $wrong);

        self::assertFalse($result['pass']);
        self::assertNotSame([], $result['diff']);
    }

    #[Test]
    public function runner_reports_a_stable_failure_after_retries(): void
    {
        if (!OracleCapture::isAvailable()) {
            self::markTestSkipped('Oracle unavailable: node or bin/.oracle-tools/node_modules missing.');
        }

        $input = tempnam(sys_get_temp_dir(), 'retry') . '.js';
        file_put_contents($input, 'const a = 1');

        try {
            $result = ScenarioRunner::run([
                'name' => 'retry-stable-failure',
                'path' => dirname($input),
                'lang' => 'javascript',
                'theme' => '___nonexistent_theme___',
                'input' => $input,
            ]);
        } finally {
            @unlink($input);
        }

        self::assertFalse($result['pass'], 'A stable oracle failure must survive retries.');
    }
}
