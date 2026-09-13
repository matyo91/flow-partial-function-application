<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flow\Driver\FiberDriver;
use Flow\FlowFactory;
use Flow\Ip;

study_heading('F. Interpretation B — list of PFA steps');
study_php_version();

$input = '  hello   [debug]  world  ';
$driver = new FiberDriver();

$steps = [
    removeNoise(...),
    normalizeWhitespace(...),
    applyBudget(?, 14),
];

study_line('step 0', $steps[0]);
study_line('step 1', $steps[1]);
study_line('step 2', $steps[2]);

/*
 * Trap: createFlow([Closure, Closure]) is NOT a two-stage pipeline.
 * Key 0 exists, so FlowFactory unpacks the array as Flow constructor
 * arguments: [0] => $job, [1] => $errorJob.
 */
$factory = new FlowFactory($driver);
try {
    $factory->createFlow($steps);
    study_line('createFlow($steps)', 'unexpected success');
} catch (TypeError $e) {
    study_line('createFlow($steps)', $e->getMessage());
}

$box = new stdClass();
$box->value = null;

$flow = $factory->create(static function () use ($steps, $box) {
    foreach ($steps as $step) {
        yield $step;
    }
    yield collect(?, $box);
});

$flow(new Ip($input));
$flow->await();

study_line('generator yield of PFA steps', $box->value);
study_line('matches native pipe', $box->value === ($input |> removeNoise(...) |> normalizeWhitespace(...) |> applyBudget(?, 14)));
