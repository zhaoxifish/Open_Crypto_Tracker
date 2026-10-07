# 币安 BTC/USDT 现货监控

打开 `https://localhost:8443/index.php#portfolio`，资产总览顶部即为币安行情面板。无需填 API 密钥或持仓。所有数值直接来自同一个 BTCUSDT 现货接口，不采用资产组合的 USD 换算或 CoinGecko 汇总涨跌幅。

| 显示项目 | 币安字段 | 单位与口径 |
| --- | --- | --- |
| 最新成交价 | `lastPrice` | USDT |
| 滚动 24 小时涨跌 | `priceChangePercent` | 百分比 |
| 24 小时成交量 | `volume` | BTC |
| 24 小时成交额 | `quoteVolume` | USDT |
| 24 小时最高／最低 | `highPrice`、`lowPrice` | USDT |
| 行情时点 | `closeTime` | 币安滚动统计窗口结束时间 |
| 价格走势 | `/api/v3/klines`，`5m` | 最近 288 根五分钟 K 线的收盘价；最新一根可能尚未结束 |

字段依据：[币安现货接口文档](https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md#24hr-ticker-price-change-statistics)。保持与上游项目现有币安适配器一致的 `www.binance.com` 主机；不切换到其他交易所作数据兜底，也不在接口失败时伪造价格。

## 启动与维护

在项目目录运行：

```powershell
docker compose --profile monitor up -d app market-monitor
docker compose --profile monitor ps
docker compose --profile monitor logs --tail 30 market-monitor
```

每 60 秒采集一次 ticker，每 5 分钟更新历史走势。网页每 60 秒读取本地快照，隐藏标签页暂停读取，恢复可见后立即读取。“刷新”只重读已有快照，不额外向币安发起请求。电脑和 Docker 必须保持运行；服务退出后由 Docker 自动重启。

仅停止行情监控：

```powershell
docker compose --profile monitor stop market-monitor
```

修改 PHP 采集代码后，重启长期运行的采集进程：

```powershell
docker compose --profile monitor restart market-monitor
```

## 与原功能的关系

- `market-monitor` 是独立的公开行情采集服务，不加载原应用初始化，不访问账户、不交易、不发送邮件或 Telegram。
- 原有 `cron` 保留为可选服务，负责原项目的图表、提醒、插件等。这次只启用了 `market-monitor`。两者不同；无需为新的首页监控启动原有 cron。
- 保留现有管理员、后台配置、资产持仓以及组合默认计价币。独立面板始终显示币安 BTC/USDT 现货；下方原资产表仍按用户自己的市场与换算设置显示。
- 原生后台提醒仍采用上次提醒／重置价格作为比较基准。新面板的 24 小时涨跌来自币安滚动窗口，二者不要混用。

## 故障与数据状态

快照存放在独立的 `cache/vars/binance-monitor/snapshot.json`，由文件锁和原子替换保护。`ajax/binance-monitor.php` 只读该文件，不能改变配置或触发采集。缓存沿用应用受保护的数据卷，不提交到 Git。

采集失败时保留最后一份有效行情，并明确显示数据滞后；采集或行情时点超过 3 分钟也会判为滞后。历史走势单独记录更新时间和故障状态。币安限流时尊重 `Retry-After` 等待期。没有可用数据时展示占位符，不显示虚构数字。

## 验证

后端测试使用注入的时钟与数据样本，不请求网络，不读取正式缓存：

```powershell
docker compose exec -T app php app-lib/binance-monitor/tests/run.php
```

浏览器验收检查桌面及 375/390px 手机宽度、所有指标的单位、接口失败后的旧值保留及走势滞后提示。
