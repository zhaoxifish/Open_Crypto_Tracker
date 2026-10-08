<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app-lib/binance-account/Http.php';
require_once dirname(__DIR__) . '/app-lib/binance-account/Auth.php';
use BtcAccount\Http;
use BtcAccount\Auth;

Http::headers();
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    Http::fail('method_not_allowed', '此接口仅支持读取账户和管理本机连接。', 405);
}
if (!Http::sameOrigin($_SERVER, $method === 'POST')) Http::fail('origin_rejected', '请从本机账户页面发起请求。', 403);
if (($_SERVER['PATH_INFO'] ?? '') !== '') Http::fail('path_not_allowed', '账户接口地址不正确。', 404);
if (($_SERVER['QUERY_STRING'] ?? '') !== '') Http::fail('query_not_allowed', '此接口不接受网址参数。', 400);
if ($method === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) Http::fail('body_too_large', '提交内容过长。', 413);
if ($method === 'POST' && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    Http::fail('unsupported_content_type', '请使用账户页面提交连接信息。', 415);
}

try {
    $auth = new Auth(dirname(__DIR__));
    if (!$auth->open()) Http::fail('login_required', '管理员登录已失效，请重新登录。', 401);
    $token = $auth->csrfToken();
    $requiresOtp = $auth->requiresOtp();
    if ($method === 'POST' && !$auth->validCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        Http::fail('csrf_expired', '安全校验已失效，请刷新页面后重新提交。', 403);
    }
    $input = [];
    if ($method === 'POST') {
        $stream = fopen('php://input', 'rb');
        $raw = $stream === false ? false : stream_get_contents($stream, 8193);
        if (is_resource($stream)) fclose($stream);
        if ($raw === false || strlen($raw) > 8192) Http::fail('body_too_large', '提交内容过长。', 413);
        try { $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Http::fail('invalid_json', '连接信息格式不正确。', 400); }
        unset($raw);
        if (!is_array($input) || array_is_list($input) || !in_array($input['action'] ?? null, ['connect', 'disconnect'], true)) {
            Http::fail('invalid_action', '此接口只支持连接或断开账户。', 400);
        }
        $allowed = $input['action'] === 'connect' ? ['action', 'apiKey', 'secret', 'otp'] : ['action', 'otp'];
        if (array_diff(array_keys($input), $allowed)) Http::fail('invalid_fields', '提交内容包含不支持的字段。', 400);
        if (!$auth->checkOtp($input['otp'] ?? null)) Http::fail('invalid_otp', '两步验证码无效或尝试过于频繁，请稍后重试。', 403);
        unset($input['otp']);
        if ($input['action'] === 'connect' && (!is_string($input['apiKey'] ?? null) || !is_string($input['secret'] ?? null))) {
            Http::fail('invalid_credentials', '请填写 API Key 和 Secret Key。', 400);
        }
    }
    // Release the original administrator session before any network or file-lock wait.
    session_write_close();
    require_once dirname(__DIR__) . '/app-lib/binance-account/Service.php';
    $service = new \BtcAccount\Service(new \BtcAccount\Store());
    if ($method === 'GET') $snapshot = $service->snapshot();
    elseif ($input['action'] === 'connect') $snapshot = $service->connect($input['apiKey'], $input['secret']);
    else $snapshot = $service->disconnect();
    unset($input);
    Http::json(['ok' => true, 'csrfToken' => $token, 'requiresOtp' => $requiresOtp, 'data' => $snapshot]);
} catch (\Throwable $error) {
    unset($input, $raw);
    if ($error instanceof \BtcAccount\AccountException) {
        if ($error->retryAfter > 0) header('Retry-After: ' . $error->retryAfter);
        Http::fail($error->errorCode, $error->getMessage(), $error->httpStatus);
    }
    // An unknown failure must never expose upstream responses, URLs, credentials or stack traces.
    Http::fail('service_unavailable', '账户服务暂时不可用，请稍后重试。', 503);
}
