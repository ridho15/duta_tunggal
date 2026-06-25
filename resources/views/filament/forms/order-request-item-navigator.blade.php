<style>
    .dt-item-panel{padding:18px;border:1px solid #2563eb;border-radius:14px;background:#fff;color:#111827;box-shadow:0 10px 30px rgba(15,23,42,.05)}
    .dt-item-toolbar{display:flex;flex-direction:column;align-items:stretch;gap:12px}
    .dt-item-title{font-weight:800;color:#111827}
    .dt-item-title .required{color:#dc2626}
    .dt-item-toolbar-main{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
    .dt-item-toolbar-left{display:flex;align-items:center;gap:12px;flex:1 1 520px;min-width:0;flex-wrap:wrap}
    .dt-item-search-wrap{position:relative;flex:1 1 320px;min-width:240px}
    .dt-item-search-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9ca3af}
    .dt-item-control{width:100%;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:10px 12px;font-size:13px;line-height:1.4}
    .dt-item-search{padding-left:38px}
    .dt-item-control:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
    .dt-item-nav-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex:0 0 auto;flex-wrap:wrap}
    .dt-item-bulk-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;width:100%;padding:10px 0 0;border-top:1px solid #eef2f7}
    .dt-item-bulk-actions .dt-item-control{flex:1 1 190px;max-width:260px;padding:8px 10px;font-size:12px}
    .dt-item-bulk-actions .dt-item-nav-button{padding:8px 12px;font-size:12px}
    .dt-item-nav-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
    .dt-item-nav-button:hover:not(:disabled){background:#f8fafc}
    .dt-item-nav-button.primary{border-color:#2563eb;background:#2563eb;color:#fff}
    .dt-item-nav-button.primary:hover:not(:disabled){background:#1d4ed8}
    .dt-item-nav-button:disabled,.dt-item-control:disabled{cursor:not-allowed;opacity:.55;background:#f8fafc}
    .dt-item-filter-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:999px;background:#2563eb;color:#fff;font-size:11px;font-weight:800}
    .dt-item-bulk-caret{font-size:12px;color:#6b7280}
    .dt-item-divider{height:1px;background:#e5e7eb;margin:14px 0}
    .dt-item-filter-drawer{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:10px;margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc}
    .dt-item-field{display:flex;flex-direction:column;gap:5px}
    .dt-item-field label{font-size:12px;font-weight:700;color:#475569}
    select.dt-item-control{background-image:none!important;padding-right:10px}
    .dt-item-chips{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .dt-item-chip-label{color:#6b7280;font-size:13px}
    .dt-item-chip{display:inline-flex;align-items:center;gap:8px;border-radius:8px;background:#eff6ff;color:#1e40af;padding:7px 11px;font-size:12px;font-weight:700}
    .dt-item-chip.muted{background:#f3f4f6;color:#6b7280}
    .dt-item-chip-clear{border:0;background:transparent;color:#64748b;padding:0;cursor:pointer;font-weight:900}
    .dt-item-clear-link{border:0;background:transparent;color:#1d4ed8;text-decoration:underline;font-size:13px;font-weight:700;cursor:pointer}
    .dt-item-count-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0;color:#4b5563;font-size:13px}
    .dt-item-loading-status{display:inline-flex;align-items:center;gap:7px;color:#1d4ed8;font-size:12px;font-weight:800}
    .dt-item-loading-spinner{width:14px;height:14px;border:2px solid #bfdbfe;border-top-color:#2563eb;border-radius:999px;animation:dt-item-spin .7s linear infinite}
    .dt-item-table-wrap{position:relative;overflow:auto;border:1px solid #e5e7eb;border-radius:12px}
    .dt-item-table-wrap.is-loading .dt-item-table{opacity:.55}
    .dt-item-loading-overlay{position:absolute;inset:0;z-index:4;display:flex;align-items:center;justify-content:center;background:rgba(248,250,252,.58);pointer-events:none}
    .dt-item-loading-card{display:inline-flex;align-items:center;gap:9px;padding:9px 13px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;color:#1d4ed8;font-size:12px;font-weight:800;box-shadow:0 8px 24px rgba(15,23,42,.1)}
    .dt-item-add-feedback{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:12px 0 0;padding:12px 14px;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff;color:#1e3a8a;font-size:13px}
    .dt-item-add-feedback strong{font-weight:900}
    .dt-item-add-feedback-chip{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:#dbeafe;color:#1d4ed8;padding:5px 10px;font-size:12px;font-weight:800}
    .dt-item-add-feedback-action{border:1px solid #93c5fd;background:#fff;color:#1d4ed8;border-radius:9px;padding:7px 10px;font-size:12px;font-weight:800;cursor:pointer}
    .dt-item-add-feedback-action:hover{background:#f8fbff}
    .dt-item-table{width:100%;border-collapse:collapse;min-width:980px;font-size:13px}
    .dt-item-table th{background:#f8fafc;color:#475569;text-align:left;font-weight:800;padding:11px 10px;border-bottom:1px solid #e5e7eb;white-space:nowrap}
    .dt-item-table td{padding:11px 10px;border-bottom:1px solid #eef2f7;vertical-align:middle}
    .dt-item-row:hover td{background:#fbfdff}
    .dt-item-row.is-expanded td{background:#fcfdff}
    .dt-item-expand-col,.dt-item-check-col{width:34px;text-align:center}
    .dt-item-expand{border:0;background:transparent;color:#475569;cursor:pointer;width:26px;height:26px;border-radius:8px;font-size:17px;line-height:1}
    .dt-item-expand:hover{background:#eef2ff;color:#1d4ed8}
    .dt-item-checkbox{width:16px;height:16px;cursor:pointer;accent-color:#2563eb}
    .dt-item-status-badge{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800;white-space:nowrap}
    .dt-item-status-badge.draft{background:#f3f4f6;color:#4b5563}
    .dt-item-status-badge.approved{background:#dcfce7;color:#166534}
    .dt-item-status-badge.rejected{background:#fee2e2;color:#991b1b}
    .dt-item-rejection-note{display:block;margin-top:4px;color:#991b1b;font-size:11px;font-weight:700;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .dt-item-lock-banner{grid-column:span 12;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:10px;padding:9px 11px;font-size:12px;font-weight:800}
    .dt-item-product{font-weight:700;color:#111827;max-width:310px}
    .dt-item-product small{display:block;margin-top:3px;color:#6b7280;font-weight:600}
    .dt-item-number{text-align:right;white-space:nowrap}
    .dt-item-tax{display:inline-flex;border-radius:999px;background:#fef3c7;color:#92400e;padding:4px 9px;font-size:11px;font-weight:800}
    .dt-item-action-col{position:sticky;right:0;background:#fff;box-shadow:-8px 0 12px -12px rgba(15,23,42,.45);text-align:center;min-width:112px}
    .dt-item-table th.dt-item-action-col{background:#f8fafc;z-index:1}
    .dt-item-row-actions{display:inline-flex;align-items:center;justify-content:center;gap:7px}
    .dt-item-icon-button{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e5e7eb;background:#fff;color:#1d4ed8;border-radius:9px;cursor:pointer;font-weight:900}
    .dt-item-icon-button:hover{background:#eff6ff;border-color:#93c5fd}
    .dt-item-icon-button.danger{color:#dc2626}
    .dt-item-icon-button.danger:hover{background:#fef2f2;border-color:#fecaca}
    .dt-item-detail-row td{padding:0;border-bottom:1px solid #e5e7eb;background:#fff}
    .dt-item-detail-card{margin:0 42px 12px 82px;border:1px solid #e5e7eb;border-radius:11px;overflow:visible;background:#fff}
    .dt-item-detail-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:13px 16px;border-bottom:1px solid #e5e7eb;background:#f8fafc;font-size:13px}
    .dt-item-detail-summary strong{font-weight:800;color:#111827}
    .dt-item-detail-footer{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;border-top:1px solid #e5e7eb;padding:13px 20px;font-size:13px}
    .dt-item-detail-footer strong{font-weight:800}
    .dt-item-inline-editor{padding:16px;background:#fff}
    .dt-item-inline-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
    .dt-item-inline-field{display:flex;flex-direction:column;gap:6px;grid-column:span 3}
    .dt-item-inline-field.wide{grid-column:span 6}
    .dt-item-inline-field.full{grid-column:span 12}
    .dt-item-inline-field label{font-size:12px;font-weight:800;color:#475569}
    .dt-item-inline-input{width:100%;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:9px 11px;font-size:13px;line-height:1.4}
    .dt-item-inline-input:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
    .dt-item-inline-input[readonly],.dt-item-inline-input:disabled{background:#f8fafc;color:#64748b}
    .dt-item-inline-input:disabled{cursor:not-allowed;opacity:.65}
    .dt-item-inline-select{width:100%;min-width:0;border:1px solid #d1d5db;border-radius:10px;background-color:#fff;color:#111827;padding:9px 38px 9px 11px;font-size:13px;line-height:1.4;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;background-size:18px 18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .dt-item-inline-select::-ms-expand{display:none}
    .dt-item-inline-select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
    .dt-item-inline-select:disabled{cursor:not-allowed;opacity:.65;background-color:#f8fafc}
    .dt-item-inline-editor .select2-container{width:100%!important;min-width:0}
    .dt-item-inline-editor .select2-container .select2-selection--single{height:38px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    .dt-item-inline-editor .select2-container .select2-selection--single .select2-selection__rendered{line-height:36px;padding-left:11px;padding-right:34px;font-size:13px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .dt-item-inline-editor .select2-container .select2-selection--single .select2-selection__arrow{height:36px;right:8px}
    .dt-item-inline-editor .select2-container--focus .select2-selection--single{border-color:#2563eb;box-shadow:0 0 0 2px #bfdbfe}
    .dt-item-inline-editor .select2-container--disabled .select2-selection--single{background:#f8fafc;cursor:not-allowed;opacity:.65}
    .dt-item-inline-editor .select2-dropdown{border-color:#d1d5db;border-radius:10px;overflow:hidden;font-size:13px}
    body > .select2-container--open{z-index:99999!important}
    .dt-or-inline-select2-dropdown{z-index:99999!important;border-color:#d1d5db;border-radius:10px;overflow:hidden;font-size:13px}
    .dt-item-money-wrap{display:flex;align-items:stretch;min-width:0}
    .dt-item-money-prefix{display:inline-flex;align-items:center;justify-content:center;min-width:48px;padding:0 10px;border:1px solid #d1d5db;border-right:0;border-radius:10px 0 0 10px;background:#f8fafc;color:#475569;font-size:12px;font-weight:800}
    .dt-item-money-wrap .dt-item-inline-input{border-radius:0 10px 10px 0;min-width:0}
    .dt-item-currency-helper,.dt-item-money-helper{margin-top:3px;color:#64748b;font-size:11px;font-weight:700}
    .dt-item-currency-helper strong,.dt-item-money-helper strong{color:#334155}
    .dt-item-number small{display:block;margin-top:3px;color:#64748b;font-size:11px;font-weight:700;white-space:nowrap}
    .dt-item-recommendation{margin-top:2px;color:#1d4ed8;font-size:11px;font-weight:700}
    .dt-item-detail-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .dt-item-delete-text{display:inline-flex;align-items:center;gap:7px;border:1px solid #fecaca;background:#fff;color:#dc2626;border-radius:9px;padding:7px 10px;font-size:12px;font-weight:800;cursor:pointer}
    .dt-item-delete-text:hover:not(:disabled){background:#fef2f2}
    .dt-item-delete-text:disabled{cursor:not-allowed;opacity:.55}
    .dt-item-tax-options{display:flex;gap:12px;flex-wrap:wrap;align-items:center;min-height:38px}
    .dt-item-tax-options label{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#111827;font-weight:700}
    .dt-item-empty{text-align:center;color:#6b7280;padding:22px!important}
    .dt-item-more{margin-top:12px;border:1px dashed #c7d2fe;border-radius:12px;padding:18px;text-align:center;color:#374151;background:#f8fafc}
    .dt-item-more-icon{display:block;margin-bottom:6px;font-size:20px}
    .dt-item-more button{margin-top:9px}
    .dt-item-pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:12px;color:#6b7280;font-size:13px}
    .dt-item-pages{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .dt-item-page{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid #e5e7eb;border-radius:9px;background:#fff;color:#475569;cursor:pointer;font-weight:700}
    .dt-item-page:hover:not(:disabled){background:#f8fafc}
    .dt-item-page:disabled{cursor:not-allowed;opacity:.45}
    .dt-item-page.active{background:#2563eb;border-color:#2563eb;color:#fff}
    .dt-item-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:14px;font-weight:800}
    .dt-item-footer-total{display:inline-flex;gap:10px;align-items:center}
    .dt-item-footer-total span:last-child{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px}
    body.dt-item-reject-modal-open{overflow:hidden}
    .dt-item-reject-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:24px 16px;background:rgba(15,23,42,.58);backdrop-filter:blur(2px)}
    .dt-item-reject-modal-dialog{width:min(520px,calc(100vw - 32px));max-height:calc(100vh - 48px);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(15,23,42,.12);border-radius:12px;background:#fff;color:#111827;box-shadow:0 24px 72px rgba(15,23,42,.28)}
    .dt-item-reject-modal-header{padding:20px 22px 12px;border-bottom:1px solid #eef2f7}
    .dt-item-reject-modal-title{margin:0;color:#111827;font-size:17px;font-weight:800;line-height:1.35}
    .dt-item-reject-modal-description{margin:6px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .dt-item-reject-modal-body{padding:16px 22px 20px;overflow:auto}
    .dt-item-reject-modal-field{display:flex;flex-direction:column;gap:8px}
    .dt-item-reject-modal-label{color:#334155;font-size:13px;font-weight:800}
    .dt-item-reject-modal-textarea{width:100%;min-height:132px;max-height:260px;resize:vertical;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:11px 12px;font-size:14px;line-height:1.5}
    .dt-item-reject-modal-textarea:focus{outline:2px solid #fecaca;border-color:#dc2626}
    .dt-item-reject-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px;border-top:1px solid #eef2f7;background:#f8fafc}
    .dt-item-reject-modal-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;padding:9px 14px;font-size:13px;font-weight:800;cursor:pointer;white-space:nowrap}
    .dt-item-reject-modal-button:hover:not(:disabled){background:#f8fafc}
    .dt-item-reject-modal-button.danger{border-color:#dc2626;background:#dc2626;color:#fff}
    .dt-item-reject-modal-button.danger:hover:not(:disabled){background:#b91c1c;border-color:#b91c1c}
    .dt-item-reject-modal-button:disabled{cursor:not-allowed;opacity:.5;background:#fee2e2;color:#991b1b}
    [x-cloak]{display:none!important}
    @keyframes dt-item-spin{to{transform:rotate(360deg)}}
    .dt-or-large-repeater .fi-fo-repeater-item{display:none!important}
    .fi-fo-field-wrp:has(.dt-or-large-repeater){height:0!important;min-height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}
    .dt-or-large-repeater{height:0!important;min-height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}
    .dt-or-large-repeater [data-dt-or-bulk-toggle]{display:none}
    .dt-or-large-repeater [data-dt-or-add-item]{display:none}
    .dt-item-highlight-target{outline:3px solid #60a5fa!important;outline-offset:4px;border-radius:12px}
    .dt-item-new-row-highlight td{animation:dt-item-new-row-pulse 4s ease-out 1;background:#eff6ff!important}
    .dt-item-new-row-highlight + .dt-item-detail-row .dt-item-detail-card{animation:dt-item-new-editor-pulse 4s ease-out 1;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.28)}
    @media (max-width: 1024px){.dt-item-toolbar-main{align-items:stretch}.dt-item-toolbar-left{flex-basis:100%}.dt-item-nav-actions{width:100%;justify-content:flex-start}.dt-item-filter-drawer{grid-template-columns:1fr 1fr}.dt-item-detail-footer{grid-template-columns:1fr 1fr}.dt-item-detail-card{margin-left:14px;margin-right:14px}.dt-item-inline-field,.dt-item-inline-field.wide{grid-column:span 6}}
    @media (max-width: 640px){.dt-item-panel{padding:12px}.dt-item-toolbar-left,.dt-item-nav-actions,.dt-item-bulk-actions{width:100%}.dt-item-search-wrap{min-width:100%}.dt-item-nav-button{flex:1 1 auto}.dt-item-bulk-actions .dt-item-nav-button{flex:1 1 46%}.dt-item-filter-drawer{grid-template-columns:1fr}.dt-item-detail-footer{grid-template-columns:1fr}.dt-item-inline-field,.dt-item-inline-field.wide{grid-column:span 12}.dt-item-reject-modal{align-items:flex-end;padding:12px}.dt-item-reject-modal-dialog{width:100%;max-height:calc(100vh - 24px)}.dt-item-reject-modal-footer{flex-direction:column-reverse;align-items:stretch}.dt-item-reject-modal-button{width:100%}}
    @keyframes dt-item-new-row-pulse{0%,70%{background:#dbeafe}100%{background:#fff}}
    @keyframes dt-item-new-editor-pulse{0%,70%{box-shadow:0 0 0 3px rgba(37,99,235,.35)}100%{box-shadow:0 0 0 3px rgba(96,165,250,.28)}}
</style>

<div
    class="dt-item-panel"
    data-dt-or-navigator
    wire:key="dt-or-navigator-{{ md5(json_encode([$search, $supplierFilter, $cabangFilter, $taxFilter, $pageSize, $currentPage])) }}"
    data-current-search="{{ e($search) }}"
    data-current-supplier="{{ e((string) ($supplierFilter ?? '')) }}"
    data-current-cabang="{{ e((string) ($cabangFilter ?? '')) }}"
    data-current-tax="{{ e((string) ($taxFilter ?? '')) }}"
    data-current-page-size="{{ e((string) $pageSize) }}"
    x-data="{
        activeKey: window.__dtOrActiveItemKey || null,
        selectedKeys: [],
        expandedKey: window.__dtOrExpandedItemKey || null,
        filterOpen: false,
        observer: null,
        reconcileTimer: null,
        select2RefreshTimer: null,
        searchValue: '',
        supplierValue: '',
        cabangValue: '',
        bulkSupplierValue: '',
        bulkCabangValue: '',
        taxValue: '',
        pageSizeValue: '10',
        isLoading: false,
        isAddingItem: false,
        loadingMessage: '',
        loadingFallbackTimer: null,
        recentlyAddedKey: window.__dtOrRecentlyAddedKey || null,
        recentlyAddedMessage: window.__dtOrRecentlyAddedMessage || '',
        recentlyAddedTimer: null,
        rejectModalOpen: false,
        rejectModalTitle: '',
        rejectModalReason: '',
        rejectModalKeys: [],
        rejectModalMode: 'single',
        select2AssetsPromise: null,
        inlineSelectChangeHandler: null,

        init() {
            this.searchValue = this.$root.dataset.currentSearch || '';
            this.supplierValue = this.$root.dataset.currentSupplier || '';
            this.cabangValue = this.$root.dataset.currentCabang || '';
            this.taxValue = this.$root.dataset.currentTax || '';
            this.pageSizeValue = this.$root.dataset.currentPageSize || '10';

            if (this.recentlyAddedMessage) {
                window.clearTimeout(this.recentlyAddedTimer);
                this.recentlyAddedTimer = window.setTimeout(() => this.clearAddFeedback(), 9000);
            }

            this.$nextTick(() => {
                this.reconcile();
                if (this.expandedKey) {
                    this.initInlineSelects(this.expandedKey);
                }

                const target = this.repeater() || this.$root.closest('form');
                if (! target) return;

                this.observer = new MutationObserver(() => {
                    window.clearTimeout(this.reconcileTimer);
                    this.reconcileTimer = window.setTimeout(() => {
                        this.reconcile();
                        this.refreshInlineSelects();
                    }, 40);
                });
                this.observer.observe(target, { childList: true, subtree: true });
            });

            this.registerInlineSelectRefreshHooks();

            this.inlineSelectChangeHandler = (event) => {
                const select = event.target?.closest?.('select[data-dt-inline-select]');
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
            document.body.classList.remove('dt-item-reject-modal-open');
            window.clearTimeout(this.reconcileTimer);
            window.clearTimeout(this.select2RefreshTimer);
            window.clearTimeout(this.loadingFallbackTimer);
            window.clearTimeout(this.recentlyAddedTimer);
        },

        startLoading(message) {
            this.loadingMessage = message;
            this.isLoading = true;
            this.$nextTick(() => this.syncInlineEditorLockState());
        },

        finishLoading() {
            this.isLoading = false;

            if (! this.isAddingItem) {
                this.loadingMessage = '';
            }

            this.$nextTick(() => this.syncInlineEditorLockState());
        },

        selectedCount() {
            return this.selectedKeys.length;
        },

        toggleSelected(key, checked) {
            key = String(key);
            if (checked && ! this.selectedKeys.includes(key)) {
                this.selectedKeys.push(key);
            }
            if (! checked) {
                this.selectedKeys = this.selectedKeys.filter((itemKey) => itemKey !== key);
            }
        },

        clearSelection() {
            this.selectedKeys = [];
        },

        async bulkStatus(status) {
            if (! this.selectedCount() || this.isLoading || this.isAddingItem) return;

            if (status === 'rejected') {
                this.openRejectModal([...this.selectedKeys], 'Reject item terpilih', 'bulk');
                return;
            }

            const message = status === 'approved' ? 'Approve item terpilih...' : 'Mengubah status item...';
            await this.runNavigatorRequest(message, () => this.$wire.bulkUpdateInlineOrderRequestItemStatus([...this.selectedKeys], status, null));
            this.clearSelection();
        },

        openRejectModal(keys, title, mode = 'single') {
            if (this.isLoading || this.isAddingItem) return;

            this.rejectModalOpen = true;
            this.rejectModalTitle = title || 'Reject item';
            this.rejectModalReason = '';
            this.rejectModalKeys = Array.isArray(keys) ? keys.map((key) => String(key)) : [];
            this.rejectModalMode = mode;
            document.body.classList.add('dt-item-reject-modal-open');
            this.$nextTick(() => document.querySelector('[data-dt-reject-reason]')?.focus());
        },

        closeRejectModal() {
            this.rejectModalOpen = false;
            this.rejectModalTitle = '';
            this.rejectModalReason = '';
            this.rejectModalKeys = [];
            this.rejectModalMode = 'single';
            document.body.classList.remove('dt-item-reject-modal-open');
        },

        async submitRejectModal() {
            if (! this.rejectModalOpen || this.isLoading || this.isAddingItem) return;

            const note = (this.rejectModalReason || '').trim();
            if (! note) return;

            const keys = [...this.rejectModalKeys];
            if (! keys.length) return;

            const message = this.rejectModalMode === 'bulk' ? 'Reject item terpilih...' : 'Reject item...';
            this.closeRejectModal();
            await this.runNavigatorRequest(message, () => this.$wire.bulkUpdateInlineOrderRequestItemStatus(keys, 'rejected', note));
            this.clearSelection();
        },

        async setItemStatus(key, status) {
            if (this.isLoading || this.isAddingItem) return;

            if (status === 'rejected') {
                this.openRejectModal([key], 'Reject item', 'single');
                return;
            }

            const message = status === 'approved' ? 'Approve item...' : 'Mengubah status item...';
            await this.runNavigatorRequest(message, () => this.$wire.updateInlineOrderRequestItemStatus(String(key), status, null));
        },

        async bulkSetSupplier() {
            if (! this.selectedCount() || ! this.bulkSupplierValue || this.isLoading || this.isAddingItem) return;

            await this.runNavigatorRequest(
                'Mengubah supplier item terpilih...',
                () => this.$wire.bulkUpdateInlineOrderRequestItemSupplier([...this.selectedKeys], this.bulkSupplierValue)
            );
            this.clearSelection();
        },

        async bulkSetCabang() {
            if (! this.selectedCount() || ! this.bulkCabangValue || this.isLoading || this.isAddingItem) return;

            await this.runNavigatorRequest(
                'Mengubah cabang item terpilih...',
                () => this.$wire.bulkUpdateInlineOrderRequestItemCabang([...this.selectedKeys], this.bulkCabangValue)
            );
            this.clearSelection();
        },

        async runNavigatorRequest(message, callback) {
            this.clearAddFeedback();
            this.startLoading(message);

            try {
                return await callback();
            } finally {
                this.finishLoading();
            }
        },

        finishAddingItem() {
            window.clearTimeout(this.loadingFallbackTimer);
            this.loadingFallbackTimer = null;
            this.isAddingItem = false;
            this.finishLoading();
        },

        showAddFeedback(key) {
            this.recentlyAddedKey = String(key);
            this.recentlyAddedMessage = 'Item baru ditambahkan di baris paling atas';
            window.__dtOrRecentlyAddedKey = this.recentlyAddedKey;
            window.__dtOrRecentlyAddedMessage = this.recentlyAddedMessage;

            window.clearTimeout(this.recentlyAddedTimer);
            this.recentlyAddedTimer = window.setTimeout(() => this.clearAddFeedback(), 9000);
        },

        clearAddFeedback() {
            this.recentlyAddedKey = null;
            this.recentlyAddedMessage = '';
            window.__dtOrRecentlyAddedKey = null;
            window.__dtOrRecentlyAddedMessage = '';

            window.clearTimeout(this.recentlyAddedTimer);
            this.recentlyAddedTimer = null;

            this.$root
                .querySelectorAll('.dt-item-new-row-highlight')
                .forEach((row) => row.classList.remove('dt-item-new-row-highlight'));
        },

        repeater() {
            return document.querySelector('.dt-or-large-repeater');
        },

        items() {
            return Array.from(document.querySelectorAll('.dt-or-large-repeater .fi-fo-repeater-item'));
        },

        keyForItem(item) {
            const input = item?.querySelector('[name*=orderRequestItem]');
            const name = input?.getAttribute('name') || '';
            const match = name.match(/orderRequestItem(?:\.|\]\[)([^\.\]\[]+)/);

            return match?.[1] || null;
        },

        itemForKey(key) {
            return this.items().find((item) => this.keyForItem(item) === String(key));
        },

        applyVisibility() {
            this.items().forEach((item) => {
                item.classList.toggle('dt-or-large-editor-active', this.keyForItem(item) === String(this.activeKey || ''));
            });
        },

        scrollEditorIntoView(key, shouldScroll = false) {
            const row = this.$root.querySelector('[data-dt-or-row=' + CSS.escape(String(key)) + ']');
            const item = this.itemForKey(key);

            this.applyVisibility();
            item?.dispatchEvent(new CustomEvent('expand'));
            row?.classList.add('dt-item-highlight-target');
            window.setTimeout(() => row?.classList.remove('dt-item-highlight-target'), 4000);

            if (this.recentlyAddedKey === String(key)) {
                row?.classList.add('dt-item-new-row-highlight');
                window.setTimeout(() => row?.classList.remove('dt-item-new-row-highlight'), 4200);
            }

            if (shouldScroll) {
                window.setTimeout(() => row?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 30);
            }

            this.$nextTick(() => this.initInlineSelects(key));
        },

        openItemWhenReady(key, shouldScroll = true, attempt = 0) {
            const row = this.$root.querySelector('[data-dt-or-row=' + CSS.escape(String(key)) + ']');

            if (row || attempt >= 4) {
                this.openItem(key, shouldScroll);
                return;
            }

            window.setTimeout(() => this.openItemWhenReady(key, shouldScroll, attempt + 1), 120);
        },

        refreshInlineSelects(delay = 80) {
            if (! this.expandedKey) return;

            window.clearTimeout(this.select2RefreshTimer);
            this.select2RefreshTimer = window.setTimeout(() => {
                const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(this.expandedKey)) + ']');
                if (editor?.querySelector('select[data-dt-select2-open]')) return;

                this.$nextTick(() => {
                    this.initInlineSelects(this.expandedKey);
                    this.syncInlineSelect2Values(this.expandedKey);
                    this.syncInlineMoneyInputs(this.expandedKey);
                    this.syncInlineEditorLockState(this.expandedKey);
                });
            }, delay);
        },

        isSelect2Managed(select) {
            if (! select) return false;

            const jq = window.jQuery || window.$;

            return Boolean(
                select.nextElementSibling?.classList?.contains('select2')
                || (jq && jq(select).data('select2'))
            );
        },

        syncInlineSelect2Values(key = this.expandedKey) {
            if (! key) return;

            const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(key)) + ']');
            const jq = window.jQuery || window.$;
            if (! editor || ! jq?.fn?.select2) return;

            editor
                .querySelectorAll('select[data-dt-inline-select]')
                .forEach((select) => {
                    if (! jq(select).data('select2')) return;

                    const renderedValue = select.value || '';
                    jq(select).val(renderedValue).trigger('change.select2');
                });
        },

        syncInlineMoneyInputs(key = this.expandedKey) {
            if (! key) return;

            const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(key)) + ']');
            if (! editor) return;

            editor
                .querySelectorAll('input[data-dt-inline-unit-price]')
                .forEach((input) => {
                    const renderedValue = input.getAttribute('value');

                    if (renderedValue !== null && input.value !== renderedValue) {
                        input.value = renderedValue;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
        },

        syncInlineEditorLockState(key = this.expandedKey) {
            if (! key) return;

            const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(key)) + ']');
            if (! editor) return;

            const shouldDisable = editor.dataset.statusLocked === 'true' || this.isLoading || this.isAddingItem;
            const editableSelector = [
                'select[data-dt-inline-product]',
                'select[data-dt-inline-supplier]',
                'select[data-dt-inline-cabang]',
                'select[data-dt-inline-currency]',
                '[data-dt-inline-quantity]',
                '[data-dt-inline-unit-price]',
                '[data-dt-inline-discount]',
                '[data-dt-inline-note]',
                '[data-dt-inline-tax-type] input[type=radio]',
            ].join(',');
            const jq = window.jQuery || window.$;

            editor.querySelectorAll(editableSelector).forEach((element) => {
                element.disabled = shouldDisable;

                if (element.matches('select[data-dt-inline-select]')) {
                    if (jq?.fn?.select2 && jq(element).data('select2')) {
                        jq(element).prop('disabled', shouldDisable).trigger('change.select2');
                    }

                    const container = element.nextElementSibling?.classList?.contains('select2')
                        ? element.nextElementSibling
                        : null;
                    container?.classList.toggle('select2-container--disabled', shouldDisable);
                    const selection = container ? container.querySelector('.select2-selection') : null;
                    selection?.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
                }
            });
        },

        registerInlineSelectRefreshHooks() {
            if (window.__dtOrSelect2LivewireHooksRegistered) return;
            window.__dtOrSelect2LivewireHooksRegistered = true;

            const refreshNavigators = () => {
                document
                    .querySelectorAll('[data-dt-or-navigator]')
                .forEach((navigator) => {
                    const alpine = navigator._x_dataStack?.[0];
                    alpine?.refreshInlineSelects?.(120);
                    alpine?.syncInlineSelect2Values?.();
                    alpine?.syncInlineMoneyInputs?.();
                    alpine?.syncInlineEditorLockState?.();
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

            if (window.__dtOrSelect2AssetsPromise) {
                return window.__dtOrSelect2AssetsPromise;
            }

            const loadStyle = () => {
                if (document.querySelector('link[data-dt-or-select2]')) return;
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
                link.dataset.dtOrSelect2 = 'true';
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

            window.__dtOrSelect2AssetsPromise = (async () => {
                loadStyle();
                if (! (window.jQuery || window.$)) {
                    await loadScript('https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js', 'data-dt-or-jquery');
                }
                const jq = window.jQuery || window.$;
                if (jq && ! window.jQuery) {
                    window.jQuery = jq;
                }
                if (! jq?.fn?.select2) {
                    await loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'data-dt-or-select2-script');
                }
                const loadedJq = window.jQuery || window.$;
                if (! loadedJq?.fn?.select2) {
                    throw new Error('Select2 assets loaded but jQuery/select2 global is unavailable.');
                }

                window.jQuery = loadedJq;
                return loadedJq;
            })();

            return window.__dtOrSelect2AssetsPromise;
        },

        async initInlineSelects(key) {
            const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(key)) + ']');
            if (! editor) return;

            let jq;
            try {
                jq = await this.ensureSelect2Assets();
            } catch (error) {
                console.warn('Select2 inline OR tidak dapat dimuat; memakai native select.', error);
                return;
            }

            editor.querySelectorAll('select[data-dt-inline-select]').forEach((element) => {
                const $select = jq(element);
                const field = element.dataset.field;
                const searchMethod = element.dataset.searchMethod;
                const hasRenderedSelect2 = element.nextElementSibling?.classList?.contains('select2');

                if ($select.data('select2') && ! hasRenderedSelect2) {
                    try {
                        $select.select2('destroy');
                    } catch (error) {
                        $select.removeData('select2');
                    }
                }

                if (! $select.data('select2')) {
                    $select.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: element.dataset.placeholder || 'Pilih data',
                        dropdownParent: jq(editor),
                        dropdownCssClass: 'dt-or-inline-select2-dropdown',
                        ajax: {
                            delay: 250,
                            transport: (params, success, failure) => {
                                const search = params.data?.term || '';
                                let request;
                                const latestProductId = this.currentInlineSelectValue(editor, 'product_id');
                                const latestCurrencyId = this.currentInlineSelectValue(editor, 'currency_id');

                                if (searchMethod === 'products') {
                                    request = this.$wire.searchInlineOrderRequestProducts(search);
                                } else if (searchMethod === 'suppliers') {
                                    request = this.$wire.searchInlineOrderRequestSuppliers(latestProductId, latestCurrencyId, search);
                                } else if (searchMethod === 'cabangs') {
                                    request = this.$wire.searchInlineOrderRequestCabangs(search);
                                } else {
                                    request = this.$wire.searchInlineOrderRequestCurrencies(search);
                                }

                                Promise.resolve(request)
                                    .then((results) => success({ results: results || [] }))
                                    .catch(failure);

                                return { abort() {} };
                            },
                            processResults: (data) => data,
                        },
                    });
                }

                $select.off('.dtOrInline');
                $select.on('select2:open.dtOrInline', () => {
                    element.setAttribute('data-dt-select2-open', 'true');
                });
                $select.on('select2:close.dtOrInline', () => {
                    element.removeAttribute('data-dt-select2-open');
                });
                $select.on('select2:select.dtOrInline', (event) => {
                    this.handleInlineSelectChange(element, event.params?.data?.id ?? element.value);
                });
                $select.on('select2:clear.dtOrInline', () => {
                    this.handleInlineSelectChange(element, '');
                });
            });

            this.syncInlineSelect2Values(key);
            this.syncInlineEditorLockState(key);
        },

        currentInlineSelectValue(editor, field) {
            const select = editor.querySelector('select[data-field=' + CSS.escape(String(field)) + ']');
            const value = select?.value || '';

            return value !== '' && ! Number.isNaN(Number(value)) ? Number(value) : null;
        },

        async handleInlineSelectChange(select, forcedValue = null) {
            if (! select || select.dataset.dtSelect2Updating === 'true') return;

            const editor = select.closest('[data-dt-inline-editor]');
            const key = editor?.dataset?.dtInlineEditor;
            const field = select.dataset.field;

            if (! key || ! field) return;
            if (select.disabled || editor?.dataset?.statusLocked === 'true') return;

            select.setAttribute('data-dt-select2-updating', 'true');

            const label = field
                .replace('_id', '')
                .replace('currency', 'mata uang');

            try {
                await this.updateInlineItem(key, field, forcedValue ?? select.value, 'Memperbarui ' + label + '…');

                if (field === 'product_id' || field === 'currency_id') {
                    this.refreshInlineSelects(120);
                }
            } finally {
                window.setTimeout(() => {
                    select.removeAttribute('data-dt-select2-updating');
                }, 0);
            }
        },

        async updateInlineItem(key, field, value, message = 'Menghitung item…') {
            const editor = this.$root.querySelector('[data-dt-inline-editor=' + CSS.escape(String(key)) + ']');
            if (editor?.dataset?.statusLocked === 'true') return null;

            this.startLoading(message);

            try {
                const result = await this.$wire.updateInlineOrderRequestItemField(String(key), String(field), value);
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

        async removeInlineItem(key) {
            if (this.isLoading || this.isAddingItem) return;

            this.startLoading('Menghapus item…');

            try {
                const removed = await this.$wire.removeInlineOrderRequestItem(String(key));

                if (removed) {
                    if (this.recentlyAddedKey === String(key)) {
                        this.clearAddFeedback();
                    }

                    this.activeKey = null;
                    this.expandedKey = null;
                    window.__dtOrActiveItemKey = null;
                    window.__dtOrExpandedItemKey = null;
                }

                return removed;
            } finally {
                this.finishLoading();
            }
        },

        reconcile() {
            const items = this.items();
            if (! items.length) return;

            const invalidItem = items.find((item) => item.querySelector('[aria-invalid=true], .fi-fo-field-wrp-error-message'));
            if (invalidItem) {
                const invalidKey = this.keyForItem(invalidItem);
                if (invalidKey) {
                    this.openItem(invalidKey, true);
                    return;
                }
            }

            if (this.activeKey && ! this.itemForKey(this.activeKey)) {
                this.activeKey = null;
                window.__dtOrActiveItemKey = null;
            }

            this.applyVisibility();
        },

        toggleDetail(key) {
            if (this.expandedKey === String(key)) {
                this.expandedKey = null;
                window.__dtOrExpandedItemKey = null;
                if (this.activeKey === String(key)) {
                    this.activeKey = null;
                    window.__dtOrActiveItemKey = null;
                    this.applyVisibility();
                }
                return;
            }

            this.openItem(key);
        },

        openItem(key, shouldScroll = true) {
            this.activeKey = String(key);
            this.expandedKey = String(key);
            window.__dtOrActiveItemKey = this.activeKey;
            window.__dtOrExpandedItemKey = this.expandedKey;
            this.$nextTick(() => this.scrollEditorIntoView(this.activeKey, shouldScroll));
        },

        closeEditor(shouldScroll = true) {
            this.activeKey = null;
            window.__dtOrActiveItemKey = null;
            this.applyVisibility();
            if (shouldScroll) {
                this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        collapseAll(shouldScroll = true) {
            this.expandedKey = null;
            window.__dtOrExpandedItemKey = null;
            this.closeEditor(shouldScroll);
        },

        async addItem() {
            if (this.isAddingItem) return;

            this.isAddingItem = true;
            this.startLoading('Menambahkan item…');

            try {
                const newKey = await this.$wire.addInlineOrderRequestItem();

                if (newKey) {
                    this.activeKey = String(newKey);
                    this.expandedKey = String(newKey);
                    window.__dtOrActiveItemKey = this.activeKey;
                    window.__dtOrExpandedItemKey = this.expandedKey;
                    this.showAddFeedback(newKey);
                    this.$nextTick(() => this.openItemWhenReady(newKey, true));
                    window.setTimeout(() => this.openItemWhenReady(newKey, true), 120);
                    this.refreshInlineSelects(140);
                }

                return newKey;
            } finally {
                this.finishAddingItem();
            }
        },

        setNavigatorState(field, value, shouldResetPage = true) {
            this.collapseAll(false);
            this.$wire.set('data.' + field, value === '' ? null : value, false);

            if (shouldResetPage) {
                this.$wire.set('data._order_request_item_page', 1, false);
            }

            return this.$wire.$commit();
        },

        setNavigatorPage(page) {
            this.collapseAll(false);
            return this.$wire.set('data._order_request_item_page', page);
        },

        updateSearch() {
            return this.runNavigatorRequest(
                'Mencari item…',
                () => this.setNavigatorState('_order_request_item_search', this.searchValue),
            );
        },

        clearSearch() {
            this.searchValue = '';

            return this.runNavigatorRequest(
                'Menghapus pencarian…',
                () => this.setNavigatorState('_order_request_item_search', ''),
            );
        },

        clearAllFilters() {
            return this.runNavigatorRequest('Menghapus filter…', () => {
                this.collapseAll(false);
                this.searchValue = '';
                this.supplierValue = '';
                this.cabangValue = '';
                this.taxValue = '';
                this.pageSizeValue = '10';
                this.$wire.set('data._order_request_item_search', null, false);
                this.$wire.set('data._order_request_item_supplier_filter', null, false);
                this.$wire.set('data._order_request_item_cabang_filter', null, false);
                this.$wire.set('data._order_request_item_tax_filter', null, false);
                this.$wire.set('data._order_request_item_page_size', 10, false);
                this.$wire.set('data._order_request_item_page', 1, false);

                return this.$wire.$commit();
            });
        },
    }"
    x-bind:aria-busy="(isLoading || isAddingItem).toString()"
>
    <div class="dt-item-title">Order request item<span class="required">*</span></div>

    <div class="dt-item-toolbar" style="margin-top:12px">
        <div class="dt-item-toolbar-main">
            <div class="dt-item-toolbar-left">
                <div class="dt-item-search-wrap">
                    <svg class="dt-item-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M9 15.5A6.5 6.5 0 1 0 9 2.5a6.5 6.5 0 0 0 0 13ZM14 14l3.5 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <input
                        id="dt-or-item-search"
                        type="search"
                        class="dt-item-control dt-item-search"
                        placeholder="Search item / product / supplier..."
                        x-model="searchValue"
                        x-on:input="startLoading('Mencari item...')"
                        x-on:input.debounce.500ms="updateSearch()"
                        x-on:keydown.enter.prevent.stop="void 0"
                        data-dt-or-search
                    >
                </div>

                <button type="button" class="dt-item-nav-button" data-dt-or-filter-toggle x-on:click="filterOpen = ! filterOpen">
                    <span aria-hidden="true">&#9776;</span>
                    <span>Filter</span>
                    <span class="dt-item-filter-badge">{{ number_format($activeFilterCount, 0, ',', '.') }}</span>
                </button>
            </div>

            <div class="dt-item-nav-actions">
                <button type="button" class="dt-item-nav-button" data-dt-or-collapse-all x-on:click="collapseAll()">Collapse All</button>
                <button
                    type="button"
                    class="dt-item-nav-button primary"
                    data-dt-or-add-navigator
                    x-on:click="addItem()"
                    x-bind:disabled="isAddingItem || isLoading"
                >
                    <span x-show="! isAddingItem && ! recentlyAddedMessage">+ Tambah Item</span>
                    <span x-cloak x-show="! isAddingItem && recentlyAddedMessage">Item baru dibuka</span>
                    <span x-cloak x-show="isAddingItem">Menambahkan item...</span>
                </button>
            </div>
        </div>

        <div class="dt-item-bulk-actions" data-dt-or-bulk-row>
            <select
                class="dt-item-control"
                x-model="bulkSupplierValue"
                x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem"
                data-dt-or-bulk-supplier-select
            >
                <option value="">Pilih supplier target</option>
                @foreach ($bulkSupplierOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-set-supplier x-bind:disabled="selectedCount() === 0 || ! bulkSupplierValue || isLoading || isAddingItem" x-on:click="bulkSetSupplier()">Set Supplier</button>
            <select
                class="dt-item-control"
                x-model="bulkCabangValue"
                x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem"
                data-dt-or-bulk-cabang-select
            >
                <option value="">Pilih cabang target</option>
                @foreach ($bulkCabangOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-set-cabang x-bind:disabled="selectedCount() === 0 || ! bulkCabangValue || isLoading || isAddingItem" x-on:click="bulkSetCabang()">Set Cabang</button>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-actions x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem" x-on:click="bulkStatus('approved')">
                <span>Approve Selected</span>
                <span class="dt-item-filter-badge" x-text="selectedCount()">0</span>
            </button>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-reject x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem" x-on:click="bulkStatus('rejected')">Reject Selected</button>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-draft x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem" x-on:click="bulkStatus('draft')">Set Draft</button>
            <button type="button" class="dt-item-nav-button" data-dt-or-bulk-clear x-bind:disabled="selectedCount() === 0 || isLoading || isAddingItem" x-on:click="clearSelection()">Clear Selection</button>
        </div>
    </div>
    <div class="dt-item-filter-drawer" x-cloak x-show="filterOpen" data-dt-or-filterbar>
        <div class="dt-item-field">
            <label for="dt-or-item-supplier">Supplier</label>
            <select
                id="dt-or-item-supplier"
                class="dt-item-control"
                x-model="supplierValue"
                x-on:change="runNavigatorRequest('Menerapkan filter…', () => setNavigatorState('_order_request_item_supplier_filter', $event.target.value))"
                x-bind:disabled="isLoading || isAddingItem"
                data-dt-or-supplier-filter
            >
                <option value="">Semua supplier</option>
                @foreach ($supplierOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="dt-item-field">
            <label for="dt-or-item-cabang">Cabang</label>
            <select
                id="dt-or-item-cabang"
                class="dt-item-control"
                x-model="cabangValue"
                x-on:change="runNavigatorRequest('Menerapkan filter…', () => setNavigatorState('_order_request_item_cabang_filter', $event.target.value))"
                x-bind:disabled="isLoading || isAddingItem"
                data-dt-or-cabang-filter
            >
                <option value="">Semua cabang</option>
                @foreach ($cabangOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="dt-item-field">
            <label for="dt-or-item-tax">Tipe pajak</label>
            <select
                id="dt-or-item-tax"
                class="dt-item-control"
                x-model="taxValue"
                x-on:change="runNavigatorRequest('Menerapkan filter…', () => setNavigatorState('_order_request_item_tax_filter', $event.target.value))"
                x-bind:disabled="isLoading || isAddingItem"
                data-dt-or-tax-filter
            >
                <option value="">Semua tipe</option>
                @foreach ($taxOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="dt-item-field">
            <label for="dt-or-item-page-size">Item / halaman</label>
            <select
                id="dt-or-item-page-size"
                class="dt-item-control"
                x-model="pageSizeValue"
                x-on:change="runNavigatorRequest('Memuat halaman…', () => setNavigatorState('_order_request_item_page_size', $event.target.value))"
                x-bind:disabled="isLoading || isAddingItem"
                data-dt-or-page-size
            >
                <option value="10">10 item</option>
                <option value="25">25 item</option>
                <option value="50">50 item</option>
                <option value="100">100 item</option>
            </select>
        </div>
    </div>

    <div class="dt-item-divider"></div>

    <div class="dt-item-chips">
        <span class="dt-item-chip-label">Active filters:</span>
        @forelse ($chips as $chip)
            <span class="dt-item-chip">{{ $chip }} <button type="button" class="dt-item-chip-clear" x-on:click="clearAllFilters()">×</button></span>
        @empty
            <span class="dt-item-chip muted">Tidak ada filter aktif</span>
        @endforelse
        @if ($hasFilters)
            <button type="button" class="dt-item-clear-link" data-dt-or-clear-all x-on:click="clearAllFilters()" x-bind:disabled="isLoading || isAddingItem">Clear all</button>
        @endif
        @if ($search !== '')
            <button type="button" class="dt-item-clear-link" data-dt-or-clear-search x-on:click="clearSearch()" x-bind:disabled="isLoading || isAddingItem">Clear search</button>
        @endif
    </div>

    <div
        class="dt-item-add-feedback"
        x-cloak
        x-show="recentlyAddedMessage"
        data-dt-add-feedback
        role="status"
        aria-live="polite"
    >
        <div>
            <strong x-text="recentlyAddedMessage">Item baru ditambahkan di baris paling atas</strong>
            <span class="dt-item-add-feedback-chip">Item baru ditampilkan di halaman pertama</span>
        </div>
        <button
            type="button"
            class="dt-item-add-feedback-action"
            data-dt-view-added-item
            x-on:click="recentlyAddedKey && openItem(recentlyAddedKey, true)"
        >
            Lihat item baru
        </button>
    </div>

    <div class="dt-item-count-row">
        <span>
            Showing {{ number_format($showingFrom, 0, ',', '.') }} to {{ number_format($showingTo, 0, ',', '.') }}
            of {{ number_format($matchedCount, 0, ',', '.') }} items{{ $hasFilters ? ' matching filters' : '' }}
            <span class="dt-item-add-feedback-chip" x-cloak x-show="recentlyAddedMessage">Item baru sedang dibuka</span>
        </span>
        <div
            class="dt-item-loading-status"
            x-cloak
            x-show="isLoading || isAddingItem"
            role="status"
            aria-live="polite"
            data-dt-or-loading-status
        >
            <span class="dt-item-loading-spinner" aria-hidden="true"></span>
            <span x-text="loadingMessage">Memuat item…</span>
        </div>
    </div>

    <div
        class="dt-item-table-wrap"
        x-bind:class="{ 'is-loading': isLoading || isAddingItem }"
        data-dt-or-table-wrap
    >
        <div
            class="dt-item-loading-overlay"
            x-cloak
            x-show="isLoading || isAddingItem"
            aria-hidden="true"
            data-dt-or-loading-overlay
        >
            <div class="dt-item-loading-card">
                <span class="dt-item-loading-spinner"></span>
                <span x-text="loadingMessage">Memuat item…</span>
            </div>
        </div>
        <table class="dt-item-table">
            <thead>
                <tr>
                    <th class="dt-item-expand-col"></th>
                    <th class="dt-item-check-col"></th>
                    <th>No</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th class="dt-item-number">Qty</th>
                    <th>UOM</th>
                    <th class="dt-item-number">Price</th>
                    <th class="dt-item-number">Subtotal</th>
                    <th>Status</th>
                    <th class="dt-item-action-col">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr
                        class="dt-item-row"
                        data-dt-or-row="{{ $row['key'] }}"
                        wire:key="dt-or-row-{{ $row['key'] }}"
                        x-bind:class="{
                            'is-expanded': expandedKey === @js($row['key']),
                            'dt-item-new-row-highlight': recentlyAddedKey === @js($row['key']),
                        }"
                    >
                        <td class="dt-item-expand-col">
                            <button
                                type="button"
                                class="dt-item-expand"
                                aria-label="Expand item"
                                x-on:click="toggleDetail(@js($row['key']))"
                                x-text="expandedKey === @js($row['key']) ? '⌄' : '›'"
                            >›</button>
                        </td>
                        <td class="dt-item-check-col">
                            <input
                                type="checkbox"
                                class="dt-item-checkbox"
                                value="{{ $row['key'] }}"
                                aria-label="Pilih item #{{ $row['number'] }}"
                                x-bind:checked="selectedKeys.includes(@js($row['key']))"
                                x-on:change="toggleSelected(@js($row['key']), $event.target.checked)"
                            >
                        </td>
                        <td>{{ $row['number'] }}</td>
                        <td class="dt-item-product">
                            {{ $row['product'] }}
                            <small>{{ $row['cabang'] }}</small>
                        </td>
                        <td>{{ $row['supplier'] }}</td>
                        <td class="dt-item-number">{{ $row['qty'] }}</td>
                        <td>{{ $row['uom'] }}</td>
                        <td class="dt-item-number">
                            {{ $row['price'] }}
                            @if ($row['is_foreign_currency'])
                                <small>{{ $row['unit_price_idr_equivalent'] }}</small>
                            @endif
                        </td>
                        <td class="dt-item-number">
                            {{ $row['subtotal'] }}
                            @if ($row['is_foreign_currency'])
                                <small>{{ $row['subtotal_idr_equivalent'] }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="dt-item-status-badge {{ $row['status_value'] }}">{{ $row['status_label'] }}</span>
                            @if ($row['status_value'] === 'rejected' && filled($row['rejection_note']))
                                <span class="dt-item-rejection-note" title="{{ $row['rejection_note'] }}">{{ $row['rejection_note'] }}</span>
                            @endif
                        </td>
                        <td class="dt-item-action-col">
                            <div class="dt-item-row-actions">
                                <button
                                    type="button"
                                    class="dt-item-icon-button"
                                    title="Buka editor item"
                                    data-dt-or-key="{{ $row['key'] }}"
                                    x-on:click="openItem(@js($row['key']))"
                                >
                                    ✎
                                </button>
                                @if ($row['status_value'] !== 'approved')
                                    <button
                                        type="button"
                                        class="dt-item-icon-button"
                                        title="Approve item"
                                        data-dt-or-approve-item="{{ $row['key'] }}"
                                        x-bind:disabled="isLoading || isAddingItem"
                                        x-on:click="setItemStatus(@js($row['key']), 'approved')"
                                    >
                                        ✓
                                    </button>
                                @endif
                                @if ($row['status_value'] !== 'rejected')
                                    <button
                                        type="button"
                                        class="dt-item-icon-button danger"
                                        title="Reject item"
                                        data-dt-or-reject-item="{{ $row['key'] }}"
                                        x-bind:disabled="isLoading || isAddingItem"
                                        x-on:click="setItemStatus(@js($row['key']), 'rejected')"
                                    >
                                        ×
                                    </button>
                                @endif
                                @if ($row['status_value'] !== 'draft')
                                    <button
                                        type="button"
                                        class="dt-item-icon-button"
                                        title="Set item ke Draft"
                                        data-dt-or-draft-item="{{ $row['key'] }}"
                                        x-bind:disabled="isLoading || isAddingItem"
                                        x-on:click="setItemStatus(@js($row['key']), 'draft')"
                                    >
                                        D
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    class="dt-item-icon-button danger"
                                    title="Hapus item"
                                    data-dt-delete-item="{{ $row['key'] }}"
                                    x-bind:disabled="isLoading || isAddingItem"
                                    x-on:click="removeInlineItem(@js($row['key']))"
                                >
                                    🗑
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="dt-item-detail-row" wire:key="dt-or-detail-{{ $row['key'] }}" x-cloak x-show="expandedKey === @js($row['key'])">
                        <td colspan="11">
                            <div class="dt-item-detail-card">
                                <div class="dt-item-detail-summary">
                                    <div>
                                        <strong>Editor item #{{ $row['number'] }}</strong>
                                        <span class="dt-item-status-badge {{ $row['status_value'] }}" style="margin-left:8px;">{{ $row['status_label'] }}</span>
                                        <span style="color:#6b7280;">· {{ $row['product'] }}</span>
                                    </div>
                                    <div class="dt-item-detail-actions">
                                        <div style="color:#6b7280;">Cabang: <strong>{{ $row['cabang'] }}</strong></div>
                                        <button
                                            type="button"
                                            class="dt-item-delete-text"
                                            data-dt-delete-item="{{ $row['key'] }}"
                                            x-bind:disabled="isLoading || isAddingItem"
                                            x-on:click="removeInlineItem(@js($row['key']))"
                                        >
                                            🗑 Hapus item
                                        </button>
                                    </div>
                                </div>
                                <div class="dt-item-inline-editor" data-dt-inline-editor="{{ $row['key'] }}" data-status-locked="{{ $row['is_status_locked'] ? 'true' : 'false' }}">
                                    <div class="dt-item-inline-grid">
                                        @if ($row['is_status_locked'])
                                            <div class="dt-item-lock-banner">Item {{ $row['status_label'] }} tidak bisa diedit dari inline editor. Gunakan bulk action untuk mengembalikan ke Draft jika perlu revisi.</div>
                                        @endif
                                        <div class="dt-item-inline-field wide">
                                            <label>Product</label>
                                            <select
                                                class="dt-item-inline-select"
                                                data-dt-inline-product
                                                data-dt-inline-select
                                                data-field="product_id"
                                                data-search-method="products"
                                                data-placeholder="Cari SKU atau nama product"
                                                title="{{ $row['product'] }}"
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                            >
                                                <option value="">Pilih product</option>
                                                @foreach ($row['product_options'] as $value => $label)
                                                    <option value="{{ $value }}" title="{{ $label }}" @selected((string) $row['product_id'] === (string) $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="dt-item-inline-field wide">
                                            <label>Supplier</label>
                                            <select
                                                class="dt-item-inline-select"
                                                data-dt-inline-supplier
                                                data-dt-inline-select
                                                data-field="supplier_id"
                                                data-search-method="suppliers"
                                                data-product-id="{{ $row['product_id'] }}"
                                                data-currency-id="{{ $row['currency_id'] }}"
                                                data-placeholder="Cari kode atau perusahaan supplier"
                                                title="{{ $row['supplier'] }}"
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                            >
                                                <option value="">Pilih supplier</option>
                                                @foreach ($row['supplier_options'] as $value => $label)
                                                    <option value="{{ $value }}" title="{{ $label }}" @selected((string) $row['supplier_id'] === (string) $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @if ($row['recommended_supplier'])
                                                <div class="dt-item-recommendation">Rekomendasi: {{ $row['recommended_supplier'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Cabang</label>
                                            <select
                                                class="dt-item-inline-select"
                                                data-dt-inline-cabang
                                                data-dt-inline-select
                                                data-field="cabang_id"
                                                data-search-method="cabangs"
                                                data-placeholder="Cari kode atau nama cabang"
                                                title="{{ $row['cabang'] }}"
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                            >
                                                <option value="">Pilih cabang</option>
                                                @foreach ($row['cabang_options'] as $value => $label)
                                                    <option value="{{ $value }}" title="{{ $label }}" @selected((string) $row['cabang_id'] === (string) $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Qty</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="dt-item-inline-input"
                                                value="{{ $row['quantity_value'] }}"
                                                data-dt-inline-quantity
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                                x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'quantity', $event.target.value)"
                                                x-on:keydown.enter.prevent.stop="void 0"
                                            >
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>UOM</label>
                                            <input
                                                type="text"
                                                class="dt-item-inline-input"
                                                value="{{ $row['unit_value'] }}"
                                                data-dt-inline-unit
                                                readonly
                                            >
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Mata Uang Item</label>
                                            <select
                                                class="dt-item-inline-select"
                                                data-dt-inline-currency
                                                data-dt-inline-select
                                                data-field="currency_id"
                                                data-search-method="currencies"
                                                data-placeholder="Cari mata uang"
                                                title="{{ $row['currency_label'] }}"
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                            >
                                                <option value="">Pilih mata uang</option>
                                                @foreach ($currencyOptions as $value => $label)
                                                    <option value="{{ $value }}" title="{{ $label }}" @selected((string) $row['currency_id'] === (string) $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @if ($row['is_foreign_currency'] && $row['currency_rate_label'])
                                                <div class="dt-item-currency-helper" data-dt-inline-currency-rate>
                                                    Kurs: <strong>{{ $row['currency_rate_label'] }}</strong>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Original price</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input
                                                    type="text"
                                                    class="dt-item-inline-input"
                                                    value="{{ $row['original_price_value'] }}"
                                                    data-dt-inline-original-price
                                                    readonly
                                                >
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-original-price-idr>{{ $row['original_price_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Harga Override</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    class="dt-item-inline-input"
                                                    value="{{ $row['unit_price_value'] }}"
                                                    data-dt-inline-unit-price
                                                    @disabled($row['is_status_locked'])
                                                    x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                                    x-mask:dynamic="$money($input, ',', '.', 2)"
                                                    x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'unit_price', $event.target.value)"
                                                    x-on:keydown.enter.prevent.stop="void 0"
                                                >
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-unit-price-idr>{{ $row['unit_price_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Discount (%)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="dt-item-inline-input"
                                                value="{{ $row['discount_value'] }}"
                                                data-dt-inline-discount
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                                x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'discount', $event.target.value)"
                                                x-on:keydown.enter.prevent.stop="void 0"
                                            >
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Discount nominal</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input type="text" class="dt-item-inline-input" value="{{ $row['discount_nominal_value'] }}" readonly data-dt-inline-discount-nominal>
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-discount-nominal-idr>{{ $row['discount_nominal_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Total</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input type="text" class="dt-item-inline-input" value="{{ $row['total_value'] }}" readonly data-dt-inline-total>
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-total-idr>{{ $row['total_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Tipe Pajak</label>
                                            <div class="dt-item-tax-options" data-dt-inline-tax-type>
                                                @foreach ($taxOptions as $value => $label)
                                                    <label>
                                                        <input
                                                            type="radio"
                                                            name="dt-inline-{{ $row['key'] }}-tipe-pajak"
                                                            value="{{ $value }}"
                                                            @checked($row['tipe_pajak_value'] === $value)
                                                            @disabled($row['is_status_locked'])
                                                            x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                                            x-on:change="updateInlineItem(@js($row['key']), 'tipe_pajak', $event.target.value)"
                                                        >
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Tax (%)</label>
                                            <input
                                                type="number"
                                                class="dt-item-inline-input"
                                                value="{{ $row['tax_value'] }}"
                                                data-dt-inline-tax
                                                readonly
                                            >
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Tax nominal</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input type="text" class="dt-item-inline-input" value="{{ $row['tax_nominal_value'] }}" readonly data-dt-inline-tax-nominal>
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-tax-nominal-idr>{{ $row['tax_nominal_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field">
                                            <label>Subtotal</label>
                                            <div class="dt-item-money-wrap">
                                                <span class="dt-item-money-prefix">{{ $row['currency_symbol'] }}</span>
                                                <input type="text" class="dt-item-inline-input" value="{{ $row['subtotal_value'] }}" readonly data-dt-inline-subtotal>
                                            </div>
                                            @if ($row['is_foreign_currency'])
                                                <div class="dt-item-money-helper" data-dt-inline-subtotal-idr>{{ $row['subtotal_idr_equivalent'] }}</div>
                                            @endif
                                        </div>
                                        <div class="dt-item-inline-field full">
                                            <label>Note</label>
                                            <textarea
                                                class="dt-item-inline-input"
                                                rows="2"
                                                data-dt-inline-note
                                                @disabled($row['is_status_locked'])
                                                x-bind:disabled="isLoading || isAddingItem || @js($row['is_status_locked'])"
                                                x-on:input.debounce.500ms="updateInlineItem(@js($row['key']), 'note', $event.target.value, 'Memperbarui note…')"
                                                x-on:keydown.enter.stop
                                            >{{ $row['note_value'] }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="dt-item-detail-footer">
                                    <div>Note: <strong>{{ $row['note'] }}</strong></div>
                                    <div>Available stock: <strong>{{ $row['available_stock'] }} {{ $row['uom'] }}</strong></div>
                                    <div>Fulfilled: <strong>{{ $row['fulfilled_qty'] }} {{ $row['uom'] }}</strong></div>
                                    <div>Remaining: <strong>{{ $row['remaining_qty'] }} {{ $row['uom'] }}</strong></div>
                                    <div>Status Item: <strong>{{ $row['status_label'] }}</strong></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr wire:key="dt-or-empty-row"><td colspan="11" class="dt-item-empty">Tidak ada item yang cocok dengan pencarian/filter aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($remaining > 0)
        <div class="dt-item-more">
            <span class="dt-item-more-icon">▣</span>
            <strong>Masih ada {{ number_format($remaining, 0, ',', '.') }} item lainnya</strong>
            <div>
                <button
                    type="button"
                    class="dt-item-nav-button"
                    x-bind:disabled="isLoading || isAddingItem || @js($currentPage >= $lastPage)"
                    x-on:click="runNavigatorRequest('Memuat halaman…', () => setNavigatorPage({{ min($lastPage, $currentPage + 1) }}))"
                >
                    Muat halaman berikutnya ↓
                </button>
            </div>
        </div>
    @endif

    <div class="dt-item-pagination">
        <span>Showing {{ number_format($showingFrom, 0, ',', '.') }} to {{ number_format($showingTo, 0, ',', '.') }} of {{ number_format($matchedCount, 0, ',', '.') }} items</span>
        <div class="dt-item-pages">
            <button
                type="button"
                class="dt-item-page"
                x-bind:disabled="isLoading || isAddingItem || @js($currentPage <= 1)"
                x-on:click="runNavigatorRequest('Memuat halaman…', () => setNavigatorPage({{ max(1, $currentPage - 1) }}))"
            >‹</button>
            @php $previousPageNumber = null; @endphp
            @foreach ($pageNumbers as $pageNumber)
                @if ($previousPageNumber !== null && $pageNumber > $previousPageNumber + 1)
                    <span class="dt-item-page" aria-hidden="true">…</span>
                @endif
                <button
                    type="button"
                    class="dt-item-page {{ $pageNumber === $currentPage ? 'active' : '' }}"
                    x-bind:disabled="isLoading || isAddingItem || @js($pageNumber === $currentPage)"
                    x-on:click="runNavigatorRequest('Memuat halaman…', () => setNavigatorPage({{ $pageNumber }}))"
                >{{ $pageNumber }}</button>
                @php $previousPageNumber = $pageNumber; @endphp
            @endforeach
            <button
                type="button"
                class="dt-item-page"
                x-bind:disabled="isLoading || isAddingItem || @js($currentPage >= $lastPage)"
                x-on:click="runNavigatorRequest('Memuat halaman…', () => setNavigatorPage({{ min($lastPage, $currentPage + 1) }}))"
            >›</button>
        </div>
    </div>

    <div class="dt-item-footer">
        <div>Total Items: {{ number_format($totalItems, 0, ',', '.') }}</div>
        <div class="dt-item-footer-total">
            <span>{{ $footerTotalLabel }}</span>
            <span>
                {{ $footerTotalValue }}
                @if ($footerTotalHelper)
                    <small style="display:block;color:#64748b;font-size:11px;font-weight:700;">{{ $footerTotalHelper }}</small>
                @endif
            </span>
        </div>
    </div>

    <template x-teleport="body">
        <div
            class="dt-item-reject-modal"
            x-cloak
            x-show="rejectModalOpen"
            x-transition.opacity
            x-on:keydown.escape.window="closeRejectModal()"
            x-on:click.self="closeRejectModal()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dt-item-reject-modal-title"
        >
            <div class="dt-item-reject-modal-dialog">
                <div class="dt-item-reject-modal-header">
                    <h3 class="dt-item-reject-modal-title" id="dt-item-reject-modal-title" x-text="rejectModalTitle"></h3>
                    <p class="dt-item-reject-modal-description">Masukkan alasan reject sebelum melanjutkan.</p>
                </div>
                <div class="dt-item-reject-modal-body">
                    <label class="dt-item-reject-modal-field">
                        <span class="dt-item-reject-modal-label">Alasan reject</span>
                        <textarea
                            class="dt-item-reject-modal-textarea"
                            rows="5"
                            x-model="rejectModalReason"
                            placeholder="Tulis alasan reject item..."
                            data-dt-reject-reason
                        ></textarea>
                    </label>
                </div>
                <div class="dt-item-reject-modal-footer">
                    <button type="button" class="dt-item-reject-modal-button" x-on:click="closeRejectModal()">Batal</button>
                    <button type="button" class="dt-item-reject-modal-button danger" x-bind:disabled="! rejectModalReason.trim()" x-on:click="submitRejectModal()">Reject</button>
                </div>
            </div>
        </div>
    </template>
</div>
