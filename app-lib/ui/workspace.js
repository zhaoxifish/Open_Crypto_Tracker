/* BTC monitor workspace. Presentation only: upstream routes, values and actions stay authoritative. */
(function () {
  'use strict';
  if (!document.documentElement.classList.contains('btc-monitor')) return;
  function overview_active() {
    return !window.is_admin && !window.is_plugin && (!location.hash || location.hash === '#portfolio') &&
      !!document.getElementById('portfolio')?.getClientRects().length;
  }
  function sync_refresh() {
    const button = document.getElementById('ws-refresh');
    if (!button) return;
    const overview = overview_active();
    const accountState = document.getElementById('bao-status')?.dataset.state;
    const accountBusy = document.getElementById('bao-refresh')?.disabled && !['private','paused'].includes(accountState);
    const busy = overview && (document.getElementById('bm-refresh')?.disabled || accountBusy);
    button.disabled = !!busy;
    button.setAttribute('aria-busy', String(!!busy));
    button.title = overview ? '读取后台每 60 秒采集的行情与账户快照；不会立即向币安发起采集' : '重新加载当前页面；有未保存修改时会先提醒';
    const label = document.getElementById('ws-refresh-label');
    const value = busy ? '正在读取…' : overview ? '更新显示' : '刷新页面';
    if (label && label.textContent !== value) label.textContent = value;
    button.setAttribute('aria-label', value);
  }
  function sync_save() {
    let privateMode = true;
    try { privateMode = typeof priv_toggle_storage === 'undefined' || localStorage.getItem(priv_toggle_storage) === 'on'; } catch (_) {}
    const dirty = window.is_admin ? window.unsaved_admin_config === true : window.unsaved_user_config === true;
    const visible = dirty && !privateMode && !window.is_login_form && !document.body?.classList.contains('btc-auth');
    document.querySelectorAll('[data-ws-save]').forEach(button => { button.hidden = !visible; });
    const notice = document.getElementById('ws-save-status');
    if (notice) notice.hidden = !visible;
  }
  // Keep the upstream dirty flags and existing submit handlers authoritative.
  const markDirty = window.red_save_button;
  if (typeof markDirty === 'function') window.red_save_button = function (...args) {
    const result = markDirty.apply(this, args);
    const target = window.is_admin && args[0] === 'iframe' ? window.parent : window;
    target.dispatchEvent(new Event('btc-save-state'));
    return result;
  };
  window.addEventListener('btc-save-state', sync_save);
  window.addEventListener('btc-privacy-change', sync_save);
  window.addEventListener('storage', sync_save);
  window.addEventListener('hashchange', () => setTimeout(sync_save, 100));
  const titles = {
    portfolio:['总览','查看 BTC 行情与币安现货资产，数据每分钟自动更新。'],
    update:['手动记账','记录其他持仓数量与成本；币安账户余额由系统自动同步。'],
    settings:['偏好设置','按自己的习惯设置显示、提醒与数据保存方式。'],
    charts:['行情图表','从价格走势与成交量中，观察市场变化。'],
    news:['市场资讯','集中阅读已订阅的信息来源。'],
    tools:['实用工具','处理地址、密钥和日常加密资产计算。'],
    mining:['质押与挖矿','估算收益与成本，了解不同方案的回报。'],
    resources:['参考资源','查阅交易平台、钱包和研究工具。'],
    admin_general:['常规设置','管理站点运行与基础选项。'],
    admin_asset_tracking:['资产跟踪','配置币种、交易对与价格提醒。'],
    admin_reset_backup_restore:['备份与恢复','管理配置备份，保留可恢复的设置副本。'],
    admin_security:['安全设置','管理登录验证、访问限制与安全级别。'],
    admin_comms:['消息通知','配置消息渠道与通知接收方式。'],
    admin_apis:['接口设置','连接行情服务，管理外部接口与 Webhook。'],
    admin_ext_apis:['外部接口','配置行情与服务接口。'],
    admin_plugins:['插件管理','管理扩展功能及其运行选项。'],
    admin_news_feeds:['资讯订阅','选择信息来源，调整资讯更新方式。'],
    admin_power_user:['高级设置','调整缓存、请求与后台任务。'],
    admin_text_gateways:['短信网关','配置通过移动网络接收通知的方式。'],
    admin_proxy:['代理设置','管理接口请求使用的代理连接。'],
    admin_system_monitoring:['系统监控','查看运行状态、访问记录与应用日志。'],
    plugin_on_chain_stats:['链上数据','观察网络活动、区块与链上指标。']
  };
  const paths = {
    portfolio:'M3 4h7v7H3zM14 4h7v7h-7zM3 15h7v6H3zM14 15h7v6h-7z',
    update:'M12 5v14M5 12h14', settings:'M4 7h16M4 17h16M8 4v6M16 14v6',
    charts:'M4 4v16h17M8 14l4-5 4 3 5-7', news:'M5 4h14v17H5zM8 8h8M8 12h8M8 16h5',
    tools:'M14 5l5 5M4 20l5-1 11-11-4-4L5 15z', mining:'M12 3l9 5v8l-9 5-9-5V8zM3 8l9 5 9-5M12 13v8',
    resources:'M4 4h6v16H4zM13 5l5-1 3 15-5 1z',
    admin_general:'M4 7h16M4 17h16M8 4v6M16 14v6',
    admin_asset_tracking:'M3 7h18v13H3zM3 7l3-4h12l3 4M8 12h8',
    admin_security:'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6',
    admin_apis:'M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16',
    admin_plugins:'M8 3h8v5h5v8h-5v5H8v-5H3V8h5z',
    admin_system_monitoring:'M3 4h18v13H3zM8 21h8M12 17v4M5 11h3l2-4 4 7 2-3h3',
    fallback:'M4 5h16v14H4zM8 9h8M8 13h5'
  };
  function icon(key) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('class','btc-icon btc-nav-icon'); svg.setAttribute('aria-hidden','true');
    const path = document.createElementNS(svg.namespaceURI,'path'); path.setAttribute('d',paths[key] || paths.fallback); svg.append(path); return svg;
  }
  // The legacy sidebar width/font controller is superseded by the responsive workspace.
  window.responsive_menu_override = function () {};
  window.toggle_sidebar = function () { set_menu(!document.body.classList.contains('btc-menu-open')); };
  window.footer_banner = function (storageKey, html) {
    if (window.showing_footer_notice || localStorage.getItem(storageKey)==='understood') return;
    window.showing_footer_notice=true;
    const notice=document.createElement('aside'); notice.className='btc-notice'; notice.setAttribute('aria-label','使用提示');
    const content=document.createElement('div'); content.innerHTML=html;
    const close=document.createElement('button'); close.type='button'; close.className='btc-button'; close.textContent='我已了解';
    close.addEventListener('click',()=>{localStorage.setItem(storageKey,'understood');notice.remove();});
    notice.append(content,close); document.body.append(notice);
  };
  function set_menu(open) {
    document.body.classList.toggle('btc-menu-open',open);
    document.querySelector('[data-btc-menu]')?.setAttribute('aria-expanded',String(open));
    const sidebar=document.getElementById('sidebar');
    const mobile=matchMedia('(max-width:760px)').matches;
    if (sidebar) sidebar.inert=mobile&&!open;
    const content=document.getElementById('secondary_wrapper');
    if (content) content.inert=mobile&&open;
    if (open) document.querySelector('#sidebar a')?.focus();
    else if (sidebar?.contains(document.activeElement)) document.querySelector('[data-btc-menu]')?.focus();
  }
  function update_heading() {
    const auth = document.body.classList.contains('btc-auth');
    const key = location.hash.slice(1) || (window.is_admin ? 'admin_general' : 'portfolio');
    const active = document.querySelector('#sidebar a[href="' + (window.is_admin ? 'admin.php' : window.is_plugin ? 'plugins.php' : 'index.php') + '#' + key + '"]');
    const text = active?.textContent.trim();
    const subsection = document.querySelector('#sidebar .dropdown-item.btc-current')?.textContent.trim();
    const auth_title = location.pathname.endsWith('/password-reset.php') ? ['找回管理员账号','通过已配置的恢复方式找回管理员访问权限。'] : document.getElementById('set_admin') ? ['创建管理员账号','设置登录凭据，保护你的监测工作台。'] : ['管理员登录','登录后管理 BTC监测器。'];
    const section = auth ? auth_title : (titles[key] ? [...titles[key]] : null) || [text || '监测工作台','查看数据与管理监测设置。'];
    if (subsection) section[0]=subsection;
    const heading = document.getElementById('btc-page-title');
    if (heading && heading.textContent !== section[0]) heading.textContent = section[0];
    const subtitle = document.getElementById('btc-page-description');
    if (subtitle && subtitle.textContent !== section[1]) subtitle.textContent = section[1];
    const crumb = document.getElementById('btc-current-area');
    if (crumb) crumb.textContent = auth ? '账户验证' : window.is_admin ? '管理后台' : window.is_plugin ? '数据扩展' : '监测工作台';
    document.title = (typeof priv_toggle_storage !== 'undefined' && localStorage.getItem(priv_toggle_storage)==='on') ? 'BTC监测器 · 隐私模式' : section[0] + ' · BTC监测器';
    document.body.dataset.btcPage = key;
  }
  function sync_submenu() {
    const current=new Set();
    document.querySelectorAll('iframe[id^="iframe_"]').forEach(frame=>{
      if(!frame.getClientRects().length) return;
      try {const params=new URL(frame.contentWindow.location.href).searchParams;
        const parent=params.get('parent'); const sub=params.get('subsection');
        if(parent&&sub) current.add('admin_'+parent+'_'+sub);
      } catch {}
    });
    document.querySelectorAll('#sidebar a[submenu-id]').forEach(a=>a.classList.toggle('btc-current',current.has(a.getAttribute('submenu-id'))));
  }
  function decorate() {
    document.querySelectorAll('#sidebar .all-nav > li > a:not([data-btc-icon])').forEach(a => {
      a.dataset.btcIcon='true'; a.prepend(icon(a.hash.slice(1)));
    });
    document.querySelectorAll('table.data_table, table.tablesorter, #coins_table').forEach(table => {
      if (table.parentElement.classList.contains('btc-table-scroll')) return;
      const wrap=document.createElement('div'); wrap.className='btc-table-scroll';
      wrap.tabIndex=0; wrap.setAttribute('role','region'); wrap.setAttribute('aria-label','数据表格，可横向滚动');
      table.before(wrap); wrap.append(table);
    });
    document.querySelectorAll('iframe[id^="iframe_"]').forEach(frame => {
      if (!frame.title) frame.title='设置内容';
      if(!frame.dataset.btcObserved) {frame.dataset.btcObserved='true'; frame.addEventListener('load',()=>{sync_submenu();update_heading();});}
    });
    document.querySelectorAll('.select_auto_refresh').forEach(select=>select.parentElement.classList.add('btc-toolbar'));
    sync_submenu();
  }
  // Preserve numeric series, data URLs, formatter functions and component attribution.
  function light_data(value, key) {
    if (!value || typeof value !== 'object' || key === 'values') return value;
    if (Array.isArray(value)) return value.map(v => light_data(v));
    const out={};
    for (const [k,v] of Object.entries(value)) {
      if (k === 'values') { out[k]=v; continue; }
      if (typeof v === 'string' && /^(backgroundColor|background-color)$/.test(k) && /^(#222(222)?|#333(333)?|#000(000)?|black|#1b1b1b|#212121)$/i.test(v)) out[k]='#ffffff';
      else if (typeof v === 'string' && /^(fontColor|font-color)$/.test(k) && /^(white|#fff(fff)?)$/i.test(v)) out[k]='#455671';
      else out[k]=light_data(v,k);
    }
    return out;
  }
  if (window.zingchart) {
    const render=window.zingchart.render;
    window.zingchart.render=function(options) { return render.call(this,options?.data && typeof options.data==='object' ? {...options,data:light_data(options.data)} : options); };
    const exec=window.zingchart.exec;
    window.zingchart.exec=function(id,command,options) { return exec.call(this,id,command,(/^(load|setdata)$/.test(command) && options?.data && typeof options.data==='object') ? {...options,data:light_data(options.data)} : options); };
  }
  // Route common navigation through the existing tab and unsaved-change handlers.
  document.addEventListener('click',event=>{
    const link=event.target.closest('[data-btc-nav]');
    if(!link || event.defaultPrevented || event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target==='_blank') return;
    const destination=new URL(link.href,location.href);
    if(destination.origin!==location.origin) return;
    const oldLink=[...document.querySelectorAll('#ws-legacy-routes .all-nav a')].find(item=>item.href===link.href);
    event.preventDefault();
    if(oldLink) oldLink.click();
    else if(typeof app_reloading_check==='function') app_reloading_check(0,link.getAttribute('href'));
    else location.href=link.href;
    setTimeout(update_heading,150);
  });
  document.addEventListener('DOMContentLoaded',function () {
    document.querySelector('.btc-topbar')?.classList.add('ws-refresh-ready');
    document.body.classList.add('ws-refresh-ready');
    document.getElementById('ws-refresh')?.addEventListener('click', event => {
      event.preventDefault();
      if (overview_active()) {
        document.getElementById('bm-refresh')?.click();
        document.getElementById('bao-refresh')?.click();
        sync_refresh();
      } else if (typeof app_reloading_check === 'function') app_reloading_check(0);
    });
    const refreshObserver = new MutationObserver(sync_refresh);
    ['bm-refresh','bao-refresh','bao-status'].forEach(id => {
      const element = document.getElementById(id);
      if (element) refreshObserver.observe(element, {attributes:true, attributeFilter:['disabled','data-state']});
    });
    sync_refresh();
    sync_save();
    const saveObserver = new MutationObserver(sync_save);
    document.querySelectorAll('[data-ws-save]').forEach(button => saveObserver.observe(button, {attributes:true, attributeFilter:['class']}));
    document.addEventListener('input', () => setTimeout(sync_save, 0));
    document.addEventListener('change', () => setTimeout(sync_save, 0));
    document.querySelector('[data-btc-menu]')?.addEventListener('click',()=>set_menu(!document.body.classList.contains('btc-menu-open')));
    document.querySelector('[data-btc-overlay]')?.addEventListener('click',()=>set_menu(false));
    document.addEventListener('keydown',event=>{ if(event.key==='Escape') set_menu(false); });
    const dialog=document.getElementById('btc-about');
    document.querySelectorAll('[data-btc-about]').forEach(button=>button.addEventListener('click',()=>{set_menu(false);dialog?.showModal();}));
    document.querySelector('[data-btc-close-about]')?.addEventListener('click',()=>dialog?.close());
    dialog?.addEventListener('click',event=>{ if(event.target===dialog) { const r=dialog.getBoundingClientRect(); if(event.clientX<r.left || event.clientX>r.right || event.clientY<r.top || event.clientY>r.bottom) dialog.close(); } });
    document.querySelectorAll('[data-btc-go]').forEach(link=>link.addEventListener('click',event=>{
      const target=document.querySelector('#sidebar .all-nav a[href="index.php#'+link.dataset.btcGo+'"]');
      if(target) {event.preventDefault(); target.click();}
    }));
    document.querySelectorAll('#sidebar .all-nav a').forEach(a=>a.addEventListener('click',()=>{ if(!a.classList.contains('dropdown-toggle')) set_menu(false); setTimeout(update_heading,150); }));
    setTimeout(()=>{
      decorate(); update_heading(); set_menu(false); sync_refresh();
      let queued=false;
      new MutationObserver(mutations=>{
        if (queued || !mutations.some(m=>m.addedNodes.length)) return;
        queued=true; requestAnimationFrame(()=>{queued=false;decorate();});
      }).observe(document.body,{childList:true,subtree:true});
    },100);
  });
  window.addEventListener('hashchange',()=>setTimeout(()=>{sync_submenu();update_heading();sync_refresh();},80));
  window.addEventListener('resize',()=>{if(!document.body.classList.contains('btc-menu-open')) set_menu(false);});
})();
