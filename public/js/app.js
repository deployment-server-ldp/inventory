/* SPIMS front-end behaviour (no build step). */
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const body = document.body;

    // ---------------- sidebar ----------------
    const isMobile = () => window.matchMedia('(max-width: 991.98px)').matches;
    try { if (localStorage.getItem('spims.sidebar') === 'collapsed' && !isMobile()) body.classList.add('sidebar-collapsed'); } catch (e) { }
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        if (isMobile()) { body.classList.toggle('sidebar-open'); return; }
        body.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('spims.sidebar', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'); } catch (e) { }
    });
    document.getElementById('sidebarBackdrop')?.addEventListener('click', () => body.classList.remove('sidebar-open'));

    // ---------------- tooltips ----------------
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // ---------------- forms: confirm + loading + double-submit guard ----------------
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.hasAttribute('data-quick-create')) return; // handled via fetch below
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { e.preventDefault(); return; }
        if (form.dataset.submitting === '1' && !form.hasAttribute('data-allow-resubmit')) { e.preventDefault(); return; }
        if (form.method.toLowerCase() === 'post') {
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"]:not([formaction])').forEach(btn => {
                btn.disabled = true;
                if (!btn.dataset.label) btn.dataset.label = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border me-1" role="status"></span> Saving…';
            });
            // re-enable if the browser comes back (bfcache)
            window.addEventListener('pageshow', () => reset(form), { once: true });
        }
    }, true);
    function reset(form) {
        form.dataset.submitting = '0';
        form.querySelectorAll('button[type="submit"]').forEach(btn => { btn.disabled = false; if (btn.dataset.label) btn.innerHTML = btn.dataset.label; });
    }
    document.querySelectorAll('[data-confirm-click]').forEach(el => el.addEventListener('click', (e) => { if (!confirm(el.dataset.confirmClick)) e.preventDefault(); }));

    // auto-submit filters
    document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
    // period select toggles custom date inputs
    document.querySelectorAll('select[name="period"]').forEach(sel => {
        const form = sel.form;
        const sync = () => form.querySelectorAll('[data-custom-range]').forEach(w => w.classList.toggle('d-none', sel.value !== 'custom'));
        sel.addEventListener('change', () => { sync(); if (sel.value !== 'custom' && sel.hasAttribute('data-autosubmit-period')) form.submit(); });
        sync();
    });

    // ---------------- image preview on file input ----------------
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.preview);
            const file = input.files && input.files[0];
            if (!target || !file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { alert('Only JPEG, PNG or WebP images are allowed.'); input.value = ''; return; }
            const url = URL.createObjectURL(file);
            target.innerHTML = '<img src="' + url + '" class="thumb thumb-lg" alt="preview">';
        });
    });

    // ---------------- static tom-select ----------------
    window.SPIMS = window.SPIMS || {};
    document.querySelectorAll('select.tom').forEach(el => {
        new TomSelect(el, { create: el.hasAttribute('data-create'), allowEmptyOption: true, maxOptions: 500, plugins: el.multiple ? ['remove_button'] : [] });
    });

    // ---------------- part picker (remote, with thumbnails) ----------------
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const optHtml = (item) => '<div class="opt-row">' + (item.thumb ? '<img src="' + esc(item.thumb) + '" alt="">' : '<span class="thumb thumb-sm thumb-empty"><i class="bi bi-image"></i></span>') +
        '<div><div>' + esc(item.name) + '</div><div class="opt-meta">' + esc(item.sku) + (item.part_number ? ' · ' + esc(item.part_number) : '') + (item.category ? ' · ' + esc(item.category) : '') +
        (item.stock !== undefined ? ' · Stock: ' + esc(item.stock) + ' ' + esc(item.unit || '') : '') + '</div></div></div>';

    function initPartPicker(el) {
        const url = el.dataset.url;
        const ts = new TomSelect(el, {
            valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'part_number', 'category'],
            maxOptions: 50, preload: 'focus', loadThrottle: 250,
            load: (query, cb) => fetch(url + (url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } })
                .then(r => r.json()).then(j => cb(j.data)).catch(() => cb()),
            render: { option: optHtml, item: optHtml, no_results: () => '<div class="no-results p-2 text-muted">No matching parts. ' + (el.dataset.quickCreate ? 'Use “New part” to add one.' : '') + '</div>' },
            onChange: (v) => showPart(el, v ? ts.options[v] : null),
        });
        el.tomselect = ts;
        if (el.dataset.selected) {
            try { const item = JSON.parse(el.dataset.selected); ts.addOption(item); ts.setValue(item.id, true); showPart(el, item); } catch (e) { }
        }
    }
    function showPart(el, item) {
        const scope = el.closest('[data-part-scope]') || document;
        scope.querySelectorAll('[data-part-field]').forEach(node => {
            const f = node.dataset.partField;
            if (f === 'image') {
                node.innerHTML = item && item.image ? '<img src="' + esc(item.image) + '" alt="' + esc(item.name) + '">' : '<div class="placeholder-img"><i class="bi bi-image"></i></div>';
            } else if (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') {
                if (!node.dataset.touched || !item) node.value = item ? (item[f] ?? '') : '';
            } else {
                node.textContent = item ? (item[f] ?? '—') : '—';
            }
        });
        scope.querySelectorAll('[data-part-visible]').forEach(n => n.classList.toggle('d-none', !item));
        el.dispatchEvent(new CustomEvent('part:selected', { detail: item, bubbles: true }));
    }
    document.querySelectorAll('select[data-part-picker]').forEach(initPartPicker);
    document.querySelectorAll('[data-part-field]').forEach(n => n.addEventListener('input', () => { n.dataset.touched = '1'; }));
    window.SPIMS.addPartToPicker = function (selector, item) {
        const el = document.querySelector(selector);
        if (!el || !el.tomselect) return;
        el.tomselect.addOption(item); el.tomselect.setValue(item.id);
    };

    // ---------------- quick-create part modal (keeps the current form intact) ----------------
    document.querySelectorAll('form[data-quick-create]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault(); e.stopImmediatePropagation();
            const btn = form.querySelector('button[type="submit"]');
            const errBox = form.querySelector('.quick-errors');
            errBox.innerHTML = ''; btn.disabled = true;
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                const json = await res.json();
                if (!res.ok) {
                    const msgs = json.errors ? Object.values(json.errors).flat() : [json.message || 'Could not save.'];
                    errBox.innerHTML = '<div class="alert alert-danger py-2 small mb-3">' + msgs.map(esc).join('<br>') + '</div>';
                } else {
                    window.SPIMS.addPartToPicker(form.dataset.quickCreate, json.data);
                    bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
                    form.reset(); form.querySelectorAll('[id$="Preview"]').forEach(p => p.innerHTML = '');
                }
            } catch (err) {
                errBox.innerHTML = '<div class="alert alert-danger py-2 small mb-3">Network error — please retry.</div>';
            } finally { btn.disabled = false; }
        }, true);
    });

    // ---------------- charts ----------------
    const palette = ['#1d4ed8', '#0891b2', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#475569', '#0d9488', '#ca8a04', '#dc2626'];
    Chart.defaults.font.family = getComputedStyle(body).fontFamily;
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
        let cfg;
        try { cfg = JSON.parse(canvas.dataset.chart); } catch (e) { return; }
        const links = cfg.links || null;
        const type = cfg.type || 'bar';
        cfg.datasets.forEach((ds, i) => {
            const many = (type === 'doughnut' || type === 'pie') || (cfg.datasets.length === 1 && type === 'bar' && cfg.multicolor);
            ds.backgroundColor ??= many ? cfg.labels.map((_, j) => palette[j % palette.length]) : (type === 'line' ? palette[i] + '22' : palette[i % palette.length]);
            ds.borderColor ??= type === 'line' ? palette[i % palette.length] : (many ? '#fff' : palette[i % palette.length]);
            if (type === 'line') { ds.tension ??= .3; ds.fill ??= true; ds.pointRadius ??= 2; }
            if (type === 'bar') { ds.borderRadius ??= 4; ds.maxBarThickness ??= 38; }
        });
        new Chart(canvas, {
            type,
            data: { labels: cfg.labels, datasets: cfg.datasets },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: cfg.horizontal ? 'y' : 'x',
                plugins: { legend: { display: cfg.datasets.length > 1 || type === 'doughnut', position: type === 'doughnut' ? 'right' : 'top' } },
                scales: (type === 'doughnut' || type === 'pie') ? {} : { x: { grid: { display: false }, stacked: !!cfg.stacked }, y: { beginAtZero: true, stacked: !!cfg.stacked, grid: { color: '#eef1f6' } } },
                onClick: (evt, els) => { if (links && els.length && links[els[0].index]) window.location = links[els[0].index]; },
                onHover: (evt, els) => { evt.native.target.style.cursor = (links && els.length) ? 'pointer' : 'default'; },
            },
        });
    });
})();
