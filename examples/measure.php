<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

/**
 * Micro-benchmark. Not a published ranking.
 * Methodology: same process, hrtime, warmup discarded, N iterations.
 * Compare creation+invocation of equivalent unary adapters.
 */

const ITERATIONS = 20000;
const WARMUP = 2000;

function bench(string $name, Closure $setup, Closure $run, int $iterations = ITERATIONS): array
{
    $ctx = $setup();
    for ($i = 0; $i < WARMUP; ++$i) {
        $run($ctx);
    }

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; ++$i) {
        $run($ctx);
    }
    $elapsedNs = hrtime(true) - $start;

    return [
        'name' => $name,
        'iterations' => $iterations,
        'ns_total' => $elapsedNs,
        'ns_per_op' => $elapsedNs / $iterations,
    ];
}

function print_row(array $row): void
{
    printf(
        "%-28s  %8d ops  %10.1f ns/op\n",
        $row['name'],
        $row['iterations'],
        $row['ns_per_op'],
    );
}

study_heading('Measure (indicative only)');
study_php_version();
study_line('iterations', ITERATIONS);
study_line('warmup', WARMUP);

$rows = [];

$rows[] = bench('arrow create+call', static fn (): array => ['budget' => 8, 'text' => 'abcdefghijklmnop'], static function (array $ctx): void {
    $fn = static fn (string $value): string => applyBudget($value, $ctx['budget']);
    $fn($ctx['text']);
});

$rows[] = bench('pfa create+call', static fn (): array => ['text' => 'abcdefghijklmnop'], static function (array $ctx): void {
    $fn = applyBudget(?, 8);
    $fn($ctx['text']);
});

$arrowReuse = static fn (string $value): string => applyBudget($value, 8);
$pfaReuse = applyBudget(?, 8);

$rows[] = bench('arrow reuse+call', static fn (): array => ['fn' => $arrowReuse, 'text' => 'abcdefghijklmnop'], static function (array $ctx): void {
    ($ctx['fn'])($ctx['text']);
});

$rows[] = bench('pfa reuse+call', static fn (): array => ['fn' => $pfaReuse, 'text' => 'abcdefghijklmnop'], static function (array $ctx): void {
    ($ctx['fn'])($ctx['text']);
});

$rows[] = bench('native pipe', static fn (): string => '  hello   [x]  world  ', static function (string $text): void {
    $text
        |> removeNoise(...)
        |> normalizeWhitespace(...)
        |> applyBudget(?, 14);
});

$rows[] = bench('flow+arrow', static function (): array {
    $driver = new FiberDriver();

    return ['driver' => $driver, 'text' => '  hello   [x]  world  '];
}, static function (array $ctx): void {
    $box = new stdClass();
    $box->value = null;
    $flow = (new Flow(removeNoise(...), driver: $ctx['driver']))
        ->fn(normalizeWhitespace(...))
        ->fn(static fn (string $value): string => applyBudget($value, 14))
        ->fn(collect(?, $box));
    $flow(new Ip($ctx['text']));
    $flow->await();
}, 500);

$rows[] = bench('flow+pfa', static function (): array {
    $driver = new FiberDriver();

    return ['driver' => $driver, 'text' => '  hello   [x]  world  '];
}, static function (array $ctx): void {
    $box = new stdClass();
    $box->value = null;
    $flow = (new Flow(removeNoise(...), driver: $ctx['driver']))
        ->fn(normalizeWhitespace(...))
        ->fn(applyBudget(?, 14))
        ->fn(collect(?, $box));
    $flow(new Ip($ctx['text']));
    $flow->await();
}, 500);

foreach ($rows as $row) {
    print_row($row);
}

echo "Flow rows use 500 iterations because each run builds a pipeline and await().\n";
echo "Do not treat this as a production benchmark.\n";
