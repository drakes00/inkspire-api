<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// passthru(sprintf(
//     'php "%s/../bin/console" doctrine:database:drop --env=test --force --if-exists',
//     __DIR__
// ));
//
// passthru(sprintf(
//     'php "%s/../bin/console" doctrine:database:create --env=test --if-not-exists',
//     __DIR__
// ));
//
// passthru(sprintf(
//     'php "%s/../bin/console" doctrine:schema:create --env=test',
//     __DIR__
// ));
