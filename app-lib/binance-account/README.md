# 币安账户只读接入

入口：[本机币安账户页面](https://localhost:8443/binance-account.php)。先登录现有管理后台，再点击左侧“行情与资产 → 币安账户”。该页不加载原来的表单保存脚本，账户数据仅对管理员开放。

## 首次连接

1. 在币安 API 管理中创建“系统生成”的 HMAC 密钥，仅保留读取权限。关闭交易、提现、划转、合约等非只读权限。
2. 在本机账户页面填写 API Key 与 Secret Key，点击“验证并连接”。不要把凭据发到聊天、放进网址或提交到 Git。表单提交后立即清空输入框。
3. 只读权限验证成功后保存连接，后台每 60 秒同步一次。现有管理员启用严格两步验证时，连接与断开还需要验证器动态码。

所有现货非零余额都会显示，包括可用、冻结和合计数量；挂单与最近100笔成交仅覆盖 BTC/USDT。数量按原币种显示，不折算全账户总资产，不把最近100笔成交当作完整成本或盈亏记录。合约、杠杆、理财、资金账户不在本次范围内。

“断开连接”会清除本机保存的凭据和账户快照。若还需撤销该密钥，请在币安 API 管理中操作。

## 服务与故障

```powershell
docker compose --profile monitor up -d app market-monitor account-monitor
docker compose --profile monitor ps
docker compose --profile monitor logs --tail 20 account-monitor
```

`account-monitor` 每10秒检查待办，持久化的60秒间隔和限流期限控制真实网络请求。未配置密钥时健康待命，不请求账户接口。修改账户采集端 PHP 后执行 `docker compose --profile monitor restart account-monitor`。

- 新连接先验证权限，最长约一分钟后出现首次数据。网页“刷新显示”只读取本机快照，不额外调用币安。
- 临时故障保留上次完整结果并提示滞后；不把部分成功的余额/订单/成交混成一次完整同步。
- 每次采集前重新检查密钥权限。权限被扩大、撤销或无法确认时暂停同步，清除凭据及旧账户快照，等待重新连接。
- 限流时遵守 `Retry-After`，断开再连接也不能绕过等待。
- HTTP 451 表示官方账户接口限制当前网络或服务地区。程序明确提示，不切换域名、代理或其他服务绕过限制。公开行情接口可访问并不保证私人账户接口也可访问。

官方依据：[API权限](https://developers.binance.com/en/docs/catalog/core-trading-wallet/api/rest-api/account)、[现货账户接口](https://developers.binance.com/en/docs/catalog/core-trading-spot-trading/api/rest-api/account)、[签名与权限](https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md#request-security)。

## 数据与权限边界

上游客户端固定使用 `https://api.binance.com`，仅允许 GET：服务器时间、API权限、现货余额、BTCUSDT挂单和成交。不开启交易接口，不接受自定义网址，不跟随重定向，不使用环境代理，不调用旧应用的行情缓存或通知代码。请求签名使用 HMAC，自动校准服务器时间。

账户及密钥保存于站点目录之外：

- `account-data` 卷：`/var/lib/btc-monitor-account`，目录0700、文件0600。
- `account-key` 卷：`/var/lib/btc-monitor-account-key/master.key`，独立的32字节主密钥，对应用与采集器只读。
- 凭据和快照使用 AES-256-GCM 加密、完整写入检查、fsync、原子替换和文件锁。断开连接通过代次标识拒绝在途请求回写旧数据。
- 不向浏览器回传完整密钥、签名、UID或原始上游响应；只显示密钥最后四位。错误及日志不包含凭据。
- 浏览器不持久化账户数据或凭据；离开页面和登录失效时清空显示。接口有管理员认证、精确HTTPS来源检查、CSRF和严格模式两步验证，响应禁止缓存。

普通 `cache` 备份不包含账户卷。恢复加密账户数据需要同时保留 `account-data` 和对应 `account-key`，应分开妥善备份；丢失主密钥不能解密旧数据。初始化程序遇到已有数据但缺失主密钥时会停止，不自动重置。

默认仅接受 `https://localhost:8443`、`https://127.0.0.1:8443`。其他部署由管理员设置 `BTC_ACCOUNT_ORIGINS`（逗号分隔的精确HTTPS来源），并配置可信证书。

## 开发接口

`new BtcAccount\Service(new BtcAccount\Store())`：

- `connect($apiKey, $secret)`：验证后保存，失败不替换原连接。
- `disconnect()`：清除本机连接和快照，保留全局限流期限。
- `snapshot()`：只读，无网络。
- `sync()`：仅供命令行采集器调用。

HTTP层为 `ajax/binance-account.php`。GET返回 `{ok,csrfToken,requiresOtp,data}`；POST JSON仅接受 `connect`/`disconnect`，必须发送 `X-CSRF-Token`。凭据只在通过认证与CSRF后从JSON正文读取。`AccountException`仅允许公开应用自有的 `errorCode`、中文消息、`retryAfter` 和 `httpStatus`；不得序列化异常、堆栈或原请求。

快照字段：`connected,status,readOnly,keyHint,permissions,sampledAt,lastAttemptAt,nextRetryAt,stale,error,errorCode,balances,openOrders,trades,scope,refreshIntervalSeconds`。时间为毫秒；金额为十进制字符串，合计使用精确字符串加法。状态为 disconnected、waiting、ready、stale、error。

## 验证

以下测试使用合成凭据、注入时钟和假响应，不访问真实币安账户：

```powershell
docker compose exec -T app php app-lib/binance-account/tests/run.php
docker compose exec -T app php app-lib/binance-account/tests/http-auth.php
```

另在独立容器中验证HTTP认证/CSRF/2FA、日志、断开清除、浏览器状态及桌面/手机布局。没有真实只读密钥时，不能将这些测试称为真实账户联调通过。
