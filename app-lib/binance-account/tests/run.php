<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Offline-only tests: fake credentials and an injected transport, never real Binance.
require_once dirname(__DIR__) . '/Service.php';
use BtcAccount\{AccountException, Client, Service, Store};

$passed = 0;
function check(bool $condition, string $description): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAILED: ' . $description);
    $passed++;
}
function fails(callable $run, string $code, string $description): AccountException {
    try { $run(); } catch (AccountException $error) {
        check($error->errorCode === $code, $description);
        check(!str_contains($error->getMessage(), 'https://') && !str_contains($error->getMessage(), 'signature=')
            && !str_contains($error->getMessage(), str_repeat('A', 64)) && !str_contains($error->getMessage(), str_repeat('B', 64)), 'safe error message');
        return $error;
    }
    throw new RuntimeException('FAILED: expected safe exception ' . $description);
}
function restrictions(): array {
    return ['ipRestrict' => true, 'enableReading' => true, 'enableWithdrawals' => false, 'enableInternalTransfer' => false,
        'enableMargin' => false, 'enableFutures' => false, 'permitsUniversalTransfer' => false, 'enableVanillaOptions' => false,
        'enableFixApiTrade' => false, 'enableFixReadOnly' => true, 'enableSpotAndMarginTrading' => false, 'enablePortfolioMarginTrading' => false];
}
function fixtures(int $now): array {
    return [
        '/sapi/v1/account/apiRestrictions' => restrictions(),
        '/api/v3/account' => ['accountType' => 'SPOT', 'uid' => 'NEVER_EXPOSE_UID', 'canTrade' => true, 'canWithdraw' => true,
            'balances' => [['asset' => 'BTC', 'free' => '0.123456789123456789', 'locked' => '0.000000000000000001'],
                ['asset' => 'USDT', 'free' => '1000.00', 'locked' => '5.10'], ['asset' => 'ETH', 'free' => '0', 'locked' => '0.0000']]],
        '/api/v3/openOrders' => [['symbol' => 'BTCUSDT', 'orderId' => 120, 'clientOrderId' => 'NEVER_EXPOSE_CLIENT_ID',
            'side' => 'BUY', 'type' => 'LIMIT', 'status' => 'NEW', 'price' => '50000.00', 'origQty' => '0.1', 'executedQty' => '0.00', 'time' => $now - 10000]],
        '/api/v3/myTrades' => [['symbol' => 'BTCUSDT', 'id' => 122, 'orderId' => 121, 'isBuyer' => true, 'isMaker' => false,
            'price' => '49000.00', 'qty' => '0.01', 'quoteQty' => '490.00', 'commission' => '0.00001', 'commissionAsset' => 'BTC', 'time' => $now - 20000]],
    ];
}
function response(mixed $data, int $status = 200, array $headers = []): array {
    return ['status' => $status, 'headers' => $headers, 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
}
function transport(array &$data, int &$now, array &$calls, ?callable $hook = null): Closure {
    return static function (string $method, string $url, array $headers) use (&$data, &$now, &$calls, $hook): array {
        $parts = parse_url($url); $path = $parts['path']; parse_str($parts['query'] ?? '', $params);
        check($method === 'GET' && $parts['scheme'] === 'https' && $parts['host'] === 'api.binance.com', 'fixed-host GET only');
        check(in_array($path, ['/api/v3/time', '/sapi/v1/account/apiRestrictions', '/api/v3/account', '/api/v3/openOrders', '/api/v3/myTrades'], true), 'allowed endpoint');
        $calls[] = $path;
        if ($path !== '/api/v3/time') {
            $signature = $params['signature'] ?? ''; unset($params['signature']);
            check(hash_equals(hash_hmac('sha256', http_build_query($params, '', '&', PHP_QUERY_RFC3986), str_repeat('B', 64)), $signature), 'HMAC signature');
            check(in_array('X-MBX-APIKEY: ' . str_repeat('A', 64), $headers, true), 'API key header only');
            check($params['recvWindow'] === '5000', 'bounded receive window');
            if (isset($params['symbol'])) check($params['symbol'] === 'BTCUSDT', 'fixed private market');
        }
        if ($hook !== null) { $override = $hook($path, $params); if ($override !== null) return $override; }
        return response($path === '/api/v3/time' ? ['serverTime' => $now] : $data[$path]);
    };
}
function newStore(string $root, string $name): Store {
    mkdir($root . '/' . $name, 0700);
    return new Store($root . '/' . $name, $root . '/key/master.key');
}
function removeTree(string $path): void {
    if (is_dir($path) && !is_link($path)) { foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') removeTree($path . '/' . $name); rmdir($path); }
    else unlink($path);
}

$root = sys_get_temp_dir() . '/btc-account-offline-' . bin2hex(random_bytes(8));
mkdir($root, 0700); mkdir($root . '/key', 0700);
file_put_contents($root . '/key/master.key', random_bytes(32)); chmod($root . '/key/master.key', 0600);
$key = str_repeat('A', 64); $secret = str_repeat('B', 64);
$now = 1780000000000; $clock = static function () use (&$now): int { return $now; };
try {
    $data = fixtures($now); $calls = [];
    $client = new Client(transport($data, $now, $calls), $clock);
    $result = $client->collect($key, $secret);
    check(count($calls) === 5, 'one complete snapshot uses five allowed requests');
    check(count($result['balances']) === 2 && $result['balances'][0]['total'] === '0.123456789123456790', 'nonzero balances retain exact decimals');
    check(Client::addAmounts('999999999999999999999999999.99', '0.01') === '1000000000000000000000000000.00', 'large exact decimal addition');
    check($result['trades'][0]['isBuyer'] === true && $result['trades'][0]['side'] === 'BUY', 'native trade fields');
    check(!str_contains(json_encode($result), 'NEVER_EXPOSE'), 'uid and client identifiers stripped');
    foreach (array_keys(restrictions()) as $field) {
        if (in_array($field, ['ipRestrict', 'enableReading', 'enableFixReadOnly'], true)) continue;
        $data['/sapi/v1/account/apiRestrictions'] = restrictions(); $data['/sapi/v1/account/apiRestrictions'][$field] = true;
        $calls = []; fails(fn() => $client->collect($key, $secret), 'unsafe_permissions', 'reject dangerous permission ' . $field);
        check(count($calls) === 2, 'dangerous key cannot reach account data');
        unset($data['/sapi/v1/account/apiRestrictions'][$field]);
        fails(fn() => $client->verifyReadOnly($key, $secret), 'permissions_unverified', 'reject missing permission ' . $field);
    }
    $data['/sapi/v1/account/apiRestrictions'] = restrictions(); $data['/sapi/v1/account/apiRestrictions']['enableFutureUnknownTrading'] = true;
    fails(fn() => $client->verifyReadOnly($key, $secret), 'permissions_unverified', 'unknown permission denied');
    $data['/sapi/v1/account/apiRestrictions'] = restrictions(); $data['/sapi/v1/account/apiRestrictions']['enableReading'] = false;
    fails(fn() => $client->verifyReadOnly($key, $secret), 'read_permission_required', 'read permission required');
    fails(fn() => $client->verifyReadOnly("bad\r\nheader", $secret), 'invalid_credentials', 'header injection denied');
    $data = fixtures($now); $attempt = 0; $calls = [];
    $timeClient = new Client(transport($data, $now, $calls, static function ($path) use (&$attempt): ?array {
        if ($path === '/sapi/v1/account/apiRestrictions' && $attempt++ === 0) return response(['code' => -1021, 'msg' => 'NEVER_EXPOSE'], 400);
        return null;
    }), $clock);
    check($timeClient->verifyReadOnly($key, $secret)['readOnly'] && count($calls) === 4, 'clock skew resynchronizes once');
    $limited = new Client(static fn() => response(['msg' => 'NEVER_EXPOSE'], 429, ['Retry-After' => '120']), $clock);
    check(fails(fn() => $limited->verifyReadOnly($key, $secret), 'rate_limited', '429 safe error')->retryAfter === 120, 'Retry-After seconds honored');
    $region = new Client(static fn() => response(['msg' => 'NEVER_EXPOSE'], 451), $clock);
    fails(fn() => $region->verifyReadOnly($key, $secret), 'region_restricted', '451 no fallback');

    $data = fixtures($now); $calls = []; $store = newStore($root, 'main');
    $service = new Service($store, new Client(transport($data, $now, $calls), $clock), $clock);
    check($service->snapshot()['status'] === 'disconnected' && count($calls) === 0, 'snapshot does not use network');
    $connected = $service->connect($key, $secret);
    check($connected['connected'] && $connected['status'] === 'waiting' && $connected['keyHint'] === '••••AAAA', 'verified connection waits for worker');
    check(count($calls) === 2, 'connect verifies before private data fetch');
    $encrypted = file_get_contents($root . '/main/state.enc.json');
    check(!str_contains($encrypted, $key) && !str_contains($encrypted, $secret) && !str_contains($encrypted, 'credentials'), 'credentials encrypted at rest');
    check((fileperms($root . '/main/state.enc.json') & 0777) === 0600, 'encrypted state private permissions');
    $service->sync(); check(count($calls) === 2, 'initial rate gate prevents duplicate requests');
    $now += 61000; $ready = $service->sync();
    check($ready['status'] === 'ready' && count($ready['balances']) === 2, 'worker publishes complete snapshot');
    check(!str_contains(json_encode($ready), $key) && !str_contains(json_encode($ready), $secret), 'public service contract never includes full keys');
    $before = count($calls); $service->snapshot(); $service->sync(); check(count($calls) === $before, 'same-minute sync does not refetch');
    $badClient = new Client(transport($data, $now, $calls, static fn($path) => $path === '/api/v3/openOrders' ? response([], 503) : null), $clock);
    $badService = new Service($store, $badClient, $clock); $now += 61000;
    $stale = $badService->sync(); check($stale['status'] === 'stale' && $stale['sampledAt'] === $ready['sampledAt'] && $stale['balances'] === $ready['balances'], 'partial failure retains complete old snapshot as stale');
    $now += 61000; $data['/sapi/v1/account/apiRestrictions']['enableWithdrawals'] = true;
    $paused = $service->sync();
    check($paused['status'] === 'error' && $paused['readOnly'] === false && $paused['balances'] === [] && !isset($store->read()['credentials']), 'changed key permission erases data and credentials');
    $before = count($calls); $now += 61000; $service->sync(); check(count($calls) === $before, 'paused key never makes further requests');

    $data = fixtures($now); $calls = []; $inFlightStore = newStore($root, 'in-flight'); $disconnectService = null; $disconnectOnce = false;
    $hook = static function ($path) use (&$disconnectService, &$disconnectOnce): ?array {
        if ($disconnectOnce && $path === '/api/v3/account') { $disconnectOnce = false; $disconnectService->disconnect(); }
        return null;
    };
    $disconnectService = new Service($inFlightStore, new Client(transport($data, $now, $calls, $hook), $clock), $clock);
    $disconnectService->connect($key, $secret); $now += 61000; $disconnectOnce = true;
    $gone = $disconnectService->sync();
    check($gone['status'] === 'disconnected' && $gone['balances'] === [] && !isset($inFlightStore->read()['credentials']), 'disconnect generation rejects in-flight snapshot');

    $connectStore = newStore($root, 'connect-race'); $racingService = null; $calls = [];
    $racingService = new Service($connectStore, new Client(transport($data, $now, $calls, static function ($path) use (&$racingService): ?array {
        if ($path === '/sapi/v1/account/apiRestrictions') $racingService->disconnect();
        return null;
    }), $clock), $clock);
    fails(fn() => $racingService->connect($key, $secret), 'connection_changed', 'disconnect invalidates in-flight connect');
    check(!$racingService->snapshot()['connected'] && !isset($connectStore->read()['credentials']), 'late key is not saved');

    $backoffStore = newStore($root, 'backoff'); $calls = [];
    $backoffClient = new Client(static function () use (&$now, &$calls): array { $calls[] = 1; $now += 8000; return response([], 429, ['Retry-After' => '120']); }, $clock);
    $backoffService = new Service($backoffStore, $backoffClient, $clock);
    fails(fn() => $backoffService->connect($key, $secret), 'rate_limited', 'connect rate limit saved');
    check($backoffService->snapshot()['nextRetryAt'] === $now + 120000, 'retry deadline based on response time');
    $backoffService->disconnect(); fails(fn() => $backoffService->connect($key, $secret), 'retry_later', 'disconnect cannot bypass global rate limit');
    check(count($calls) === 1, 'throttled connection makes no new requests');

    $store->exclusiveOperation(function () use ($store): void { fails(fn() => $store->exclusiveOperation(static fn() => null), 'operation_busy', 'operation lock serializes collectors'); });
    $envelope = json_decode(file_get_contents($root . '/main/state.enc.json'), true);
    $envelope['tag'] = base64_encode(str_repeat('x', 16)); file_put_contents($root . '/main/state.enc.json', json_encode($envelope));
    fails(fn() => $store->read(), 'storage_unavailable', 'GCM tampering rejected');
    check(is_file($root . '/main/state.enc.json'), 'corrupt storage not silently reset');
    echo "PASS {$passed} offline assertions; zero real network calls.\n";
} finally { removeTree($root); }
