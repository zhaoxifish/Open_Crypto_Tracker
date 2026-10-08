<?php
declare(strict_types=1);

require_once __DIR__ . '/app-lib/binance-account/Http.php';
require_once __DIR__ . '/app-lib/binance-account/Auth.php';
\BtcAccount\Http::headers();
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !\BtcAccount\Http::sameOrigin($_SERVER, false) || ($_SERVER['QUERY_STRING'] ?? '') !== '' || ($_SERVER['PATH_INFO'] ?? '') !== '') {
    http_response_code(403);
    exit('请通过本机 HTTPS 地址打开账户页面。');
}
$auth = new \BtcAccount\Auth(__DIR__);
try {
    if (!$auth->open()) {
        http_response_code(401);
        require __DIR__ . '/templates/interface/php/user/user-elements/binance-account-login.php';
        exit;
    }
    session_write_close();
    define('BTC_ACCOUNT_AUTHENTICATED', true);
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/templates/interface/php/user/user-elements/binance-account.php';
} catch (\Throwable) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    http_response_code(503);
    exit('账户页面暂时不可用，请重新登录管理后台后重试。');
}
