<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Shikiphp\Tests\Oracle\ScenarioRepository;
use Shikiphp\Tests\Oracle\ScenarioRunner;

$scenarios = (new ScenarioRepository())->all();
if (($argv[1] ?? '') === '--list') {
    echo json_encode(array_column($scenarios, 'name'), JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? '') === '--batch') {
    $names = array_slice($argv, 2);
    $selected = array_values(array_filter(
        $scenarios,
        static fn (array $scenario): bool => in_array($scenario['name'], $names, true),
    ));
    echo json_encode(ScenarioRunner::runAll($selected), JSON_THROW_ON_ERROR);
    exit(0);
}

$name = $argv[1] ?? '';
foreach ($scenarios as $scenario) {
    if ($scenario['name'] === $name) {
        echo json_encode(ScenarioRunner::runAll([$scenario])[0], JSON_THROW_ON_ERROR);
        exit(0);
    }
}

fwrite(STDERR, "Unknown scenario: {$name}\n");
exit(2);
