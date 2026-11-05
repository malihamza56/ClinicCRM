/*
 * Clinic CRM - small progressive-enhancement script.
 * Every page still works without JavaScript for plain forms; this file adds comfort features:
 * mobile menu, confirm dialogs, loading buttons, patient search, time-slot picker, repeating rows and charts.
 * No inline scripts are used (the Content-Security-Policy forbids them).
 */
(function () {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const csrfToken = () => ($('input[name="_token"]') || {}).value || '';

    /* ---------- Mobile menu ---------- */
    const menuToggle = $('#menuToggle');
    if (menuToggle) {
        const setOpen = (open) => {
            document.body.classList.toggle('nav-open', open);
            menuToggle.setAttribute('aria-expanded', String(open));
        };
        menuToggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
        document.addEventListener('click', (e) => {
            if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar') && !e.target.closest('#menuToggle')) setOpen(false);
        });
    }

    /* ---------- Dismissible banners ---------- */
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-dismiss]');
        if (button) button.closest('.alert').remove();
    });

    /* ---------- Confirm dialog for risky actions: <form data-confirm="Message"> or <button data-confirm> ---------- */
    const dialog = $('#confirmDialog');
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const trigger = e.submitter && e.submitter.dataset.confirm ? e.submitter : (form.dataset.confirm ? form : null);
        if (!trigger || form.dataset.confirmed === '1') return;
        e.preventDefault();
        const message = trigger.dataset.confirm;
        if (!dialog || typeof dialog.showModal !== 'function') {
            if (window.confirm(message)) { form.dataset.confirmed = '1'; form.requestSubmit(e.submitter || undefined); }
            return;
        }
        $('#confirmText').textContent = message;
        $('#confirmOk').textContent = trigger.dataset.confirmLabel || 'Yes, continue';
        dialog.returnValue = '';
        dialog.showModal();
        dialog.addEventListener('close', function onClose() {
            dialog.removeEventListener('close', onClose);
            if (dialog.returnValue === 'ok') { form.dataset.confirmed = '1'; form.requestSubmit(e.submitter || undefined); }
        });
    });

    /* ---------- Loading state: stops double submits ---------- */
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (form.method.toLowerCase() !== 'post') return;
        const button = e.submitter || $('button[type="submit"]', form);
        if (button && !form.hasAttribute('data-no-loading')) {
            setTimeout(() => { button.classList.add('is-loading'); button.setAttribute('aria-busy', 'true'); button.disabled = true; }, 0);
        }
    });
    window.addEventListener('pageshow', () => $$('.is-loading').forEach((b) => { b.classList.remove('is-loading'); b.disabled = false; }));

    /* ---------- Show / hide password ---------- */
    $$('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = $(button.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.querySelector('.bi').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        });
    });

    /* ---------- Filters that apply as soon as they change: <select data-autosubmit> ---------- */
    $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    /* ---------- Print buttons ---------- */
    $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

    /* ---------- Patient typeahead ----------
       <div data-patient-search data-url="/patients/lookup"> with an input[type=search], a hidden input[name=patient_id]. */
    $$('[data-patient-search]').forEach((root) => {
        const input = $('input[type="search"]', root);
        const hidden = $('input[type="hidden"]', root);
        const list = $('.typeahead-list', root);
        const chosen = $('.selected-patient', root);
        let timer = null;

        const choose = (p) => {
            hidden.value = p.id;
            $('.sp-name', chosen).textContent = p.full_name;
            $('.sp-meta', chosen).textContent = p.patient_code + ' \u00b7 ' + p.phone;
            chosen.hidden = false; input.closest('.field').hidden = true; list.hidden = true;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const clear = () => { hidden.value = ''; chosen.hidden = true; input.closest('.field').hidden = false; input.value = ''; input.focus(); };
        $('.sp-clear', chosen).addEventListener('click', clear);

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { list.hidden = true; return; }
            timer = setTimeout(async () => {
                try {
                    const res = await fetch(root.dataset.url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const rows = await res.json();
                    list.innerHTML = '';
                    if (!rows.length) { list.innerHTML = '<p class="small muted" style="padding:10px">No matching patient. Check the spelling or register a new patient.</p>'; list.hidden = false; return; }
                    rows.forEach((p) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.innerHTML = '<span class="avatar"></span><span><strong></strong><small></small></span>';
                        $('.avatar', b).textContent = p.full_name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
                        $('strong', b).textContent = p.full_name;
                        $('small', b).textContent = p.patient_code + ' \u00b7 ' + p.phone;
                        b.addEventListener('click', () => choose(p));
                        list.appendChild(b);
                    });
                    list.hidden = false;
                } catch (err) { list.innerHTML = '<p class="small muted" style="padding:10px">Search is unavailable. Please try again.</p>'; list.hidden = false; }
            }, 250);
        });
        document.addEventListener('click', (e) => { if (!root.contains(e.target)) list.hidden = true; });
        if (hidden.value && chosen.dataset.name) { choose({ id: hidden.value, full_name: chosen.dataset.name, patient_code: chosen.dataset.code, phone: chosen.dataset.phone }); }
    });

    /* ---------- Time-slot picker ---------- */
    const picker = $('[data-slot-picker]');
    if (picker) {
        const doctor = $(picker.dataset.doctor);
        const date = $(picker.dataset.date);
        const box = $('.slot-box', picker);
        const selected = picker.dataset.selected || '';
        const load = async () => {
            if (!doctor.value || !date.value) { box.innerHTML = '<p class="muted small">Choose a doctor and a date to see free times.</p>'; return; }
            box.innerHTML = '<p class="muted small">Loading available times...</p>';
            try {
                const q = new URLSearchParams({ doctor_id: doctor.value, date: date.value, exclude: picker.dataset.exclude || '' });
                const res = await fetch(picker.dataset.url + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                if (!data.slots.length) { box.innerHTML = '<p class="muted small">This doctor has no working hours on that date. Try another day.</p>'; return; }
                box.innerHTML = '<div class="slot-grid" role="radiogroup" aria-label="Available times"></div>';
                const grid = $('.slot-grid', box);
                data.slots.forEach((s) => {
                    const label = document.createElement('label');
                    label.className = 'slot';
                    label.innerHTML = '<input type="radio" name="start_time"><span></span>';
                    const input = $('input', label);
                    input.value = s.start; input.disabled = !s.available; input.checked = s.available && s.start === selected;
                    $('span', label).textContent = s.label;
                    grid.appendChild(label);
                });
                if (!data.slots.some((s) => s.available)) box.insertAdjacentHTML('beforeend', '<p class="muted small" style="margin-top:8px">All times are taken. Try another day.</p>');
            } catch (err) { box.innerHTML = '<p class="muted small">Could not load times. Please refresh and try again.</p>'; }
        };
        doctor.addEventListener('change', load);
        date.addEventListener('change', load);
        load();
    }

    /* ---------- Repeating rows (medicines, invoice services) ---------- */
    $$('[data-rows]').forEach((container) => {
        const template = $(container.dataset.rows);
        const max = parseInt(container.dataset.max || '20', 10);
        const add = () => {
            if ($$('[data-row]', container).length >= max) return;
            container.appendChild(template.content.firstElementChild.cloneNode(true));
            container.dispatchEvent(new Event('rows-changed', { bubbles: true }));
        };
        const addButton = $(container.dataset.add);
        if (addButton) addButton.addEventListener('click', add);
        container.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-remove-row]');
            if (!remove) return;
            const rows = $$('[data-row]', container);
            if (rows.length > 1) remove.closest('[data-row]').remove(); else $$('input,select', rows[0]).forEach((i) => { i.value = ''; });
            container.dispatchEvent(new Event('rows-changed', { bubbles: true }));
        });
        if (!$('[data-row]', container)) add();
    });

    /* ---------- Invoice live estimate (display only - the server recalculates everything) ---------- */
    const calc = $('[data-invoice-calc]');
    if (calc) {
        const rate = parseFloat(calc.dataset.taxRate || '0');
        const symbol = calc.dataset.symbol || 'Rs.';
        const toMinor = (v) => { const m = String(v).trim().match(/^(\d+)(?:\.(\d{1,2}))?$/); return m ? parseInt(m[1], 10) * 100 + parseInt((m[2] || '').padEnd(2, '0') || '0', 10) : 0; };
        const fmt = (n) => symbol + ' ' + Math.floor(n / 100).toLocaleString('en-US') + '.' + String(n % 100).padStart(2, '0');
        const update = () => {
            let subtotal = 0;
            const doc = $('[name="consultation_doctor_id"]', calc);
            if (doc && doc.value) subtotal += toMinor(doc.selectedOptions[0].dataset.fee || '0');
            $$('[data-row]', calc).forEach((row) => {
                const sel = $('select', row); const qty = parseInt($('input[type="number"]', row).value || '0', 10);
                if (sel.value && qty > 0) subtotal += toMinor(sel.selectedOptions[0].dataset.price || '0') * qty;
            });
            let discount = toMinor($('[name="discount"]', calc).value || '0');
            if (discount > subtotal) discount = subtotal;
            const tax = Math.floor(((subtotal - discount) * Math.round(rate * 100) + 5000) / 10000);
            $('[data-sum="subtotal"]', calc).textContent = fmt(subtotal);
            $('[data-sum="discount"]', calc).textContent = '- ' + fmt(discount);
            $('[data-sum="tax"]', calc).textContent = fmt(tax);
            $('[data-sum="total"]', calc).textContent = fmt(subtotal - discount + tax);
        };
        calc.addEventListener('input', update); calc.addEventListener('change', update); calc.addEventListener('rows-changed', update);
        update();
    }

    /* ---------- Charts: <canvas data-chart='{"type":"line",...}'> ---------- */
    if (window.Chart) {
        const css = getComputedStyle(document.documentElement);
        const color = (name) => css.getPropertyValue(name).trim();
        Chart.defaults.font.family = color('--font-body');
        Chart.defaults.color = color('--muted');
        Chart.defaults.plugins.legend.display = false;

        $$('canvas[data-chart]').forEach((canvas) => {
            const cfg = JSON.parse(canvas.dataset.chart);
            const box = canvas.closest('.chart-box');
            const total = cfg.datasets.reduce((sum, d) => sum + d.data.reduce((a, b) => a + b, 0), 0);
            if (total === 0) { box.classList.add('is-empty'); return; }
            const palette = { green: color('--green-500'), orange: color('--orange-500'), red: color('--red-500'), blue: color('--blue-500'), grey: '#cdd8d1' };
            const datasets = cfg.datasets.map((d) => {
                const c = palette[d.color] || palette.green;
                if (cfg.type === 'doughnut') return { data: d.data, backgroundColor: d.colors.map((n) => palette[n]), borderWidth: 0 };
                return { label: d.label, data: d.data, borderColor: c, backgroundColor: cfg.type === 'bar' ? c : c + '22', tension: 0.35, fill: cfg.type === 'line' && d.fill === true, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2.5, borderRadius: 6 };
            });
            new Chart(canvas, {
                type: cfg.type,
                data: { labels: cfg.labels, datasets },
                options: {
                    animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450 },
                    responsive: true, maintainAspectRatio: false, cutout: cfg.type === 'doughnut' ? '72%' : undefined,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: cfg.money ? { label: (ctx) => ' ' + ctx.dataset.label + ': ' + cfg.money + ' ' + Number(ctx.parsed.y).toLocaleString('en-US') } : {} } },
                    scales: cfg.type === 'doughnut' ? {} : {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                        y: { beginAtZero: true, grid: { color: '#edf1ee' }, ticks: { precision: 0 } },
                    },
                },
            });
        });
    }
})();
