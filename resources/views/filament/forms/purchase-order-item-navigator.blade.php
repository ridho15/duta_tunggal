<style>
    .dt-po-panel{padding:18px;border:1px solid #2563eb;border-radius:14px;background:#fff;color:#111827;box-shadow:0 10px 30px rgba(15,23,42,.05)}
    .dt-po-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
    .dt-po-title{font-weight:800;color:#111827}
    .dt-po-toolbar-left{display:flex;align-items:center;gap:12px;flex:1 1 620px;min-width:280px}
    .dt-po-search-wrap{position:relative;flex:1;min-width:260px}
    .dt-po-search-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9ca3af}
    .dt-po-control{width:100%;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:10px 12px;font-size:13px;line-height:1.4}
    .dt-po-search{padding-left:38px}
    .dt-po-control:focus,.dt-po-inline-input:focus,.dt-po-inline-select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
    .dt-po-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .dt-po-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
    .dt-po-button:hover:not(:disabled){background:#f8fafc}
    .dt-po-button.primary{border-color:#2563eb;background:#2563eb;color:#fff}
    .dt-po-button.primary:hover:not(:disabled){background:#1d4ed8}
    .dt-po-button:disabled,.dt-po-control:disabled{cursor:not-allowed;opacity:.55;background:#f8fafc}
    .dt-po-divider{height:1px;background:#e5e7eb;margin:14px 0}
    .dt-po-filter-drawer{display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:10px;margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc}
    .dt-po-field{display:flex;flex-direction:column;gap:5px}
    .dt-po-field label{font-size:12px;font-weight:700;color:#475569}
    .dt-po-chips{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
    .dt-po-chip{display:inline-flex;align-items:center;gap:8px;border-radius:8px;background:#eff6ff;color:#1e40af;padding:7px 11px;font-size:12px;font-weight:700}
    .dt-po-chip.muted{background:#f3f4f6;color:#6b7280}
    .dt-po-count-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0;color:#4b5563;font-size:13px}
    .dt-po-loading-status{display:inline-flex;align-items:center;gap:7px;color:#1d4ed8;font-size:12px;font-weight:800}
    .dt-po-loading-spinner{width:14px;height:14px;border:2px solid #bfdbfe;border-top-color:#2563eb;border-radius:999px;animation:dt-po-spin .7s linear infinite}
    .dt-po-table-wrap{position:relative;overflow:auto;border:1px solid #e5e7eb;border-radius:12px}
    .dt-po-table-wrap.is-loading .dt-po-table{opacity:.55}
    .dt-po-loading-overlay{position:absolute;inset:0;z-index:4;display:flex;align-items:center;justify-content:center;background:rgba(248,250,252,.58);pointer-events:none}
    .dt-po-loading-card{display:inline-flex;align-items:center;gap:9px;padding:9px 13px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;color:#1d4ed8;font-size:12px;font-weight:800;box-shadow:0 8px 24px rgba(15,23,42,.1)}
    .dt-po-table{width:100%;border-collapse:collapse;min-width:980px;font-size:13px}
    .dt-po-table th{background:#f8fafc;color:#475569;text-align:left;font-weight:800;padding:11px 10px;border-bottom:1px solid #e5e7eb;white-space:nowrap}
    .dt-po-table td{padding:11px 10px;border-bottom:1px solid #eef2f7;vertical-align:middle}
    .dt-po-row:hover td,.dt-po-row.is-expanded td{background:#fbfdff}
    .dt-po-expand-col{width:34px;text-align:center}
    .dt-po-expand{border:0;background:transparent;color:#475569;cursor:pointer;width:26px;height:26px;border-radius:8px;font-size:17px;line-height:1}
    .dt-po-expand:hover{background:#eef2ff;color:#1d4ed8}
    .dt-po-product{font-weight:700;color:#111827;max-width:310px}
    .dt-po-product small{display:block;margin-top:3px;color:#6b7280;font-weight:600}
    .dt-po-number{text-align:right;white-space:nowrap}
    .dt-po-badge{display:inline-flex;border-radius:999px;background:#eff6ff;color:#1e40af;padding:4px 9px;font-size:11px;font-weight:800}
    .dt-po-badge.manual{background:#f3f4f6;color:#374151}
    .dt-po-action-col{position:sticky;right:0;background:#fff;box-shadow:-8px 0 12px -12px rgba(15,23,42,.45);text-align:center;min-width:86px}
    .dt-po-table th.dt-po-action-col{background:#f8fafc;z-index:1}
    .dt-po-icon-button{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e5e7eb;background:#fff;color:#1d4ed8;border-radius:9px;cursor:pointer;font-weight:900}
    .dt-po-icon-button:hover{background:#eff6ff;border-color:#93c5fd}
    .dt-po-icon-button.danger{color:#dc2626}
    .dt-po-icon-button.danger:hover{background:#fef2f2;border-color:#fecaca}
    .dt-po-icon-button:disabled{opacity:.45;cursor:not-allowed;background:#f8fafc;color:#94a3b8}
    .dt-po-icon-button:disabled:hover{background:#f8fafc;border-color:#e5e7eb}
    .dt-po-detail-row td{padding:0;border-bottom:1px solid #e5e7eb;background:#fff}
    .dt-po-detail-card{margin:0 42px 12px 82px;border:1px solid #e5e7eb;border-radius:11px;overflow:visible;background:#fff}
    .dt-po-detail-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:13px 16px;border-bottom:1px solid #e5e7eb;background:#f8fafc;font-size:13px}
    .dt-po-inline-editor{padding:16px;background:#fff}
    .dt-po-inline-editor .select2-container{width:100%!important;min-width:0}
    .dt-po-inline-editor .select2-container .select2-selection--single{height:38px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    .dt-po-inline-editor .select2-container .select2-selection--single .select2-selection__rendered{line-height:36px;padding-left:11px;padding-right:34px;font-size:13px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .dt-po-inline-editor .select2-container .select2-selection--single .select2-selection__arrow{height:36px;right:8px}
    .dt-po-inline-editor .select2-container--focus .select2-selection--single{border-color:#2563eb;box-shadow:0 0 0 2px #bfdbfe}
    body > .select2-container--open{z-index:99999!important}
    .dt-po-inline-select2-dropdown{z-index:99999!important;border-color:#d1d5db;border-radius:10px;overflow:hidden;font-size:13px}
    .dt-po-inline-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
    .dt-po-inline-field{display:flex;flex-direction:column;gap:6px;grid-column:span 3}
    .dt-po-inline-field.wide{grid-column:span 6}
    .dt-po-inline-field label{font-size:12px;font-weight:800;color:#475569}
    .dt-po-inline-input,.dt-po-inline-select{width:100%;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:9px 11px;font-size:13px;line-height:1.4}
    .dt-po-inline-select{padding-right:34px}
    .dt-po-inline-input[readonly],.dt-po-inline-select:disabled{background:#f8fafc;color:#64748b;cursor:not-allowed}
    .dt-po-tax-select{background-repeat:no-repeat!important;background-position:right 11px center!important;background-size:18px 18px!important}
    .dt-po-filter-select{background-repeat:no-repeat!important;background-position:right 12px center!important;background-size:18px 18px!important;padding-right:38px}
    .dt-po-lock-note{display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;border:1px solid #dbeafe;border-radius:10px;background:#eff6ff;color:#1e40af;padding:8px 10px;font-size:12px;font-weight:800}
    .dt-po-money-wrap{display:flex;align-items:stretch;min-width:0}
    .dt-po-money-prefix{display:inline-flex;align-items:center;justify-content:center;min-width:48px;padding:0 10px;border:1px solid #d1d5db;border-right:0;border-radius:10px 0 0 10px;background:#f8fafc;color:#475569;font-size:12px;font-weight:800}
    .dt-po-money-wrap .dt-po-inline-input{border-radius:0 10px 10px 0;min-width:0}
    .dt-po-empty{text-align:center;color:#6b7280;padding:22px!important}
    .dt-po-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:14px;font-weight:800}
    .dt-po-footer-total span:last-child{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px}
    .dt-po-large-repeater,.dt-po-large-repeater .fi-fo-repeater-item{height:0!important;min-height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}
    .dt-po-large-repeater [data-dt-or-bulk-toggle],.dt-po-large-repeater [data-dt-or-add-item]{display:none}
    [x-cloak]{display:none!important}
    @media (max-width:1024px){.dt-po-toolbar-left{flex-basis:100%}.dt-po-filter-drawer{grid-template-columns:1fr 1fr}.dt-po-detail-card{margin-left:14px;margin-right:14px}.dt-po-inline-field,.dt-po-inline-field.wide{grid-column:span 6}}
    @media (max-width:640px){.dt-po-panel{padding:12px}.dt-po-toolbar-left,.dt-po-actions{width:100%}.dt-po-button{flex:1}.dt-po-filter-drawer{grid-template-columns:1fr}.dt-po-inline-field,.dt-po-inline-field.wide{grid-column:span 12}}
    @keyframes dt-po-spin{to{transform:rotate(360deg)}}
</style>

<div
    class="dt-po-panel"
    data-dt-po-navigator
    data-current-search="{{ e($search) }}"
    data-current-tax="{{ e((string) ($taxFilter ?? '')) }}"
    data-current-source="{{ e((string) ($sourceFilter ?? '')) }}"
    data-current-cabang="{{ e((string) ($cabangFilter ?? '')) }}"
    x-data="{
        expandedKey: window.__dtPoExpandedItemKey || null,
        filterOpen: false,
        searchValue: '',
        taxValue: '',
        sourceValue: '',
        cabangValue: '',
        isLoading: false,
        loadingMessage: '',
        observer: null,
        select2RefreshTimer: null,
        select2AssetsPromise: null,
        inlineSelectChangeHandler: null,
        init() {
            this.searchValue = this.$root.dataset.currentSearch || '';
            this.taxValue = this.$root.dataset.currentTax || '';
            this.sourceValue = this.$root.dataset.currentSource || '';
            this.cabangValue = this.$root.dataset.currentCabang || '';

            this.$nextTick(() => {
                if (this.expandedKey) {
                    this.initInlineSelects(this.expandedKey);
                }

                const target = this.$root.closest('form') || this.$root;
                this.observer = new MutationObserver(() => this.refreshInlineSelects(100));
                this.observer.observe(target, { childList: true, subtree: true });
            });

            this.registerInlineSelectRefreshHooks();

            this.inlineSelectChangeHandler = (event) => {
                const select = event.target?.closest?.('select[data-dt-po-inline-select]');
                if (! select || ! this.$root.contains(select)) return;
                if (this.isSelect2Managed(select)) return;

                this.handleInlineSelectChange(select);
            };

            this.$root.addEventListener('change', this.inlineSelectChangeHandler);
        },
        destroy() {
            this.observer?.disconnect();
            if (this.inlineSelectChangeHandler) {
                this.$root.removeEventListener('change', this.inlineSelectChangeHandler);
            }
            window.clearTimeout(this.select2RefreshTimer);
        },
        startLoading(message) {
            this.loadingMessage = message;
            this.isLoading = true;
        },
        finishLoading() {
            this.isLoading = false;
            this.loadingMessage = '';
        },
        async setNavigatorState(field, value) {
            this.startLoading('Memperbarui tampilan item...');
            try {
                this.$wire.set('data.' + field, value === '' ? null : value, false);
                return await this.$wire.$commit();
            } finally {
                this.finishLoading();
            }
        },
        async updateInlineItem(key, field, value, message = 'Menghitung item...') {
            this.startLoading(message);
            try {
                const result = await this.$wire.updateInlinePurchaseOrderItemField(String(key), String(field), value);
                this.$nextTick(() => {
                    this.syncInlineSelect2Values(String(key));
                    this.syncInlineMoneyInputs(String(key));
                });

                return result;
            } finally {
                this.finishLoading();
                this.refreshInlineSelects();
            }
        },
        async addItem() {
            this.startLoading('Menambahkan item...');
            try {
                const key = await this.$wire.addInlinePurchaseOrderItem();
                this.expandedKey = String(key);
                window.__dtPoExpandedItemKey = this.expandedKey;
                this.$nextTick(() => this.openItemWhenReady(key));
                window.setTimeout(() => this.openItemWhenReady(key), 120);
            } finally {
                this.finishLoading();
            }
        },
        async removeItem(key) {
            this.startLoading('Menghapus item...');
            try {
                const removed = await this.$wire.removeInlinePurchaseOrderItem(String(key));
                if (removed && this.expandedKey === String(key)) {
                    this.expandedKey = null;
                    window.__dtPoExpandedItemKey = null;
                }
            } finally {
                this.finishLoading();
            }
        },
        toggleDetail(key) {
            if (this.expandedKey === String(key)) {
                this.expandedKey = null;
                window.__dtPoExpandedItemKey = null;
                return;
            }

            this.expandedKey = String(key);
            window.__dtPoExpandedItemKey = this.expandedKey;
            this.$nextTick(() => this.openItemWhenReady(this.expandedKey));
        },
        openItemWhenReady(key, attempt = 0) {
            const row = this.$root.querySelector('[data-dt-po-row=' + CSS.escape(String(key)) + ']');

            if (row || attempt >= 4) {
                this.expandedKey = String(key);
                window.__dtPoExpandedItemKey = this.expandedKey;
                this.$nextTick(() => {
                    this.initInlineSelects(this.expandedKey);
                    this.syncInlineSelect2Values(this.expandedKey);
                    this.syncInlineMoneyInputs(this.expandedKey);
                    row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                return;
            }

            window.setTimeout(() => this.openItemWhenReady(key, attempt + 1), 120);
        },
        refreshInlineSelects(delay = 80) {
            if (! this.expandedKey) return;

            window.clearTimeout(this.select2RefreshTimer);
            this.select2RefreshTimer = window.setTimeout(() => {
                const editor = this.$root.querySelector('[data-dt-po-inline-editor=' + CSS.escape(String(this.expandedKey)) + ']');
                if (editor?.querySelector('select[data-dt-select2-open]')) return;

                this.$nextTick(() => {
                    this.initInlineSelects(this.expandedKey);
                    this.syncInlineSelect2Values(this.expandedKey);
                    this.syncInlineMoneyInputs(this.expandedKey);
                });
            }, delay);
        },
        isElementVisibleForSelect2(element) {
            if (! element || ! element.isConnected) return false;

            const editor = element.closest('[data-dt-po-inline-editor]');
            const row = element.closest('.dt-po-detail-row');
            const editorRect = editor?.getBoundingClientRect();
            const rowRect = row?.getBoundingClientRect();

            return Boolean(
                editor
                && row
                && window.getComputedStyle(row).display !== 'none'
                && editorRect?.width > 0
                && editorRect?.height > 0
                && rowRect?.width > 0
                && rowRect?.height > 0
            );
        },
        destroyInlineSelect2(select, jq = window.jQuery || window.$) {
            if (! select) return;

            const $select = jq?.fn?.select2 ? jq(select) : null;

            if ($select?.data('select2')) {
                try {
                    $select.select2('destroy');
                } catch (error) {
                    $select.removeData('select2');
                }
            }

            if (select.nextElementSibling?.classList?.contains('select2')) {
                select.nextElementSibling.remove();
            }

            select.classList.remove('select2-hidden-accessible');
            select.removeAttribute('data-select2-id');
            select.removeAttribute('aria-hidden');
            select.removeAttribute('tabindex');
            select.style.removeProperty('display');
            select.style.removeProperty('visibility');
        },
        isSelect2Managed(select) {
            if (! select) return false;

            const jq = window.jQuery || window.$;

            return Boolean(jq?.fn?.select2 && jq(select).data('select2'));
        },
        syncInlineSelect2Values(key = this.expandedKey) {
            if (! key) return;

            const editor = this.$root.querySelector('[data-dt-po-inline-editor=' + CSS.escape(String(key)) + ']');
            const jq = window.jQuery || window.$;
            if (! editor || ! jq?.fn?.select2) return;

            editor
                .querySelectorAll('select[data-dt-po-inline-select]')
                .forEach((select) => {
                    if (! jq(select).data('select2')) return;

                    const renderedValue = select.value || '';
                    jq(select).val(renderedValue).trigger('change.select2');
                });
        },
        syncInlineMoneyInputs(key = this.expandedKey) {
            if (! key) return;

            const editor = this.$root.querySelector('[data-dt-po-inline-editor=' + CSS.escape(String(key)) + ']');
            if (! editor) return;

            editor
                .querySelectorAll('input[data-dt-po-inline-money]')
                .forEach((input) => {
                    const renderedValue = input.getAttribute('value');

                    if (document.activeElement === input) return;

                    if (renderedValue !== null && input.value !== renderedValue) {
                        input.value = renderedValue;
                    }
                });
        },
        registerInlineSelectRefreshHooks() {
            if (window.__dtPoSelect2LivewireHooksRegistered) return;
            window.__dtPoSelect2LivewireHooksRegistered = true;

            const refreshNavigators = () => {
                document
                    .querySelectorAll('[data-dt-po-navigator]')
                    .forEach((navigator) => {
                        const alpine = navigator._x_dataStack?.[0];
                        alpine?.refreshInlineSelects?.(120);
                        alpine?.syncInlineSelect2Values?.();
                        alpine?.syncInlineMoneyInputs?.();
                    });
            };

            const registerLivewireHook = () => {
                if (! window.Livewire || typeof window.Livewire.hook !== 'function') return;

                window.Livewire.hook('message.processed', () => refreshNavigators());
                window.Livewire.hook('morph.updated', () => refreshNavigators());
            };

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                registerLivewireHook();
            } else {
                document.addEventListener('livewire:load', registerLivewireHook, { once: true });
                document.addEventListener('livewire:init', registerLivewireHook, { once: true });
            }

            document.addEventListener('filament:page:loaded', refreshNavigators);
        },
        ensureSelect2Assets() {
            const existingJq = window.jQuery || window.$;
            if (existingJq?.fn?.select2) {
                window.jQuery = existingJq;
                return Promise.resolve(existingJq);
            }

            if (window.__dtPoSelect2AssetsPromise) {
                return window.__dtPoSelect2AssetsPromise;
            }

            const loadStyle = () => {
                if (document.querySelector('link[data-dt-po-select2]')) return;
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
                link.dataset.dtPoSelect2 = 'true';
                document.head.appendChild(link);
            };
            const loadScript = (src, marker) => new Promise((resolve, reject) => {
                const existing = document.querySelector('script[' + marker + ']');
                if (existing) {
                    if (existing.dataset.loaded === 'true') return resolve();
                    existing.addEventListener('load', resolve, { once: true });
                    existing.addEventListener('error', reject, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.setAttribute(marker, 'true');
                script.addEventListener('load', () => {
                    script.dataset.loaded = 'true';
                    resolve();
                }, { once: true });
                script.addEventListener('error', reject, { once: true });
                document.head.appendChild(script);
            });

            window.__dtPoSelect2AssetsPromise = (async () => {
                loadStyle();
                if (! (window.jQuery || window.$)) {
                    await loadScript('https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js', 'data-dt-po-jquery');
                }
                const jq = window.jQuery || window.$;
                if (jq && ! window.jQuery) {
                    window.jQuery = jq;
                }
                if (! jq?.fn?.select2) {
                    await loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'data-dt-po-select2-script');
                }
                const loadedJq = window.jQuery || window.$;
                if (! loadedJq?.fn?.select2) {
                    throw new Error('Select2 assets loaded but jQuery/select2 global is unavailable.');
                }

                window.jQuery = loadedJq;
                return loadedJq;
            })();

            return window.__dtPoSelect2AssetsPromise;
        },
        async initInlineSelects(key, attempt = 0) {
            const editor = this.$root.querySelector('[data-dt-po-inline-editor=' + CSS.escape(String(key)) + ']');
            if (! editor) return;

            const inlineSelects = Array.from(editor.querySelectorAll('select[data-dt-po-inline-select]'));
            const isVisible = inlineSelects.some((element) => this.isElementVisibleForSelect2(element));

            if (! isVisible) {
                inlineSelects.forEach((element) => this.destroyInlineSelect2(element));

                if (attempt < 8 && this.expandedKey === String(key)) {
                    window.setTimeout(() => this.initInlineSelects(key, attempt + 1), 90);
                }

                return;
            }

            let jq;
            try {
                jq = await this.ensureSelect2Assets();
            } catch (error) {
                editor
                    .querySelectorAll('select[data-dt-po-inline-select]')
                    .forEach((element) => this.destroyInlineSelect2(element));
                console.warn('Select2 inline PO tidak dapat dimuat; memakai native select.', error);
                return;
            }

            inlineSelects.forEach((element) => {
                let $select = jq(element);
                const searchMethod = element.dataset.searchMethod;
                const hasRenderedSelect2 = element.nextElementSibling?.classList?.contains('select2');
                const renderedSelect2 = hasRenderedSelect2 ? element.nextElementSibling : null;
                const renderedRect = renderedSelect2?.getBoundingClientRect();

                if (
                    ($select.data('select2') && ! hasRenderedSelect2)
                    || (! $select.data('select2') && hasRenderedSelect2)
                    || ($select.data('select2') && renderedSelect2 && (! renderedRect?.width || ! renderedRect?.height))
                ) {
                    this.destroyInlineSelect2(element, jq);
                    $select = jq(element);
                }

                if (! $select.data('select2')) {
                    $select.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: element.dataset.placeholder || 'Pilih data',
                        dropdownParent: jq(document.body),
                        dropdownCssClass: 'dt-po-inline-select2-dropdown',
                        ajax: {
                            delay: 250,
                            transport: (params, success, failure) => {
                                const search = params.data?.term || '';
                                const request = searchMethod === 'products'
                                    ? this.$wire.searchInlinePurchaseOrderProducts(search)
                                    : this.$wire.searchInlinePurchaseOrderCurrencies(search);

                                Promise.resolve(request)
                                    .then((results) => success({ results: results || [] }))
                                    .catch(failure);

                                return { abort() {} };
                            },
                            processResults: (data) => data,
                        },
                    });

                    const container = element.nextElementSibling;
                    const containerRect = container?.getBoundingClientRect();
                    if (container?.classList?.contains('select2') && (! containerRect?.width || ! containerRect?.height)) {
                        this.destroyInlineSelect2(element, jq);

                        if (attempt < 8 && this.expandedKey === String(key)) {
                            window.setTimeout(() => this.initInlineSelects(key, attempt + 1), 90);
                        }

                        return;
                    }
                }

                $select.off('.dtPoInline');
                $select.on('select2:open.dtPoInline', () => {
                    element.setAttribute('data-dt-select2-open', 'true');
                });
                $select.on('select2:close.dtPoInline', () => {
                    element.removeAttribute('data-dt-select2-open');
                });
                $select.on('select2:select.dtPoInline', (event) => {
                    this.handleInlineSelectChange(element, event.params?.data?.id ?? element.value);
                });
                $select.on('select2:clear.dtPoInline', () => {
                    this.handleInlineSelectChange(element, '');
                });
            });

            this.syncInlineSelect2Values(key);
        },
        async handleInlineSelectChange(select, forcedValue = null) {
            if (! select || select.dataset.dtSelect2Updating === 'true') return;

            const editor = select.closest('[data-dt-po-inline-editor]');
            const key = editor?.dataset?.dtPoInlineEditor;
            const field = select.dataset.field;

            if (! key || ! field) return;

            select.setAttribute('data-dt-select2-updating', 'true');

            const label = field
                .replace('_id', '')
                .replace('currency', 'mata uang');

            try {
                await this.updateInlineItem(key, field, forcedValue ?? select.value, 'Memperbarui ' + label + '...');
            } finally {
                window.setTimeout(() => {
                    select.removeAttribute('data-dt-select2-updating');
                }, 0);
            }
        },
    }"
    x-bind:aria-busy="isLoading.toString()"
>
    <div class="dt-po-title">Purchase order item<span style="color:#dc2626">*</span></div>

    <div class="dt-po-toolbar" style="margin-top:12px">
        <div class="dt-po-toolbar-left">
            <div class="dt-po-search-wrap">
                <svg class="dt-po-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M9 15.5A6.5 6.5 0 1 0 9 2.5a6.5 6.5 0 0 0 0 13ZM14 14l3.5 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
                <input
                    type="search"
                    class="dt-po-control dt-po-search"
                    placeholder="Search item / product / source..."
                    x-model="searchValue"
                    x-on:input.debounce.500ms="setNavigatorState('_purchase_order_item_search', searchValue)"
                >
            </div>
        </div>
        <div class="dt-po-actions">
            <button type="button" class="dt-po-button" x-on:click="filterOpen = ! filterOpen">Filter</button>
            <button type="button" class="dt-po-button primary" x-on:click="addItem()">Tambah Item</button>
        </div>
    </div>

    <div class="dt-po-filter-drawer" x-show="filterOpen" x-cloak>
        <div class="dt-po-field">
            <label>Tipe Pajak</label>
            <select class="dt-po-control dt-po-filter-select" x-model="taxValue" x-on:change="setNavigatorState('_purchase_order_item_tax_filter', taxValue)">
                <option value="">Semua tipe</option>
                @foreach ($taxOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="dt-po-field">
            <label>Sumber Item</label>
            <select class="dt-po-control dt-po-filter-select" x-model="sourceValue" x-on:change="setNavigatorState('_purchase_order_item_source_filter', sourceValue)">
                <option value="">Semua sumber</option>
                <option value="order_request">Dari Order Request</option>
                <option value="manual">Manual</option>
            </select>
        </div>
        <div class="dt-po-field">
            <label>Cabang Item</label>
            <select class="dt-po-control dt-po-filter-select" x-model="cabangValue" x-on:change="setNavigatorState('_purchase_order_item_cabang_filter', cabangValue)">
                <option value="">Semua cabang</option>
                @foreach ($cabangOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="dt-po-chips">
        <span style="color:#6b7280;font-size:13px;">Active filters:</span>
        @if ($search !== '')
            <span class="dt-po-chip">Search: {{ $search }}</span>
        @endif
        @if ($taxFilter)
            <span class="dt-po-chip">Tipe Pajak: {{ strtoupper($taxFilter) }}</span>
        @endif
        @if ($sourceFilter)
            <span class="dt-po-chip">Sumber: {{ $sourceFilter === 'order_request' ? 'Dari Order Request' : 'Manual' }}</span>
        @endif
        @if ($cabangFilter && isset($cabangOptions[$cabangFilter]))
            <span class="dt-po-chip">Cabang: {{ $cabangOptions[$cabangFilter] }}</span>
        @endif
        @if ($search === '' && ! $taxFilter && ! $sourceFilter && ! $cabangFilter)
            <span class="dt-po-chip muted">Tidak ada filter aktif</span>
        @endif
    </div>

    <div class="dt-po-count-row">
        <div>Showing {{ number_format($matchedCount, 0, ',', '.') }} of {{ number_format($totalItems, 0, ',', '.') }} items</div>
        <template x-if="isLoading">
            <div class="dt-po-loading-status"><span class="dt-po-loading-spinner"></span><span x-text="loadingMessage"></span></div>
        </template>
    </div>

    <div class="dt-po-table-wrap" x-bind:class="{ 'is-loading': isLoading }">
        <template x-if="isLoading">
            <div class="dt-po-loading-overlay"><div class="dt-po-loading-card"><span class="dt-po-loading-spinner"></span><span x-text="loadingMessage"></span></div></div>
        </template>
        <table class="dt-po-table">
            <thead>
                <tr>
                    <th class="dt-po-expand-col"></th>
                    <th>#</th>
                    <th>Product</th>
                    <th>Source</th>
                    <th class="dt-po-number">Qty</th>
                    <th>Unit</th>
                    <th class="dt-po-number">Unit Price</th>
                    <th class="dt-po-number">Subtotal</th>
                    <th>Tipe Pajak</th>
                    <th class="dt-po-action-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr
                        class="dt-po-row"
                        data-dt-po-row="{{ $row['key'] }}"
                        wire:key="dt-po-row-{{ $row['key'] }}"
                        x-bind:class="{ 'is-expanded': expandedKey === @js($row['key']) }"
                    >
                        <td class="dt-po-expand-col">
                            <button type="button" class="dt-po-expand" x-on:click="toggleDetail(@js($row['key']))">
                                <span x-show="expandedKey !== @js($row['key'])">+</span>
                                <span x-show="expandedKey === @js($row['key'])">-</span>
                            </button>
                        </td>
                        <td>{{ $row['number'] }}</td>
                        <td class="dt-po-product">{{ $row['product'] }}<small>{{ $row['refer_item_model_id'] ? 'OR Item #' . $row['refer_item_model_id'] : 'Manual item' }}</small></td>
                        <td><span class="dt-po-badge {{ $row['is_order_request_backed'] ? '' : 'manual' }}">{{ $row['source'] }}</span></td>
                        <td class="dt-po-number">{{ number_format((float) $row['quantity'], 2, ',', '.') }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="dt-po-number">{{ $row['currency_symbol'] }} {{ $row['unit_price'] }}</td>
                        <td class="dt-po-number">{{ $row['currency_symbol'] }} {{ $row['subtotal'] }}</td>
                        <td>{{ strtoupper($row['tipe_pajak']) }}</td>
                        <td class="dt-po-action-col">
                            <button type="button" class="dt-po-icon-button danger" x-on:click="removeItem(@js($row['key']))" @disabled($row['is_order_request_backed']) title="{{ $row['is_order_request_backed'] ? 'Item dari Order Request tidak bisa dihapus' : 'Hapus item' }}">x</button>
                        </td>
                    </tr>
                    <tr class="dt-po-detail-row" wire:key="dt-po-detail-{{ $row['key'] }}" x-show="expandedKey === @js($row['key'])" x-cloak>
                        <td colspan="10">
                            <div class="dt-po-detail-card">
                                <div class="dt-po-detail-summary">
                                    <strong>{{ $row['product'] }}</strong>
                                    <span class="dt-po-badge {{ $row['is_order_request_backed'] ? '' : 'manual' }}">{{ $row['source'] }}</span>
                                </div>
                                <div class="dt-po-inline-editor" data-dt-po-inline-editor="{{ $row['key'] }}">
                                    @if ($row['is_order_request_backed'])
                                        <div class="dt-po-lock-note">Dikunci dari Order Request</div>
                                    @endif
                                    <div class="dt-po-inline-grid">
                                        <div class="dt-po-inline-field wide">
                                            <label>Product</label>
                                            <select
                                                class="dt-po-inline-select"
                                                data-dt-po-inline-select
                                                data-field="product_id"
                                                data-search-method="products"
                                                data-placeholder="Pilih product"
                                                x-bind:disabled="isLoading || @js($row['is_order_request_backed'])"
                                                @disabled($row['is_order_request_backed'])
                                            >
                                                <option value="">Pilih product</option>
                                                @foreach ($row['product_options'] as $id => $label)
                                                    <option value="{{ $id }}" @selected((string) $row['product_id'] === (string) $id)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Quantity</label>
                                            <input type="number" step="0.01" min="0" class="dt-po-inline-input" value="{{ $row['quantity'] }}" x-bind:disabled="isLoading" x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'quantity', $event.target.value)">
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Unit</label>
                                            <input type="text" class="dt-po-inline-input" value="{{ $row['unit'] }}" readonly>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Mata Uang</label>
                                            <select
                                                class="dt-po-inline-select"
                                                data-dt-po-inline-select
                                                data-field="currency_id"
                                                data-search-method="currencies"
                                                data-placeholder="Pilih mata uang"
                                                x-bind:disabled="isLoading || @js($row['is_order_request_backed'])"
                                                @disabled($row['is_order_request_backed'])
                                            >
                                                <option value="">Pilih mata uang</option>
                                                @foreach ($row['currency_options'] as $id => $label)
                                                    <option value="{{ $id }}" @selected((string) $row['currency_id'] === (string) $id)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Unit Price</label>
                                            <div class="dt-po-money-wrap">
                                                <span class="dt-po-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input type="text" class="dt-po-inline-input" value="{{ $row['unit_price'] }}" data-dt-po-inline-money x-bind:disabled="isLoading || @js($row['is_order_request_backed'])" x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'unit_price', $event.target.value)" @readonly($row['is_order_request_backed'])>
                                            </div>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Discount (%)</label>
                                            <input type="number" step="0.01" min="0" max="100" class="dt-po-inline-input" value="{{ $row['discount'] }}" x-bind:disabled="isLoading || @js($row['is_order_request_backed'])" x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'discount', $event.target.value)" @readonly($row['is_order_request_backed'])>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Tipe Pajak</label>
                                            <select class="dt-po-inline-select dt-po-tax-select" x-bind:disabled="isLoading || @js($row['is_order_request_backed'])" x-on:change="updateInlineItem(@js($row['key']), 'tipe_pajak', $event.target.value)" @disabled($row['is_order_request_backed'])>
                                                @foreach ($taxOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($row['tipe_pajak'] === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Tax (%)</label>
                                            <input type="number" class="dt-po-inline-input" value="{{ $row['tax'] }}" readonly>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Discount nominal</label>
                                            <div class="dt-po-money-wrap"><span class="dt-po-money-prefix">{{ $row['currency_symbol'] }}</span><input type="text" class="dt-po-inline-input" value="{{ $row['discount_nominal'] }}" data-dt-po-inline-money readonly></div>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Tax nominal</label>
                                            <div class="dt-po-money-wrap"><span class="dt-po-money-prefix">{{ $row['currency_symbol'] }}</span><input type="text" class="dt-po-inline-input" value="{{ $row['tax_nominal'] }}" data-dt-po-inline-money readonly></div>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Total</label>
                                            <div class="dt-po-money-wrap"><span class="dt-po-money-prefix">{{ $row['currency_symbol'] }}</span><input type="text" class="dt-po-inline-input" value="{{ $row['total'] }}" data-dt-po-inline-money readonly></div>
                                        </div>
                                        <div class="dt-po-inline-field">
                                            <label>Subtotal</label>
                                            <div class="dt-po-money-wrap"><span class="dt-po-money-prefix">{{ $row['currency_symbol'] }}</span><input type="text" class="dt-po-inline-input" value="{{ $row['subtotal'] }}" data-dt-po-inline-money readonly></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr wire:key="dt-po-empty-row"><td class="dt-po-empty" colspan="10">Belum ada item. Klik Tambah Item untuk mulai mengisi Purchase Order.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="dt-po-footer">
        <div>Total Items: {{ number_format($totalItems, 0, ',', '.') }}</div>
        <div>Total Qty: {{ number_format($totalQty, 2, ',', '.') }}</div>
        <div class="dt-po-footer-total">Subtotal Preview: <span>Rp {{ number_format($totalSubtotal, 2, ',', '.') }}</span></div>
    </div>
</div>
