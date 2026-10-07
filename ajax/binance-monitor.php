<?php
declare(strict_types=1);

// Read-only public market snapshot. Fetches never run in web requests.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo '{"ok":false,"error":"仅支持读取行情。"}';
    exit;
}
require_once dirname(__DIR__) . '/app-lib/binance-monitor/MarketFeed.php';
$feed = new \BtcMonitor\MarketFeed(dirname(__DIR__) . '/cache/vars/binance-monitor');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
    echo json_encode($feed->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
