<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(__DIR__.'/Application/config/bootstrap.php')) {
    require __DIR__.'/Application/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(__DIR__.'/Application/.env', 'test');
}
