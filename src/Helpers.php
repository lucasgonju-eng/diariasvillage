<?php

namespace App;

class Helpers
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public static function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::json(['ok' => false, 'error' => 'Metodo invalido.'], 405);
        }
    }

    public static function requireAuth(): array
    {
        if (!isset($_SESSION['user'])) {
            self::json(['ok' => false, 'error' => 'Nao autenticado.'], 401);
        }

        return $_SESSION['user'];
    }

    public static function requireAuthWeb(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login.php');
            exit;
        }

        return $_SESSION['user'];
    }

    public static function baseUrl(): string
    {
        return rtrim(Env::get('APP_URL', ''), '/');
    }

    public static function randomCode(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    public static function randomNumericCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }
        return $code;
    }
}
