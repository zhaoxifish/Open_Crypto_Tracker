<?php
declare(strict_types=1);

namespace BtcAccount;

final class Http
{
    public static function headers(): void
    {
        ini_set('display_errors', '0');
        ini_set('zend.exception_ignore_args', '1');
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('Vary: Cookie');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    }

    public static function sameOrigin(array $server, bool $mutation): bool
    {
        if (($server['HTTPS'] ?? '') !== 'on') return false;
        $host = strtolower($server['HTTP_HOST'] ?? '');
        // Trust only operator-configured origins, never a caller-controlled Host.
        $origins = explode(',', getenv('BTC_ACCOUNT_ORIGINS') ?: 'https://localhost:8443,https://127.0.0.1:8443');
        if (!in_array('https://' . $host, $origins, true)) return false;
        if (isset($server['HTTP_SEC_FETCH_SITE']) && !in_array($server['HTTP_SEC_FETCH_SITE'], ['same-origin', 'none'], true)) return false;
        if ($mutation || isset($server['HTTP_ORIGIN'])) {
            if (($server['HTTP_ORIGIN'] ?? '') !== 'https://' . $host) return false;
        }
        return true;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function fail(string $code, string $message, int $status): never
    {
        self::json(['ok' => false, 'code' => $code, 'error' => $message], $status);
    }
}
