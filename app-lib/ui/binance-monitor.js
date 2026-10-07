/* Local, read-only Binance spot monitor. No portfolio state or prices are changed. */
(function () {
    'use strict';

    function mount() {
        const panel = document.getElementById('binance-monitor');
        if (!panel || panel.dataset.mounted === 'true') return;
        panel.dataset.mounted = 'true';
        const byId = id => document.getElementById(id);
        const refreshButton = byId('bm-refresh');
        const status = byId('bm-status');
        const notice = byId('bm-notice');
        const refreshMs = 60000;
        const timeoutMs = 12000;
        let timer = null;
        let controller = null;
        let requestId = 0;
        let lastData = null;

        const decimal = (value, digits) => {
            const number = numeric(value);
            return number === null ? '—' : number.toLocaleString('en-US', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            });
        };
        const fullTime = new Intl.DateTimeFormat('zh-CN', {
            month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
            second: '2-digit', hourCycle: 'h23'
        });
        const axisTime = new Intl.DateTimeFormat('zh-CN', {
            month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        });

        function numeric(value) {
            if (value === null || value === undefined || typeof value === 'boolean' ||
                (typeof value === 'string' && value.trim() === '')) return null;
            if (typeof value !== 'number' && typeof value !== 'string') return null;
            const number = Number(value);
            return Number.isFinite(number) ? number : null;
        }

        function timestamp(value) {
            const number = numeric(value);
            return number !== null && number > 0 && Number.isFinite(new Date(number).getTime()) ? number : null;
        }

        function text(id, value) {
            const element = byId(id);
            if (element.textContent !== value) element.textContent = value;
        }

        function setTime(id, value) {
            const element = byId(id);
            const time = timestamp(value);
            text(id, time === null ? '—' : fullTime.format(new Date(time)));
            if (time === null) element.removeAttribute('datetime');
            else element.dateTime = new Date(time).toISOString();
        }

        function setStatus(state, message) {
            status.dataset.state = state;
            if (status.textContent !== message) status.textContent = message;
        }

        function setNotice(message) {
            notice.hidden = !message;
            if (notice.textContent !== message) notice.textContent = message;
        }

        function safeError(value, fallback) {
            return typeof value === 'string' && value.trim() ? value.trim().slice(0, 200) : fallback;
        }

        function chart(history, historySource, historyStale) {
            const samples = new Map();
            if (Array.isArray(history)) history.slice(-2000).forEach(point => {
                if (!point || typeof point !== 'object') return;
                const time = timestamp(point.time);
                const price = numeric(point.price);
                if (time !== null && price !== null && price > 0) samples.set(time, {time, price});
            });
            const points = [...samples.values()].sort((a, b) => a.time - b.time);
            const svg = byId('bm-chart');
            const empty = byId('bm-chart-empty');
            const bounds = byId('bm-chart-bounds');
            const label = historyStale && points.length >= 2 ? '历史走势 · 待更新' :
                historySource === 'binance_5m_klines' ? '近 24 小时 · 5 分钟' : '自启动以来的采样走势';
            text('bm-history-label', points.length ? label : '等待历史数据');
            if (points.length < 2) {
                svg.setAttribute('hidden', '');
                bounds.hidden = true;
                empty.hidden = false;
                empty.textContent = points.length ? '已采集首个价格，后续采样将逐步形成走势。' : '暂无可用走势，等待采集服务积累真实数据。';
                text('bm-chart-start', points.length ? axisTime.format(new Date(points[0].time)) : '—');
                text('bm-chart-end', '—');
                return false;
            }
            let min = Infinity, max = -Infinity;
            points.forEach(point => { min = Math.min(min, point.price); max = Math.max(max, point.price); });
            const spread = max - min;
            const pad = spread > 0 ? spread * .12 : Math.max(max * .0001, .01);
            const floor = min - pad, ceiling = max + pad;
            const start = points[0].time, end = points[points.length - 1].time;
            const coords = points.map(point => ({
                x: 12 + (point.time - start) / (end - start) * 936,
                y: 18 + (ceiling - point.price) / (ceiling - floor) * 144
            }));
            const line = coords.map((point, i) => `${i ? 'L' : 'M'}${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');
            byId('bm-chart-line').setAttribute('d', line);
            byId('bm-chart-area').setAttribute('d', `${line} L948,174 L12,174 Z`);
            const last = coords[coords.length - 1];
            byId('bm-chart-dot').setAttribute('cx', last.x.toFixed(2));
            byId('bm-chart-dot').setAttribute('cy', last.y.toFixed(2));
            svg.removeAttribute('hidden');
            svg.setAttribute('aria-label', `币安 BTC/USDT 现货走势，${label}，共 ${points.length} 个数据点，价格范围 ${decimal(min, 2)} 至 ${decimal(max, 2)} USDT。`);
            bounds.hidden = false;
            empty.hidden = true;
            text('bm-chart-max', decimal(max, 2));
            text('bm-chart-min', decimal(min, 2));
            text('bm-chart-start', axisTime.format(new Date(start)));
            text('bm-chart-end', axisTime.format(new Date(end)));
            return true;
        }

        function render(data) {
            const ticker = data.ticker;
            text('bm-price', decimal(ticker.lastPrice, 2));
            text('bm-volume', decimal(ticker.volume, 2));
            text('bm-turnover', decimal(ticker.quoteVolume, 2));
            text('bm-high', decimal(ticker.highPrice, 2));
            text('bm-low', decimal(ticker.lowPrice, 2));
            const change = numeric(ticker.priceChangePercent);
            text('bm-change', change === null ? '—' : `${change > 0 ? '+' : ''}${decimal(change, 2)}%`);
            byId('bm-change').dataset.direction = change === null || change === 0 ? 'flat' : change > 0 ? 'up' : 'down';
            const start = timestamp(ticker.openTime), end = timestamp(ticker.closeTime);
            byId('bm-change').title = start !== null && end !== null ? `滚动统计区间：${fullTime.format(new Date(start))} — ${fullTime.format(new Date(end))}` : '币安滚动 24 小时统计';
            setTime('bm-market-time', ticker.closeTime);
            setTime('bm-sample-time', data.sampledAt);
            const hasHistory = chart(data.history, data.historySource, data.historyStale);
            const historyNotice = byId('bm-history-notice');
            const historyTime = timestamp(data.historyUpdatedAt);
            const historyMessage = data.historyStale && hasHistory ?
                `${!data.stale && numeric(ticker.lastPrice) !== null ? '最新行情已更新，走势图暂未更新。' : '走势图暂未更新。'} 上次历史更新：${historyTime === null ? '暂不可用' : fullTime.format(new Date(historyTime))}。` : '';
            historyNotice.hidden = !historyMessage;
            text('bm-history-notice', historyMessage);
            if (data.stale) {
                setStatus('stale', '数据滞后');
                setNotice(safeError(data.error, '采集暂未更新，当前显示上次取得的行情。请留意本机采集时间。'));
            } else if (numeric(ticker.lastPrice) === null) {
                setStatus('error', '最新价暂不可用');
                setNotice('部分行情字段暂不可用，缺失数据以“—”显示。');
            } else {
                setStatus('ready', '采集正常');
                setNotice('');
            }
        }

        function failed(message) {
            if (lastData) {
                setStatus('stale', '数据滞后');
                setNotice(`${message} 当前保留上次取得的行情，请留意本机采集时间。`);
            } else {
                setStatus('error', '等待行情数据');
                setNotice(message);
                chart([], 'local_samples');
            }
        }

        function clearTimer() {
            if (timer !== null) window.clearTimeout(timer);
            timer = null;
        }

        async function refresh() {
            if (document.hidden || controller) return;
            clearTimer();
            const current = ++requestId;
            const request = new AbortController();
            controller = request;
            refreshButton.disabled = true;
            if (!lastData) setStatus('loading', '正在读取');
            const timeout = window.setTimeout(() => request.abort(), timeoutMs);
            try {
                const response = await fetch('ajax/binance-monitor.php', {
                    method: 'GET', credentials: 'same-origin', cache: 'no-store',
                    headers: {'Accept': 'application/json'}, signal: request.signal
                });
                let data;
                try { data = await response.json(); } catch (_) { throw new Error('invalid_response'); }
                if (current !== requestId) return;
                if (!data || data.ok !== true || !data.ticker || typeof data.ticker !== 'object') {
                    failed(safeError(data && data.error, '暂未取得行情，采集服务就绪后会自动更新。'));
                } else if (!response.ok || !data.market || data.market.symbol !== 'BTCUSDT' || data.market.type !== 'spot' || data.market.exchange !== 'Binance') {
                    failed('行情来源校验未通过，暂时无法更新。');
                } else {
                    lastData = data;
                    render(data);
                }
            } catch (error) {
                if (current === requestId) failed(error.name === 'AbortError' ? '读取行情超时，将在下次刷新时重试。' : '暂时无法连接本机行情服务，将自动重试。');
            } finally {
                window.clearTimeout(timeout);
                if (current === requestId) {
                    controller = null;
                    refreshButton.disabled = false;
                    if (!document.hidden) timer = window.setTimeout(refresh, refreshMs);
                }
            }
        }

        refreshButton.addEventListener('click', refresh);
        document.addEventListener('visibilitychange', () => {
            clearTimer();
            if (document.hidden) {
                ++requestId;
                if (controller) controller.abort();
                controller = null;
                refreshButton.disabled = false;
            } else refresh();
        });
        window.addEventListener('pagehide', () => {
            clearTimer();
            ++requestId;
            if (controller) controller.abort();
            controller = null;
        });
        window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
        refresh();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once: true});
    else mount();
})();
