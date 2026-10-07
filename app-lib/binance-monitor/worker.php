<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/MarketFeed.php';

$feed = new \BtcMonitor\MarketFeed(dirname(__DIR__, 2) . '/cache/vars/binance-monitor');
if (in_array('--health', $argv, true)) {
    $snapshot = $feed->snapshot();
    exit($snapshot['ok'] && !$snapshot['stale'] ? 0 : 1);
}
$once = in_array('--once', $argv, true);
do {
    $started = microtime(true);
    try {
        $snapshot = $feed->collect();
        echo gmdate('c') . ' BTCUSDT ' . ($snapshot['ok'] && !$snapshot['stale'] ? 'updated' : 'waiting; retry scheduled') . PHP_EOL;
    } catch (\Throwable) {
        fwrite(STDERR, gmdate('c') . ' Market cache unavailable; retry scheduled.' . PHP_EOL);
    }
    if (!$once) {
        sleep(max(1, (int) ceil(\BtcMonitor\MarketFeed::INTERVAL - (microtime(true) - $started))));
    }
} while (!$once);
