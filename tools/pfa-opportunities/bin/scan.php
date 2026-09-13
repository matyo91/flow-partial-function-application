#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/functions.php';

$roots = array_slice($argv, 1);
if ($roots === []) {
    fwrite(STDERR, "Usage: php tools/pfa-opportunities/bin/scan.php <path> [<path> ...]\n");
    exit(2);
}

$config = new ScanConfig();
$files = [];
foreach ($roots as $root) {
    foreach (findPhpFiles($root) as $file) {
        $files[$file] = true;
    }
}
$files = array_keys($files);

$pfa = 0;
$fcc = 0;
foreach ($files as $file) {
    foreach (scanFile($file, $config) as $finding) {
        if ($finding->kind === 'pfa') {
            ++$pfa;
        } else {
            ++$fcc;
        }
        echo formatFinding($finding, displayPath($finding->file, $roots)), "\n";
    }
}

fwrite(STDERR, sprintf(
    "scanned %d files; %d PFA candidates; %d FCC candidates\n",
    count($files),
    $pfa,
    $fcc,
));
