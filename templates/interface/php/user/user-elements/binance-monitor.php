<?php
/* Native Binance spot market display; independent of portfolio conversion settings. */
?>
<section id="binance-monitor" class="bm-panel" data-i18n="skip" aria-labelledby="bm-title">
  <header class="bm-header">
    <div class="bm-heading">
      <span class="bm-market-mark" aria-hidden="true">₿</span>
      <div><h3 id="bm-title">币安现货 · BTC/USDT</h3><p>币安同源行情 <span aria-hidden="true">·</span> 每 60 秒采集</p></div>
    </div>
    <div class="bm-actions">
      <span id="bm-status" class="bm-status" data-state="loading" role="status" aria-live="polite">正在读取</span>
      <button type="button" id="bm-refresh" class="btc-button bm-refresh" aria-label="刷新显示，读取本机已采集的币安现货行情" title="读取本机已采集的行情；新行情每 60 秒采集一次"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#refresh" /></svg><span>刷新显示</span></button>
    </div>
  </header>
  <div class="bm-overview">
    <div class="bm-price-block">
      <p class="bm-label">最新成交价</p>
      <div class="bm-price-line"><strong id="bm-price">—</strong><span>USDT</span></div>
      <div class="bm-change-line"><span id="bm-change" class="bm-change">—</span><span>滚动 24 小时涨跌</span></div>
    </div>
    <dl class="bm-metrics">
      <div><dt>24 小时成交量 <span>BTC</span></dt><dd id="bm-volume">—</dd></div>
      <div><dt>24 小时成交额 <span>USDT</span></dt><dd id="bm-turnover">—</dd></div>
      <div><dt>24 小时最高 <span>USDT</span></dt><dd id="bm-high">—</dd></div>
      <div><dt>24 小时最低 <span>USDT</span></dt><dd id="bm-low">—</dd></div>
    </dl>
  </div>
  <div class="bm-chart-heading"><strong>价格走势 <span>USDT</span></strong><span id="bm-history-label">等待历史数据</span></div>
  <div class="bm-chart-frame">
    <svg id="bm-chart" class="bm-chart" viewBox="0 0 960 180" preserveAspectRatio="none" role="img" aria-label="等待可用的币安价格走势数据" hidden>
      <defs><linearGradient id="bm-chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1665d8" stop-opacity=".17" /><stop offset="100%" stop-color="#1665d8" stop-opacity="0" /></linearGradient></defs>
      <path class="bm-chart-grid" d="M12 18H948 M12 90H948 M12 162H948" />
      <path id="bm-chart-area" fill="url(#bm-chart-fill)" />
      <path id="bm-chart-line" class="bm-chart-line" />
      <circle id="bm-chart-dot" r="3.5" fill="#1665d8" />
    </svg>
    <p id="bm-chart-empty" class="bm-chart-empty">正在读取真实行情记录…</p>
    <div id="bm-chart-bounds" class="bm-chart-bounds" hidden><span id="bm-chart-max"></span><span id="bm-chart-min"></span></div>
  </div>
  <div class="bm-chart-axis"><span id="bm-chart-start">—</span><span id="bm-chart-end">—</span></div>
  <p id="bm-history-notice" class="bm-notice" hidden></p>
  <footer class="bm-footer"><span>行情时点：<time id="bm-market-time">—</time></span><span>本机采集：<time id="bm-sample-time">—</time></span><span>时间按本地时区显示</span></footer>
  <p id="bm-notice" class="bm-notice" hidden></p>
  <noscript><p class="bm-notice">请启用 JavaScript，以读取和更新币安现货行情。</p></noscript>
</section>
