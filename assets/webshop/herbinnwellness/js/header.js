(function () {
    'use strict';
    var ctx = window.GP_HEADER_CTX || {};
    var searchBase = typeof ctx.webshop_url === 'string' ? ctx.webshop_url : '';

    var tog = document.getElementById('gp-search-toggle');
    var bar = document.getElementById('gp-search-bar');
    var input = document.getElementById('gp-search-input');
    var clearBtn = document.getElementById('gp-search-clear');
    var box = document.getElementById('gp-suggest');
    var list = document.getElementById('gp-suggest-list');
    var emptyEl = document.getElementById('gp-suggest-empty');
    var ham = document.getElementById('gp-hamburger');
    var nav = document.getElementById('gp-nav');
    if (ham && nav) ham.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        ham.setAttribute('aria-expanded', open);
    });

    if (!bar || !input || !box) return;

    var hdr = document.getElementById('gp-header');
    var suggestUrl = searchBase + '/search_suggest';
    var DEBOUNCE_MS = 220;
    var MIN_CHARS = 2;
    var REC_KEY = 'gp_search_recents_v1';
    var TRENDING = ['Vitamins', 'First aid', 'Baby care', 'Skin care', 'Pain relief'];

    var cache = Object.create(null);
    var inflight = null;
    var debounceTimer = null;
    var activeIndex = -1;
    var lastItems = [];
    var discoverMode = false;

    function isDesktopSearchToggle() {
        return window.matchMedia('(min-width:769px)').matches;
    }

    if (tog) {
        tog.addEventListener('click', function () {
            if (!isDesktopSearchToggle()) return;
            if (hdr) hdr.classList.toggle('gp-search-open');
            var open = hdr && hdr.classList.contains('gp-search-open');
            if (open) {
                setTimeout(function () { input.focus(); }, 0);
            } else {
                hideSuggest();
            }
        });
    }

    function loadRecents() {
        try { var a = JSON.parse(localStorage.getItem(REC_KEY) || '[]'); return Array.isArray(a) ? a : []; } catch (e) { return []; }
    }
    function saveRecent(q) {
        if (!q || q.length < 2) return;
        var r = loadRecents().filter(function (x) { return String(x).toLowerCase() !== q.toLowerCase(); });
        r.unshift(q.slice(0, 80));
        if (r.length > 8) r.length = 8;
        try { localStorage.setItem(REC_KEY, JSON.stringify(r)); } catch (e) {}
    }

    function renderDiscover() {
        discoverMode = true;
        lastItems = [];
        activeIndex = -1;
        var recent = loadRecents();
        var html = '';
        if (recent.length) {
            html += '<li class="gp-suggest-meta" role="presentation">Recent searches</li>';
            recent.forEach(function (term) {
                html += '<li role="presentation"><button type="button" class="gp-suggest-chip" data-q="' + escapeHtml(term) + '">' + escapeHtml(term) + '</button></li>';
            });
        }
        html += '<li class="gp-suggest-meta" role="presentation">Trending</li>';
        TRENDING.forEach(function (term) {
            html += '<li role="presentation"><button type="button" class="gp-suggest-chip" data-q="' + escapeHtml(term) + '">' + escapeHtml(term) + '</button></li>';
        });
        list.innerHTML = html;
        emptyEl.hidden = true;
        Array.prototype.forEach.call(list.querySelectorAll('.gp-suggest-chip'), function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.getAttribute('data-q') || '';
                input.value = t;
                if (clearBtn) clearBtn.hidden = t.length === 0;
                discoverMode = false;
                fetchSuggestions(t);
            });
        });
        showSuggest();
    }

    var voiceBtn = document.getElementById('gp-search-voice');
    if (voiceBtn) {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SR) {
            var recog = new SR();
            recog.continuous = false;
            recog.interimResults = false;
            recog.lang = (navigator.language || 'en-US');
            recog.onresult = function (ev) {
                var t = ev.results && ev.results[0] && ev.results[0][0] ? ev.results[0][0].transcript : '';
                if (t) {
                    input.value = t.trim();
                    if (clearBtn) clearBtn.hidden = input.value.length === 0;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };
            recog.onerror = function () {};
            voiceBtn.addEventListener('click', function () {
                try { recog.start(); } catch (e) {}
            });
        } else {
            voiceBtn.setAttribute('aria-disabled', 'true');
            voiceBtn.style.opacity = '0.4';
            voiceBtn.title = 'Voice search not supported in this browser';
        }
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        discoverMode = false;
        if (clearBtn) clearBtn.hidden = q.length === 0;
        if (debounceTimer) clearTimeout(debounceTimer);
        if (q.length < MIN_CHARS) {
            cancelInflight();
            hideSuggest();
            return;
        }
        debounceTimer = setTimeout(function () { fetchSuggestions(q); }, DEBOUNCE_MS);
    });

    input.addEventListener('focus', function () {
        var q = input.value.trim();
        if (q.length >= MIN_CHARS && lastItems.length) showSuggest();
        else if (q.length < MIN_CHARS) renderDiscover();
    });

    input.addEventListener('keydown', function (e) {
        if (box.hidden) {
            if (e.key === 'ArrowDown' && lastItems.length && !discoverMode) { e.preventDefault(); showSuggest(); setActive(0); }
            return;
        }
        if (discoverMode) {
            if (e.key === 'Escape') {
                hideSuggest();
                if (hdr) hdr.classList.remove('gp-search-open');
            }
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, lastItems.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0 && lastItems[activeIndex]) {
                window.location.href = lastItems[activeIndex].url;
            } else if (lastItems.length) {
                window.location.href = lastItems[0].url;
            }
        } else if (e.key === 'Escape') {
            hideSuggest();
            if (hdr) hdr.classList.remove('gp-search-open');
        }
    });

    if (clearBtn) clearBtn.addEventListener('click', function () {
        input.value = '';
        clearBtn.hidden = true;
        lastItems = [];
        hideSuggest();
        input.focus();
    });

    document.addEventListener('click', function (e) {
        var inSearchUi = bar.contains(e.target) || (tog && tog.contains(e.target));
        if (!inSearchUi) {
            hideSuggest();
            if (hdr && isDesktopSearchToggle()) hdr.classList.remove('gp-search-open');
        }
    });

    function cancelInflight() {
        if (inflight) { try { inflight.abort(); } catch (e) {} inflight = null; }
    }

    function fetchSuggestions(q) {
        var key = q.toLowerCase();
        if (cache[key]) {
            render(q, cache[key]);
            return;
        }
        renderLoading();
        cancelInflight();
        inflight = ('AbortController' in window) ? new AbortController() : null;
        var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
        if (inflight) opts.signal = inflight.signal;
        fetch(suggestUrl + '?q=' + encodeURIComponent(q), opts)
            .then(function (r) { return r.ok ? r.json() : { items: [] }; })
            .then(function (data) {
                var items = (data && Array.isArray(data.items)) ? data.items : [];
                cache[key] = items;
                if (input.value.trim().toLowerCase() === key) {
                    if (items.length) saveRecent(q);
                    render(q, items);
                }
            })
            .catch(function () { /* aborted or network error — silent */ });
    }

    function renderLoading() {
        discoverMode = false;
        list.innerHTML = '<li class="gp-suggest-loading"><span class="gp-suggest-spinner"></span>Searching…</li>';
        emptyEl.hidden = true;
        showSuggest();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function highlight(name, q) {
        var safe = escapeHtml(name);
        if (!q) return safe;
        var pat = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        try { return safe.replace(new RegExp('(' + pat + ')', 'ig'), '<mark>$1</mark>'); }
        catch (e) { return safe; }
    }

    function render(q, items) {
        discoverMode = false;
        lastItems = items || [];
        activeIndex = -1;
        if (!lastItems.length) {
            list.innerHTML = '';
            emptyEl.hidden = false;
            showSuggest();
            return;
        }
        emptyEl.hidden = true;
        var html = '';
        for (var i = 0; i < lastItems.length; i++) {
            var it = lastItems[i];
            var priceHtml = '';
            if (typeof it.price === 'number' && it.price > 0) {
                priceHtml = '<div class="gp-suggest-price">' + escapeHtml(it.price.toFixed(2));
                if (it.mrp && it.mrp > it.price) priceHtml += '<span class="gp-suggest-mrp">' + escapeHtml(Number(it.mrp).toFixed(2)) + '</span>';
                priceHtml += '</div>';
            }
            html += '<li role="option"><a class="gp-suggest-item" href="' + escapeHtml(it.url) + '" data-idx="' + i + '">' +
                    '<img class="gp-suggest-thumb" src="' + escapeHtml(it.image || '') + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' +
                    '<div class="gp-suggest-body"><div class="gp-suggest-name">' + highlight(it.name, q) + '</div>' + priceHtml + '</div>' +
                    '</a></li>';
        }
        list.innerHTML = html;
        Array.prototype.forEach.call(list.querySelectorAll('.gp-suggest-item'), function (el) {
            el.addEventListener('mouseenter', function () { setActive(parseInt(el.getAttribute('data-idx'), 10)); });
        });
        showSuggest();
    }

    function setActive(i) {
        activeIndex = i;
        var els = list.querySelectorAll('.gp-suggest-item');
        for (var k = 0; k < els.length; k++) {
            if (k === i) els[k].classList.add('is-active');
            else els[k].classList.remove('is-active');
        }
        if (els[i] && els[i].scrollIntoView) els[i].scrollIntoView({ block: 'nearest' });
    }

    function showSuggest() { box.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function hideSuggest() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); activeIndex = -1; discoverMode = false; }
})();
