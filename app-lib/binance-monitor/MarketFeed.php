<?php
declare(strict_types=1);

namespace BtcMonitor;

/** Public Binance spot data only. This collector never loads account configuration. */
final class FeedRequestException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter = 60)
    {
        parent::__construct($message);
    }
}

final class MarketFeed
{
    public const INTERVAL = 60;
    public const STALE_AFTER = 180;
    private const TICKER_URL = 'https://www.binance.com/api/v3/ticker/24hr?symbol=BTCUSDT';
    private const HISTORY_URL = 'https://www.binance.com/api/v3/klines?symbol=BTCUSDT&interval=5m&limit=288';
    private \Closure $request;
    private \Closure $clock;

    public function __construct(private string $cacheDirectory, ?callable $request = null, ?callable $clock = null)
    {
        $this->request = $request !== null ? \Closure::fromCallable($request) : self::fetchJson(...);
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn(): int => (int) floor(microtime(true) * 1000);
    }

    public static function normalizeTicker(array $data, int $now): array
    {
        if (($data['symbol'] ?? null) !== 'BTCUSDT') {
            throw new \UnexpectedValueException('Unexpected market.');
        }
        $ticker = [];
        foreach (['lastPrice', 'priceChangePercent', 'volume', 'quoteVolume', 'highPrice', 'lowPrice'] as $field) {
            $ticker[$field] = self::decimal($data[$field] ?? null, $field === 'priceChangePercent');
        }
        if ((float) $ticker['lastPrice'] <= 0 || (float) $ticker['lowPrice'] <= 0
            || (float) $ticker['highPrice'] < (float) $ticker['lowPrice']
            || (float) $ticker['lastPrice'] < (float) $ticker['lowPrice']
            || (float) $ticker['lastPrice'] > (float) $ticker['highPrice']
            || (float) $ticker['priceChangePercent'] < -100) {
            throw new \UnexpectedValueException('Invalid market values.');
        }
        foreach (['openTime', 'closeTime'] as $field) {
            $value = $data[$field] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value != (int) $value || (int) $value <= 0) {
                throw new \UnexpectedValueException('Invalid market timestamp.');
            }
            $ticker[$field] = (int) $value;
        }
        if ($ticker['openTime'] >= $ticker['closeTime'] || $ticker['closeTime'] > $now + 60000) {
            throw new \UnexpectedValueException('Invalid market time window.');
        }
        return $ticker;
    }

    public static function normalizeHistory(array $data): array
    {
        if (!array_is_list($data) || count($data) < 2 || count($data) > 1000) {
            throw new \UnexpectedValueException('Invalid price history.');
        }
        $history = [];
        $lastTime = 0;
        foreach ($data as $row) {
            if (!is_array($row) || count($row) < 7 || !is_numeric($row[0]) || (int) $row[0] <= $lastTime) {
                throw new \UnexpectedValueException('Invalid history order.');
            }
            $price = self::decimal($row[4]);
            $volume = self::decimal($row[5]);
            if ((float) $price <= 0) {
                throw new \UnexpectedValueException('Invalid history price.');
            }
            $lastTime = (int) $row[0];
            $history[] = ['time' => $lastTime, 'price' => $price, 'volume' => $volume];
        }
        return array_slice($history, -288);
    }

    /** Called only by the CLI collector, never by the HTTP endpoint. */
    public function collect(): array
    {
        if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0770, true) && !is_dir($this->cacheDirectory)) {
            throw new \RuntimeException('Cannot create market cache.');
        }
        $lock = fopen($this->cacheDirectory . '/collect.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open collector lock.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $this->snapshot();
        }
        try {
            $state = $this->readState();
            $now = ($this->clock)();
            if (($state['retryAt'] ?? 0) > $now) {
                return $this->snapshot();
            }
            try {
                $ticker = self::normalizeTicker(($this->request)(self::TICKER_URL), $now);
                if ($now - $ticker['closeTime'] > self::STALE_AFTER * 1000) {
                    throw new \UnexpectedValueException('Upstream ticker is stale.');
                }
                $state['ticker'] = $ticker;
                $state['sampledAt'] = ($this->clock)();
                $state['error'] = null;
                $state['retryAt'] = 0;
                if (empty($state['history']) || ($state['historySource'] ?? null) !== 'binance_5m_klines' || $now - ($state['historyUpdatedAt'] ?? 0) >= 300000) {
                    try {
                        $history = self::normalizeHistory(($this->request)(self::HISTORY_URL));
                        if (end($history)['time'] > $now + 60000 || end($history)['time'] < $now - 900000) {
                            throw new \UnexpectedValueException('History is not current.');
                        }
                        $state['history'] = $history;
                        $state['historyUpdatedAt'] = ($this->clock)();
                        $state['historySource'] = 'binance_5m_klines';
                        $state['historyError'] = null;
                    } catch (\Throwable $error) {
                        $state['historyError'] = '价格走势暂时无法更新。';
                        if ($error instanceof FeedRequestException) {
                            $state['retryAt'] = ($this->clock)() + $error->retryAfter * 1000;
                        }
                    }
                }
                if (($state['historySource'] ?? null) !== 'binance_5m_klines') {
                    $history = $state['history'] ?? [];
                    $history[] = ['time' => $state['sampledAt'], 'price' => $ticker['lastPrice'], 'volume' => null];
                    $state['history'] = array_values(array_filter(array_slice($history, -1440), static fn(array $point): bool => $point['time'] >= $now - 86400000));
                    $state['historySource'] = 'local_samples';
                    $state['historyUpdatedAt'] = $state['sampledAt'];
                }
            } catch (\Throwable $error) {
                $state['error'] = !empty($state['ticker']) ? '币安行情暂时无法更新，当前显示上次有效数据。' : '暂时无法获取币安行情，采集服务会自动重试。';
                $state['retryAt'] = ($this->clock)() + ($error instanceof FeedRequestException ? $error->retryAfter : self::INTERVAL) * 1000;
            }
            $state['lastAttemptAt'] = ($this->clock)();
            $this->writeState($state);
            return $this->snapshot();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Only public market fields are exposed; no application or account data. */
    public function snapshot(): array
    {
        $state = $this->readState();
        $now = ($this->clock)();
        $ticker = null;
        try {
            if (isset($state['ticker'])) {
                $ticker = self::normalizeTicker(['symbol' => 'BTCUSDT'] + $state['ticker'], $now);
            }
        } catch (\Throwable) {
            $state = [];
        }
        $sampledAt = isset($state['sampledAt']) ? (int) $state['sampledAt'] : null;
        $stale = $ticker === null || !$sampledAt || $sampledAt > $now + 60000
            || $now - $sampledAt > self::STALE_AFTER * 1000
            || $now - $ticker['closeTime'] > self::STALE_AFTER * 1000 || !empty($state['error']);
        return [
            'ok' => $ticker !== null,
            'market' => ['exchange' => 'Binance', 'type' => 'spot', 'symbol' => 'BTCUSDT', 'baseAsset' => 'BTC', 'quoteAsset' => 'USDT'],
            'ticker' => $ticker,
            'sampledAt' => $sampledAt,
            'lastAttemptAt' => $state['lastAttemptAt'] ?? null,
            'stale' => $stale,
            'refreshIntervalSeconds' => self::INTERVAL,
            'history' => $ticker !== null ? ($state['history'] ?? []) : [],
            'historySource' => $state['historySource'] ?? 'local_samples',
            'historyUpdatedAt' => $state['historyUpdatedAt'] ?? null,
            'historyStale' => !empty($state['historyError']) || $now - ($state['historyUpdatedAt'] ?? 0) > 900000,
            'error' => $state['error'] ?? ($stale ? ($ticker !== null ? '采集数据已过期，当前显示上次有效数据。' : '正在等待首次行情采集。') : null),
        ];
    }

    private static function decimal(mixed $value, bool $negative = false): string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value) || !is_finite((float) $value)
            || (!$negative && (float) $value < 0)) {
            throw new \UnexpectedValueException('Invalid numeric field.');
        }
        return (string) $value;
    }

    private function readState(): array
    {
        $file = $this->cacheDirectory . '/snapshot.json';
        clearstatcache(true, $file);
        if (!is_file($file) || filesize($file) > 2097152) {
            return [];
        }
        $raw = file_get_contents($file);
        $state = json_decode($raw === false ? '' : $raw, true);
        return is_array($state) ? $state : [];
    }

    private function writeState(array $state): void
    {
        $temp = tempnam($this->cacheDirectory, '.snapshot-');
        if ($temp === false) {
            throw new \RuntimeException('Cannot stage market data.');
        }
        try {
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !chmod($temp, 0660) || !rename($temp, $this->cacheDirectory . '/snapshot.json')) {
                throw new \RuntimeException('Cannot save market data.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private static function fetchJson(string $url): array
    {
        $body = '';
        $retryAfter = self::INTERVAL;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'BTC-Monitor/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2097152) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$retryAfter): int {
                if (preg_match('/^Retry-After:\s*(\d+)/i', $header, $match)) {
                    $retryAfter = max(self::INTERVAL, (int) $match[1]);
                }
                return strlen($header);
            },
        ]);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($result === false || $status !== 200) {
            throw new FeedRequestException('Public market endpoint unavailable.', $retryAfter);
        }
        $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || isset($data['code'])) {
            throw new FeedRequestException('Invalid public market response.');
        }
        return $data;
    }
}
