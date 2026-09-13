<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

study_heading('C. PFA passed directly to Flow');
study_php_version();

$corpus = new Corpus(['alpha  one', 'bravo [noise]']);
$driver = new FiberDriver();
$box = new stdClass();
$box->value = null;

/*
 * Hypothesis: Flow already type-hints Closure. PFA produces a Closure.
 * No Flow modification should be required.
 */
$arrowBudget = static fn (BenchmarkState $state): BenchmarkState => applyBudgetToState($state, 12);
$pfaBudget = applyBudgetToState(?, 12);

$flow = new Flow(loadPassages(?, $corpus), driver: $driver);
$flow
    ->fn($pfaBudget)
    ->fn(collect(?, $box));

study_line('first job', loadPassages(?, $corpus));
study_line('budget via arrow (not used)', $arrowBudget);
study_line('budget via PFA (used)', $pfaBudget);
study_line('collector via PFA', collect(?, $box));

$flow(new Ip(new BenchmarkState()));
$flow->await();

$result = $box->value;
if (!$result instanceof BenchmarkState) {
    throw new RuntimeException('Flow did not deliver a BenchmarkState');
}

study_line('result class', $result::class);
study_line('result', $result);
study_line('Flow library modified', 'no');
