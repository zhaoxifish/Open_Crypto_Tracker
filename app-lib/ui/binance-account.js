/* Private, same-origin account viewer. No legacy code or browser persistence is used. */
(function () {
    'use strict';
    const form = document.getElementById('ba-connect-form');
    if (!form) return;
    const byId = id => document.getElementById(id);
    const apiKey = byId('ba-api-key');
    const secret = byId('ba-secret');
    const otp = byId('ba-otp');
    const connectButton = byId('ba-connect');
    const disconnectButton = byId('ba-disconnect');
    const refreshButton = byId('ba-refresh');
    const timeFormat = new Intl.DateTimeFormat('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit',
        minute: '2-digit', second: '2-digit', hourCycle: 'h23'
    });
    const typeLabels = {
        LIMIT: '限价', MARKET: '市价', STOP_LOSS: '止损', STOP_LOSS_LIMIT: '限价止损',
        TAKE_PROFIT: '止盈', TAKE_PROFIT_LIMIT: '限价止盈', LIMIT_MAKER: '只挂单限价'
    };
    const orderLabels = {
        NEW: '待成交', PENDING_NEW: '待确认', PARTIALLY_FILLED: '部分成交', FILLED: '全部成交',
        CANCELED: '已撤销', PENDING_CANCEL: '撤销中', REJECTED: '已拒绝',
        EXPIRED: '已过期', EXPIRED_IN_MATCH: '撮合时过期'
    };
    let csrfToken = null;
    let requiresOtp = false;
    let connected = false;
    let busy = false;
    let activeAction = null;
    let authExpired = false;
    let controller = null;
    let generation = 0;
    let timer = null;
    let messageKind = null;

    function text(id, value) {
        const element = byId(id);
        if (element.textContent !== value) element.textContent = value;
    }

    function safeText(value, fallback = '—') {
        return typeof value === 'string' && value.trim() ? value.trim().slice(0, 240) : fallback;
    }

    // Preserve exchange decimal strings instead of rounding tiny balances through Number.
    function decimal(value) {
        if (typeof value !== 'string' && typeof value !== 'number') return '—';
        const raw = String(value).trim();
        if (!/^-?\d+(?:\.\d+)?$/.test(raw)) return '—';
        let [whole, fraction = ''] = raw.split('.');
        fraction = fraction.replace(/0+$/, '');
        whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return whole + (fraction ? '.' + fraction : '');
    }

    function nonzero(value) {
        return decimal(value) !== '—' && /[1-9]/.test(String(value));
    }

    function timestamp(value) {
        if (value === null || value === undefined || value === '' || typeof value === 'boolean') return null;
        const number = Number(value);
        return Number.isFinite(number) && number > 0 && Number.isFinite(new Date(number).getTime()) ? number : null;
    }

    function formattedTime(value) {
        const time = timestamp(value);
        return time === null ? '—' : timeFormat.format(new Date(time));
    }

    function setTime(id, value) {
        text(id, formattedTime(value));
        const time = timestamp(value);
        if (time === null) byId(id).removeAttribute('datetime');
        else byId(id).dateTime = new Date(time).toISOString();
    }

    function setStatus(state, label) {
        byId('ba-status').dataset.state = state;
        text('ba-status', label);
    }

    function setMessage(message, kind = null, tone = 'error') {
        const element = byId('ba-message');
        messageKind = kind;
        element.hidden = !message;
        element.dataset.tone = tone;
        text('ba-message', message);
    }

    function clearCredentials() {
        apiKey.value = '';
        secret.value = '';
        otp.value = '';
    }

    function clearPrivateView() {
        connected = false;
        byId('ba-account-data').hidden = true;
        byId('ba-connected-summary').hidden = true;
        for (const id of ['ba-balances', 'ba-orders', 'ba-trades']) byId(id).replaceChildren();
        for (const id of ['ba-balance-count', 'ba-order-count', 'ba-key-hint']) text(id, '—');
        text('ba-permission', '权限待验证');
        text('ba-ip-permission', 'IP 限制待确认');
        text('ba-retry-info', '后台每 60 秒采集一次');
        setTime('ba-sampled-at', null);
        setTime('ba-permission-time', null);
        byId('ba-snapshot-error').hidden = true;
        text('ba-snapshot-error', '');
    }

    function updateControls() {
        const enabled = !busy && !authExpired && Boolean(csrfToken);
        apiKey.disabled = !enabled || connected;
        secret.disabled = !enabled || connected;
        otp.disabled = !enabled || !requiresOtp;
        otp.required = requiresOtp;
        byId('ba-otp-wrap').hidden = !requiresOtp;
        byId('ba-credentials').hidden = connected;
        connectButton.hidden = connected;
        disconnectButton.hidden = !connected;
        connectButton.disabled = !enabled;
        disconnectButton.disabled = !enabled;
        refreshButton.disabled = busy;
        form.hidden = authExpired;
        connectButton.textContent = activeAction === 'connect' ? '正在验证…' : '验证并连接';
        disconnectButton.textContent = activeAction === 'disconnect' ? '正在断开…' : '断开连接';
    }

    function cell(row, value, className) {
        const element = document.createElement('td');
        element.textContent = value;
        if (className) element.className = className;
        row.append(element);
        return element;
    }

    function sideCell(row, side) {
        const element = cell(row, '');
        const badge = document.createElement('span');
        badge.className = 'ba-side';
        badge.dataset.side = side === 'BUY' ? 'buy' : side === 'SELL' ? 'sell' : 'unknown';
        badge.textContent = side === 'BUY' ? '买入' : side === 'SELL' ? '卖出' : '—';
        element.append(badge);
    }

    function renderRows(id, items, build) {
        const fragment = document.createDocumentFragment();
        for (const item of items) {
            const row = document.createElement('tr');
            build(row, item);
            fragment.append(row);
        }
        byId(id).replaceChildren(fragment);
    }

    function emptyMessage(id, length, sampled, emptyText) {
        byId(id).hidden = length > 0;
        text(id, sampled ? emptyText : '等待首次采集…');
    }

    function renderSnapshot(data) {
        connected = data.connected === true;
        byId('ba-login').hidden = true;
        if (!connected) {
            clearPrivateView();
            setStatus('disconnected', '未连接');
            text('ba-connection-description', '在本机填写 API 凭据，验证只读权限后连接。');
            text('ba-form-note', 'API 凭据仅发送到本应用的同源接口。');
            return;
        }

        const paused = data.status === 'error' && (data.readOnly === false ||
            ['unsafe_permissions', 'permissions_unverified', 'read_permission_required', 'credentials_rejected'].includes(data.errorCode));
        byId('ba-connected-summary').hidden = false;
        text('ba-key-hint', safeText(data.keyHint));
        text('ba-connection-description', paused ? '同步已暂停，请断开连接后重新配置只读密钥。' :
            '已连接现货账户，后台每 60 秒采集一次；刷新按钮只读取已采集的快照。');
        text('ba-form-note', '断开后停止账户采集；重新查看时需要再次连接。');
        const permissions = data.permissions || {};
        text('ba-permission', paused ? '只读连接验证未通过' : permissions.readOnly === true && data.readOnly === true ? '只读权限已验证' : '只读权限待验证');
        text('ba-ip-permission', permissions.ipRestricted === true ? 'IP 访问限制：已启用' : permissions.ipRestricted === false ? 'IP 访问限制：未启用' : 'IP 限制待确认');
        setTime('ba-permission-time', permissions.checkedAt);

        const scopeValid = data.scope && data.scope.market === 'BTCUSDT' && data.scope.accountType === 'SPOT';
        if (!scopeValid) {
            byId('ba-account-data').hidden = true;
            for (const id of ['ba-balances', 'ba-orders', 'ba-trades']) byId(id).replaceChildren();
            setStatus('error', '数据范围待确认');
            byId('ba-snapshot-error').hidden = false;
            text('ba-snapshot-error', '账户数据范围校验未通过，暂时不显示余额与交易记录。');
            return;
        }

        byId('ba-account-data').hidden = paused;
        const items = value => Array.isArray(value) ? value.filter(item => item && typeof item === 'object') : [];
        const balances = items(data.balances).filter(item => [item.free, item.locked, item.total].some(nonzero));
        balances.sort((a, b) => safeText(a.asset).localeCompare(safeText(b.asset)));
        const forMarket = item => !item.symbol || item.symbol === 'BTCUSDT';
        const orders = items(data.openOrders).filter(forMarket).sort((a, b) => (timestamp(b.time) || 0) - (timestamp(a.time) || 0));
        const trades = items(data.trades).filter(forMarket).sort((a, b) => (timestamp(b.time) || 0) - (timestamp(a.time) || 0)).slice(0, 100);
        const sampled = timestamp(data.sampledAt) !== null;
        text('ba-balance-count', sampled ? String(balances.length) : '—');
        text('ba-order-count', sampled ? String(orders.length) : '—');
        setTime('ba-sampled-at', data.sampledAt);
        const nextRetry = timestamp(data.nextRetryAt);
        text('ba-retry-info', nextRetry !== null && (data.stale || data.status === 'error') ? `下次尝试：${formattedTime(nextRetry)}` : '后台每 60 秒采集一次');

        renderRows('ba-balances', balances, (row, item) => {
            cell(row, safeText(item.asset), 'ba-asset');
            cell(row, decimal(item.free), 'ba-numeric');
            cell(row, decimal(item.locked), 'ba-numeric');
            cell(row, decimal(item.total), 'ba-numeric');
        });
        renderRows('ba-orders', orders, (row, item) => {
            cell(row, formattedTime(item.time));
            sideCell(row, item.side);
            cell(row, typeLabels[item.type] || '其他类型');
            cell(row, decimal(item.price), 'ba-numeric');
            cell(row, decimal(item.origQty), 'ba-numeric');
            cell(row, decimal(item.executedQty), 'ba-numeric');
            cell(row, orderLabels[item.status] || '状态待确认');
        });
        renderRows('ba-trades', trades, (row, item) => {
            cell(row, formattedTime(item.time));
            sideCell(row, item.isBuyer === true ? 'BUY' : item.isBuyer === false ? 'SELL' : item.side);
            cell(row, decimal(item.price), 'ba-numeric');
            cell(row, decimal(item.qty), 'ba-numeric');
            cell(row, decimal(item.quoteQty), 'ba-numeric');
            cell(row, `${decimal(item.commission)} ${safeText(item.commissionAsset, '')}`.trim(), 'ba-numeric');
        });
        emptyMessage('ba-balances-empty', balances.length, sampled, '现货账户暂无非零余额。');
        emptyMessage('ba-orders-empty', orders.length, sampled, 'BTC/USDT 暂无未完成挂单。');
        emptyMessage('ba-trades-empty', trades.length, sampled, '暂无可展示的 BTC/USDT 成交记录。');

        if (data.status === 'error') setStatus('error', paused ? '同步已暂停' : '采集异常');
        else if (data.stale || data.status === 'stale') setStatus('stale', sampled ? '数据滞后' : '等待首次采集');
        else if (data.status === 'ready') setStatus('ready', '采集正常');
        else setStatus('waiting', '等待首次采集');
        const snapshotError = safeText(data.error, data.stale && sampled ? '当前显示上次采集的快照，请留意最近采集时间。' : '');
        byId('ba-snapshot-error').hidden = !snapshotError;
        text('ba-snapshot-error', snapshotError);
    }

    function acceptEnvelope(envelope) {
        if (!envelope || envelope.ok !== true || !envelope.data || typeof envelope.data !== 'object' ||
            typeof envelope.csrfToken !== 'string' || !envelope.csrfToken) throw new Error('invalid_response');
        csrfToken = envelope.csrfToken;
        requiresOtp = envelope.requiresOtp === true;
        if (!requiresOtp) otp.value = '';
        authExpired = false;
        renderSnapshot(envelope.data);
    }

    function clearTimer() {
        if (timer !== null) window.clearTimeout(timer);
        timer = null;
    }

    function schedule() {
        clearTimer();
        if (!document.hidden && !authExpired) timer = window.setTimeout(() => readSnapshot(), 60000);
    }

    function expireSession() {
        clearTimer();
        csrfToken = null;
        requiresOtp = false;
        authExpired = true;
        clearCredentials();
        clearPrivateView();
        byId('ba-login').hidden = false;
        setStatus('error', '登录已失效');
        text('ba-connection-description', '登录已失效，账户数据与输入框已清空。');
        setMessage('请重新登录管理后台，再从导航中的“币安账户”返回此页。', 'auth');
    }

    async function readResponse(response) {
        try { return await response.json(); }
        catch (_) { return null; }
    }

    function responseError(response, envelope) {
        if (response.status === 429) return safeText(envelope && envelope.error, '请求较频繁，请稍后重试。');
        if (response.status === 403) return safeText(envelope && envelope.error, '页面验证已过期，请刷新显示后重试。');
        return safeText(envelope && envelope.error, '暂时无法读取账户服务，请稍后重试。');
    }

    async function readSnapshot(manual = false) {
        if (busy || document.hidden || authExpired && !manual) return;
        clearTimer();
        const current = ++generation;
        const request = new AbortController();
        controller = request;
        busy = true;
        updateControls();
        if (manual) setMessage('');
        const timeout = window.setTimeout(() => request.abort(), 12000);
        try {
            const response = await fetch('ajax/binance-account.php', {
                method: 'GET', credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json'}, signal: request.signal
            });
            const envelope = await readResponse(response);
            if (current !== generation) return;
            if (response.status === 401) { expireSession(); return; }
            if (!response.ok || !envelope || envelope.ok !== true) {
                setStatus(connected ? 'stale' : 'error', connected ? '数据滞后' : '账户服务暂不可用');
                setMessage(responseError(response, envelope) + (connected ? ' 当前保留上次采集的快照。' : ''), 'refresh');
                return;
            }
            acceptEnvelope(envelope);
            if (messageKind === 'refresh' || messageKind === 'auth') setMessage('');
        } catch (error) {
            if (current !== generation) return;
            setStatus(connected ? 'stale' : 'error', connected ? '数据滞后' : '账户服务暂不可用');
            setMessage((error.name === 'AbortError' ? '读取账户快照超时，将自动重试。' : '暂时无法连接本机账户服务，将自动重试。') + (connected ? ' 当前保留上次采集的快照。' : ''), 'refresh');
        } finally {
            window.clearTimeout(timeout);
            if (current === generation) {
                busy = false;
                controller = null;
                updateControls();
                schedule();
            }
        }
    }

    async function sendAction(action) {
        if (busy || authExpired || !csrfToken) return;
        if (action === 'connect' && !form.reportValidity()) return;
        if (requiresOtp && !/^[0-9]{6}$/.test(otp.value.trim())) {
            setMessage('请输入验证器中的 6 位动态码。', 'action');
            otp.focus();
            return;
        }
        clearTimer();
        setMessage('');
        const current = ++generation;
        const request = new AbortController();
        let body = JSON.stringify(action === 'connect' ? {
            action, apiKey: apiKey.value.trim(), secret: secret.value.trim(),
            ...(requiresOtp ? {otp: otp.value.trim()} : {})
        } : {action, ...(requiresOtp ? {otp: otp.value.trim()} : {})});
        clearCredentials();
        controller = request;
        busy = true;
        activeAction = action;
        updateControls();
        const timeout = window.setTimeout(() => request.abort(), 45000);
        let refreshToken = false;
        try {
            const pending = fetch('ajax/binance-account.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body, signal: request.signal
            });
            body = null;
            const response = await pending;
            const envelope = await readResponse(response);
            if (current !== generation) return;
            if (response.status === 401) { expireSession(); return; }
            if (!response.ok || !envelope || envelope.ok !== true) {
                if (response.status === 403) { csrfToken = null; refreshToken = true; }
                setMessage(responseError(response, envelope), 'action');
                return;
            }
            acceptEnvelope(envelope);
            setMessage(action === 'connect' ? '只读连接已建立，后台将开始采集账户快照。' : '已断开连接，账户采集已停止。', 'action', 'success');
        } catch (error) {
            if (current === generation) setMessage(error.name === 'AbortError' ? '请求超时。请先刷新显示，确认连接状态后再试。' : '未能确认操作结果。请刷新显示，确认连接状态后再试。', 'action');
        } finally {
            body = null;
            window.clearTimeout(timeout);
            if (current === generation) {
                busy = false;
                activeAction = null;
                controller = null;
                updateControls();
                if (refreshToken && !authExpired && !document.hidden) readSnapshot();
                else schedule();
            }
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        event.stopPropagation();
        sendAction('connect');
    });
    disconnectButton.addEventListener('click', () => sendAction('disconnect'));
    refreshButton.addEventListener('click', () => readSnapshot(true));
    document.addEventListener('visibilitychange', () => {
        clearTimer();
        if (document.hidden) {
            // Do not abort a requested connection change solely because the user switches tabs.
            if (controller && !activeAction) {
                ++generation;
                controller.abort();
                controller = null;
                busy = false;
                updateControls();
            }
        } else if (!busy) readSnapshot();
    });
    window.addEventListener('pagehide', () => {
        clearTimer();
        ++generation;
        if (controller) controller.abort();
        controller = null;
        csrfToken = null;
        requiresOtp = false;
        busy = false;
        activeAction = null;
        clearCredentials();
        clearPrivateView();
        setMessage('');
        setStatus('loading', '等待重新读取');
        updateControls();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) { authExpired = false; readSnapshot(true); }
    });
    clearCredentials();
    readSnapshot();
})();
