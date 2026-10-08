<?php
declare(strict_types=1);

namespace BtcAccount;

/** Reuses the existing administrator session without loading application inputs, plugins or logging. */
final class Auth
{
    public function __construct(private string $root) {}

    public function sessionName(): string
    {
        return 'SESS_SERVER_' . substr(md5('server_session' . str_replace('\\', '/', $this->root)), 0, 10);
    }

    public static function matches(array $session, array $cookies, string $name): bool
    {
        $nonce = $session['nonce'] ?? null;
        $cookie = $cookies['admin_auth_' . $name] ?? null;
        $digest = is_array($session['admin_logged_in'] ?? null) ? ($session['admin_logged_in']['auth_hash'] ?? null) : null;
        return is_string($nonce) && strlen(trim($nonce)) >= 32 && strlen($nonce) <= 256
            && is_string($cookie) && preg_match('/^[a-f0-9]{64}$/D', $cookie) === 1
            && is_string($digest) && strlen($digest) === 40
            && hash_equals($digest, hash('ripemd160', $cookie . $nonce));
    }

    public function open(): bool
    {
        $name = $this->sessionName();
        if (!is_string($_COOKIE[$name] ?? null) || !is_string($_COOKIE['admin_auth_' . $name] ?? null)) {
            return false;
        }
        $path = session_save_path();
        if ($path === '') {
            $path = $this->root . '/cache/secured/php_sessions';
            if (!is_dir($path)) return false;
            session_save_path($path);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '21600');
        session_name($name);
        session_set_cookie_params(['lifetime' => 21600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start()) return false;
        if (!self::matches($_SESSION, $_COOKIE, $name) || $this->latestFile('admin_login_') === null) {
            session_write_close();
            return false;
        }
        if (!is_string($_SESSION['btc_account_csrf'] ?? null) || strlen($_SESSION['btc_account_csrf']) !== 64) {
            $_SESSION['btc_account_csrf'] = bin2hex(random_bytes(32));
        }
        return true;
    }

    public function csrfToken(): string
    {
        return $_SESSION['btc_account_csrf'];
    }

    public function validCsrf(mixed $token): bool
    {
        return is_string($token) && strlen($token) === 64 && hash_equals($this->csrfToken(), $token);
    }

    public function requiresOtp(): bool
    {
        $file = $this->root . '/cache/vars/admin_area_2fa.dat';
        if (!is_file($file)) throw new \RuntimeException('Administrator security mode unavailable.');
        $mode = trim($this->readSmallFile($file));
        if (!in_array($mode, ['off', 'on', 'strict'], true)) {
            throw new \RuntimeException('Administrator security mode unavailable.');
        }
        return $mode === 'strict';
    }

    public function checkOtp(mixed $code): bool
    {
        if (!$this->requiresOtp()) return true;
        $now = time();
        $attempt = $_SESSION['btc_account_otp_attempt'] ?? ['start' => $now, 'count' => 0];
        if ($now - $attempt['start'] >= 300) $attempt = ['start' => $now, 'count' => 0];
        if ($attempt['count'] >= 5) return false;
        $attempt['count']++;
        $_SESSION['btc_account_otp_attempt'] = $attempt;
        if (!is_string($code) || preg_match('/^[0-9]{6}$/D', $code) !== 1) return false;
        $loginFile = $this->latestFile('admin_login_');
        $secretFile = $this->latestFile('secret_var_');
        if ($loginFile === null || $secretFile === null) return false;
        $login = explode('||', trim($this->readSmallFile($loginFile)));
        $secret = trim($this->readSmallFile($secretFile));
        $host = parse_url(trim($this->readSmallFile($this->root . '/cache/vars/base_url.dat')), PHP_URL_HOST);
        if (count($login) < 2 || $login[0] === '' || !is_string($host) || $host === '' || strlen($secret) < 32) return false;
        $lib = $this->root . '/app-lib/php/classes/3rd-party/google-authenticator/';
        require_once $lib . 'FixedBitNotation.php';
        require_once $lib . 'GoogleAuthenticatorInterface.php';
        require_once $lib . 'GoogleAuthenticator.php';
        $base32 = new \Sonata\GoogleAuthenticator\FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true);
        $authenticator = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $valid = $authenticator->checkCode($base32->encode(hash('ripemd160', $login[0] . $host . $secret)), $code);
        unset($login, $secret);
        if ($valid) unset($_SESSION['btc_account_otp_attempt']);
        return $valid;
    }

    private function latestFile(string $prefix): ?string
    {
        $files = array_filter(glob($this->root . '/cache/secured/' . $prefix . '*.dat') ?: [],
            static fn(string $file): bool => is_file($file) && !is_link($file)
                && preg_match('/^' . preg_quote($prefix, '/') . '[a-f0-9]{32}\.dat$/D', basename($file)) === 1);
        usort($files, static fn(string $a, string $b): int => (filemtime($b) <=> filemtime($a)) ?: strcmp(basename($b), basename($a)));
        return $files[0] ?? null;
    }

    private function readSmallFile(string $file): string
    {
        if (!is_file($file) || is_link($file) || filesize($file) > 8192) throw new \RuntimeException('Administrator security file unavailable.');
        $data = file_get_contents($file);
        if ($data === false) throw new \RuntimeException('Administrator security file unavailable.');
        return $data;
    }
}
