<?php

declare(strict_types=1);

$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer install inside the PHP 8.6 container first.\n");
    exit(1);
}

require $autoload;
require __DIR__.'/lib/study.php';

if (PHP_VERSION_ID < 80600) {
    fwrite(STDERR, 'These examples require PHP 8.6. Running '.PHP_VERSION.".\n");
    exit(1);
}
