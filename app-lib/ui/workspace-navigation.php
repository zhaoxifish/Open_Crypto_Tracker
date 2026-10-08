<?php
/* Shared navigation contains public links only; never load application state or credentials here. */
?>
<nav class="ws-primary-nav" aria-label="常用功能" data-i18n="skip">
  <a href="index.php#portfolio" data-btc-nav data-ws-item="overview"><svg class="ws-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#charts" /></svg>总览</a>
  <a href="binance-account.php" data-btc-nav data-ws-item="assets"><svg class="ws-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#shield" /></svg>我的资产</a>
  <a href="workspace-settings.php" data-btc-nav data-ws-item="settings"><svg class="ws-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#settings" /></svg>设置</a>
</nav>
<details class="ws-more-menu" data-ws-more data-i18n="skip">
  <summary><span>更多功能</span><span class="ws-chevron" aria-hidden="true">⌄</span></summary>
  <nav aria-label="更多功能">
    <a href="index.php#update" data-btc-nav>手动记账</a>
    <a href="index.php#charts" data-btc-nav>行情图表</a>
    <a href="index.php#news" data-btc-nav>市场资讯</a>
    <a href="index.php#tools" data-btc-nav>实用工具</a>
    <a href="index.php#mining" data-btc-nav>质押与挖矿</a>
    <a href="index.php#resources" data-btc-nav>参考资源</a>
    <a href="plugins.php" data-btc-nav>数据扩展</a>
    <a href="admin.php" data-btc-nav>高级管理</a>
  </nav>
</details>
