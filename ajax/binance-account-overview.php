<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app-lib/binance-account/Http.php';
require_once dirname(__DIR__) . '/app-lib/binance-account/Auth.php';
use BtcAccount\Http;
use BtcAccount\Auth;

Http::headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    Http::fail('method_not_allowed', '资产总览仅支持读取已同步的账户余额。', 405);
}
if (!Http::sameOrigin($_SERVER, false)) Http::fail('origin_rejected', '请从本机资产总览查看账户。', 403);
if (($_SERVER['PATH_INFO'] ?? '') !== '') Http::fail('path_not_allowed', '账户接口地址不正确。', 404);
if (($_SERVER['QUERY_STRING'] ?? '') !== '') Http::fail('query_not_allowed', '此接口不接受网址参数。', 400);

try {
    $auth = new Auth(dirname(__DIR__));
    if (!$auth->open()) Http::fail('login_required', '请先登录管理后台，再查看币安账户资产。', 401);
    // Match the account page's fail-closed handling of invalid authentication configuration.
    $auth->requiresOtp();
    session_write_close();
    require_once dirname(__DIR__) . '/app-lib/binance-account/Service.php';
    $snapshot = (new \BtcAccount\Service(new \BtcAccount\Store()))->snapshot();
    // The public shell receives only its authenticated balance view, never management tokens,
    // key hints, permission details, orders or trades. Reading a snapshot makes no network call.
    $fields = ['connected', 'status', 'readOnly', 'sampledAt', 'stale', 'error', 'errorCode',
        'balances', 'scope', 'refreshIntervalSeconds'];
    Http::json(['ok' => true, 'data' => array_intersect_key($snapshot, array_fill_keys($fields, true))]);
} catch (\Throwable) {
    Http::fail('service_unavailable', '账户服务暂时不可用，请稍后刷新，或前往币安账户查看连接状态。', 503);
}
