<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

study_heading('A. Classic closure adapter');
study_php_version();

$corpus = new Corpus(['alpha  one', 'bravo']);
$state = new BenchmarkState();

/*
 * Real Flow consumer pattern (LanguagePipelineFactory):
 *
 *   yield static function (BenchmarkState $state) use ($corpus): BenchmarkState {
 *       $state->passages = $corpus->passages();
 *       return $state;
 *   };
 *
 * After extracting the body to loadPassages(), the remaining tax is the adapter.
 */
$load = static fn (BenchmarkState $value): BenchmarkState => loadPassages($value, $corpus);

study_line('callable', $load);

$loaded = $load($state);
study_line('after load', $loaded);

/*
 * flow-pipe ApplyBudgetStep needs a bound budget. Same tax:
 *   fn ($text) => applyBudget($text, $budget)
 */
$budget = 8;
$trim = static fn (string $value): string => applyBudget($value, $budget);
study_line('budget callable', $trim);
study_line('budgeted text', $trim($loaded->text));

/*
 * Unary method wrap (TokenPipelineFlowRunner) is already FCC, not PFA:
 *   fn (PipelineContext $ctx) => $step->apply($ctx)
 *   ≡ $step->apply(...)
 */
$step = new NormalizeStep();
$viaArrow = static fn (string $text): string => $step->apply($text);
$viaFcc = $step->apply(...);
study_line('method arrow', $viaArrow);
study_line('method FCC', $viaFcc);
study_line('same result', $viaArrow("  a\n\tb  ") === $viaFcc("  a\n\tb  "));
