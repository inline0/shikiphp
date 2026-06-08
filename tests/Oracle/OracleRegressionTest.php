<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OracleRegressionTest extends TestCase
{
    #[Test]
    public function every_scenario_matches_shiki(): void
    {
        if (!OracleCapture::isAvailable()) {
            self::markTestSkipped('Oracle unavailable: node or bin/.oracle-tools/node_modules missing.');
        }

        $scenarios = (new ScenarioRepository())->all();
        self::assertNotSame([], $scenarios, 'No scenarios discovered.');

        $failures = [];
        foreach (ScenarioRunner::runAll($scenarios) as $result) {
            if (!$result['pass']) {
                $failures[] = $result['name'] . "\n" . implode("\n", $result['diff']);
            }
        }

        self::assertSame([], $failures, "Scenarios diverged from Shiki:\n" . implode("\n\n", $failures));
    }
}
