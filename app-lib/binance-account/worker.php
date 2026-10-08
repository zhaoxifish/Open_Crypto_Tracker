<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/Service.php';

$service = new \BtcAccount\Service(new \BtcAccount\Store());
if (in_array('--health', $argv, true)) {
    try {
        $snapshot = $service->snapshot();
        exit(!$snapshot['connected'] || ($snapshot['status'] === 'ready') ? 0 : 1);
    } catch (\Throwable) { exit(1); }
}
$once = in_array('--once', $argv, true);
do {
    $started = microtime(true);
    try {
        $snapshot = $service->sync();
        // The state label is application-owned; never log account identifiers or upstream responses.
        echo gmdate('c') . ' account-monitor ' . $snapshot['status'] . PHP_EOL;
    } catch (\Throwable) {
        fwrite(STDERR, gmdate('c') . ' account-monitor storage-unavailable' . PHP_EOL);
    }
    if (!$once) sleep(max(1, (int) ceil(10 - (microtime(true) - $started))));
} while (!$once);
