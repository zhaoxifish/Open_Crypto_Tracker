<?php
declare(strict_types=1);

namespace BtcAccount;

/** Only these application-owned messages may cross the HTTP/logging boundary. */
final class AccountException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $retryAfter = 60,
        public readonly int $httpStatus = 400,
    ) { parent::__construct($message); }
}

/** Fixed-host, GET-only HMAC client. No application bootstrap, proxy, or trading API. */
final class Client
{
    private const HOST = 'https://api.binance.com';
    private const PATHS = ['/api/v3/time', '/sapi/v1/account/apiRestrictions', '/api/v3/account', '/api/v3/openOrders', '/api/v3/myTrades'];
    private const FORBIDDEN = ['enableWithdrawals', 'enableInternalTransfer', 'enableMargin', 'enableFutures',
        'permitsUniversalTransfer', 'enableVanillaOptions', 'enableFixApiTrade', 'enableSpotAndMarginTrading', 'enablePortfolioMarginTrading'];
    private \Closure $transport;
    private \Closure $clock;
    private int $offset = 0;

    public function __construct(?callable $transport = null, ?callable $clock = null)
    {
        $this->transport = $transport !== null ? \Closure::fromCallable($transport) : self::transport(...);
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn(): int => (int) floor(microtime(true) * 1000);
    }

    public static function validateCredentials(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $secret): void
    {
        if (!preg_match('/^[A-Za-z0-9]{32,256}$/D', $apiKey) || !preg_match('/^[A-Za-z0-9]{32,256}$/D', $secret)) {
            throw new AccountException('invalid_credentials', '请填写币安系统生成的 HMAC API Key 和 Secret Key。');
        }
    }

    public function verifyReadOnly(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $secret): array
    {
        self::validateCredentials($apiKey, $secret);
        $this->synchronizeTime();
        $data = $this->signed('/sapi/v1/account/apiRestrictions', [], $apiKey, $secret);
        // Missing permission fields are not proof of a read-only key: fail closed.
        if (($data['enableReading'] ?? null) !== true) {
            throw new AccountException('read_permission_required', '此密钥未开启读取权限，请在币安 API 管理中开启“启用读取”。');
        }
        foreach (self::FORBIDDEN as $field) {
            if (!array_key_exists($field, $data) || !is_bool($data[$field])) {
                throw new AccountException('permissions_unverified', '无法完整确认密钥权限，未接入账户。请检查币安权限设置后重试。');
            }
            if ($data[$field]) {
                throw new AccountException('unsafe_permissions', '此密钥具有交易、提现或划转等非只读权限。请关闭这些权限，或新建仅开启读取权限的密钥。');
            }
        }
        foreach ($data as $field => $value) {
            if (preg_match('/^(?:enable|permits)/', (string) $field)
                && !in_array($field, ['enableReading', 'enableFixReadOnly'], true)
                && !in_array($field, self::FORBIDDEN, true) && $value !== false) {
                throw new AccountException('permissions_unverified', '密钥包含尚未识别的权限，未接入账户。请使用仅开启读取权限的密钥。');
            }
        }
        if (!isset($data['ipRestrict']) || !is_bool($data['ipRestrict'])) {
            throw new AccountException('permissions_unverified', '无法完整确认密钥权限，未接入账户。');
        }
        return ['readOnly' => true, 'ipRestricted' => $data['ipRestrict'], 'checkedAt' => ($this->clock)()];
    }

    public function collect(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $secret): array
    {
        // Re-check before every collection; account.canTrade is an account capability,
        // not this API key's permission, and is deliberately not used here.
        $permissions = $this->verifyReadOnly($apiKey, $secret);
        $account = $this->signed('/api/v3/account', ['omitZeroBalances' => 'true'], $apiKey, $secret);
        $orders = $this->signed('/api/v3/openOrders', ['symbol' => 'BTCUSDT'], $apiKey, $secret);
        $trades = $this->signed('/api/v3/myTrades', ['symbol' => 'BTCUSDT', 'limit' => 100], $apiKey, $secret);
        return ['permissions' => $permissions, 'balances' => self::balances($account),
            'openOrders' => self::orders($orders), 'trades' => self::trades($trades), 'sampledAt' => ($this->clock)()];
    }

    private function synchronizeTime(): void
    {
        $started = ($this->clock)();
        $data = $this->request('/api/v3/time', [], []);
        $ended = ($this->clock)();
        $server = self::integer($data['serverTime'] ?? null);
        if ($server <= 0) self::invalidData();
        $this->offset = $server - (int) floor(($started + $ended) / 2);
    }

    private function signed(string $path, array $params, #[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $secret): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $signed = $params + ['recvWindow' => 5000, 'timestamp' => ($this->clock)() + $this->offset];
            $query = http_build_query($signed, '', '&', PHP_QUERY_RFC3986);
            $signed['signature'] = hash_hmac('sha256', $query, $secret);
            try {
                return $this->request($path, $signed, ['X-MBX-APIKEY: ' . $apiKey]);
            } catch (AccountException $error) {
                if ($error->errorCode !== 'clock_skew' || $attempt !== 0) throw $error;
                $this->synchronizeTime();
            }
        }
        throw new AccountException('clock_skew', '本机时间与币安服务器不同步，请检查系统时间后重试。');
    }

    private function request(string $path, #[\SensitiveParameter] array $params, #[\SensitiveParameter] array $headers): array
    {
        if (!in_array($path, self::PATHS, true)) throw new \LogicException('Account endpoint is not permitted.');
        $expected = match ($path) {
            '/api/v3/time' => [],
            '/sapi/v1/account/apiRestrictions' => ['recvWindow', 'timestamp', 'signature'],
            '/api/v3/account' => ['omitZeroBalances', 'recvWindow', 'timestamp', 'signature'],
            '/api/v3/openOrders' => ['symbol', 'recvWindow', 'timestamp', 'signature'],
            '/api/v3/myTrades' => ['symbol', 'limit', 'recvWindow', 'timestamp', 'signature'],
        };
        if (array_keys($params) !== $expected || (isset($params['symbol']) && $params['symbol'] !== 'BTCUSDT')
            || (isset($params['limit']) && $params['limit'] !== 100)) throw new \LogicException('Account request is not permitted.');
        $url = self::HOST . $path . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
        try {
            $response = ($this->transport)('GET', $url, array_merge(['Accept: application/json'], $headers));
        } catch (\Throwable) {
            throw new AccountException('network_unavailable', '暂时无法连接币安官方账户接口，将自动重试。', 60, 503);
        }
        $status = $response['status'] ?? 0;
        $retry = self::retryAfter($response['headers'] ?? [], ($this->clock)());
        if ($status === 429 || $status === 418) {
            throw new AccountException('rate_limited', '币安接口正在限流，已暂停请求并按服务器要求等待。', max($status === 418 ? 120 : 60, $retry), 429);
        }
        if ($status === 451) throw new AccountException('region_restricted', '币安官方账户接口限制了当前网络或服务地区的访问，暂时无法接入。账户密钥不会发送到其他域名。', 300, 503);
        if ($status >= 500 || $status === 0) throw new AccountException('upstream_unavailable', '币安账户接口暂时不可用，将自动重试。', max(60, $retry), 503);
        try {
            $body = $response['body'] ?? '';
            if (!is_string($body) || strlen($body) > 4194304) self::invalidData();
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\Throwable) { self::invalidData(); }
        if (!is_array($data)) self::invalidData();
        $code = $data['code'] ?? null;
        if ($code === -1021) throw new AccountException('clock_skew', '本机时间与币安服务器不同步，请检查系统时间后重试。');
        if (in_array($code, [-2014, -2015, -1022], true) || $status === 401) {
            throw new AccountException('credentials_rejected', '币安未接受此密钥，请检查密钥、读取权限和 IP 白名单。', 300, 400);
        }
        if ($status !== 200 || isset($data['code'])) throw new AccountException('request_rejected', '币安未接受账户读取请求，请检查权限与网络后重试。', 60, 503);
        return $data;
    }

    private static function retryAfter(array $headers, int $now): int
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'retry-after' || !is_scalar($value)) continue;
            $value = trim((string) $value);
            if (preg_match('/^\d{1,10}$/D', $value)) return max(60, (int) $value);
            $date = strtotime($value);
            if ($date !== false) return max(60, (int) ceil($date - $now / 1000));
        }
        return 60;
    }

    private static function transport(string $method, #[\SensitiveParameter] string $url, #[\SensitiveParameter] array $headers): array
    {
        $parts = parse_url($url);
        if ($method !== 'GET' || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'api.binance.com'
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', self::PATHS, true)) {
            throw new \LogicException('Account transport is not permitted.');
        }
        $body = ''; $responseHeaders = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
            CURLOPT_USERAGENT => 'BTC-Monitor-Account/1.0', CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 4194304) return 0;
                $body .= $chunk; return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                if (preg_match('/^Retry-After:\s*(.*?)\s*$/i', $line, $match)) $responseHeaders['retry-after'] = $match[1];
                return strlen($line);
            },
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($result === false) throw new \RuntimeException('Account transport failed.');
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
    }

    private static function invalidData(): never { throw new AccountException('invalid_response', '币安账户数据暂时无法校验，保留上次完整同步结果。', 60, 503); }
    private static function decimal(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\d{1,40}(?:\.\d{1,30})?$/D', $value)) self::invalidData();
        return $value;
    }
    private static function integer(mixed $value): int
    {
        if (!is_int($value) || $value < 0) self::invalidData();
        return $value;
    }
    private static function id(mixed $value): string
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^\d{1,30}$/D', (string) $value)) self::invalidData();
        return (string) $value;
    }
    private static function asset(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[\p{L}\p{N}._-]{1,40}$/uD', $value)) self::invalidData();
        return $value;
    }
    /** Decimal addition without floats or an optional BCMath extension. */
    public static function addAmounts(string $left, string $right): string
    {
        self::decimal($left); self::decimal($right);
        [$li, $lf] = array_pad(explode('.', $left), 2, '');
        [$ri, $rf] = array_pad(explode('.', $right), 2, '');
        $scale = max(strlen($lf), strlen($rf));
        $a = $li . str_pad($lf, $scale, '0'); $b = $ri . str_pad($rf, $scale, '0');
        $length = max(strlen($a), strlen($b));
        $a = str_pad($a, $length, '0', STR_PAD_LEFT); $b = str_pad($b, $length, '0', STR_PAD_LEFT);
        $out = ''; $carry = 0;
        for ($i = $length - 1; $i >= 0; $i--) { $sum = (int) $a[$i] + (int) $b[$i] + $carry; $out = ($sum % 10) . $out; $carry = intdiv($sum, 10); }
        if ($carry) $out = $carry . $out;
        $out = str_pad($out, $scale + 1, '0', STR_PAD_LEFT);
        if ($scale) $out = substr($out, 0, -$scale) . '.' . substr($out, -$scale);
        $out = ltrim($out, '0'); if ($out === '' || $out[0] === '.') $out = '0' . $out;
        return $out;
    }
    private static function balances(array $data): array
    {
        if (($data['accountType'] ?? '') !== 'SPOT' || !isset($data['balances']) || !is_array($data['balances'])
            || !array_is_list($data['balances']) || count($data['balances']) > 4000) self::invalidData();
        $result = []; $seen = [];
        foreach ($data['balances'] as $row) {
            if (!is_array($row)) self::invalidData();
            $asset = self::asset($row['asset'] ?? null); $free = self::decimal($row['free'] ?? null); $locked = self::decimal($row['locked'] ?? null);
            if (isset($seen[$asset])) self::invalidData(); $seen[$asset] = true;
            $total = self::addAmounts($free, $locked);
            if (preg_match('/^0+(?:\.0+)?$/D', $total)) continue;
            $result[] = compact('asset', 'free', 'locked', 'total');
        }
        usort($result, static fn(array $a, array $b): int => strcmp($a['asset'], $b['asset']));
        return $result;
    }
    private static function orders(array $rows): array
    {
        if (!array_is_list($rows) || count($rows) > 2000) self::invalidData();
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['symbol'] ?? null) !== 'BTCUSDT'
                || !in_array($row['side'] ?? null, ['BUY', 'SELL'], true)
                || !in_array($row['type'] ?? null, ['LIMIT', 'MARKET', 'STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'], true)
                || !in_array($row['status'] ?? null, ['NEW', 'PARTIALLY_FILLED', 'PENDING_NEW'], true)) self::invalidData();
            $result[] = ['id' => self::id($row['orderId'] ?? null), 'symbol' => 'BTCUSDT', 'side' => $row['side'],
                'type' => $row['type'], 'status' => $row['status'], 'price' => self::decimal($row['price'] ?? null),
                'origQty' => self::decimal($row['origQty'] ?? null), 'executedQty' => self::decimal($row['executedQty'] ?? null), 'time' => self::integer($row['time'] ?? null)];
        }
        usort($result, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);
        return $result;
    }
    private static function trades(array $rows): array
    {
        if (!array_is_list($rows) || count($rows) > 100) self::invalidData();
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['symbol'] ?? null) !== 'BTCUSDT' || !is_bool($row['isBuyer'] ?? null) || !is_bool($row['isMaker'] ?? null)) self::invalidData();
            $result[] = ['id' => self::id($row['id'] ?? null), 'orderId' => self::id($row['orderId'] ?? null), 'symbol' => 'BTCUSDT',
                'side' => $row['isBuyer'] ? 'BUY' : 'SELL', 'isBuyer' => $row['isBuyer'], 'price' => self::decimal($row['price'] ?? null), 'qty' => self::decimal($row['qty'] ?? null),
                'quoteQty' => self::decimal($row['quoteQty'] ?? null), 'commission' => self::decimal($row['commission'] ?? null),
                'commissionAsset' => self::asset($row['commissionAsset'] ?? null), 'time' => self::integer($row['time'] ?? null), 'isMaker' => $row['isMaker']];
        }
        usort($result, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);
        return $result;
    }
}
