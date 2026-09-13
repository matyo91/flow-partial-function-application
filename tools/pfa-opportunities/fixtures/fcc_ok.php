<?php

declare(strict_types=1);

$step = new stdClass();

$a = fn ($ctx) => $step->apply($ctx);
$b = fn ($x) => foo($x);
$c = fn ($x) => $this->foo($x);

function foo(mixed $x): mixed
{
    return $x;
}
