{{--
    Peningkat <select> → dropdown dengan pencarian (varian untuk baris item / select yang dibuat lewat JS).

    Pemakaian: tambahkan atribut `data-searchable` pada <select> biasa, termasuk select yang dirender oleh
    template Alpine (x-for) atau string template JS (innerHTML). Select aslinya TETAP ada di DOM (disembunyikan secara visual),
    sehingga name/id, x-model, onchange, atribut data-*, validasi `required`, dan kode JS yang membaca `.value` tetap bekerja.

    Atribut opsional pada <select>:
      data-placeholder="..."   teks bila tidak ada opsi kosong
      data-size="sm|md"        ukuran tombol (default: sm bila class mengandung text-xs)
    Atribut opsional pada <option>:
      data-subtext="..."       teks kecil di bawah label
    API global: window.enhanceSearchableSelect(el), window.refreshSearchableSelects(root?)

    Pembeda dengan <x-searchable-select>: komponen Blade itu untuk field biasa (nilai awal dari server);
    ini untuk select yang sudah ada/dibuat oleh JS dan tidak bisa diganti komponen Blade.
--}}
<script>
(function () {
    if (window.__searchableSelectEnhancer) { return; }
    window.__searchableSelectEnhancer = true;

    var valueDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    var indexDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    var instances = new Set();
    var active = null; // instance dengan panel terbuka

    var SVG_CHEVRON = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    var SVG_CLEAR = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';

    function clean(text) { return String(text || '').replace(/\s+/g, ' ').trim(); }

    function Instance(sel) {
        var self = this;
        this.sel = sel;
        this.sig = '';
        this.panel = null;
        this.highlight = 0;
        this.items = [];

        var small = sel.dataset.size ? sel.dataset.size === 'sm' : /\btext-xs\b/.test(sel.className);
        this.btnSize = small ? 'py-1.5 px-2.5 text-xs' : 'py-2.5 px-3.5 text-sm';

        this.wrap = document.createElement('div');
        this.wrap.className = 'relative w-full';
        this.wrap.setAttribute('data-ss-wrap', '');

        this.btn = document.createElement('button');
        this.btn.type = 'button';
        this.btn.setAttribute('aria-haspopup', 'listbox');
        this.btn.className = 'w-full text-left bg-white rounded-lg border border-gray-300 hover:border-gray-400 ' + this.btnSize +
            ' flex items-center justify-between gap-2 transition focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]';
        this.label = document.createElement('span');
        this.label.className = 'flex-1 truncate min-w-0';
        this.tools = document.createElement('span');
        this.tools.className = 'flex items-center gap-1 flex-shrink-0 text-gray-400';
        this.clearBtn = document.createElement('span');
        this.clearBtn.setAttribute('role', 'button');
        this.clearBtn.title = 'Hapus Pilihan';
        this.clearBtn.className = 'p-0.5 rounded hover:bg-gray-100 hover:text-gray-600 transition';
        this.clearBtn.innerHTML = SVG_CLEAR;
        this.chevron = document.createElement('span');
        this.chevron.innerHTML = SVG_CHEVRON;
        this.tools.appendChild(this.clearBtn);
        this.tools.appendChild(this.chevron);
        this.btn.appendChild(this.label);
        this.btn.appendChild(this.tools);
        this.wrap.appendChild(this.btn);

        // Wrapper di depan select; select disembunyikan secara visual (bukan display:none) agar validasi `required` tetap bisa menampilkan pesan.
        sel.parentNode.insertBefore(this.wrap, sel);
        var st = sel.style;
        st.opacity = '0'; st.pointerEvents = 'none'; st.height = '0'; st.minHeight = '0'; st.padding = '0';
        st.margin = '0'; st.border = '0'; st.overflow = 'hidden'; st.width = '100%';
        sel.tabIndex = -1;
        sel.setAttribute('data-ss-ready', '1');
        sel.setAttribute('aria-hidden', 'true');

        // Penetapan nilai lewat JS (select.value = x / selectedIndex = n) ikut memperbarui tampilan.
        Object.defineProperty(sel, 'value', {
            configurable: true,
            get: function () { return valueDesc.get.call(sel); },
            set: function (v) { valueDesc.set.call(sel, v); self.refresh(); }
        });
        Object.defineProperty(sel, 'selectedIndex', {
            configurable: true,
            get: function () { return indexDesc.get.call(sel); },
            set: function (v) { indexDesc.set.call(sel, v); self.refresh(); }
        });

        this.btn.addEventListener('click', function (e) {
            if (e.target.closest('[role="button"]') === self.clearBtn) { return; }
            self.toggle();
        });
        this.btn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); self.open(); }
        });
        this.clearBtn.addEventListener('click', function (e) { e.stopPropagation(); self.choose(''); });
        sel.addEventListener('change', function () { self.refresh(); });
        this.btn.addEventListener('mouseenter', function () { self.refresh(); });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && sel.isConnected) { self.refresh(); } });

        instances.add(this);
        this.refresh();
    }

    Instance.prototype.options = function () {
        var out = [];
        var list = this.sel.options;
        for (var i = 0; i < list.length; i++) {
            var o = list[i];
            var grp = o.parentNode && o.parentNode.tagName === 'OPTGROUP' ? clean(o.parentNode.label) : '';
            out.push({ value: o.value, text: clean(o.text), sub: clean(o.dataset ? o.dataset.subtext : ''), disabled: o.disabled, group: grp });
        }
        return out;
    };

    Instance.prototype.placeholder = function () {
        var list = this.sel.options;
        for (var i = 0; i < list.length; i++) {
            if (list[i].value === '' && clean(list[i].text) !== '') { return clean(list[i].text); }
        }
        return this.sel.dataset.placeholder || '-- Pilih --';
    };

    Instance.prototype.hasEmpty = function () {
        var list = this.sel.options;
        for (var i = 0; i < list.length; i++) { if (list[i].value === '') { return true; } }
        return false;
    };

    Instance.prototype.signature = function () {
        var s = this.sel;
        return [indexDesc.get.call(s), s.options.length, s.disabled ? 1 : 0, window.getComputedStyle(s).display === 'none' ? 0 : 1,
            s.options.length ? clean(s.options[Math.max(0, indexDesc.get.call(s))] && s.options[Math.max(0, indexDesc.get.call(s))].text) : ''].join('|');
    };

    Instance.prototype.refresh = function () {
        var s = this.sel;
        var idx = indexDesc.get.call(s);
        var opt = idx >= 0 ? s.options[idx] : null;
        var hasValue = !!(opt && opt.value !== '');
        this.label.textContent = hasValue ? clean(opt.text) : this.placeholder();
        this.label.className = 'flex-1 truncate min-w-0 ' + (hasValue ? 'text-gray-800 font-medium' : 'text-gray-400 font-normal');
        this.clearBtn.style.display = hasValue && !s.disabled && this.hasEmpty() && !s.required ? '' : 'none';
        this.btn.disabled = !!s.disabled;
        this.btn.classList.toggle('opacity-60', !!s.disabled);
        this.btn.classList.toggle('cursor-not-allowed', !!s.disabled);
        this.wrap.style.display = window.getComputedStyle(s).display === 'none' ? 'none' : '';
        this.sig = this.signature();
        if (this.panel) { this.renderList(); }
    };

    Instance.prototype.toggle = function () { this.panel ? this.close() : this.open(); };

    Instance.prototype.open = function () {
        if (this.sel.disabled || this.panel) { return; }
        if (active && active !== this) { active.close(); }
        active = this;
        var self = this;
        var p = document.createElement('div');
        p.setAttribute('data-ss-panel', '');
        p.className = 'fixed z-[70] bg-white rounded-xl shadow-xl border border-gray-200 py-2 overflow-hidden';
        p.innerHTML = '<div class="px-2.5 pb-2 border-b border-gray-100"><input type="text" autocomplete="off" placeholder="Ketik untuk mencari..." ' +
            'class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] font-medium placeholder-gray-400"></div>' +
            '<ul role="listbox" class="max-h-60 overflow-y-auto px-1 pt-1 space-y-0.5 text-xs"></ul>';
        document.body.appendChild(p);
        this.panel = p;
        this.input = p.querySelector('input');
        this.ul = p.querySelector('ul');
        this.query = '';
        this.highlight = 0;
        this.btn.setAttribute('aria-expanded', 'true');

        this.input.addEventListener('input', function () { self.query = self.input.value.toLowerCase().trim(); self.highlight = 0; self.renderList(); });
        this.input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); self.move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); self.move(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); var it = self.items[self.highlight]; if (it) { self.choose(it.value); } }
            else if (e.key === 'Escape') { e.preventDefault(); self.close(); self.btn.focus(); }
            else if (e.key === 'Tab') { self.close(); }
        });
        p.addEventListener('mousedown', function (e) { e.stopPropagation(); });

        this.renderList();
        this.place();
        this.input.focus();
        var cur = this.items.findIndex(function (it) { return it.value === valueDesc.get.call(self.sel) && it.value !== ''; });
        if (cur >= 0) { this.highlight = cur; this.renderList(); this.scrollToHighlight(); }
    };

    Instance.prototype.place = function () {
        if (!this.panel) { return; }
        var r = this.btn.getBoundingClientRect();
        var width = Math.min(Math.max(r.width, 340), window.innerWidth - 16);
        var left = Math.min(Math.max(8, r.left), Math.max(8, window.innerWidth - width - 8));
        var below = window.innerHeight - r.bottom;
        var panelH = Math.min(this.panel.offsetHeight || 300, 320);
        var s = this.panel.style;
        s.width = width + 'px';
        s.left = left + 'px';
        if (below < panelH + 12 && r.top > below) { s.top = ''; s.bottom = (window.innerHeight - r.top + 4) + 'px'; }
        else { s.bottom = ''; s.top = (r.bottom + 4) + 'px'; }
    };

    Instance.prototype.renderList = function () {
        if (!this.panel) { return; }
        var q = this.query || '';
        var self = this;
        var all = this.options();
        var cur = valueDesc.get.call(this.sel);
        this.items = all.filter(function (o) {
            if (o.value === '') { return q === '' && clean(o.text) !== ''; }
            return q === '' || o.text.toLowerCase().indexOf(q) !== -1 || o.sub.toLowerCase().indexOf(q) !== -1;
        });
        this.ul.innerHTML = '';
        var lastGroup = null;
        this.items.forEach(function (o, i) {
            if (o.group && o.group !== lastGroup) {
                var g = document.createElement('li');
                g.className = 'px-2.5 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-400';
                g.textContent = o.group;
                self.ul.appendChild(g);
            }
            lastGroup = o.group;
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.dataset.idx = i;
            var selected = o.value === cur && o.value !== '';
            li.className = 'px-2.5 py-2 rounded-lg flex items-center justify-between gap-2 transition ' +
                (o.disabled ? 'opacity-50 cursor-not-allowed ' : 'cursor-pointer ') +
                (selected ? 'bg-[#f0fdf4] text-[#14532d] font-bold' : (i === self.highlight ? 'bg-gray-100 text-gray-900' : (o.value === '' ? 'text-gray-400 italic' : 'text-gray-700')));
            var box = document.createElement('div');
            box.className = 'flex-1 truncate';
            var t = document.createElement('div');
            t.className = 'truncate';
            t.textContent = o.text;
            box.appendChild(t);
            if (o.sub) { var sb = document.createElement('div'); sb.className = 'text-[11px] text-gray-400 truncate mt-0.5'; sb.textContent = o.sub; box.appendChild(sb); }
            li.appendChild(box);
            if (selected) { var ck = document.createElement('span'); ck.className = 'text-[#15803d] font-bold'; ck.textContent = '✓'; li.appendChild(ck); }
            if (!o.disabled) {
                li.addEventListener('mouseenter', function () { self.highlight = i; self.paintHighlight(); });
                li.addEventListener('click', function () { self.choose(o.value); });
            }
            self.ul.appendChild(li);
        });
        if (!this.items.length) {
            var e = document.createElement('li');
            e.className = 'py-5 px-3 text-center text-gray-400 select-none';
            e.innerHTML = '<div class="font-semibold text-gray-600 text-xs">Tidak Ada Hasil Ditemukan</div><div class="text-[11px] text-gray-400 mt-0.5">Coba kata kunci pencarian yang lain.</div>';
            this.ul.appendChild(e);
        }
        this.place();
    };

    Instance.prototype.paintHighlight = function () {
        var cur = valueDesc.get.call(this.sel);
        var self = this;
        Array.prototype.forEach.call(this.ul.querySelectorAll('li[role="option"]'), function (li) {
            var i = parseInt(li.dataset.idx, 10);
            var o = self.items[i];
            if (!o || (o.value === cur && o.value !== '')) { return; }
            li.classList.toggle('bg-gray-100', i === self.highlight);
            li.classList.toggle('text-gray-900', i === self.highlight);
        });
    };

    Instance.prototype.move = function (d) {
        if (!this.items.length) { return; }
        this.highlight = Math.min(this.items.length - 1, Math.max(0, this.highlight + d));
        this.paintHighlight();
        this.scrollToHighlight();
    };

    Instance.prototype.scrollToHighlight = function () {
        var li = this.ul.querySelector('li[data-idx="' + this.highlight + '"]');
        if (li && li.scrollIntoView) { li.scrollIntoView({ block: 'nearest' }); }
    };

    Instance.prototype.choose = function (value) {
        var s = this.sel;
        var changed = valueDesc.get.call(s) !== value;
        valueDesc.set.call(s, value);
        this.close();
        this.refresh();
        if (changed) {
            s.dispatchEvent(new Event('input', { bubbles: true }));
            s.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.btn.focus();
    };

    Instance.prototype.close = function () {
        if (this.panel) { this.panel.remove(); this.panel = null; }
        this.btn.setAttribute('aria-expanded', 'false');
        if (active === this) { active = null; }
    };

    Instance.prototype.destroy = function () {
        this.close();
        this.wrap.remove();
        instances.delete(this);
    };

    function enhance(sel) {
        if (!sel || sel.tagName !== 'SELECT' || sel.multiple || sel.getAttribute('data-ss-ready')) { return; }
        if (!sel.parentNode) { return; }
        new Instance(sel);
    }

    function scan(root) {
        (root || document).querySelectorAll('select[data-searchable]:not([data-ss-ready])').forEach(enhance);
    }

    window.enhanceSearchableSelect = enhance;
    window.refreshSearchableSelects = function (root) {
        scan(root);
        instances.forEach(function (i) { i.refresh(); });
    };

    // Select baru (baris item yang ditambahkan lewat JS/Alpine) dienhance otomatis.
    new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) { return; }
                if (n.matches && n.matches('select[data-searchable]')) { enhance(n); }
                else if (n.querySelectorAll) { scan(n); }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    // Sinkronisasi: perubahan yang tidak memicu event (mis. x-model Alpine menetapkan option.selected, opsi diganti) dipantau ringan.
    setInterval(function () {
        if (document.hidden) { return; }
        instances.forEach(function (i) {
            if (!i.sel.isConnected) { i.destroy(); return; }
            if (i.signature() !== i.sig) { i.refresh(); }
        });
    }, 250);

    document.addEventListener('mousedown', function (e) {
        if (active && !active.wrap.contains(e.target) && !(active.panel && active.panel.contains(e.target))) { active.close(); }
    });
    window.addEventListener('resize', function () { if (active) { active.place(); } });
    window.addEventListener('scroll', function () { if (active) { active.place(); } }, true);
    document.addEventListener('reset', function (e) {
        setTimeout(function () { instances.forEach(function (i) { if (e.target.contains(i.sel)) { i.refresh(); } }); }, 0);
    });

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { scan(); }); }
    else { scan(); }
})();
</script>
