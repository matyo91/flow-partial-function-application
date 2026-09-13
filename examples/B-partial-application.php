<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

study_heading('B. PHP 8.6 Partial Function Application');
study_php_version();

$corpus = new Corpus(['alpha  one', 'bravo']);
$state = new BenchmarkState();

$load = loadPassages(?, $corpus);
$trim = applyBudget(?, 8);

study_line('load PFA', $load);
study_line('trim PFA', $trim);
study_line('after load', $load($state));
study_line('budgeted text', $trim($state->text));

study_heading('? vs ... on optional parameters');
$question = exampleOptional(?, ?);
$ellipsis = exampleOptional('foo', ...);
study_line('exampleOptional(?, ?)', $question);
study_line('exampleOptional("foo", ...)', $ellipsis);
study_line('question arity', (new ReflectionFunction($question))->getNumberOfRequiredParameters());
study_line('ellipsis arity required', (new ReflectionFunction($ellipsis))->getNumberOfRequiredParameters());
study_line('ellipsis() uses defaults', $ellipsis());

study_heading('Named placeholders reorder');
$reordered = four(c: ?, d: 4, b: ?, a: 1);
study_line('four(c: ?, d: 4, b: ?, a: 1)', $reordered);
study_line('call (2, 3)', $reordered(2, 3));

study_heading('FCC is degenerate PFA');
$fcc = applyBudget(...);
$fullPfa = applyBudget(?, ?);
study_line('applyBudget(...)', $fcc);
study_line('applyBudget(?, ?)', $fullPfa);

study_heading('intval(?) vs intval(...)');
$safe = intval(?);
$unsafe = intval(...);
study_line('intval(?)', $safe);
study_line('intval(...)', $unsafe);
study_line('safe extra arg ignored', $safe('10', 2));
try {
    study_line('unsafe extra arg as base', $unsafe('10', 2));
} catch (Throwable $e) {
    study_line('unsafe extra arg', $e::class.': '.$e->getMessage());
}

study_heading('Bound arguments evaluate at creation');
echo "creating arrow\n";
$arrow = static fn (string $who): string => speak($who, getBoundArg());
echo "creating PFA\n";
$partial = speak(?, getBoundArg());
echo "invoking arrow\n";
study_line('arrow', $arrow('Larry'));
echo "invoking PFA\n";
study_line('pfa', $partial('Larry'));

study_heading('new is forbidden');
$newSnippet = '<?php new stdClass(?);';
$tmp = tempnam(sys_get_temp_dir(), 'pfa-new-');
file_put_contents($tmp, $newSnippet);
passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmp).' 2>&1', $newCode);
unlink($tmp);
study_line('subprocess exit', $newCode);

study_heading('Instance method PFA');
$step = new NormalizeStep();
$method = $step->apply(?);
study_line('step->apply(?)', $method);
study_line('applied', $method("  x\ty  "));
