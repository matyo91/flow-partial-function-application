<?php

declare(strict_types=1);

/**
 * Shared study helpers.
 *
 * Patterns are lifted from real Flow consumers, then extracted into
 * named functions so PHP 8.6 PFA can bind the extra argument.
 *
 * - loadPassages($state, $corpus)  — LanguagePipelineFactory `use ($corpus)`
 * - collect($data, $box)           — FlowCollector result box
 * - applyBudget($text, $budget)    — flow-pipe ApplyBudgetStep
 * - Step::apply($text)             — flow-pipe unary step (FCC, not PFA)
 */

function study_heading(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function study_line(string $label, mixed $value): void
{
    echo $label, ': ', study_export($value), "\n";
}

function study_export(mixed $value): string
{
    if ($value instanceof Closure) {
        $ref = new ReflectionFunction($value);

        return sprintf(
            'Closure %s(%s): %s',
            $ref->isStatic() ? 'static ' : '',
            study_params($ref),
            study_type($ref->getReturnType()),
        );
    }

    if ($value instanceof Stringable || is_scalar($value) || $value === null) {
        return var_export($value, true);
    }

    if (is_object($value)) {
        return $value::class.' '.json_encode(get_object_vars($value), JSON_UNESCAPED_UNICODE);
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'unencodable';
}

function study_params(ReflectionFunction $ref): string
{
    $parts = [];
    foreach ($ref->getParameters() as $param) {
        $part = study_type($param->getType()).' $'.$param->getName();
        if ($param->isVariadic()) {
            $part = '...'.$part;
        }
        if ($param->isOptional() && !$param->isVariadic()) {
            $part .= ' = '.var_export($param->getDefaultValue(), true);
        }
        $parts[] = $part;
    }

    return implode(', ', $parts);
}

function study_type(?ReflectionType $type): string
{
    if ($type === null) {
        return 'mixed';
    }

    return (string) $type;
}

function study_php_version(): void
{
    study_line('PHP', PHP_VERSION);
}

final class Corpus
{
    /**
     * @param list<string> $passages
     */
    public function __construct(private readonly array $passages)
    {
    }

    /**
     * @return list<string>
     */
    public function passages(): array
    {
        return $this->passages;
    }
}

final class BenchmarkState
{
    /** @var list<string> */
    public array $passages = [];

    /** @var list<string> */
    public array $trace = [];

    public string $text = '';
}

/**
 * Extracted from LanguagePipelineFactory's first yield:
 * `static function (BenchmarkState $state) use ($corpus)`.
 */
function loadPassages(BenchmarkState $state, Corpus $corpus): BenchmarkState
{
    $state->passages = $corpus->passages();
    $state->text = implode(' ', $state->passages);
    $state->trace[] = 'LOAD';

    return $state;
}

/**
 * flow-pipe ApplyBudgetStep: keep a bounded prefix.
 */
function applyBudget(string $text, int $budget): string
{
    if ($budget < 0) {
        throw new InvalidArgumentException('budget must be >= 0');
    }

    return substr($text, 0, $budget);
}

function applyBudgetToState(BenchmarkState $state, int $budget): BenchmarkState
{
    $state->text = applyBudget($state->text, $budget);
    $state->trace[] = 'BUDGET:'.$budget;

    return $state;
}

function normalizeWhitespace(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function removeNoise(string $text): string
{
    return preg_replace('/\[[^\]]*\]/', '', $text) ?? $text;
}

/**
 * Extracted from FlowCollector: capture the last job payload.
 */
function collect(mixed $data, object $box): mixed
{
    $box->value = $data;

    return $data;
}

final class NormalizeStep
{
    public function apply(string $text): string
    {
        return normalizeWhitespace($text);
    }
}

function expensive(int $a, int $b, string $label): string
{
    $expensiveCalls = $GLOBALS['expensive_calls'] ?? 0;
    $GLOBALS['expensive_calls'] = $expensiveCalls + 1;

    return sprintf('%s:%d', $label, $a + $b);
}

function getBoundArg(): string
{
    echo "getBoundArg()\n";

    return 'bound';
}

function speak(string $who, string $msg): string
{
    return sprintf('%s: %s', $who, $msg);
}

function exampleOptional(mixed $a, string $b = 'default', string $c = 'also optional'): string
{
    return json_encode([$a, $b, $c], JSON_UNESCAPED_UNICODE) ?: '';
}

function four(int $a, int $b, int $c, int $d): string
{
    return "$a, $b, $c, $d";
}
