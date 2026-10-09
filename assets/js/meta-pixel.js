/* Meta Pixel: persetujuan dahulu, satu library, pengiriman eksplisit per ID. */
(function () {
    'use strict';
    if (window.scribeMetaPixel) return;
    var node = document.getElementById('metaPixelConfig');
    if (!node) return;
    var config = JSON.parse(node.textContent);
    var panel = document.getElementById('metaConsent');
    var preferences = document.getElementById('metaPreferences');
    // URL dibaca di browser juga karena HTML dapat berasal dari page cache.
    function safeUrl(value) {
        if (!value) return true;
        try {
            var u = new URL(value, window.location.href);
            if (u.hash) return false;
            return Array.from(u.searchParams.entries()).every(function (pair) {
                return pair[0] === 'page' && /^[1-9][0-9]*$/.test(pair[1]);
            });
        } catch (e) { return false; }
    }
    if (!safeUrl(window.location.href) || !safeUrl(document.referrer)) return;
    // Jangan mengambil alih pemasangan pixel manual/plugin lain.
    if (window.fbq) return;
    window.scribeMetaPixel = true;
    var allowed = false, started = false, viewed = false;
    var ttl = 180 * 24 * 60 * 60 * 1000;
    function send(event, data, custom) {
        if (!allowed || !safeUrl(window.location.href)) return;
        config.ids.forEach(function (id) {
            window.fbq(custom ? 'trackSingleCustom' : 'trackSingle', id, event, data || {});
        });
    }
    function start() {
        if (!started) {
            started = true;
            var fbq = window.fbq = function () {
                if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments);
                else fbq.queue.push(arguments);
            };
            window._fbq = fbq; fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = [];
            fbq('consent', 'grant');
            config.ids.forEach(function (id) {
                fbq('set', 'autoConfig', false, id);
                fbq('init', id);
            });
            var script = document.createElement('script');
            script.async = true; script.src = 'https://connect.facebook.net/en_US/fbevents.js';
            document.head.appendChild(script);
        } else window.fbq('consent', 'grant');
        if (!viewed) {
            viewed = true;
            send('PageView');
            if (config.page === 'article') send('ViewContent', { content_type: 'article' });
        }
    }
    function choose(value, persist) {
        allowed = value === 'accept';
        if (persist) {
            try { localStorage.setItem(config.storageKey, JSON.stringify({ value: value, signature: config.signature, at: Date.now() })); } catch (e) {}
        }
        panel.hidden = true;
        if (allowed) start();
        else if (started) window.fbq('consent', 'revoke');
    }
    preferences.hidden = false;
    preferences.addEventListener('click', function () {
        panel.hidden = false;
        document.getElementById('metaReject').focus();
    });
    ['Accept', 'Reject'].forEach(function (name) {
        document.getElementById('meta' + name).addEventListener('click', function () {
            choose(name.toLowerCase(), true); preferences.focus();
        });
    });
    function readChoice(raw) {
        try {
            var choice = JSON.parse(raw);
            if (choice && choice.signature === config.signature && choice.at <= Date.now()
                && Date.now() - choice.at < ttl && ['accept', 'reject'].includes(choice.value)) return choice.value;
        } catch (e) {}
        return null;
    }
    var saved = null;
    try { saved = readChoice(localStorage.getItem(config.storageKey)); } catch (e) {}
    if (saved) choose(saved, false); else panel.hidden = false;
    window.addEventListener('storage', function (event) {
        if (event.key !== config.storageKey && event.key !== null) return;
        var value = readChoice(event.newValue);
        choose(value || 'reject', false);
        if (!value) panel.hidden = false;
    });
    document.addEventListener('click', function (event) {
        if (!config.cta || !allowed || !(event.target instanceof Element)) return;
        var link = event.target.closest('a[data-meta-cta], a.bio-btn');
        if (!link) return;
        var location = link.classList.contains('bio-btn') ? 'biolink' : link.getAttribute('data-meta-cta');
        if (['biolink', 'header', 'promo', 'article'].includes(location)) send('CTAClick', { placement: location }, true);
    });
})();
