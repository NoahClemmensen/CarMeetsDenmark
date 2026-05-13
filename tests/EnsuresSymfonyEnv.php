<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\Dotenv\Dotenv;

/**
 * Bootstraps Symfony env vars when phpunit.xml/bootstrap.php aren't loaded
 * (e.g. PhpStorm's default --no-configuration invocation).
 *
 * Idempotent: only runs once per process.
 */
trait EnsuresSymfonyEnv
{
    private static bool $envBootstrapped = false;

    protected static function ensureSymfonyEnv(): void
    {
        if (self::$envBootstrapped) {
            return;
        }
        self::$envBootstrapped = true;

        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
        $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '1';

        if (!isset($_SERVER['DATABASE_URL']) && method_exists(Dotenv::class, 'bootEnv')) {
            (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
        }
    }
}
