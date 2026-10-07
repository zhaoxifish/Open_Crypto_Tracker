/* Simplified Chinese display localization for the personal Open Crypto Tracker fork.
 * GPL-3.0-or-later. Original application copyright / attribution remains intact.
 */
(function (window, document) {
    'use strict';
    if (window.OCT_I18N) return;
    const catalog = window.OCT_ZH_CN || { messages: {}, patterns: [] };
    const normalize = value => String(value).replace(/\s+/g, ' ').trim();
    const messages = new Map(Object.entries(catalog.messages).map(([key, value]) => [normalize(key), value]));
    const folded = new Map();
    messages.forEach((value, key) => { if (!folded.has(key.toLowerCase())) folded.set(key.toLowerCase(), value); });
    const patterns = (catalog.patterns || []).map(item => ({ re: new RegExp(item.pattern, item.flags || ''), replacement: item.replacement, translateGroups: item.translateGroups || [] }));
    const htmlMessages = new Map();
    messages.forEach((value, key) => {
        if (!/<[a-z][\s\S]*>/i.test(key)) return;
        const template = document.createElement('template');
        template.innerHTML = key;
        htmlMessages.set(normalize(template.innerHTML), value);
    });
    const originalNodes = new WeakMap();
    const originalAttributes = new WeakMap();
    const inputLabels = new WeakMap();
    const untranslated = new Set();
    let locale = 'zh-CN';
    try { locale = localStorage.getItem('oct_interface_language') === 'en' ? 'en' : 'zh-CN'; } catch (_) {}
    const skipped = 'script,style,noscript,textarea,code,pre,[translate="no"],[data-i18n="skip"],[contenteditable]:not([contenteditable="false"]),.oct-language-switch,.oct-input-label,select[id$="_pair"],select[id$="_pairs"],select[id$="_mrkt"]';

    function translate(value, track, depth = 0) {
        if (typeof value !== 'string' || locale === 'en') return value;
        const key = normalize(value);
        if (!key || !/[A-Za-z]/.test(key)) return value;
        let result = messages.get(key) || folded.get(key.toLowerCase());
        if (!result) {
            const punctuation = key.match(/^(.*?)(\s*[:：…]+)$/);
            if (punctuation) {
                const stem = normalize(punctuation[1]);
                const match = messages.get(stem) || folded.get(stem.toLowerCase());
                if (match) result = match + (punctuation[2].includes(':') ? '：' : punctuation[2]);
            }
        }
        if (!result) {
            for (const entry of patterns) {
                entry.re.lastIndex = 0;
                if (entry.re.test(key)) {
                    entry.re.lastIndex = 0;
                    result = key.replace(entry.re, (...matches) => entry.replacement.replace(/\$(\d+)/g, (_, number) => {
                        const captured = matches[Number(number)] || '';
                        return depth < 3 && entry.translateGroups.includes(Number(number)) ? translate(captured, false, depth + 1) : captured;
                    }));
                    break;
                }
            }
        }
        if (!result) {
            if (track && key.length > 2 && !/^https?:\/\//i.test(key)) untranslated.add(key);
            return value;
        }
        return value.match(/^\s*/)[0] + result + value.match(/\s*$/)[0];
    }

    function excluded(element) { return !element || !!element.closest(skipped); }

    function textNode(node) {
        if (excluded(node.parentElement)) return;
        const current = node.nodeValue;
        // Chart libraries render ticker captions into SVG after configuration is
        // localized. Preserve short uppercase identifiers during that second pass.
        if (node.parentElement.closest('svg') && /^[A-Z0-9]{1,12}$/.test(normalize(current))) return;
        const previous = originalNodes.get(node);
        if (previous && current === previous.translated) return;
        const result = translate(current, true);
        if (result !== current) {
            // An option without a value attribute otherwise submits its translated label.
            const option = node.parentElement.closest('option');
            if (option && !option.hasAttribute('value')) option.setAttribute('value', option.value);
            originalNodes.set(node, { source: current, translated: result });
            node.nodeValue = result;
        }
    }

    function attributes(element) {
        if (excluded(element)) return;
        for (const name of ['title', 'placeholder', 'aria-label', 'alt', 'data-original-title', 'data-bs-original-title']) {
            if (!element.hasAttribute(name)) continue;
            const value = element.getAttribute(name);
            const records = originalAttributes.get(element) || {};
            if (records[name] && records[name].translated === value) continue;
            const result = translate(value, true);
            if (result !== value) {
                records[name] = { source: value, translated: result };
                originalAttributes.set(element, records);
                element.setAttribute(name, result);
            }
        }
        if (element.matches('input[type="submit"],input[type="button"],input[type="reset"]')) inputLabel(element);
    }

    function inputLabel(input) {
        if (excluded(input)) return;
        // Keep the original node, click handlers, name and submitted value unchanged.
        // An inert sibling renders the translated caption over the original control.
        const translated = translate(input.value, true);
        let record = inputLabels.get(input);
        if (translated === input.value && !record) return;
        if (!record) {
            const wrapper = document.createElement('span');
            wrapper.className = 'oct-input-wrap';
            const label = document.createElement('span');
            label.className = 'oct-input-label';
            label.setAttribute('aria-hidden', 'true');
            label.style.color = getComputedStyle(input).color;
            input.parentNode.insertBefore(wrapper, input);
            wrapper.append(input, label);
            input.classList.add('oct-translated-input');
            record = { label, source: input.value };
            inputLabels.set(input, record);
        }
        record.source = input.value;
        if (record.label.textContent !== translated) record.label.textContent = translated;
        if (input.getAttribute('aria-label') !== translated) input.setAttribute('aria-label', translated);
    }

    function walk(root) {
        if (locale === 'en' || !root) return;
        if (root.nodeType === Node.TEXT_NODE) { textNode(root); return; }
        if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_FRAGMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return;
        if (root.nodeType === Node.ELEMENT_NODE && excluded(root)) return;
        const nodes = [];
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                if (node.nodeType === Node.ELEMENT_NODE && excluded(node)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        if (root.nodeType === Node.ELEMENT_NODE) nodes.push(root);
        while (walker.nextNode()) nodes.push(walker.currentNode);
        for (const node of nodes) {
            if (node.nodeType === Node.TEXT_NODE) textNode(node);
            else attributes(node);
        }
    }

    function sourceText(element) {
        if (!element) return '';
        let result = '';
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            const record = originalNodes.get(node);
            result += record && record.translated === node.nodeValue ? record.source : node.nodeValue;
        }
        return result;
    }

    function translateHtml(html) {
        if (locale === 'en') return html;
        if (typeof html !== 'string' || !html.includes('<')) return translate(html);
        const direct = translate(html);
        if (direct !== html) return direct;
        const template = document.createElement('template');
        template.innerHTML = html;
        const full = htmlMessages.get(normalize(template.innerHTML));
        if (full) return full;
        walk(template.content);
        return template.innerHTML;
    }

    function chartLabels(value, key) {
        if (typeof value === 'string') {
            // Uppercase tickers can coincide with words such as ON and ALL.
            if (/^[A-Z0-9]{1,12}$/.test(value)) return value;
            return /^(text|label|title|description|tooltipText|legend-text|legendText|no-data|emptyText)$/i.test(key || '') ? translateHtml(value) : value;
        }
        if (Array.isArray(value)) return value.map(item => chartLabels(item, key));
        if (!value || typeof value !== 'object') return value;
        if (Object.getPrototypeOf(value) !== Object.prototype && Object.getPrototypeOf(value) !== null) return value;
        const copy = {};
        for (const entry of Object.keys(value)) copy[entry] = chartLabels(value[entry], entry);
        return copy;
    }

    window.OCT_I18N = {
        t: translate, html: translateHtml, scan: () => walk(document.body), sourceText,
        isMessage: (element, message) => normalize(sourceText(element)) === normalize(message) || normalize(element && element.textContent || '') === normalize(translate(message)),
        missing: () => Array.from(untranslated).sort(), locale,
        version: catalog.version || '1'
    };
    if (locale === 'en') { document.documentElement.lang = 'en'; return; }
    document.documentElement.lang = 'zh-CN';
    ['alert', 'confirm', 'prompt'].forEach(name => {
        const native = window[name];
        window[name] = function (message, ...rest) { return native.call(window, translate(message), ...rest); };
    });
    if (window.zingchart && typeof window.zingchart.render === 'function') {
        const render = window.zingchart.render;
        window.zingchart.render = function (options) {
            const localized = Object.assign({}, options, { data: chartLabels(options.data) });
            return render.call(this, localized);
        };
        if (typeof window.zingchart.exec === 'function') {
            const exec = window.zingchart.exec;
            window.zingchart.exec = function (id, command, options, ...rest) {
                // Only localize explicit chart configurations. Requests using
                // dataurl retain the library's loading/cache/error semantics;
                // their rendered SVG text is handled by the DOM observer.
                if (/^(load|setdata)$/i.test(command) && options &&
                    typeof options === 'object' && Object.prototype.hasOwnProperty.call(options, 'data')) {
                    const localized = Object.assign({}, options, { data: chartLabels(options.data) });
                    return exec.call(this, id, command, localized, ...rest);
                }
                return exec.apply(this, arguments);
            };
        }
    }
    if (window.jQuery && window.jQuery.tablesorter && window.jQuery.tablesorter.language) {
        Object.assign(window.jQuery.tablesorter.language, {
            sortAsc: '已按升序排列，', sortDesc: '已按降序排列，', sortNone: '尚未排序，',
            sortDisabled: '已禁用排序', nextAsc: '点击按升序排列', nextDesc: '点击按降序排列', nextNone: '点击取消排序'
        });
    }
    if (window.jQuery && window.jQuery.datepicker) {
        window.jQuery.datepicker.setDefaults({ closeText: '关闭', prevText: '上个月', nextText: '下个月', currentText: '今天',
            monthNames: ['一月','二月','三月','四月','五月','六月','七月','八月','九月','十月','十一月','十二月'],
            monthNamesShort: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'],
            dayNames: ['星期日','星期一','星期二','星期三','星期四','星期五','星期六'],
            dayNamesShort: ['周日','周一','周二','周三','周四','周五','周六'], dayNamesMin: ['日','一','二','三','四','五','六'], weekHeader: '周' });
    }

    function start() {
        walk(document.body);
        document.title = translate(document.title);
        let queued = false;
        const pending = new Set();
        const observer = new MutationObserver(records => {
            for (const record of records) {
                if (record.type === 'childList') record.addedNodes.forEach(node => pending.add(node));
                else pending.add(record.target);
            }
            if (!queued && pending.size) {
                queued = true;
                queueMicrotask(() => {
                    queued = false;
                    const roots = Array.from(pending);
                    pending.clear();
                    roots.forEach(node => { if (node.isConnected) walk(node); });
                });
            }
        });
        observer.observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true,
            attributeFilter: ['title','placeholder','aria-label','alt','value','data-original-title','data-bs-original-title'] });
        // Property-only .value updates do not generate MutationObserver records.
        document.addEventListener('click', () => setTimeout(() => {
            document.querySelectorAll('input[type="submit"],input[type="button"],input[type="reset"]').forEach(inputLabel);
        }, 0), true);
        // Async jobs can change a caption through .value without a DOM mutation.
        setInterval(() => {
            if (!document.hidden) document.querySelectorAll('input[type="submit"],input[type="button"],input[type="reset"]').forEach(inputLabel);
        }, 500);
        document.dispatchEvent(new CustomEvent('oct:localized'));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})(window, document);
