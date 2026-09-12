/* ==========================================================================
   58拍卖 · 竞价台前端（auction-hall-redesign）
   倒计时（服务端时间基准）/ 3 秒轮询 / 快捷加价 / 被超越提示 / 关注
   ========================================================================== */
(function () {
    'use strict';

    var AuctionUI = window.AuctionUI = {};

    /* ---------------- 服务端时间基准 ---------------- */
    var serverOffset = 0; // server 时间 - 本机时间（秒）

    AuctionUI.setServerNow = function (ts) {
        ts = parseInt(ts, 10);
        if (!ts) return;
        serverOffset = ts - Math.floor(Date.now() / 1000);
    };
    AuctionUI.now = function () {
        return Math.floor(Date.now() / 1000) + serverOffset;
    };

    /* ---------------- 轻提示 ---------------- */
    var toastWrap = null;
    AuctionUI.toast = function (msg, type) {
        if (!toastWrap) {
            toastWrap = document.createElement('div');
            toastWrap.className = 'ac-toast-wrap';
            document.body.appendChild(toastWrap);
        }
        var el = document.createElement('div');
        el.className = 'ac-toast' + (type ? ' ac-toast-' + type : '');
        el.textContent = msg;
        toastWrap.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.parentNode && el.parentNode.removeChild(el); }, 320);
        }, 3600);
    };

    /* ---------------- 时间格式化 ---------------- */
    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function fmtCountdown(sec) {
        if (sec < 0) sec = 0;
        var d = Math.floor(sec / 86400);
        var h = Math.floor((sec % 86400) / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        if (d > 0) return d + '天 ' + pad(h) + ':' + pad(m) + ':' + pad(s);
        return pad(h) + ':' + pad(m) + ':' + pad(s);
    }

    AuctionUI.fmtRelative = function (ts) {
        var diff = AuctionUI.now() - parseInt(ts, 10);
        if (isNaN(diff)) return '';
        if (diff < 10) return '刚刚';
        if (diff < 60) return diff + ' 秒前';
        if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
        return Math.floor(diff / 86400) + ' 天前';
    };

    AuctionUI.fmtMoney = function (v) {
        var n = parseFloat(v) || 0;
        return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    /* ---------------- 倒计时 ---------------- */
    function refreshCountdowns() {
        var nodes = document.querySelectorAll('[data-end-ts]');
        var now = AuctionUI.now();
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            var endTs = parseInt(el.getAttribute('data-end-ts'), 10);
            if (isNaN(endTs)) continue;
            var left = endTs - now;
            var out = el.querySelector('[data-cd-out]');
            if (out) out.textContent = fmtCountdown(left);

            el.classList.remove('is-warn', 'is-urgent', 'is-over');
            if (el.getAttribute('data-ended') === '1' || el.getAttribute('data-pending') === '1') continue;
            if (left <= 0) {
                el.classList.add('is-over');
                if (!el.getAttribute('data-fired')) {
                    el.setAttribute('data-fired', '1');
                    el.dispatchEvent(new CustomEvent('cd:zero', { bubbles: true }));
                }
            } else if (left <= 300) {
                el.classList.add('is-urgent');
            } else if (left <= 3600) {
                el.classList.add('is-warn');
            }
        }
        // 相对时间
        var rels = document.querySelectorAll('[data-ts]');
        for (var j = 0; j < rels.length; j++) {
            rels[j].textContent = AuctionUI.fmtRelative(rels[j].getAttribute('data-ts'));
        }
    }

    /* ---------------- 叫价流渲染 ---------------- */
    function renderBids(container, bids, myId) {
        container.innerHTML = '';
        if (!bids || !bids.length) {
            var p = document.createElement('div');
            p.className = 'ac-hint';
            p.style.padding = '14px 4px';
            p.textContent = '等待第一口出价';
            container.appendChild(p);
            return;
        }
        bids.forEach(function (b) {
            var isMe = myId && parseInt(b.bidder_id, 10) === myId;
            var row = document.createElement('div');
            row.className = 'ac-bid-row' + (isMe ? ' is-me' : '');

            var who = document.createElement('div');
            who.className = 'ac-bid-who';

            var av = document.createElement('img');
            av.className = 'ac-bid-avatar';
            av.alt = '';
            av.src = b.bidder_avatar ? b.bidder_avatar : (window.DEFAULT_AVATAR || '');
            who.appendChild(av);

            var name = document.createElement('span');
            name.textContent = isMe ? '我' : (b.bidder_name || '匿名藏家');
            if (b.bidder_name && b.bidder_name.length > 1) {
                var n = b.bidder_name;
                name.textContent = isMe ? '我' : (n.charAt(0) + '***');
            }
            who.appendChild(name);
            row.appendChild(who);

            var right = document.createElement('div');
            right.style.display = 'flex';
            right.style.alignItems = 'center';
            right.style.gap = '12px';

            var amount = document.createElement('span');
            amount.className = 'ac-bid-amount';
            amount.textContent = (b.currency_symbol || '') + AuctionUI.fmtMoney(b.amount);

            var time = document.createElement('span');
            time.className = 'ac-bid-time';
            time.setAttribute('data-ts', b.created_ts);

            right.appendChild(amount);
            right.appendChild(time);
            row.appendChild(right);
            container.appendChild(row);
        });
    }

    /* ---------------- 详情页轮询 ---------------- */
    AuctionUI.initLot = function (opts) {
        opts = opts || {};
        var api = opts.api || '/api/lot.php';
        var auctionId = parseInt(opts.auctionId, 10);
        var myId = parseInt(opts.myId, 10) || 0;
        if (!auctionId) return;

        var lastKey = '';
        var timer = null;
        var stopped = false;

        function setText(sel, text) {
            document.querySelectorAll(sel).forEach(function (el) { el.textContent = text; });
        }

        function applyLot(data) {
            if (!data || data.success !== true) return;
            var d = data.data || {};
            AuctionUI.setServerNow(data.server_now);

            // 倒计时
            var cd = document.getElementById('acCountdown');
            if (cd && d.end_ts) cd.setAttribute('data-end-ts', d.end_ts);

            // 数值
            setText('[data-live="price"]', AuctionUI.fmtMoney(d.current_price));
            setText('[data-live="bid_count"]', d.bid_count);
            setText('[data-live="bidder_count"]', d.bidder_count);
            setText('[data-live="watch_count"]', d.watch_count);
            setText('[data-live="next_min"]', AuctionUI.fmtMoney(d.next_min));

            // 快捷加价基准
            var form = document.getElementById('acBidForm');
            if (form) {
                form.setAttribute('data-price', d.current_price);
                form.setAttribute('data-increment', d.bid_increment);
                form.setAttribute('data-next-min', d.next_min);
            }

            // 领先状态
            var verdict = document.getElementById('acVerdict');
            if (verdict) {
                var prev = verdict.getAttribute('data-state');
                verdict.className = 'ac-verdict ac-verdict-' +
                    (d.my_state === 'leading' ? 'lead' : (d.my_state === 'outbid' ? 'outbid' : 'none'));
                verdict.setAttribute('data-state', d.my_state);
                if (d.my_state === 'leading') {
                    verdict.innerHTML = '<i class="fas fa-crown"></i> 你正领先';
                } else if (d.my_state === 'outbid') {
                    verdict.innerHTML = '<i class="fas fa-triangle-exclamation"></i> 你已被超越，快夺回领先';
                } else {
                    verdict.innerHTML = '<i class="fas fa-eye"></i> 你尚未参与这口竞价';
                }
                if (prev && prev === 'leading' && d.my_state === 'outbid') {
                    AuctionUI.toast('你已被超越！当前价 ' + AuctionUI.fmtMoney(d.current_price), 'live');
                }
            }

            // 底价进度
            var res = document.getElementById('acReserve');
            if (res && d.reserve_price !== null) {
                var ratio = Math.min(100, Math.round(d.current_price / d.reserve_price * 100));
                var bar = res.querySelector('.ac-reserve-bar');
                var txt = res.querySelector('[data-reserve-text]');
                if (bar) {
                    bar.style.width = ratio + '%';
                    if (d.current_price >= d.reserve_price) bar.classList.add('passed');
                    else bar.classList.remove('passed');
                }
                if (txt) {
                    txt.textContent = d.current_price >= d.reserve_price
                        ? '已过底价 · 保证成交'
                        : '底价未达（' + ratio + '%）';
                }
            }

            // 叫价流：仅在内容变化时重绘
            var bidBox = document.getElementById('acBids');
            if (bidBox) {
                var key = (d.bids || []).map(function (b) { return b.id; }).join(',');
                if (key !== lastKey) {
                    var hadKey = lastKey !== '';
                    lastKey = key;
                    renderBids(bidBox, d.bids, myId);
                    var first = bidBox.querySelector('.ac-bid-row');
                    if (first && hadKey) first.classList.add('is-new');
                }
            }

            // 价格变化时闪一下
            var priceEl = document.querySelector('[data-live="price"]');
            if (priceEl && d.price_changed) {
                priceEl.classList.remove('ac-flash');
                void priceEl.offsetWidth;
                priceEl.classList.add('ac-flash');
            }
        }

        function poll() {
            if (stopped) return;
            fetch(api + '?id=' + auctionId, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(applyLot)
                .catch(function () { /* 网络抖动忽略，下个周期重试 */ });
        }

        function start() {
            if (timer) return;
            timer = setInterval(poll, 3000);
        }
        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                poll();
                start();
            }
        });

        // 倒计时归零 → 立即对齐服务端（可能发生自动延时）
        var cdEl = document.getElementById('acCountdown');
        if (cdEl) {
            cdEl.addEventListener('cd:zero', function () { poll(); });
        }

        start();
    };

    /* ---------------- 出价表单 ---------------- */
    AuctionUI.initBidForm = function (opts) {
        opts = opts || {};
        var form = document.getElementById('acBidForm');
        if (!form) return;
        var input = form.querySelector('input[name="amount"]');
        var submit = form.querySelector('button[type="submit"]');

        // 快捷加价
        document.querySelectorAll('[data-quick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var step = parseInt(btn.getAttribute('data-quick'), 10) || 1;
                var price = parseFloat(form.getAttribute('data-price')) || 0;
                var inc = parseFloat(form.getAttribute('data-increment')) || 0;
                var base = price > 0 ? price : parseFloat(form.getAttribute('data-start-price')) || 0;
                if (base <= 0 && price <= 0) {
                    input.value = '';
                    input.focus();
                    return;
                }
                var amount = price > 0 ? (price + inc * step) : base;
                input.value = amount.toFixed(2);
                input.focus();
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var amount = parseFloat(input.value);
            var nextMin = parseFloat(form.getAttribute('data-next-min')) || 0;
            if (!amount || amount <= 0) {
                AuctionUI.toast('请输入出价金额', 'err');
                return;
            }
            if (amount < nextMin) {
                AuctionUI.toast('出价需 ≥ ' + AuctionUI.fmtMoney(nextMin), 'err');
                return;
            }

            if (submit) { submit.disabled = true; }
            var body = new FormData();
            body.append('auction_id', opts.auctionId);
            body.append('amount', amount);
            body.append('csrf_token', opts.csrf || '');

            fetch(opts.api || '/api/bid.php', {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        AuctionUI.setServerNow(data.server_now);
                        AuctionUI.toast(data.message || '出价成功，你已领先', 'ok');
                        input.value = '';
                        if (typeof opts.onSuccess === 'function') opts.onSuccess(data);
                    } else {
                        AuctionUI.toast((data && (data.message || data.msg)) || '出价失败', 'err');
                    }
                })
                .catch(function () { AuctionUI.toast('网络异常，请稍后重试', 'err'); })
                .finally(function () { if (submit) { submit.disabled = false; } });
        });
    };

    /* ---------------- 关注 ---------------- */
    AuctionUI.initWatch = function (opts) {
        opts = opts || {};
        var btn = document.getElementById('acWatchBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var body = new FormData();
            body.append('auction_id', opts.auctionId);
            body.append('csrf_token', opts.csrf || '');
            btn.disabled = true;
            fetch(opts.api || '/api/watch.php', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        var d = data.data || {};
                        btn.setAttribute('data-watching', d.watching ? '1' : '0');
                        btn.innerHTML = d.watching
                            ? '<i class="fas fa-star"></i> 已关注'
                            : '<i class="far fa-star"></i> 关注';
                        document.querySelectorAll('[data-live="watch_count"]').forEach(function (el) {
                            el.textContent = d.watch_count;
                        });
                        AuctionUI.toast(data.message || '已更新', 'ok');
                    } else {
                        if (data && data.need_login) {
                            location.href = 'https://58.tl/auth/login.php';
                            return;
                        }
                        AuctionUI.toast((data && data.message) || '操作失败', 'err');
                    }
                })
                .catch(function () { AuctionUI.toast('网络异常，请稍后重试', 'err'); })
                .finally(function () { btn.disabled = false; });
        });
    };

    /* ---------------- 启动 ---------------- */
    function boot() {
        if (typeof window.AC_SERVER_NOW !== 'undefined') AuctionUI.setServerNow(window.AC_SERVER_NOW);
        refreshCountdowns();
        setInterval(refreshCountdowns, 1000);

        var page = window.AC_PAGE;
        if (page && page.auctionId) {
            AuctionUI.initBidForm({ auctionId: page.auctionId, csrf: page.csrf, api: page.apiBid });
            AuctionUI.initWatch({ auctionId: page.auctionId, csrf: page.csrf, api: page.apiWatch });
            if (page.poll) {
                AuctionUI.initLot({ auctionId: page.auctionId, myId: page.myId, api: page.apiLot });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
