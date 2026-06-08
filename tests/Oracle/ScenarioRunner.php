<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Runs scenarios through oracle + actual + compare.
 *
 * @phpstan-import-type Scenario from ScenarioRepository
 */
final class ScenarioRunner
{
    /**
     * @param Scenario $scenario
     * @return array{name: string, pass: bool, diff: list<string>}
     */
    public static function run(array $scenario): array
    {
        try {
            $oracle = OracleCapture::html($scenario['lang'], $scenario['theme'], $scenario['input']);
            $actual = ActualCapture::html($scenario['lang'], $scenario['theme'], $scenario['input']);
            $comparison = ScenarioComparator::compare($oracle, $actual);

            return [
                'name' => $scenario['name'],
                'pass' => $comparison['pass'],
                'diff' => $comparison['diff'],
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $scenario['name'],
                'pass' => false,
                'diff' => [$e->getMessage()],
            ];
        }
    }

    /**
     * @param list<Scenario> $scenarios
     * @return list<array{name: string, pass: bool, diff: list<string>}>
     */
    public static function runAll(array $scenarios): array
    {
        $results = [];
        foreach ($scenarios as $scenario) {
            $results[] = self::run($scenario);
        }

        return $results;
    }
}
