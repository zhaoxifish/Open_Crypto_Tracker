<header class="btc-topbar">
  <div class="btc-breadcrumb"><button type="button" class="btc-button btc-icon-button btc-mobile-menu" data-btc-menu aria-controls="sidebar" aria-expanded="false" aria-label="展开导航"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#menu" /></svg></button><span>BTC监测器</span><span aria-hidden="true">/</span><strong id="btc-current-area"><?=( $is_admin ? '管理后台' : ($is_plugin ? '数据扩展' : '监测工作台') )?></strong></div>
  <div class="btc-top-actions">
    <a class="btc-button" id="pm_link" href="javascript:privacy_mode(true);" title="隐藏个人资产数据">隐私模式：关闭</a>
    <button type="button" class="btc-button btc-icon-button toggle_alerts" id="sb_alerts" title="查看应用提醒" aria-label="查看应用提醒"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#bell" /></svg></button>
    <a class="btc-button btc-refresh" href="javascript:app_reloading_check();" title="刷新行情与页面数据"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#refresh" /></svg><span>刷新数据</span></a>
    <?php if ( $ct['sec']->admin_logged_in() && $is_admin ) { ?>
    <a href="javascript:" class="btc-button btc-primary admin_settings_save settings_save"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#save" /></svg>保存设置</a>
    <?php } elseif ( !$is_admin && !$is_plugin ) { ?>
    <a href="javascript:" class="btc-button btc-primary user_settings_save settings_save"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#save" /></svg>保存更改</a>
    <?php } ?>
    <?php if ( $ct['sec']->admin_logged_in() ) { ?>
    <a class="btc-button btc-icon-button admin_logout" href="?logout=1&amp;admin_nonce=<?=$ct['sec']->admin_nonce('logout')?>" title="退出管理后台" aria-label="退出管理后台"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#logout" /></svg></a>
    <?php } ?>
  </div>
</header>
