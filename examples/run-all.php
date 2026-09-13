<?php

declare(strict_types=1);

$scripts = [
    'A-classic-closure.php',
    'B-partial-application.php',
    'C-flow-pfa.php',
    'D-native-pipeline.php',
    'E-thunk.php',
    'F-pfa-step-list.php',
    'G-scanner-flow.php',
    'measure.php',
];

foreach ($scripts as $script) {
    echo "\n########## {$script} ##########\n";
    $code = 0;
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/'.$script), $code);
    if ($code !== 0) {
        fwrite(STDERR, "{$script} exited {$code}\n");
        exit($code);
    }
}
