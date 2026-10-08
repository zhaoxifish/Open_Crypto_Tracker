<?php
/* Copyright 2014-2026 GPLv3, Open Crypto Tracker by Mike Kilday: Mike@DragonFrugal.com (leave this copyright / attribution intact in ALL forks / copies!) */
?>
<button type="button" class="btc-menu-overlay" data-btc-overlay aria-label="关闭导航"></button>
<nav id="sidebar" aria-label="主导航">
  <a class="btc-brand" data-btc-go="portfolio" href="index.php#portfolio"><img class="btc-brand-mark" src="app-lib/ui/mark.svg" alt="" /><span><strong>BTC监测器</strong><small>行情、资产与链上洞察</small></span></a>
  <p class="btc-workspace-label">我的工作空间</p>
  <ul id="sidebar_menu" class="list-unstyled components">
<!-- Admin area -->
            <li class="admin-nav-wrapper">
                
                <a href="#adminSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle <?=( preg_match("/admin\.php/i", $_SERVER['REQUEST_URI']) ? 'active' : '' )?>">Admin Area</a>
                
                <ul class="admin-nav all-nav collapse list-unstyled" id="adminSubmenu">
                
        
                    <?php
                    if ( $ct['sec']->admin_logged_in() ) {
                    ?>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_general" title='General admin settings.'>General</a>
                    </li>
                    
                    
                    <!-- START custom 3-deep config -->
                    <li class="nav-item dropdown custom-3deep open-first">
                        
                        <a class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" href="admin.php#admin_asset_tracking" onclick='javascript:load_iframe("iframe_asset_tracking")' title='Admin area for adding / removing currencies and markets.'>Asset Tracking</a>
                        
                        <ul class="dropdown-menu">
                        
                        
                        <li>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_asset_tracking" submenu-id="admin_asset_tracking_currency_support" onclick='javascript: setTimeout(function(){ load_iframe("iframe_asset_tracking", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_currency_support')?>&parent=asset_tracking&subsection=currency_support") }, <?=( $is_admin ? '1000' : '0' )?>);' title='Admin area for adding / removing currencies.'>Currency Support</a>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_asset_tracking" submenu-id="admin_asset_tracking_portfolio_assets" onclick='javascript: setTimeout(function(){ load_iframe("iframe_asset_tracking", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_portfolio_assets')?>&parent=asset_tracking&subsection=portfolio_assets") }, <?=( $is_admin ? '1000' : '0' )?>);' title='Add / remove / update the available assets for portfolio tracking.'>Portfolio Assets</a>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_asset_tracking" submenu-id="admin_asset_tracking_price_alerts_charts" onclick='javascript: setTimeout(function(){ load_iframe("iframe_asset_tracking", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_price_alerts_charts')?>&parent=asset_tracking&subsection=price_alerts_charts") }, <?=( $is_admin ? '1000' : '0' )?>);' title='Configure charts and price alerts.'>Price Alerts / Charts</a>
                        
                        </li>
                          <!-- <li><hr class="dropdown-divider"></li> -->
                        
                        </ul>
                        
                    </li>
                    <!-- END custom 3-deep config -->


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_reset_backup_restore" title='Reset, backup, or restore your app configuration settings / chart data / etc.'>Reset / Backup & Restore</a>
                    </li>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_security" title='Admin area for all security-related settings.'>Security</a>
                    </li>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_comms" title='Configure email / text / Alexa / Telegram communications, and more.'>Communications</a>
                    </li>
                    
                    
                    <!-- START custom 3-deep config -->
                    <li class="nav-item dropdown custom-3deep open-first">
                        
                        <a class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" href="admin.php#admin_apis" onclick='javascript:load_iframe("iframe_apis")' title='Configure options for external third party APIs, and available internal APIs / Webhooks.'>APIs</a>
                        
                        <ul class="dropdown-menu">
                        
                        
                        <li>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_apis" submenu-id="admin_apis_ext_apis" onclick='javascript: setTimeout(function(){ load_iframe("iframe_apis", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_ext_apis')?>&parent=apis&subsection=ext_apis") }, <?=( $is_admin ? '1000' : '0' )?>);' title='Configure options for external third party APIs.'>External APIs</a>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_apis" submenu-id="admin_apis_webhook_int_api" onclick='javascript: setTimeout(function(){ load_iframe("iframe_apis", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_webhook_int_api')?>&parent=apis&subsection=webhook_int_api") }, <?=( $is_admin ? '1000' : '0' )?>);' title='Documentation / keys for using the built-in API to connect to other apps.'>Internal API / Webhook</a>
                        
                        </li>
                          <!-- <li><hr class="dropdown-divider"></li> -->
                        
                        </ul>
                        
                    </li>
                    <!-- END custom 3-deep config -->
                    
                    
                        <?php
                        
                        // Plugin link(s)
                        $navbar_plugins = array();
                        
                        
                        // Active plugins subnav, IF NOT high security mode
                        if ( $ct['admin_area_sec_level'] != 'high' ) {

                             foreach ( $plug['activated']['ui'] as $plugin_key => $unused ) {
                             $navbar_plugins[$plugin_key] = 1;
                             }
     
                             foreach ( $plug['activated']['cron'] as $plugin_key => $unused ) {
                             $navbar_plugins[$plugin_key] = 1;
                             }
     
                             foreach ( $plug['activated']['webhook'] as $plugin_key => $unused ) {
                             $navbar_plugins[$plugin_key] = 1;
                             }
                        
                        }
                        
                        
                        if ( sizeof($navbar_plugins) > 0 ) {
                        ksort($navbar_plugins); // Alphabetical order (for admin UI)
                    ?>
                    
                    <!-- START custom 3-deep config -->
                    <li class="nav-item dropdown custom-3deep open-first">
                        
                        <a class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" href="admin.php#admin_plugins" onclick='javascript:load_iframe("iframe_plugins")' title='Manage plugin addons for this app.'>Plugins</a>
                        
                        <ul class="dropdown-menu">
                        
                    <?php
                        }

                        foreach ( $navbar_plugins as $plugin_key => $unused ) {
                        ?>
                        
                        <li>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_plugins" submenu-id="admin_plugins_<?=$plugin_key?>" onclick='javascript: setTimeout(function(){ load_iframe("iframe_plugins", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_' . $plugin_key)?>&plugin=<?=$plugin_key?>") }, <?=( $is_admin ? '1000' : '0' )?>);' title='<?=$plug['conf'][$plugin_key]['ui_name']?> plugin settings and documentation.'><?=$plug['conf'][$plugin_key]['ui_name']?></a>
                        
                        </li>
                          <!-- <li><hr class="dropdown-divider"></li> -->
                          
                        <?php
                        }
                        
                        if ( sizeof($navbar_plugins) > 0 ) {
                        ?>
                        
                        </ul>
                        
                    </li>
                    <!-- END custom 3-deep config -->
                    
                    <?php
                        }
                        else {
                    ?>
                    
                    <!-- NO PLUGINS activated -->
                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_plugins" title='Manage plugin addons for this app.'>Plugins</a>
                    </li>

                    <?php
                    }
                    ?>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_news_feeds" title='Edit the news feeds for the news page.'>市场资讯</a>
                    </li>
                    

                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_power_user" title='Power user settings (for advanced users).'>Power User</a>
                    </li>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_text_gateways" title='Add / remove / update the mobile text gateways available, to use for mobile text communications.'>Mobile Text Gateways</a>
                    </li>


                    <li class='sidebar-item nav-item'>
                        <a class="nav-link admin_change_width" data-width="fixed_max" href="admin.php#admin_proxy" title='Enable / disable proxy services (for privacy connecting to third party APIs).'>Proxies</a>
                    </li>
                    
                    
                    <!-- START custom 3-deep config -->
                    <li class="nav-item dropdown custom-3deep open-first">
                        
                        <a class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" href="admin.php#admin_system_monitoring" onclick='javascript:load_iframe("iframe_system_monitoring")' title='View system / access stats, and app logs.'>System Monitoring</a>
                        
                        <ul class="dropdown-menu">
                        
                        
                        <li>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_system_monitoring" submenu-id="admin_system_monitoring_system_stats" onclick='javascript: setTimeout(function(){ load_iframe("iframe_system_monitoring", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_system_stats')?>&parent=system_monitoring&subsection=system_stats") }, <?=( $is_admin ? '1000' : '0' )?>);' title='View system stats, to keep track of your app server system health.'>System Stats</a>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_system_monitoring" submenu-id="admin_system_monitoring_access_stats" onclick='javascript: setTimeout(function(){ load_iframe("iframe_system_monitoring", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_access_stats')?>&parent=system_monitoring&subsection=access_stats") }, <?=( $is_admin ? '1000' : '0' )?>);' title='View user access stats, to track IP addresses / Browser versions / page views of who has been accessing this app.'>Access Stats</a>
                        
                        <!-- WE ONLY NEED A 1000 MILLISECOND DELAY IF WE ARE IN THE ADMIN AREA (FOR UNSAVED SETTING CHANGES CHECKING) -->
                        <a class="dropdown-item" href="admin.php#admin_system_monitoring" submenu-id="admin_system_monitoring_logs" onclick='javascript: setTimeout(function(){ load_iframe("iframe_system_monitoring", "admin.php?iframe_nonce=<?=$ct['sec']->admin_nonce('iframe_app_logs')?>&parent=system_monitoring&subsection=app_logs") }, <?=( $is_admin ? '1000' : '0' )?>);' title='View logs for this app.'>App Logs</a>
                        
                        </li>
                          <!-- <li><hr class="dropdown-divider"></li> -->
                        
                        </ul>
                        
                    </li>
                    <!-- END custom 3-deep config -->


                    <?php
                    }
                    else {
                    ?>
                    
                    <li class='sidebar-item'>
                        <a href="admin.php" title='Login to the admin area.'>Login</a>
                    </li>

                    <?php
                    }
                    ?>
                    
                    
                </ul>
                
            </li>
            
            
            <!-- User area -->
            <li class="user-nav-wrapper">
            
                <a href="#userSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle <?=( preg_match("/index\.php/i", $_SERVER['REQUEST_URI']) ? 'active' : '' )?>">行情与资产</a>
                
                <ul class="user-nav all-nav collapse list-unstyled" id="userSubmenu">
          
                <li class='sidebar-item'><a href='index.php#portfolio' title='View your portfolio.'>资产总览</a></li>
                <li class='sidebar-item'><a href='binance-account.php' title='管理员专属：只读查看币安现货账户'>币安账户</a></li>
                
                <li class='sidebar-item update_portfolio_link'><a class='update_portfolio_link' id='update_link_2' href='index.php#update' title='Update your portfolio data.'>管理资产</a></li>
     
                <li class='sidebar-item'><a href='index.php#settings' title='Update your user settings.'>偏好设置</a></li>
                <?php
     		 if ( $ct['conf']['charts_alerts']['enable_price_charts'] == 'on' ) {
     		 ?>
                <li class='sidebar-item'><a href='index.php#charts' title='View price charts.'>行情图表</a></li>
     		 <?php
     		 }
     		 ?>
     		 
                <li class='sidebar-item'><a href='index.php#news' title='View News Feeds.'>市场资讯</a></li>
                
                <li class='sidebar-item'><a href='index.php#tools' title='Use various crypto tools.'>Tools</a></li>
     
                <li class='sidebar-item'><a href='index.php#mining' title='Calculate coin mining profits.'>Staking / Mining</a></li>
     
                <li class='sidebar-item'><a href='index.php#resources' title='View 3rd party resources.'>参考资源</a></li>
                
                </ul>
                
            </li>
            
            
           <?php 
          foreach ( $plug['activated']['ui'] as $plugin_key => $plugin_init ) {
                      		
          $this_plug = $plugin_key;
               
               if ( $plug['conf'][$this_plug]['ui_location'] == 'nav_menu_tab' ) {
               
               $render_plugin_nav_menu .= "\n <li class='sidebar-item'><a href='plugins.php#plugin_" . $this_plug . "' title='Plugin interface for: " . htmlspecialchars($plug['conf'][$this_plug]['ui_name']) . "'>" . $plug['conf'][$this_plug]['ui_name'] . "</a></li> \n";

               }
               elseif ( $plug['conf'][$this_plug]['ui_location'] == 'nav_menu_page' ) {
               
               $render_plugin_nav_menu .= "\n <li class='sidebar-item'><a nav-menu-page-id='" . $this_plug . "' href='plugins.php?plugin=" . $this_plug . "' title='Plugin interface for: " . htmlspecialchars($plug['conf'][$this_plug]['ui_name']) . "'>" . $plug['conf'][$this_plug]['ui_name'] . "</a></li> \n";

               }
          
          // Reset $this_plug at end of loop
          unset($this_plug); 
            	
          }
               
               
          if ( $is_plugin_nav_menu ) {
          ?>
                 
                 <!-- plugin area -->
                 <li class="plugin-nav-wrapper">
                 
                     <a href="#pluginSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle <?=( preg_match("/plugins\.php/i", $_SERVER['REQUEST_URI']) ? 'active' : '' )?>">数据扩展</a>
                     
                     <ul class="plugin-nav all-nav collapse list-unstyled" id="pluginSubmenu">
                     
                     <?=$render_plugin_nav_menu?>
               
                     </ul>
                     
                 </li>
                 
          <?php
          }
          ?>
            
            

  </ul>
  <div class="btc-sidebar-footer"><div class="btc-mobile-account"><a id="pm_link2" class="btc-button" href="javascript:privacy_mode(true);" title="切换隐私模式">隐私模式</a><?php if ($ct['sec']->admin_logged_in()) { ?><a class="btc-button admin_logout" href="?logout=1&amp;admin_nonce=<?=$ct['sec']->admin_nonce('logout')?>">退出管理后台</a><?php } ?></div><button type="button" data-btc-about>关于与开源许可</button><p>BTC监测器 · 私有部署</p></div>
</nav>
