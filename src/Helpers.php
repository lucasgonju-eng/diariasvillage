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
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            self::json([
                'ok' => false,
                'error' => 'Método inválido.',
                'method' => $method,
                'content_type' => $contentType,
            ], 405);
        }
    }

    public static function requireAuth(): array
    {
        if (!isset($_SESSION['user'])) {
            self::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
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

    /**
     * Regra comercial de precificação do Day Use:
     * - Até 16/03/2026 (inclusive): sempre R$ 77,00 e tipo planejada.
     * - A partir de 17/03/2026: até 10h no dia => planejada (R$ 77,00); após 10h => emergencial (R$ 97,00).
     * - Datas futuras mantêm planejada (R$ 77,00).
     *
     * @return array{amount: float, daily_type: string}
     */
    public static function resolveDayUseCharge(string $dayUseDate): array
    {
        $timestamp = strtotime($dayUseDate);
        if ($timestamp === false) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }
        $dayUseIso = date('Y-m-d', $timestamp);
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $hour = (int) $now->format('H');
        $promoDeadline = '2026-03-16';

        if ($dayUseIso <= $promoDeadline) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        if ($dayUseIso > $today) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        if ($dayUseIso === $today && $hour < 10) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        return ['amount' => 97.00, 'daily_type' => 'emergencial'];
    }
}
