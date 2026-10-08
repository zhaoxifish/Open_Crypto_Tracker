<?php
/* Private account view: the entry point authenticates the administrator before inclusion. */
if (!defined('BTC_ACCOUNT_AUTHENTICATED') || BTC_ACCOUNT_AUTHENTICATED !== true) {
    http_response_code(404);
    exit;
}
$accountUiRoot = dirname(__DIR__, 5);
$accountCssVersion = filemtime($accountUiRoot . '/app-lib/ui/binance-account.css');
$accountJsVersion = filemtime($accountUiRoot . '/app-lib/ui/binance-account.js');
?>
<!doctype html>
<html lang="zh-CN" class="btc-account">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="referrer" content="no-referrer">
  <title>币安账户 · BTC监测器</title>
  <link rel="icon" href="app-lib/ui/mark.svg">
  <link rel="stylesheet" href="app-lib/ui/binance-account.css?v=<?=$accountCssVersion?>">
  <script src="app-lib/ui/binance-account.js?v=<?=$accountJsVersion?>" defer></script>
</head>
<body>
  <a class="ba-skip" href="#ba-main">跳到主要内容</a>
  <aside class="ba-sidebar" aria-label="主导航">
    <a class="ba-brand" href="index.php#portfolio"><img src="app-lib/ui/mark.svg" alt="" width="36" height="36"><span>BTC监测器<small>私有监测空间</small></span></a>
    <p class="ba-nav-label">行情与资产</p>
    <nav>
      <a href="index.php#portfolio"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#charts" /></svg>资产总览</a>
      <a href="binance-account.php" aria-current="page"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#shield" /></svg>币安账户</a>
      <a href="admin.php"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#settings" /></svg>管理后台</a>
    </nav>
    <p class="ba-sidebar-note">账户数据仅在此页面显示。<br>离开页面后清空当前显示。</p>
  </aside>
  <div class="ba-workspace">
    <header class="ba-topbar"><span>BTC监测器 <span aria-hidden="true">/</span> <strong>币安账户</strong></span><span class="ba-private"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#shield" /></svg>管理员专属</span></header>
    <main id="ba-main" class="ba-main">
      <div class="ba-page-heading"><div><p class="ba-eyebrow">BINANCE · SPOT</p><h1>币安账户</h1><p>只读查看现货余额，以及 BTC/USDT 的挂单与最近成交。</p></div><button id="ba-refresh" type="button" class="ba-button" title="读取本机已采集的账户快照；后台每 60 秒采集一次"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#refresh" /></svg>刷新显示</button></div>
      <p id="ba-message" class="ba-message" role="status" aria-live="polite" hidden></p>
      <p id="ba-login" class="ba-login" hidden><a class="ba-button ba-primary" href="admin.php">重新登录管理后台</a><span>登录后，从导航中的“币安账户”返回此页。</span></p>
      <section class="ba-card ba-connection" aria-labelledby="ba-connection-title">
        <div class="ba-card-heading"><div><h2 id="ba-connection-title">连接状态</h2><p id="ba-connection-description">在本机填写 API 凭据，验证只读权限后连接。</p></div><span id="ba-status" class="ba-status" data-state="loading" role="status" aria-live="polite">正在读取</span></div>
        <div class="ba-purpose"><svg class="ba-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#shield" /></svg><p>仅接受只读 API 权限。此页面不提供下单、撤单、划转或提币操作。请在币安关闭这些权限后，再连接账户。</p></div>
        <details class="ba-help"><summary>如何获取只读密钥</summary><ol><li>进入币安账户的“API 管理”，创建“系统生成 / HMAC”类型的密钥。</li><li>仅保留“启用读取”，关闭交易、提现、划转、合约等权限。</li><li>将 API Key 和 Secret Key 填入本机此页，不要发送到聊天中。</li><li>Secret Key 仅在创建时可查看，请按币安提示妥善保存。</li></ol></details>
        <div id="ba-connected-summary" class="ba-connected-summary" hidden><span>API Key <strong id="ba-key-hint">—</strong></span><span id="ba-permission">权限待验证</span><span id="ba-ip-permission">IP 限制待确认</span><span>权限验证：<time id="ba-permission-time">—</time></span></div>
        <form id="ba-connect-form" autocomplete="off">
          <div id="ba-credentials" class="ba-credentials">
            <label for="ba-api-key">API Key（访问标识）<input id="ba-api-key" type="password" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="256" required disabled><small>只在本机填写；提交后立即清空输入框。</small></label>
            <label for="ba-secret">Secret Key（密钥）<input id="ba-secret" type="password" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="256" required disabled><small>不会在页面中回显，也不会写入浏览器存储。</small></label>
          </div>
          <label id="ba-otp-wrap" class="ba-otp" for="ba-otp" hidden>两步验证动态码<input id="ba-otp" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" disabled><small>请输入验证器中的 6 位数字；连接和断开时均需验证。</small></label>
          <div class="ba-form-actions"><button id="ba-connect" type="submit" class="ba-button ba-primary" disabled>验证并连接</button><button id="ba-disconnect" type="button" class="ba-button ba-danger" hidden disabled>断开连接</button><span id="ba-form-note">API 凭据仅发送到本应用的同源接口。</span></div>
        </form>
        <p id="ba-snapshot-error" class="ba-message" hidden></p>
      </section>
      <div id="ba-account-data" hidden>
        <div class="ba-summary-grid">
          <section class="ba-summary"><h2>非零余额币种</h2><strong id="ba-balance-count">—</strong><p>仅现货账户，不折算总资产</p></section>
          <section class="ba-summary"><h2>BTC/USDT 当前挂单</h2><strong id="ba-order-count">—</strong><p>只读展示，不支持撤单</p></section>
          <section class="ba-summary"><h2>最近采集</h2><time id="ba-sampled-at">—</time><p id="ba-retry-info">后台每 60 秒采集一次</p></section>
        </div>
        <section class="ba-card" aria-labelledby="ba-balances-title"><div class="ba-card-heading"><div><h2 id="ba-balances-title">现货余额</h2><p>列出账户中所有非零余额币种，数量按币种原单位显示。</p></div><span class="ba-tag">SPOT</span></div><div class="ba-table-scroll" tabindex="0" role="region" aria-label="现货余额，可横向滚动"><table><thead><tr><th scope="col">币种</th><th scope="col" class="ba-numeric">可用</th><th scope="col" class="ba-numeric">冻结</th><th scope="col" class="ba-numeric">合计</th></tr></thead><tbody id="ba-balances"></tbody></table></div><p id="ba-balances-empty" class="ba-empty">等待首次采集…</p></section>
        <section class="ba-card" aria-labelledby="ba-orders-title"><div class="ba-card-heading"><div><h2 id="ba-orders-title">当前挂单 <span>BTC/USDT</span></h2><p>价格单位为 USDT，数量单位为 BTC。</p></div><span class="ba-tag">只读</span></div><div class="ba-table-scroll" tabindex="0" role="region" aria-label="BTC/USDT 当前挂单，可横向滚动"><table><thead><tr><th scope="col">时间</th><th scope="col">方向</th><th scope="col">类型</th><th scope="col" class="ba-numeric">委托价</th><th scope="col" class="ba-numeric">委托量</th><th scope="col" class="ba-numeric">已成交量</th><th scope="col">状态</th></tr></thead><tbody id="ba-orders"></tbody></table></div><p id="ba-orders-empty" class="ba-empty">等待首次采集…</p></section>
        <section class="ba-card" aria-labelledby="ba-trades-title"><div class="ba-card-heading"><div><h2 id="ba-trades-title">最近成交 <span>BTC/USDT</span></h2><p>最多显示最近 100 笔。手续费按实际扣费币种显示。</p></div><span class="ba-tag">最多 100 笔</span></div><div class="ba-table-scroll" tabindex="0" role="region" aria-label="BTC/USDT 最近成交，可横向滚动"><table><thead><tr><th scope="col">时间</th><th scope="col">方向</th><th scope="col" class="ba-numeric">成交价 · USDT</th><th scope="col" class="ba-numeric">成交量 · BTC</th><th scope="col" class="ba-numeric">成交额 · USDT</th><th scope="col" class="ba-numeric">手续费</th></tr></thead><tbody id="ba-trades"></tbody></table></div><p id="ba-trades-empty" class="ba-empty">等待首次采集…</p></section>
        <p class="ba-data-footnote">页面显示本机保存的最近一次采集快照；时间按本地时区显示。挂单与成交仅涵盖 BTC/USDT 现货交易对。</p>
      </div>
      <noscript><p class="ba-message">请启用 JavaScript，以验证连接并读取账户数据。</p></noscript>
    </main>
    <footer class="ba-footer"><span>BTC监测器 · 私有部署</span><a href="index.php#portfolio">返回资产总览</a></footer>
  </div>
</body>
</html>
