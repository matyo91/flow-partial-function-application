<?php

declare(strict_types=1);

$a = fn ($value) => complicated($value) && other($value);
$b = fn ($x) => foo($x, $x);
$c = fn ($x) => foo($y, $x);
$d = fn ($x) => $x->toArray();
$e = fn ($x, $y) => foo($x, $y);
$f = fn ($x) => foo($config, $x);
$g = function ($x) use ($c) {
    return foo($x, $c);
};
$h = fn ($x) => foo(...);
$i = fn (&$x) => foo($x, $c);
$j = fn ($x) => foo(bar($x), $c);
$k = fn ($x) => foo($x)->next();

function complicated(mixed $value): bool
{
    return true;
}

function other(mixed $value): bool
{
    return true;
}

function foo(mixed ...$args): mixed
{
    return $args[0] ?? null;
}

function bar(mixed $x): mixed
{
    return $x;
}
