/* Authenticated balance display only. No portfolio writes or browser-storage writes. */
(function () {
    'use strict';
    function mount() {
        const panel = document.getElementById('binance-account-overview');
        if (!panel || panel.dataset.mounted === 'true') return;
        panel.dataset.mounted = 'true';
        const byId = id => document.getElementById(id);
        const refreshButton = byId('bao-refresh');
        const clock = new Intl.DateTimeFormat('zh-CN', {
            year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit',
            minute: '2-digit', second: '2-digit', hourCycle: 'h23'
        });
        const permissionErrors = ['unsafe_permissions', 'permissions_unverified', 'read_permission_required', 'credentials_rejected'];
        let privacyEnabled = readPrivacy();
        let pageLeft = false;
        let blocked = false;
        let hasSnapshot = false;
        let controller = null;
        let generation = 0;
        let timer = null;

        function readPrivacy() {
            try {
                return typeof priv_toggle_storage !== 'undefined' && localStorage.getItem(priv_toggle_storage) === 'on';
            } catch (_) {
                // An unreadable privacy preference must not reveal account data.
                return true;
            }
        }

        function portfolioActive() { return location.hash === '' || location.hash === '#portfolio'; }
        function active() { return !pageLeft && !document.hidden && portfolioActive() && !privacyEnabled && !readPrivacy(); }

        function text(id, value) {
            const element = byId(id);
            if (element.textContent !== value) element.textContent = value;
        }

        function status(state, label) {
            byId('bao-status').dataset.state = state;
            text('bao-status', label);
        }

        function notice(value) {
            byId('bao-notice').hidden = !value;
            text('bao-notice', value);
        }

        function safeError(value, fallback) {
            return typeof value === 'string' && value.trim() ? value.trim().slice(0, 240) : fallback;
        }

        function decimal(value) {
            if (typeof value !== 'string' || !/^\d+(?:\.\d+)?$/.test(value)) return '—';
            const parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts.join('.');
        }

        function nonzero(value) { return decimal(value) !== '—' && /[1-9]/.test(value); }

        function timestamp(value) {
            if (value === null || value === undefined || value === '' || typeof value === 'boolean') return null;
            const number = Number(value);
            return Number.isFinite(number) && number > 0 && Number.isFinite(new Date(number).getTime()) ? number : null;
        }

        function clearData() {
            hasSnapshot = false;
            byId('bao-data').hidden = true;
            byId('bao-balances').replaceChildren();
            for (const id of ['bao-asset-count', 'bao-btc', 'bao-usdt', 'bao-sampled-at']) text(id, '—');
            byId('bao-sampled-at').removeAttribute('datetime');
            byId('bao-empty').hidden = true;
            text('bao-empty', '现货账户暂无非零余额。');
            notice('');
        }

        function placeholder(message, label = '', href = 'binance-account.php') {
            byId('bao-placeholder').hidden = false;
            text('bao-placeholder-text', message);
            byId('bao-entry').hidden = !label;
            text('bao-entry', label);
            byId('bao-entry').setAttribute('href', href);
        }

        function clearTimer() {
            if (timer !== null) window.clearTimeout(timer);
            timer = null;
        }

        function suspend() {
            clearTimer();
            ++generation;
            if (controller) controller.abort();
            controller = null;
            clearData();
            refreshButton.disabled = true;
            if (privacyEnabled || readPrivacy()) {
                status('private', '余额已隐藏');
                placeholder('隐私模式已开启。关闭后将重新验证登录并读取账户余额。');
            } else {
                status('paused', '显示已暂停');
                placeholder('返回资产总览后，将重新验证登录并读取账户余额。');
            }
        }

        function denyAccess() {
            blocked = true;
            clearTimer();
            clearData();
            status('locked', '登录后查看');
            placeholder('账户余额仅向已登录的管理员显示。登录后返回资产总览并刷新显示。', '管理员登录', 'admin.php');
        }

        function render(data) {
            if (data.connected !== true) {
                clearData();
                status('disconnected', '尚未连接');
                placeholder('连接只读 API 后，即可在此查看币安现货余额。', '连接币安账户');
                return;
            }
            if (data.readOnly !== true || permissionErrors.includes(data.errorCode)) {
                clearData();
                status('error', '同步已暂停');
                placeholder('只读连接验证未通过，余额已清空。请在账户管理中断开后重新配置只读密钥。', '管理币安账户');
                notice(safeError(data.error, '为保护账户，当前不展示余额。'));
                return;
            }
            if (!data.scope || data.scope.accountType !== 'SPOT' || !Array.isArray(data.balances)) {
                clearData();
                status('error', '数据范围待确认');
                placeholder('未取得完整的现货余额快照，请稍后刷新显示。', '查看账户状态');
                return;
            }

            const sampledAt = timestamp(data.sampledAt);
            const sampled = sampledAt !== null;
            const balances = sampled ? data.balances.filter(item => item && typeof item === 'object' &&
                typeof item.asset === 'string' && item.asset && [item.free, item.locked, item.total].some(nonzero)) : [];
            const priority = asset => asset === 'BTC' ? 0 : asset === 'USDT' ? 1 : 2;
            balances.sort((a, b) => priority(a.asset) - priority(b.asset) || a.asset.localeCompare(b.asset, 'en'));
            const btc = balances.find(item => item.asset === 'BTC');
            const usdt = balances.find(item => item.asset === 'USDT');
            text('bao-asset-count', sampled ? String(balances.length) : '—');
            text('bao-btc', sampled ? btc ? decimal(btc.total) : '0' : '—');
            text('bao-usdt', sampled ? usdt ? decimal(usdt.total) : '0' : '—');
            text('bao-sampled-at', sampled ? clock.format(new Date(sampledAt)) : '—');
            if (sampled) byId('bao-sampled-at').dateTime = new Date(sampledAt).toISOString();
            else byId('bao-sampled-at').removeAttribute('datetime');
            const fragment = document.createDocumentFragment();
            for (const item of balances) {
                const row = document.createElement('tr');
                for (const [index, value] of [item.asset, decimal(item.free), decimal(item.locked), decimal(item.total)].entries()) {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    if (index > 0) cell.className = 'bao-number';
                    row.append(cell);
                }
                fragment.append(row);
            }
            byId('bao-balances').replaceChildren(fragment);
            byId('bao-empty').hidden = balances.length > 0;
            text('bao-empty', sampled ? '现货账户暂无非零余额。' : '等待后台完成首次采集…');
            byId('bao-placeholder').hidden = true;
            text('bao-placeholder-text', '');
            byId('bao-entry').hidden = true;
            text('bao-entry', '');
            byId('bao-data').hidden = false;
            hasSnapshot = sampled;
            if (data.status === 'error') status('error', '采集异常');
            else if (!sampled) status('waiting', '等待首次采集');
            else if (data.stale || data.status === 'stale') status('stale', '数据滞后');
            else status('ready', '采集正常');
            notice(safeError(data.error, data.stale && sampled ? '当前显示上次采集的余额，请留意最近采集时间。' : ''));
        }

        function failed(message) {
            status(hasSnapshot ? 'stale' : 'error', hasSnapshot ? '数据滞后' : '暂不可用');
            notice(message + (hasSnapshot ? ' 当前保留上次采集的余额，请留意采集时间。' : ''));
            if (!hasSnapshot) placeholder('暂时无法读取账户余额，可稍后刷新显示或查看账户状态。', '查看账户状态');
        }

        async function refresh(manual = false) {
            if (!active()) { suspend(); return; }
            if (controller || blocked && !manual) return;
            if (manual) blocked = false;
            clearTimer();
            const current = ++generation;
            const request = new AbortController();
            controller = request;
            refreshButton.disabled = true;
            if (!hasSnapshot) status('loading', '正在读取');
            const timeout = window.setTimeout(() => request.abort(), 12000);
            try {
                const response = await fetch('ajax/binance-account-overview.php', {
                    method: 'GET', credentials: 'same-origin', cache: 'no-store',
                    headers: {'Accept': 'application/json'}, signal: request.signal
                });
                if (current !== generation) return;
                if (!active()) { suspend(); return; }
                if (response.status === 401 || response.status === 403) { denyAccess(); return; }
                const envelope = await response.json();
                if (current !== generation) return;
                if (!active()) { suspend(); return; }
                if (!response.ok || !envelope || envelope.ok !== true || !envelope.data || typeof envelope.data !== 'object') {
                    failed(safeError(envelope && envelope.error, '暂时无法读取本机账户快照，将自动重试。'));
                } else render(envelope.data);
            } catch (error) {
                if (current !== generation) return;
                if (!active()) { suspend(); return; }
                failed(error.name === 'AbortError' ? '读取账户余额超时，将自动重试。' : '暂时无法连接本机账户服务，将自动重试。');
            } finally {
                window.clearTimeout(timeout);
                if (current === generation) {
                    controller = null;
                    refreshButton.disabled = !active();
                    if (active() && !blocked) timer = window.setTimeout(() => refresh(), 60000);
                }
            }
        }

        function reactivate() {
            if (!active()) { suspend(); return; }
            blocked = false;
            refreshButton.disabled = false;
            refresh();
        }

        function privacyChanged(enabled) {
            privacyEnabled = enabled;
            if (enabled) suspend();
            else reactivate();
        }

        refreshButton.addEventListener('click', () => refresh(true));
        window.addEventListener('btc-privacy-change', event => {
            privacyChanged(event.detail && typeof event.detail.enabled === 'boolean' ? event.detail.enabled : readPrivacy());
        }, true);
        window.addEventListener('storage', event => {
            if (event.key === null || typeof priv_toggle_storage !== 'undefined' && event.key === priv_toggle_storage) privacyChanged(readPrivacy());
        });
        window.addEventListener('hashchange', reactivate);
        document.addEventListener('visibilitychange', reactivate);
        window.addEventListener('pagehide', () => { pageLeft = true; suspend(); });
        window.addEventListener('pageshow', event => {
            if (event.persisted) { pageLeft = false; privacyEnabled = readPrivacy(); reactivate(); }
        });
        reactivate();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once: true});
    else mount();
})();
