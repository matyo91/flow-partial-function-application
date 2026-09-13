<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/tools/pfa-opportunities/src/functions.php';

use Flow\Driver\FiberDriver;
use Flow\Flow\Flow;
use Flow\Ip;

study_heading('G. Scanner through Flow');
study_php_version();

$roots = array_slice($argv, 1);
if ($roots === []) {
    $roots = [dirname(__DIR__).'/tools/pfa-opportunities/fixtures'];
}

$config = new ScanConfig();
$files = [];
foreach ($roots as $root) {
    foreach (findPhpFiles($root) as $file) {
        $files[$file] = true;
    }
}
$files = array_keys($files);

$bag = new stdClass();
$bag->findings = [];

$flow = (new Flow(scanFile(?, $config), driver: new FiberDriver()))
    ->fn(appendScanFindings(?, $bag));

foreach ($files as $file) {
    $flow(new Ip($file));
}
$flow->await();

$pfa = 0;
$fcc = 0;
foreach ($bag->findings as $finding) {
    assert($finding instanceof Finding);
    if ($finding->kind === 'pfa') {
        ++$pfa;
    } else {
        ++$fcc;
    }
}

study_line('files', count($files));
study_line('PFA', $pfa);
study_line('FCC', $fcc);
study_line('Flow adds', 'Ip + FiberDriver + await(); same scanFile() as bin/scan.php');

/**
 * @param list<Finding> $findings
 *
 * @return list<Finding>
 */
function appendScanFindings(array $findings, object $bag): array
{
    foreach ($findings as $finding) {
        $bag->findings[] = $finding;
    }

    return $findings;
}
