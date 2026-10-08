<?php
declare(strict_types=1);

namespace BtcAccount;
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/Store.php';

/** Admin HTTP code must authenticate and enforce CSRF before calling this service. */
final class Service
{
    public const INTERVAL = 60;
    public const STALE_AFTER = 180;
    private Client $client;
    private \Closure $clock;

    public function __construct(private Store $store, ?Client $client = null, ?callable $clock = null)
    {
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn(): int => (int) floor(microtime(true) * 1000);
        $this->client = $client ?? new Client(null, $this->clock);
    }

    public function connect(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $secret): array
    {
        Client::validateCredentials($apiKey, $secret);
        return $this->store->exclusiveOperation(function () use ($apiKey, $secret): array {
            $state = $this->store->read();
            $now = ($this->clock)();
            $this->assertAllowed($state, $now);
            $generation = $state['generation'];
            $this->reserveAttempt($generation, $now);
            try {
                $permissions = $this->client->verifyReadOnly($apiKey, $secret);
            } catch (AccountException $error) {
                $this->delayAll($error->retryAfter);
                throw $error;
            } catch (\Throwable) {
                $this->delayAll(self::INTERVAL);
                throw new AccountException('connection_failed', '账户验证暂时未完成，请稍后重试。', 60, 503);
            }
            $this->store->update(function (array $current) use ($generation, $apiKey, $secret, $permissions): array {
                if ($current['generation'] !== $generation) throw new AccountException('connection_changed', '账户连接已变更，本次验证结果已丢弃。', 0, 409);
                return ['generation' => bin2hex(random_bytes(16)), 'connected' => true,
                    'credentials' => ['apiKey' => $apiKey, 'secret' => $secret], 'keyHint' => '••••' . substr($apiKey, -4),
                    'permissions' => $permissions, 'connectedAt' => ($this->clock)(), 'paused' => false,
                    'nextAllowedAt' => $current['nextAllowedAt'] ?? 0, 'lastAttemptAt' => null, 'data' => null, 'error' => null, 'errorCode' => null];
            });
            return $this->snapshot();
        });
    }

    public function disconnect(): array
    {
        // No operation lock: this immediately invalidates an in-flight connect/sync.
        // Keep the global backoff, so disconnect/reconnect cannot bypass a Binance ban.
        $this->store->update(static fn(array $current): array => ['generation' => bin2hex(random_bytes(16)),
            'connected' => false, 'nextAllowedAt' => $current['nextAllowedAt'] ?? 0]);
        return $this->snapshot();
    }

    /** CLI only in production. A web refresh reads snapshot(), never sends signed requests. */
    public function sync(): array
    {
        try {
            return $this->store->exclusiveOperation(function (): array {
                $state = $this->store->read();
                $now = ($this->clock)();
                if (!$state['connected'] || !empty($state['paused']) || ($state['nextAllowedAt'] ?? 0) > $now) return $this->snapshot();
                $generation = $state['generation'];
                $credentials = $state['credentials'] ?? null;
                if (!is_array($credentials) || !is_string($credentials['apiKey'] ?? null) || !is_string($credentials['secret'] ?? null)) {
                    throw new AccountException('storage_unavailable', '账户连接信息无法读取，请重新接入账户。', 60, 503);
                }
                $this->reserveAttempt($generation, $now);
                try {
                    $data = $this->client->collect($credentials['apiKey'], $credentials['secret']);
                    $this->store->update(function (array $current) use ($generation, $data): array {
                        if ($current['generation'] !== $generation || !$current['connected']) return $current;
                        $current['data'] = $data;
                        $current['permissions'] = $data['permissions'];
                        $current['lastAttemptAt'] = ($this->clock)();
                        $current['error'] = $current['errorCode'] = null;
                        return $current;
                    });
                } catch (AccountException $error) {
                    $this->recordFailure($generation, $error);
                } catch (\Throwable) {
                    $this->recordFailure($generation, new AccountException('sync_failed', '账户同步暂时失败，保留上次完整同步结果。', 60, 503));
                }
                return $this->snapshot();
            });
        } catch (AccountException $error) {
            if ($error->errorCode === 'operation_busy') return $this->snapshot();
            throw $error;
        }
    }

    /** Authenticated admin output only. Credentials and raw Binance responses never leave storage. */
    public function snapshot(): array
    {
        $state = $this->store->read();
        $connected = $state['connected'];
        $data = $connected && empty($state['paused']) && is_array($state['data'] ?? null) ? $state['data'] : null;
        $now = ($this->clock)();
        $sampled = isset($data['sampledAt']) && is_int($data['sampledAt']) ? $data['sampledAt'] : null;
        $stale = $connected && ($sampled === null || $sampled > $now + 60000 || $now - $sampled > self::STALE_AFTER * 1000 || !empty($state['error']));
        $status = !$connected ? 'disconnected' : (!empty($state['paused']) ? 'error' : ($data === null ? (!empty($state['error']) ? 'error' : 'waiting') : ($stale ? 'stale' : 'ready')));
        $error = $connected ? ($state['error'] ?? null) : null;
        $errorCode = $connected ? ($state['errorCode'] ?? null) : null;
        if ($connected && $data !== null && $stale && $error === null) {
            $error = '账户数据尚未更新，当前显示上次完整同步结果。'; $errorCode = 'stale_data';
        }
        return ['connected' => $connected, 'status' => $status,
            'readOnly' => $connected ? (empty($state['paused']) && ($state['permissions']['readOnly'] ?? false) === true) : null,
            'keyHint' => $connected ? ($state['keyHint'] ?? null) : null,
            'permissions' => $connected ? ($state['permissions'] ?? null) : null,
            'sampledAt' => $sampled, 'lastAttemptAt' => $connected ? ($state['lastAttemptAt'] ?? null) : null,
            'nextRetryAt' => ($state['nextAllowedAt'] ?? 0) > $now ? $state['nextAllowedAt'] : null,
            'stale' => $stale, 'error' => $error, 'errorCode' => $errorCode,
            'balances' => $data['balances'] ?? [], 'openOrders' => $data['openOrders'] ?? [], 'trades' => $data['trades'] ?? [],
            'scope' => ['market' => 'BTCUSDT', 'accountType' => 'SPOT', 'tradesLimit' => 100],
            'refreshIntervalSeconds' => self::INTERVAL];
    }

    private function assertAllowed(array $state, int $now): void
    {
        if (($state['nextAllowedAt'] ?? 0) > $now) {
            throw new AccountException('retry_later', '请求较频繁，或币安要求暂缓请求，请等待后重试。', (int) ceil(($state['nextAllowedAt'] - $now) / 1000), 429);
        }
    }

    private function reserveAttempt(string $generation, int $now): void
    {
        $this->store->update(static function (array $current) use ($generation, $now): array {
            if ($current['generation'] !== $generation) throw new AccountException('connection_changed', '账户连接已变更，请重新操作。', 0, 409);
            $current['nextAllowedAt'] = max($current['nextAllowedAt'] ?? 0, $now + self::INTERVAL * 1000);
            return $current;
        });
    }

    private function delayAll(int $seconds): void
    {
        $deadline = ($this->clock)() + max(self::INTERVAL, $seconds) * 1000;
        $this->store->update(static function (array $current) use ($deadline): array {
            $current['nextAllowedAt'] = max($current['nextAllowedAt'] ?? 0, $deadline);
            return $current;
        });
    }

    private function recordFailure(string $generation, AccountException $error): void
    {
        $now = ($this->clock)();
        $this->store->update(static function (array $current) use ($generation, $error, $now): array {
            // IP rate limits remain global even if disconnected during the request.
            $current['nextAllowedAt'] = max($current['nextAllowedAt'] ?? 0, $now + max(self::INTERVAL, $error->retryAfter) * 1000);
            if ($current['generation'] !== $generation || !$current['connected']) return $current;
            $current['lastAttemptAt'] = $now;
            $current['error'] = $error->getMessage();
            $current['errorCode'] = $error->errorCode;
            if (in_array($error->errorCode, ['unsafe_permissions', 'permissions_unverified', 'read_permission_required', 'credentials_rejected'], true)) {
                // Stop using a revoked/non-read-only key; hide and erase its cached financial data.
                $current['paused'] = true;
                $current['data'] = null;
                unset($current['credentials']);
                $current['permissions'] = null;
            }
            return $current;
        });
    }
}
