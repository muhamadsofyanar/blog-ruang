(function () {
    'use strict';
    document.querySelectorAll('[data-article-cta]').forEach(function (panel) {
        var config = JSON.parse(panel.querySelector('[data-cta-config]').textContent);
        var fields = {};
        panel.querySelectorAll('[data-cta-field]').forEach(function (field) { fields[field.dataset.ctaField] = field; });
        function refresh() {
            var custom = fields.mode.value === 'custom';
            panel.querySelector('[data-cta-custom]').hidden = !custom;
            Object.keys(fields).forEach(function (key) { if (key !== 'mode') fields[key].disabled = !custom; });
            ['title', 'label', 'url'].forEach(function (key) { fields[key].required = custom; });
            var cta = { mode: fields.mode.value };
            if (custom) ['title', 'caption', 'label'].forEach(function (key) { cta[key] = fields[key].value.replace(/<[^>]*>/g, '').trim(); });
            if (cta.mode === 'inherit') {
                var category = panel.closest('form').querySelector('[name="category_id"]');
                cta = (category && config.fallbacks[category.value]) || config.global;
            }
            panel.querySelector('[data-cta-preview]').hidden = cta.mode !== 'custom';
            panel.querySelector('[data-cta-status]').textContent = cta.mode === 'off' ? 'CTA disembunyikan.' : cta.mode === 'legacy' ? 'Mengikuti CTA Beranda (Band Bawah) yang sudah ada.' : custom ? 'Preview CTA khusus' : 'Preview CTA dari ' + (cta.source === 'category' ? 'kategori' : 'global');
            ['title', 'caption', 'label'].forEach(function (key) { panel.querySelector('[data-cta-' + key + ']').textContent = cta[key] || ''; });
        }
        panel.addEventListener('input', refresh);
        panel.addEventListener('change', refresh);
        var form = panel.closest('form');
        form.addEventListener('change', refresh);
        form.addEventListener('reset', function () { setTimeout(refresh, 0); });
        panel.addEventListener('cta:load', function (event) {
            var data = event.detail || { mode: 'inherit' };
            Object.keys(fields).forEach(function (key) {
                if (key === 'blank') fields[key].checked = !!data[key];
                else fields[key].value = data[key] || (key === 'mode' ? 'inherit' : '');
            });
            refresh();
        });
        refresh();
    });
})();
