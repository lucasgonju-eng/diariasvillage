<?php

namespace App;

use Dotenv\Dotenv;

class Env
{
    public static function load(string $basePath): void
    {
        if (file_exists($basePath . DIRECTORY_SEPARATOR . '.env')) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
        }
        date_default_timezone_set(self::get('APP_TIMEZONE', 'America/Sao_Paulo'));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }
}
