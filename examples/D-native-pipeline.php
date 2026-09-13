<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

study_heading('D. Native |> + PFA versus Flow');
study_php_version();

$input = "  hello   [debug]  world  ";

$native = $input
    |> removeNoise(...)
    |> normalizeWhitespace(...)
    |> applyBudget(?, 14);

study_line('native pipeline', $native);

$box = new stdClass();
$box->value = null;

$flow = (new Flow(removeNoise(...), driver: new FiberDriver()))
    ->fn(normalizeWhitespace(...))
    ->fn(applyBudget(?, 14))
    ->fn(collect(?, $box));

$flow(new Ip($input));
$flow->await();

study_line('flow pipeline', $box->value);
study_line('same string result', $native === $box->value);

/*
 * Native PHP stops at composing and calling unary functions.
 * Flow still wraps each stage in an Ip, a dispatcher, an async handler,
 * and a driver loop. That is orchestration, not syntax.
 */
study_line('native does', 'compose + call');
study_line('flow also does', 'Ip + events + FiberDriver + await()');
