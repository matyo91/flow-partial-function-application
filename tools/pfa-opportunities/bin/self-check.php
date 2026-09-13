#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/functions.php';

$root = dirname(__DIR__).'/fixtures';
$config = new ScanConfig();

$pfa = scanFile($root.'/pfa_ok.php', $config);
$fcc = scanFile($root.'/fcc_ok.php', $config);
$skip = scanFile($root.'/skip.php', $config);

$fail = 0;
if (count($pfa) !== 4 || array_filter($pfa, static fn (Finding $f): bool => $f->kind !== 'pfa') !== []) {
    fwrite(STDERR, 'pfa_ok.php: expected 4 PFA, got '.count($pfa)."\n");
    $fail = 1;
}
if (count($fcc) !== 3 || array_filter($fcc, static fn (Finding $f): bool => $f->kind !== 'fcc') !== []) {
    fwrite(STDERR, 'fcc_ok.php: expected 3 FCC, got '.count($fcc)."\n");
    $fail = 1;
}
if ($skip !== []) {
    fwrite(STDERR, 'skip.php: expected 0, got '.count($skip)."\n");
    foreach ($skip as $finding) {
        fwrite(STDERR, '  '.$finding->kind.' '.$finding->snippet."\n");
    }
    $fail = 1;
}

exit($fail);
