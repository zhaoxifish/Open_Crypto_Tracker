<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/MarketFeed.php';
require __DIR__ . '/fixtures.php';

use BtcMonitor\MarketFeed;
use BtcMonitor\FeedRequestException;

$results = [];
$createdCacheDirectories = [];

// Only remove this process's newly created, flat fixture directories.
register_shutdown_function(static function (): void {
    global $createdCacheDirectories;
    $temporaryRoot = realpath(sys_get_temp_dir());
    foreach ($createdCacheDirectories as $directory) {
        if (is_link($directory) || realpath($directory) !== $directory
            || dirname($directory) !== $temporaryRoot
            || !preg_match('/^btc-monitor-qa-[a-z-]+-[a-f0-9]{12}$/D', basename($directory))) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name !== 'snapshot.json' && $name !== 'collect.lock'
                && !preg_match('/^\.snapshot-[a-zA-Z0-9]+$/D', $name)) {
                continue;
            }
            $file = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($file) && !is_link($file) && realpath(dirname($file)) === $directory) {
                unlink($file);
            }
        }
        if (count(scandir($directory) ?: []) === 2) {
            rmdir($directory);
        }
    }
});
function expect_result(string $name, bool $passed, mixed $detail = null): void
{
    global $results;
    $result = ['name' => $name, 'passed' => $passed];
    if ($detail !== null) $result['detail'] = $detail;
    $results[] = $result;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
function rejected(callable $operation): bool
{
    try { $operation(); return false; } catch (Throwable) { return true; }
}
function cache_directory(string $scenario): string
{
    global $createdCacheDirectories;
    if (!preg_match('/^[a-z-]+$/D', $scenario)) {
        throw new InvalidArgumentException('Invalid fixture scenario.');
    }
    $temporaryRoot = realpath(sys_get_temp_dir());
    if ($temporaryRoot === false) {
        throw new RuntimeException('Temporary directory is unavailable.');
    }
    $directory = $temporaryRoot . DIRECTORY_SEPARATOR . 'btc-monitor-qa-' . $scenario . '-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Could not create fixture directory.');
    }
    $created = realpath($directory);
    if ($created === false || dirname($created) !== $temporaryRoot) {
        throw new RuntimeException('Unexpected fixture directory.');
    }
    $createdCacheDirectories[] = $created;
    return $created;
}

$now = 1791360000000;
$ticker = MarketFeed::normalizeTicker(fixture_ticker($now), $now);
expect_result('Last price retains decimals from ticker', (float)($ticker['lastPrice'] ?? 0) === 100000.25);
expect_result('Negative 24h percentage remains negative', (float)($ticker['priceChangePercent'] ?? 0) === -1.961);
expect_result('BTC base volume and USDT quote volume remain distinct',
    abs((float)($ticker['volume'] ?? 0) - 12345.6789) < 0.00000001
    && abs((float)($ticker['quoteVolume'] ?? 0) - 987654321.99) < 0.000001);

$invalid = [
    'wrong trading pair' => ['symbol' => 'ETHUSDT'],
    'malformed price' => ['lastPrice' => 'not-a-number'],
    'infinite price' => ['lastPrice' => '1e9999'],
    'negative price' => ['lastPrice' => '-1'],
    'array price' => ['lastPrice' => ['100000']],
    'negative BTC volume' => ['volume' => '-1'],
    'negative USDT volume' => ['quoteVolume' => '-1'],
    'invalid percentage' => ['priceChangePercent' => 'NaN'],
];
foreach ($invalid as $name => $changes) {
    expect_result('Reject ' . $name, rejected(fn() => MarketFeed::normalizeTicker(array_replace(fixture_ticker($now), $changes), $now)));
}
$missing = fixture_ticker($now); unset($missing['quoteVolume']);
expect_result('Reject absent quoteVolume instead of substituting BTC volume', rejected(fn() => MarketFeed::normalizeTicker($missing, $now)));
$zero = MarketFeed::normalizeTicker(array_replace(fixture_ticker($now), ['volume' => '0', 'quoteVolume' => '0']), $now);
expect_result('Legitimate zero turnover does not invent market activity', (float)$zero['volume'] === 0.0 && (float)$zero['quoteVolume'] === 0.0);

$history = MarketFeed::normalizeHistory(fixture_history($now));
expect_result('Normalize all 288 five-minute points', count($history) === 288);

$clock = $now;
$mode = 'good';
$calls = [];
$request = function (string $url) use (&$clock, &$mode, &$calls): array {
    $calls[] = $url;
    if ($mode === 'timeout') throw new RuntimeException('Fixture request timed out');
    if (str_contains($url, 'klines')) return fixture_history($clock);
    if ($mode === 'malformed') return ['code' => -1000, 'msg' => 'Fixture API failure'];
    return fixture_ticker($clock);
};
$feed = new MarketFeed(cache_directory('snapshot'), $request, function () use (&$clock): int { return $clock; });
$good = $feed->collect();
expect_result('Collected market explicitly identifies Binance BTCUSDT spot',
    ($good['market'] ?? null) === ['exchange' => 'Binance', 'type' => 'spot', 'symbol' => 'BTCUSDT', 'baseAsset' => 'BTC', 'quoteAsset' => 'USDT']);
expect_result('Successful collection supplies one coherent ticker snapshot',
    isset($good['ticker']) && (float)$good['ticker']['lastPrice'] === 100000.25
    && (float)$good['ticker']['priceChangePercent'] === -1.961
    && abs((float)$good['ticker']['volume'] - 12345.6789) < 0.00000001
    && abs((float)$good['ticker']['quoteVolume'] - 987654321.99) < 0.000001,
    ['keys' => array_keys($good)]);
expect_result('Backend requests BTCUSDT ticker and five-minute klines',
    count(array_filter($calls, fn($url) => str_contains($url, 'symbol=BTCUSDT'))) >= 2
    && count(array_filter($calls, fn($url) => str_contains($url, 'interval=5m'))) >= 1);

$mode = 'timeout'; $clock += 60000;
$timeout = $feed->collect();
expect_result('Timeout retains previous valid ticker and sampling time',
    ($timeout['ticker'] ?? null) === ($good['ticker'] ?? null)
    && ($timeout['sampledAt'] ?? null) === ($good['sampledAt'] ?? null));
expect_result('Timeout does not report a silently healthy snapshot', !empty($timeout['stale']) || !empty($timeout['error']));

$mode = 'malformed'; $clock += 60000;
$malformed = $feed->collect();
expect_result('Malformed exchange response cannot overwrite good values',
    ($malformed['ticker'] ?? null) === ($good['ticker'] ?? null)
    && ($malformed['sampledAt'] ?? null) === ($good['sampledAt'] ?? null));

$clock += 3600000;
$expired = $feed->snapshot();
expect_result('Expired cache is explicitly stale', !empty($expired['stale']));
expect_result('Expired cache retains original price rather than fabricating zero',
    isset($expired['ticker']['lastPrice']) && (float)$expired['ticker']['lastPrice'] === 100000.25);

$empty = new MarketFeed(cache_directory('empty'), $request, function () use (&$clock): int { return $clock; });
$beforeRead = count($calls);
$cold = $empty->snapshot();
expect_result('Reading a missing cache performs no upstream request', !$cold['ok'] && $cold['ticker'] === null && $cold['stale'] && count($calls) === $beforeRead);
$firstFailure = $empty->collect();
expect_result('First failure without a cache exposes no fake ticker', empty($firstFailure['ticker']));

$historyFails = true; $historyCalls = 0; $historyClock = $now;
$historyRequest = function (string $url) use (&$historyFails, &$historyCalls, &$historyClock): array {
    if (str_contains($url, 'klines')) {
        ++$historyCalls;
        if ($historyFails) throw new RuntimeException('Fixture history timeout');
        return fixture_history($historyClock);
    }
    return fixture_ticker($historyClock);
};
$historyFeed = new MarketFeed(cache_directory('history-recovery'), $historyRequest, function () use (&$historyClock): int { return $historyClock; });
$firstHistory = $historyFeed->collect();
expect_result('First history failure preserves live ticker and labels local samples',
    $firstHistory['ok'] && $firstHistory['historySource'] === 'local_samples' && count($firstHistory['history']) === 1 && $firstHistory['historyStale']);
$historyFails = false; $historyClock += 60000;
$recoveredHistory = $historyFeed->collect();
expect_result('Next collection retries missing history and recovers all 288 points',
    $recoveredHistory['historySource'] === 'binance_5m_klines' && count($recoveredHistory['history']) === 288
    && !$recoveredHistory['historyStale'] && $historyCalls === 2);
$historyClock += 60000; $historyFeed->collect();
expect_result('Fresh five-minute history is not refetched every ticker minute', $historyCalls === 2);

$corruptDir = cache_directory('corrupt');
file_put_contents($corruptDir . '/snapshot.json', '{broken-json');
$corrupt = new MarketFeed($corruptDir, fn(string $url): array => str_contains($url, 'klines') ? fixture_history($now) : fixture_ticker($now), fn(): int => $now);
$broken = $corrupt->snapshot();
expect_result('Malformed cache is unavailable rather than an exception or fabricated value', !$broken['ok'] && $broken['ticker'] === null && $broken['stale']);
expect_result('Collection replaces corrupt cache with a valid snapshot', $corrupt->collect()['ok']);
file_put_contents($corruptDir . '/snapshot.json', json_encode(['ticker' => ['lastPrice' => 'bad'], 'sampledAt' => $now]));
$invalidCache = $corrupt->snapshot();
expect_result('Structurally invalid cached ticker is rejected', !$invalidCache['ok'] && $invalidCache['ticker'] === null);

$retryClock = $now; $rateLimited = true; $retryCalls = 0;
$retryRequest = function(string $url) use (&$retryCalls, &$retryClock, &$rateLimited): array {
    ++$retryCalls;
    if ($rateLimited) throw new FeedRequestException('Fixture 429', 180);
    return str_contains($url, 'klines') ? fixture_history($retryClock) : fixture_ticker($retryClock);
};
$retryFeed = new MarketFeed(cache_directory('cooldown'), $retryRequest, function() use (&$retryClock): int { return $retryClock; });
$retryFeed->collect(); $retryClock += 60000; $retryFeed->collect();
expect_result('Rate-limit cooldown suppresses premature network retries', $retryCalls === 1);
$rateLimited = false; $retryClock += 120001;
expect_result('Rate-limit cooldown expires and collection resumes', $retryFeed->collect()['ok'] && $retryCalls === 3);

$slowClock = $now; $slowCalls = 0; $slowFails = true;
$slowRequest = function(string $url) use (&$slowClock, &$slowCalls, &$slowFails): array {
    ++$slowCalls;
    if ($slowFails) { $slowClock += 15000; throw new FeedRequestException('Fixture delayed rate limit', 60); }
    return str_contains($url, 'klines') ? fixture_history($slowClock) : fixture_ticker($slowClock);
};
$slowFeed = new MarketFeed(cache_directory('delayed-cooldown'), $slowRequest, function() use (&$slowClock): int { return $slowClock; });
$slowFeed->collect(); $slowFails = false; $slowClock = $now + 74000;
$slowFeed->collect();
expect_result('Retry-After waits 59 full seconds after the delayed error response', $slowCalls === 1);
$slowClock = $now + 75000;
expect_result('Retry-After permits collection at 60 seconds after error receipt', $slowFeed->collect()['ok'] && $slowCalls === 3);

$staleFeed = new MarketFeed(cache_directory('stale-upstream'), fn(string $url): array => fixture_ticker($now - 180001), fn(): int => $now);
$staleUpstream = $staleFeed->collect();
expect_result('An already stale upstream ticker is not accepted as a first live sample', !$staleUpstream['ok'] && $staleUpstream['ticker'] === null);

$failed = count(array_filter($results, fn($result) => !$result['passed']));
echo json_encode(['summary' => ['passed' => count($results) - $failed, 'failed' => $failed, 'total' => count($results)]]) . "\n";
exit($failed > 0 ? 1 : 0);
