<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/tools/pfa-opportunities/src/functions.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

const MEASURE_ITERATIONS = 8;

$memoryOnly = null;
$roots = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--memory-only=')) {
        $memoryOnly = substr($arg, strlen('--memory-only='));
        continue;
    }
    $roots[] = $arg;
}

if ($roots === []) {
    fwrite(STDERR, "Usage: php examples/H-scanner-measure.php [--memory-only=plain|flow] <path> [...]\n");
    exit(2);
}

$config = new ScanConfig();
$files = [];
$bytes = 0;
foreach ($roots as $root) {
    foreach (findPhpFiles($root) as $file) {
        if (!isset($files[$file])) {
            $files[$file] = true;
            $bytes += (int) filesize($file);
        }
    }
}
$files = array_keys($files);

if ($memoryOnly === 'plain') {
    measure_plain($files, $config);
    echo memory_get_peak_usage(true), "\n";
    exit(0);
}
if ($memoryOnly === 'flow') {
    measure_flow($files, $config);
    echo memory_get_peak_usage(true), "\n";
    exit(0);
}

$tokens = 0;
foreach ($files as $file) {
    $tokens += countTokens($file);
}

function isolated_peak(string $mode, array $roots): int
{
    $cmd = array_merge(
        [PHP_BINARY, __FILE__, '--memory-only='.$mode],
        $roots,
    );
    $quoted = implode(' ', array_map(escapeshellarg(...), $cmd));
    $out = shell_exec($quoted);
    if ($out === null || !is_numeric(trim($out))) {
        return 0;
    }

    return (int) trim($out);
}

function measure_plain(array $files, ScanConfig $config): array
{
    $findings = [];
    $start = hrtime(true);
    $usage0 = memory_get_usage(true);
    foreach ($files as $file) {
        foreach (scanFile($file, $config) as $finding) {
            $findings[] = $finding;
        }
    }
    $ns = hrtime(true) - $start;

    return [$ns, memory_get_usage(true) - $usage0, $findings];
}

function measure_flow(array $files, ScanConfig $config): array
{
    $bag = new stdClass();
    $bag->findings = [];
    $start = hrtime(true);
    $usage0 = memory_get_usage(true);
    $flow = (new Flow(scanFile(?, $config), driver: new FiberDriver()))
        ->fn(static function (array $findings) use ($bag): array {
            foreach ($findings as $finding) {
                $bag->findings[] = $finding;
            }

            return $findings;
        });
    foreach ($files as $file) {
        $flow(new Ip($file));
    }
    $flow->await();
    $ns = hrtime(true) - $start;

    return [$ns, memory_get_usage(true) - $usage0, $bag->findings];
}

$plainTimes = [];
$flowTimes = [];
$plainFindings = [];
$flowFindings = [];
$plainMem = 0;
$flowMem = 0;

for ($n = 0; $n < MEASURE_ITERATIONS; ++$n) {
    [$ns, $mem, $plainFindings] = measure_plain($files, $config);
    $plainTimes[] = $ns;
    $plainMem = max($plainMem, $mem);
    [$ns, $mem, $flowFindings] = measure_flow($files, $config);
    $flowTimes[] = $ns;
    $flowMem = max($flowMem, $mem);
}

sort($plainTimes);
sort($flowTimes);
$plainMed = $plainTimes[(int) floor(count($plainTimes) / 2)];
$flowMed = $flowTimes[(int) floor(count($flowTimes) / 2)];

$pfa = count(array_filter($plainFindings, static fn (Finding $f): bool => $f->kind === 'pfa'));
$fcc = count($plainFindings) - $pfa;

study_heading('H. Scanner measurements');
study_php_version();
study_line('iterations', MEASURE_ITERATIONS);
study_line('files', count($files));
study_line('bytes', $bytes);
study_line('significant tokens', $tokens);
study_line('PFA findings', $pfa);
study_line('FCC findings', $fcc);
study_line('plain median ns', $plainMed);
study_line('flow median ns', $flowMed);
study_line('plain median ms', round($plainMed / 1_000_000, 3));
study_line('flow median ms', round($flowMed / 1_000_000, 3));
study_line('plain usage-delta bytes', $plainMem);
study_line('flow usage-delta bytes', $flowMem);
study_line('plain isolated peak bytes', isolated_peak('plain', $roots));
study_line('flow isolated peak bytes', isolated_peak('flow', $roots));
study_line('same finding count', count($plainFindings) === count($flowFindings));
