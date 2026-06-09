<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Runs scenarios through oracle + actual + compare. A scenario whose first
 * compare diverges is re-captured and re-compared up to {@see RETRIES} more
 * times before being treated as a real failure, so a transient node hiccup
 * never fails the suite while a genuine mismatch (stable across retries) still
 * does. The comparison itself is never weakened.
 *
 * @phpstan-import-type Scenario from ScenarioRepository
 */
final class ScenarioRunner
{
    private const RETRIES = 2;

    /**
     * @param Scenario $scenario
     * @return array{name: string, pass: bool, diff: list<string>}
     */
    public static function run(array $scenario): array
    {
        $result = self::attempt($scenario);

        for ($attempt = 0; !$result['pass'] && $attempt < self::RETRIES; $attempt++) {
            $result = self::attempt($scenario);
        }

        return $result;
    }

    /**
     * A single capture + compare pass.
     *
     * @param Scenario $scenario
     * @return array{name: string, pass: bool, diff: list<string>}
     */
    private static function attempt(array $scenario): array
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
