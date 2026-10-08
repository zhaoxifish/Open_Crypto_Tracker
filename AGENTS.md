# BTC监测器 · 项目开发记忆

## 用户的长期要求

开发前拆解完整的基础步骤，按依赖顺序实施。每完成一步立即用中文说明结果和验证情况，继续下一步；无需逐步重复征求已授权事项的许可。多步骤任务维护开发清单，跨会话按清单继续。

## 产品方向与现有边界

- 面向初学者，简体中文，名称“BTC监测器”，浅色界面，常用入口简洁，复杂功能按需展开。
- 用户主要查看币安 BTC/USDT 现货价格、涨跌幅、成交量及个人现货余额。
- 币安账户只读，不交易、不撤单、不划转、不提现。保留认证、同源校验、加密私密卷、隐私清空和无缓存策略。
- 账户余额不混入手动持仓的表单、Cookie、浏览器存储或公共行情缓存。USDT余额不等于全账户估值，最近成交不等于完整成本记录。
- 保留 LICENSE 和必要开源许可；个性化界面与上游业务逻辑尽量分离，便于后续同步。
- 不读取或输出真实 API 密钥、会话和账户数据用于界面测试；使用隔离环境与合成数据。

## 环境与开发清单

- 实际仓库：`D:\git-work\Open_Crypto_Tracker`，分支 `develop`；`D:\Open_Crypto_Tracker` 是本聊天暂存/验收目录。
- 本地入口：`https://localhost:8443/index.php#portfolio`，Docker Compose PHP 8.3。
- 开发说明：`LOCAL_DEVELOPMENT.md`；账户设计：`app-lib/binance-account/README.md`。
- 当前改版清单：`docs/development/2026-10-08-beginner-workspace.md`。
