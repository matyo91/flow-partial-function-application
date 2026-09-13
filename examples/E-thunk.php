<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

study_heading('E. Thunk / deferred execution');
study_php_version();

$GLOBALS['expensive_calls'] = 0;

$thunk = expensive(3, 4, 'sum', ...);
study_line('thunk', $thunk);
study_line('calls after construction', $GLOBALS['expensive_calls']);

$shouldRun = true;
if ($shouldRun) {
    study_line('thunk()', $thunk());
}
study_line('calls after invoke', $GLOBALS['expensive_calls']);

study_heading('What a thunk is not');
echo <<<'TXT'
callable construction : expensive(3, 4, 'sum', ...) built a Closure. No work ran.
deferred execution    : $thunk() ran later. Still synchronous, still this process.
orchestration         : Flow schedules many Ips through stages and await().
concurrency           : FiberDriver can overlap jobs; PFA does not.
asynchronous I/O      : a driver / poll / Amp / React concern. PFA has none.

TXT;

study_heading('Flow still needs await(), thunk does not replace it');
$box = new stdClass();
$box->value = null;

$flow = (new Flow(normalizeWhitespace(...), driver: new FiberDriver()))
    ->fn(applyBudget(?, 5))
    ->fn(collect(?, $box));

$flow(new Ip('  abcdef  '));
study_line('before await', $box->value);
$flow->await();
study_line('after await', $box->value);

study_heading('IIFE thunk');
study_line('(four(1, 2, 3, 4, ...))()', (four(1, 2, 3, 4, ...))());
