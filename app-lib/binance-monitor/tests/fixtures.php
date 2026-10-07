<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Deliberately different base-asset and quote-asset quantities detect unit mixups.
function fixture_ticker(int $now): array
{
    return [
        'symbol' => 'BTCUSDT',
        'priceChange' => '-1999.75',
        'priceChangePercent' => '-1.961',
        'weightedAvgPrice' => '80000.0009',
        'prevClosePrice' => '102000.00',
        'lastPrice' => '100000.25',
        'lastQty' => '0.12345',
        'bidPrice' => '100000.24',
        'bidQty' => '1.25',
        'askPrice' => '100000.25',
        'askQty' => '2.50',
        'openPrice' => '102000.00',
        'highPrice' => '105000.00',
        'lowPrice' => '75000.00',
        'volume' => '12345.67890000',
        'quoteVolume' => '987654321.99000000',
        'openTime' => $now - 86400000,
        'closeTime' => $now,
        'firstId' => 100,
        'lastId' => 2099,
        'count' => 2000,
    ];
}

function fixture_history(int $now, int $count = 288): array
{
    $history = [];
    $lastOpen = intdiv($now, 300000) * 300000;
    for ($index = 0; $index < $count; ++$index) {
        $openTime = $lastOpen - ($count - 1 - $index) * 300000;
        $price = 97000 + $index * 10;
        $history[] = [
            $openTime, (string)$price, (string)($price + 20), (string)($price - 20),
            (string)($price + 5), '12.34560000', $openTime + 299999,
            '1234567.89000000', 100, '6.17280000', '617283.94500000', '0',
        ];
    }
    return $history;
}
