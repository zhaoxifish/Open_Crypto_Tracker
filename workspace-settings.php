<?php
declare(strict_types=1);
require_once __DIR__ . '/app-lib/binance-account/Http.php';
\BtcAccount\Http::headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !\BtcAccount\Http::sameOrigin($_SERVER, false) || ($_SERVER['QUERY_STRING'] ?? '') !== '' || ($_SERVER['PATH_INFO'] ?? '') !== '') {
    http_response_code(403);
    exit('请通过本机 HTTPS 地址打开设置。');
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-CN" class="btc-account">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
  <title>设置 · BTC监测器</title><link rel="icon" href="app-lib/ui/mark.svg">
  <link rel="stylesheet" href="app-lib/ui/binance-account.css?v=<?=filemtime(__DIR__.'/app-lib/ui/binance-account.css')?>">
  <link rel="stylesheet" href="app-lib/ui/workspace-common.css?v=<?=filemtime(__DIR__.'/app-lib/ui/workspace-common.css')?>">
  <link rel="stylesheet" href="app-lib/ui/workspace-settings.css?v=<?=filemtime(__DIR__.'/app-lib/ui/workspace-settings.css')?>">
  <script src="app-lib/ui/workspace-common.js?v=<?=filemtime(__DIR__.'/app-lib/ui/workspace-common.js')?>" defer></script>
</head>
<body>
  <a class="ba-skip" href="#ws-settings-main">跳到主要内容</a>
  <aside class="ba-sidebar" aria-label="主导航">
    <a class="ba-brand" href="index.php#portfolio"><img src="app-lib/ui/mark.svg" alt="" width="36" height="36"><span>BTC监测器<small>私有监测空间</small></span></a>
    <?php require __DIR__.'/app-lib/ui/workspace-navigation.php'; ?>
    <p class="ba-sidebar-note">按需要打开设置。<br>查看行情和资产无需保存。</p>
  </aside>
  <div class="ba-workspace">
    <header class="ba-topbar"><span>BTC监测器 <span aria-hidden="true">/</span> <strong>设置</strong></span><a href="index.php#portfolio">返回总览</a></header>
    <main class="ba-main" id="ws-settings-main">
      <div class="ba-page-heading"><div><h1>设置</h1><p>常用设置放在这里，复杂选项按需要展开。</p></div></div>
      <div class="ws-settings-grid">
        <section class="ba-card ws-setting-card"><span class="ws-setting-icon" aria-hidden="true">01</span><h2>账户连接</h2><p>首次连接币安，或管理已保存的只读连接。连接后在“我的资产”中查看余额。</p><a class="ba-button ba-primary" href="binance-account.php#connection">管理币安连接</a><small>需要管理员登录 · 不提供交易操作</small></section>
        <section class="ba-card ws-setting-card"><span class="ws-setting-icon" aria-hidden="true">02</span><h2>显示与偏好</h2><p>调整数字格式、页面刷新及手动持仓的数据保存方式。</p><a class="ba-button" href="index.php#settings">调整显示偏好</a><small>币安账户由后台每分钟自动同步</small></section>
        <section class="ba-card ws-setting-card"><span class="ws-setting-icon" aria-hidden="true">03</span><h2>登录与安全</h2><p>管理管理员登录与两步验证，保护个人账户数据。</p><a class="ba-button" href="admin.php#admin_security">打开安全设置</a><small>需要管理员登录</small></section>
      </div>
      <details class="ba-card ws-advanced-settings"><summary><span>高级选项</span><small>通知、备份与系统维护</small></summary><div class="ws-advanced-links">
        <a href="admin.php#admin_comms"><strong>通知渠道</strong><span>配置已有的消息接收方式</span></a>
        <a href="admin.php#admin_reset_backup_restore"><strong>备份与恢复</strong><span>管理原应用配置与历史图表；币安账户数据需单独备份</span></a>
        <a href="admin.php#admin_system_monitoring"><strong>运行状态与日志</strong><span>排查服务与数据更新问题</span></a>
        <a href="admin.php"><strong>完整管理后台</strong><span>资产配置、接口、插件及其他系统选项</span></a>
      </div><p>这些入口保留原有权限检查。只有需要调整对应功能时，再进入操作。</p></details>
      <section class="ba-card ws-settings-help"><h2>日常使用</h2><p>在“总览”看行情和资产摘要，在“我的资产”看完整余额与最近成交。只有修改设置或手动记录时才需要保存；“更新显示”读取本机最新已采集的数据。</p></section>
    </main>
    <footer class="ba-footer"><span>BTC监测器 · 私有部署</span><a href="index.php#portfolio">返回总览</a></footer>
  </div>
</body>
</html>
