/* Shared navigation state only. This script never reads account data or browser storage. */
(function () {
  'use strict';
  const mobile = matchMedia('(max-width:760px)');
  function highlight() {
    const page = location.pathname.split('/').pop();
    const connection = page === 'binance-account.php' && location.hash === '#connection';
    const item = page === 'binance-account.php' ? (connection ? 'settings' : 'assets') :
      page === 'workspace-settings.php' || page === 'admin.php' || location.hash === '#settings' ? 'settings' :
      (page === 'index.php' || page === '') && (!location.hash || location.hash === '#portfolio') ? 'overview' : '';
    document.querySelectorAll('[data-ws-item]').forEach(link => {
      if (link.dataset.wsItem === item) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    document.querySelectorAll('[data-ws-more] a').forEach(link => {
      const active = link.pathname === location.pathname && link.hash === location.hash;
      if (active) { link.setAttribute('aria-current', 'page'); link.closest('details').open = true; }
      else link.removeAttribute('aria-current');
    });
  }
  function focusable(sidebar) {
    // Closed details may retain layout rectangles, but only their first summary is reachable.
    const closedDetails = [...sidebar.querySelectorAll('details:not([open])')];
    return [...sidebar.querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex]')].filter(item =>
      item.tabIndex >= 0 && !item.matches(':disabled') && !item.closest('[inert]') &&
      closedDetails.every(details => !details.contains(item) || details.querySelector(':scope > summary')?.contains(item)) &&
      item.getClientRects().length > 0 && getComputedStyle(item).visibility !== 'hidden');
  }
  function focusNavigation(sidebar) {
    const items = focusable(sidebar);
    const target = items.find(item => item.getAttribute('aria-current') === 'page') || items[0];
    if (target) target.focus({preventScroll:true});
    else { sidebar.tabIndex = -1; sidebar.focus({preventScroll:true}); }
  }
  function trapTab(event, sidebar) {
    if (event.key !== 'Tab') return;
    const items = focusable(sidebar);
    const first = items[0], last = items[items.length - 1];
    const active = document.activeElement;
    if (!first) { event.preventDefault(); focusNavigation(sidebar); }
    else if (!sidebar.contains(active) || !items.includes(active)) {
      event.preventDefault(); (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && active === first) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault(); first.focus();
    }
  }
  function iconButton(className, label, path) {
    const button = document.createElement('button');
    button.type = 'button'; button.className = className;
    button.setAttribute('aria-label', label); button.title = label;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('class', 'ws-icon'); svg.setAttribute('aria-hidden', 'true');
    const shape = document.createElementNS(svg.namespaceURI, 'path'); shape.setAttribute('d', path);
    svg.append(shape); button.append(svg); return button;
  }
  function accountDrawer() {
    if (!document.documentElement.classList.contains('btc-account')) return;
    const sidebar = document.querySelector('.ba-sidebar');
    const workspace = document.querySelector('.ba-workspace');
    const topbar = document.querySelector('.ba-topbar');
    if (!sidebar || !workspace || !topbar || sidebar.dataset.wsDrawer === 'ready') return;
    sidebar.dataset.wsDrawer = 'ready'; sidebar.id = 'ws-account-sidebar';
    const trigger = iconButton('ws-mobile-toggle', '展开导航', 'M4 6h16M4 12h16M4 18h16');
    trigger.setAttribute('aria-controls', sidebar.id); trigger.setAttribute('aria-expanded', 'false');
    const trail = [...topbar.children].find(item => item.tagName === 'SPAN' && !item.classList.contains('ba-private'));
    if (trail) trail.classList.add('ws-topbar-trail');
    topbar.prepend(trigger);
    const close = iconButton('ws-mobile-close', '关闭导航', 'M6 6l12 12M6 18L18 6');
    sidebar.prepend(close);
    const overlay = document.createElement('button');
    overlay.type = 'button'; overlay.className = 'ws-mobile-overlay'; overlay.tabIndex = -1;
    overlay.setAttribute('aria-label', '关闭导航'); overlay.hidden = true;
    document.body.append(overlay);
    document.body.classList.add('ws-account-nav-ready');
    // Skip links move keyboard focus without changing the account view's hash route.
    document.querySelectorAll('a.ba-skip').forEach(link => link.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const target = document.getElementById(link.hash.slice(1));
      if (!target) return;
      event.preventDefault();
      if (!target.hasAttribute('tabindex')) target.tabIndex = -1;
      target.focus({preventScroll:true}); target.scrollIntoView({block:'start', behavior:'auto'});
    }));
    let open = false;
    function setOpen(next, restore = true) {
      const wasOpen = open;
      const focused = document.activeElement;
      open = mobile.matches && next;
      document.body.classList.toggle('ws-menu-open', open);
      sidebar.inert = mobile.matches && !open;
      workspace.inert = open;
      overlay.hidden = !open;
      trigger.setAttribute('aria-expanded', String(open));
      trigger.setAttribute('aria-label', open ? '关闭导航' : '展开导航');
      trigger.title = open ? '关闭导航' : '展开导航';
      if (open) {
        sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true');
        focusNavigation(sidebar);
      } else {
        sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal');
        if (mobile.matches && ((wasOpen && restore) || sidebar.contains(focused))) trigger.focus({preventScroll:true});
        else if (!mobile.matches && (focused === close || focused === trigger || focused === overlay)) focusNavigation(sidebar);
      }
    }
    trigger.addEventListener('click', () => setOpen(!open));
    close.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));
    sidebar.addEventListener('click', event => {
      const link = event.target.closest('a[href]');
      if (open && link && sidebar.contains(link)) setOpen(false);
    });
    document.addEventListener('keydown', event => {
      if (!mobile.matches || !open) return;
      if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
      else trapTab(event, sidebar);
    }, true);
    window.addEventListener('hashchange', () => { if (open) setOpen(false); });
    mobile.addEventListener('change', () => setOpen(false, false));
    window.addEventListener('pageshow', () => setOpen(false, false));
    setOpen(false, false);
  }
  function legacyFocus() {
    if (!document.documentElement.classList.contains('btc-monitor')) return;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    document.addEventListener('keydown', event => {
      if (mobile.matches && document.body.classList.contains('btc-menu-open')) trapTab(event, sidebar);
    }, true);
    // A drawer opened on a phone must not leave the desktop workspace inert after resizing.
    mobile.addEventListener('change', () => {
      if (mobile.matches) return;
      const focused = document.activeElement;
      const trigger = document.querySelector('[data-btc-menu]');
      document.body.classList.remove('btc-menu-open');
      sidebar.inert = false;
      const workspace = document.getElementById('secondary_wrapper');
      if (workspace) workspace.inert = false;
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      if (focused === trigger || focused === document.querySelector('[data-btc-overlay]')) focusNavigation(sidebar);
    });
  }
  function start() { highlight(); accountDrawer(); legacyFocus(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
  window.addEventListener('hashchange', highlight);
})();
