<?php

declare(strict_types=1);

$configuration = [];
$budget = 8;
$repo = null;

$a = fn ($value) => transform($value, $configuration);
$b = static fn (string $value): string => applyBudget($value, $budget);
$c = fn ($x) => Foo::bar($x, $cfg);
$d = fn ($x) => $this->save($x, $repo);

function transform(mixed $value, array $configuration): mixed
{
    return $value;
}

function applyBudget(string $value, int $budget): string
{
    return $value;
}

class Foo
{
    public static function bar(mixed $x, mixed $cfg): mixed
    {
        return $x;
    }

    public function save(mixed $x, mixed $repo): mixed
    {
        return $x;
    }
}
