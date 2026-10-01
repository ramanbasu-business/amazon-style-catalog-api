<?php

declare(strict_types=1);

use Catalog\App\Kernel;
use Catalog\Support\Config;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// .env is for local development only. In a deployed environment the process
// already has these set, and safeLoad leaves existing values alone.
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$config = Config::fromEnvironment($_ENV + $_SERVER);

Kernel::createApp($config)->run();
