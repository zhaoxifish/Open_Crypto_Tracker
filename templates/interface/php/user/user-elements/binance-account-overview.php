<?php
/* Public shell only. Private balances are requested after session and privacy checks. */
?>
<section id="binance-account-overview" data-i18n="skip" aria-labelledby="bao-title">
  <header class="bao-header">
    <div class="bao-heading"><span class="bao-mark" aria-hidden="true"><svg class="btc-icon"><use href="app-lib/ui/icons.svg#shield" /></svg></span><div><h3 id="bao-title">币安现货余额</h3><p>只读账户快照 <span aria-hidden="true">·</span> 每 60 秒采集</p></div></div>
    <div class="bao-actions"><span id="bao-status" class="bao-status" data-state="loading" role="status" aria-live="polite">等待读取</span><button id="bao-refresh" type="button" class="btc-button" title="读取本机已采集的账户余额；不会触发交易或即时同步"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#refresh" /></svg>刷新显示</button><a class="bao-manage" href="binance-account.php">查看全部资产 <span aria-hidden="true">↗</span></a></div>
  </header>
  <div id="bao-placeholder" class="bao-placeholder"><p id="bao-placeholder-text">正在验证登录并读取账户余额…</p><a id="bao-entry" class="btc-button" href="binance-account.php#connection" hidden>设置账户连接</a></div>
  <div id="bao-data" hidden>
    <dl class="bao-summary">
      <div><dt>非零余额币种</dt><dd id="bao-asset-count">—</dd><p>现货账户中的资产种类</p></div>
      <div><dt>BTC 数量 <span>BTC</span></dt><dd id="bao-btc">—</dd><p>可用与冻结数量之和</p></div>
      <div><dt>USDT 数量 <span>USDT</span></dt><dd id="bao-usdt">—</dd><p>币种余额，不是账户总估值</p></div>
    </dl>
    <div class="bao-table-scroll" tabindex="0" role="region" aria-label="币安现货全部非零余额，可横向滚动"><table class="bao-table"><thead><tr><th scope="col">币种</th><th scope="col" class="bao-number">可用</th><th scope="col" class="bao-number">冻结</th><th scope="col" class="bao-number">合计</th></tr></thead><tbody id="bao-balances"></tbody></table></div>
    <button id="bao-expand" class="btc-button bao-expand" type="button" aria-controls="bao-balances" aria-expanded="false" hidden>展开全部余额</button>
    <p id="bao-empty" class="bao-empty" hidden>现货账户暂无非零余额。</p>
    <footer class="bao-footer"><span>最近采集：<time id="bao-sampled-at">—</time></span><span>全部余额按币种原单位显示 · 本地时区</span></footer>
  </div>
  <p id="bao-notice" class="bao-notice" hidden></p>
  <noscript><p class="bao-notice">请启用 JavaScript，再登录管理后台以读取账户余额。</p></noscript>
</section>
