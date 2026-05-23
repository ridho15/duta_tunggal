<x-filament-panels::page>

    {{-- Select2 CSS & JS via CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- ═══════════════════════════════════════════════
     STYLES (Bulletproof CSS Classes with !important)
     ═══════════════════════════════════════════════ --}}
    @verbatim
        <style>
            /* ── Print Media ── */
            @media print {
                .no-print {
                    display: none !important;
                }

                .fi-topbar,
                .fi-sidebar,
                .fi-page-header,
                .premium-filter-card,
                .premium-tab-bar,
                .premium-table-card,
                .premium-btn-print {
                    display: none !important;
                }

                body {
                    margin: 0;
                    padding: 20px;
                }

                .inv-print-header {
                    display: block !important;
                    text-align: center;
                    margin-bottom: 20px;
                }
            }

            .inv-print-header {
                display: none;
            }

            /* ── Premium Report Header Banner ── */
            .premium-header {
                background: linear-gradient(135deg, #0d5c4a 0%, #0d9488 60%, #14b8a6 100%) !important;
                border-radius: 16px !important;
                padding: 24px 28px !important;
                margin-bottom: 24px !important;
                position: relative !important;
                overflow: hidden !important;
                box-shadow: 0 10px 25px -5px rgba(13, 148, 136, 0.25) !important;
                color: #ffffff !important;
                border: none !important;
            }

            .premium-circle-1 {
                position: absolute !important;
                top: -40px !important;
                right: -40px !important;
                width: 180px !important;
                height: 180px !important;
                border-radius: 50% !important;
                background: rgba(255, 255, 255, 0.06) !important;
                pointer-events: none !important;
                z-index: 1 !important;
            }

            .premium-circle-2 {
                position: absolute !important;
                bottom: -60px !important;
                right: 80px !important;
                width: 220px !important;
                height: 220px !important;
                border-radius: 50% !important;
                background: rgba(255, 255, 255, 0.04) !important;
                pointer-events: none !important;
                z-index: 1 !important;
            }

            .premium-icon-container {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 44px !important;
                height: 44px !important;
                border-radius: 12px !important;
                background-color: rgba(255, 255, 255, 0.15) !important;
                flex-shrink: 0 !important;
            }

            .premium-company {
                font-size: 1.4rem !important;
                font-weight: 800 !important;
                letter-spacing: 0.02em !important;
                line-height: 1.25 !important;
                color: #ffffff !important;
            }

            .premium-subtitle {
                font-size: 1.0rem !important;
                font-weight: 600 !important;
                color: rgba(255, 255, 255, 0.9) !important;
                margin-top: 6px !important;
                line-height: 1.4 !important;
            }

            .premium-period {
                font-size: 0.85rem !important;
                color: rgba(255, 255, 255, 0.75) !important;
                margin-top: 6px !important;
                line-height: 1.4 !important;
            }

            .premium-btn-excel {
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 8px 16px !important;
                border-radius: 8px !important;
                background: rgba(255, 255, 255, 0.15) !important;
                color: #ffffff !important;
                font-size: 0.875rem !important;
                font-weight: 600 !important;
                transition: all 0.2s !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                cursor: pointer !important;
                text-decoration: none !important;
            }

            .premium-btn-excel:hover {
                background: rgba(255, 255, 255, 0.25) !important;
            }

            .premium-btn-pdf {
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 8px 16px !important;
                border-radius: 8px !important;
                background: rgba(255, 255, 255, 0.1) !important;
                color: #ffffff !important;
                font-size: 0.875rem !important;
                font-weight: 600 !important;
                transition: all 0.2s !important;
                border: 1px solid rgba(255, 255, 255, 0.15) !important;
                cursor: pointer !important;
                text-decoration: none !important;
            }

            .premium-btn-pdf:hover {
                background: rgba(255, 255, 255, 0.2) !important;
            }

            /* ── Tab Switcher Bar ── */
            .premium-tab-bar {
                display: inline-flex !important;
                flex-wrap: wrap !important;
                gap: 8px !important;
                background-color: #f3f4f6 !important;
                border-radius: 14px !important;
                padding: 6px !important;
                margin-bottom: 24px !important;
            }

            .dark .premium-tab-bar {
                background-color: #1f2937 !important;
            }

            .premium-tab-btn {
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 10px 18px !important;
                border-radius: 10px !important;
                font-size: 0.875rem !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                border: none !important;
                outline: none !important;
                transition: all 0.2s !important;
                background-color: transparent !important;
                color: #4b5563 !important;
            }

            .dark .premium-tab-btn {
                color: #9ca3af !important;
            }

            .premium-tab-btn:hover {
                background-color: rgba(13, 148, 136, 0.08) !important;
                color: #0d9488 !important;
            }

            .dark .premium-tab-btn:hover {
                background-color: rgba(20, 184, 166, 0.1) !important;
                color: #2dd4bf !important;
            }

            .premium-tab-btn.active {
                background-color: #0d9488 !important;
                color: #ffffff !important;
                box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3) !important;
            }

            .dark .premium-tab-btn.active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }

            /* ── Filter Card ── */
            .premium-filter-card {
                background-color: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
                overflow: hidden !important;
                margin-bottom: 24px !important;
            }

            .dark .premium-filter-card {
                background-color: #111827 !important;
                border-color: #1f2937 !important;
            }

            .premium-card-header {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                padding: 16px 24px !important;
                border-bottom: 1px solid #f3f4f6 !important;
                background-color: #f9fafb !important;
            }

            .dark .premium-card-header {
                background-color: rgba(31, 41, 55, 0.5) !important;
                border-color: #1f2937 !important;
            }

            .premium-card-icon-container {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 36px !important;
                height: 36px !important;
                border-radius: 10px !important;
                background-color: #ccfbf1 !important;
                color: #0d9488 !important;
                flex-shrink: 0 !important;
            }

            .dark .premium-card-icon-container {
                background-color: rgba(13, 148, 136, 0.2) !important;
                color: #2dd4bf !important;
            }

            .premium-card-title {
                font-size: 0.875rem !important;
                font-weight: 700 !important;
                color: #1f2937 !important;
                margin: 0 !important;
                line-height: 1.25 !important;
            }

            .dark .premium-card-title {
                color: #f3f4f6 !important;
            }

            .premium-card-subtitle {
                font-size: 0.75rem !important;
                color: #6b7280 !important;
                margin-top: 2px !important;
            }

            .dark .premium-card-subtitle {
                color: #9ca3af !important;
            }

            .premium-label {
                display: block !important;
                font-size: 0.75rem !important;
                font-weight: 700 !important;
                color: #4b5563 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                margin-bottom: 6px !important;
            }

            .dark .premium-label {
                color: #9ca3af !important;
            }

            /* ── Stat Cards ── */
            .premium-stat-card {
                background-color: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                padding: 20px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
                display: flex !important;
                align-items: center !important;
                gap: 16px !important;
                transition: transform 0.2s, box-shadow 0.2s !important;
            }

            .dark .premium-stat-card {
                background-color: #111827 !important;
                border-color: #1f2937 !important;
            }

            .premium-stat-card:hover {
                transform: translateY(-3px) !important;
                box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.1) !important;
            }

            .premium-stat-label {
                font-size: 0.75rem !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                color: #6b7280 !important;
                margin: 0 !important;
            }

            .dark .premium-stat-label {
                color: #9ca3af !important;
            }

            .premium-stat-value {
                font-size: 1.5rem !important;
                font-weight: 800 !important;
                line-height: 1.25 !important;
                margin-top: 2px !important;
            }

            .premium-stat-sub {
                font-size: 0.75rem !important;
                color: #9ca3af !important;
                margin-top: 2px !important;
                margin: 0 !important;
            }

            .dark .premium-stat-sub {
                color: #6b7280 !important;
            }

            /* ── Table Container Card ── */
            .premium-table-card {
                background-color: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
                overflow: hidden !important;
                margin-top: 24px !important;
            }

            .dark .premium-table-card {
                background-color: #111827 !important;
                border-color: #1f2937 !important;
            }

            .premium-btn-print {
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                border-radius: 8px !important;
                border: 1px solid #d1d5db !important;
                background-color: #ffffff !important;
                color: #374151 !important;
                padding: 6px 14px !important;
                font-size: 0.875rem !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                transition: background-color 0.15s !important;
            }

            .premium-btn-print:hover {
                background-color: #f9fafb !important;
            }

            .dark .premium-btn-print {
                background-color: #1f2937 !important;
                border-color: #374151 !important;
                color: #d1d5db !important;
            }

            .dark .premium-btn-print:hover {
                background-color: #374151 !important;
            }

            /* ── Select2 overrides to match Tailwind inputs ── */
            .select2-container--default .select2-selection--single {
                height: 38px !important;
                border: 1px solid #d1d5db !important;
                border-radius: 0.5rem !important;
                background-color: #ffffff !important;
                display: flex !important;
                align-items: center !important;
                transition: border-color .15s, box-shadow .15s !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 38px !important;
                padding-left: 0.75rem !important;
                padding-right: 2rem !important;
                color: #111827 !important;
                font-size: 0.875rem !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px !important;
                right: 6px !important;
            }

            .select2-container--default.select2-container--focus .select2-selection--single,
            .select2-container--default.select2-container--open .select2-selection--single {
                border-color: #0d9488 !important;
                box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.15) !important;
                outline: none !important;
            }

            .select2-dropdown {
                border: 1px solid #d1d5db !important;
                border-radius: 0.5rem !important;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12) !important;
                font-size: 0.875rem !important;
                overflow: hidden !important;
            }

            .select2-container--default .select2-search--dropdown .select2-search__field {
                border: 1px solid #e5e7eb !important;
                border-radius: 0.375rem !important;
                padding: 0.4rem 0.75rem !important;
                font-size: 0.875rem !important;
                outline: none !important;
            }

            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #0d9488 !important;
            }

            .select2-results__option {
                padding: 0.5rem 0.75rem !important;
            }

            /* ── Select2 dark mode ── */
            .dark .select2-container--default .select2-selection--single {
                background-color: #1f2937 !important;
                border-color: #374151 !important;
            }

            .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: #f3f4f6 !important;
            }

            .dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
                border-color: #9ca3af transparent transparent transparent !important;
            }

            .dark .select2-dropdown {
                background-color: #1f2937 !important;
                border-color: #374151 !important;
            }

            .dark .select2-container--default .select2-search--dropdown .select2-search__field {
                background-color: #111827 !important;
                border-color: #374151 !important;
                color: #f3f4f6 !important;
            }

            .dark .select2-container--default .select2-results__option {
                color: #e5e7eb !important;
            }

            .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #0d9488 !important;
                color: #fff !important;
            }

            .dark .select2-container--default .select2-results__option[aria-selected=true] {
                background-color: #115e59 !important;
                color: #ccfbf1 !important;
            }
        </style>
    @endverbatim

    {{-- ═══════════════════════════════════════════════
     PRINT HEADER (hidden on screen)
     ═══════════════════════════════════════════════ --}}
    <div class="inv-print-header">
        <h1 style="font-size:1.5rem;font-weight:bold;">Laporan Inventori</h1>
        <p>
            @if ($show_movement_history)
                History Movement Stok
            @elseif($show_aging_stock)
                Aging Stock Analysis
            @else
                Stok Barang per Gudang
            @endif
            — Dicetak: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="space-y-5 no-print-wrapper" style="margin-top: 10px;">

        {{-- ═══════════════════════════════════════════════
         GRADIENT REPORT HEADER (Bulletproof standard CSS)
         ═══════════════════════════════════════════════ --}}
        <div class="no-print premium-header">

            <!-- Premium Decorative background circles -->
            <div class="premium-circle-1"></div>
            <div class="premium-circle-2"></div>

            <div class="flex items-start justify-between gap-4 relative z-10"
                style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                <div>
                    <div class="flex items-center gap-3 mb-1"
                        style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <div class="premium-icon-container">
                            <x-heroicon-o-archive-box class="w-6 h-6 text-white" />
                        </div>
                        <div class="premium-company">{{ config('app.name', 'Duta Tunggal ERP') }}</div>
                    </div>
                    <div class="premium-subtitle">
                        LAPORAN INVENTORI —
                        @if ($show_movement_history)
                            History Movement Stok
                        @elseif($show_aging_stock)
                            Aging Stock Analysis
                        @else
                            Stok Barang per Gudang
                        @endif
                    </div>
                    <div class="premium-period">
                        Per hari ini: {{ now()->isoFormat('D MMMM GGGG') }}
                        @if ($warehouse_id)
                            &bull; Gudang: {{ \App\Models\Warehouse::find($warehouse_id)?->name }}
                        @endif
                    </div>
                </div>
                <div class="flex flex-col gap-2 flex-shrink-0"
                    style="display: flex; flex-direction: column; gap: 8px; flex-shrink: 0;">
                    <button wire:click="exportExcel" class="premium-btn-excel">
                        <x-heroicon-o-table-cells class="w-4 h-4" />
                        Export Excel
                    </button>
                    <button wire:click="exportPdf" class="premium-btn-pdf">
                        <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                        Export PDF
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
         TAB SWITCHER (Bulletproof standard CSS)
         ═══════════════════════════════════════════════ --}}
        <div class="premium-tab-bar no-print">
            {{-- Stok per Gudang --}}
            <button wire:click="$set('show_movement_history', false); $set('show_aging_stock', false)"
                class="premium-tab-btn {{ !$show_movement_history && !$show_aging_stock ? 'active' : '' }}"
                id="tab-stock">
                <x-heroicon-o-archive-box class="w-4 h-4" />
                Stok per Gudang
            </button>
            {{-- History Movement --}}
            <button wire:click="$set('show_movement_history', true); $set('show_aging_stock', false)"
                class="premium-tab-btn {{ $show_movement_history ? 'active' : '' }}" id="tab-movement">
                <x-heroicon-o-arrow-trending-up class="w-4 h-4" />
                History Movement
            </button>
            {{-- Aging Stock --}}
            <button wire:click="$set('show_aging_stock', true); $set('show_movement_history', false)"
                class="premium-tab-btn {{ $show_aging_stock ? 'active' : '' }}" id="tab-aging">
                <x-heroicon-o-clock class="w-4 h-4" />
                Aging Stock
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════
         FILTER CARD
         ═══════════════════════════════════════════════ --}}
        <div id="filter-card-container" class="premium-filter-card no-print">

            {{-- Card Header --}}
            <div class="premium-card-header">
                <div class="premium-card-icon-container">
                    <x-heroicon-o-adjustments-horizontal class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="premium-card-title">Filter Laporan</h2>
                    <p class="premium-card-subtitle">Saring data berdasarkan gudang, produk, dan periode</p>
                </div>
            </div>

            {{-- Filter Fields --}}
            <div class="p-6" style="padding: 24px;">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                    {{-- Tanggal Mulai (only for Movement) --}}
                    @if ($show_movement_history)
                        <div wire:key="filter-start-date" class="space-y-1.5" style="margin-bottom: 12px;">
                            <label class="premium-label">
                                <x-heroicon-m-calendar-days class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />
                                Tanggal Mulai
                            </label>
                            <input wire:model.live="start_date" type="date"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                        </div>
                        <div wire:key="filter-end-date" class="space-y-1.5" style="margin-bottom: 12px;">
                            <label class="premium-label">
                                <x-heroicon-m-calendar-days class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />
                                Tanggal Akhir
                            </label>
                            <input wire:model.live="end_date" type="date"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                        </div>
                    @endif

                    {{-- Gudang (Select2 Searchable) --}}
                    <div wire:key="filter-gudang" wire:ignore class="space-y-1.5" style="margin-bottom: 12px;">
                        <label class="premium-label">
                            <x-heroicon-m-building-office-2 class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />
                            Gudang
                        </label>
                        <select wire:model.live="warehouse_id" id="select-gudang" data-inv-select2
                            data-placeholder="— Semua Gudang —"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                            <option value="">— Semua Gudang —</option>
                            @foreach (\App\Models\Warehouse::orderBy('name')->get() as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Produk (Select2 Searchable) --}}
                    <div wire:key="filter-produk" wire:ignore class="space-y-1.5" style="margin-bottom: 12px;">
                        <label class="premium-label">
                            <x-heroicon-m-cube class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />
                            Produk
                        </label>
                        <select wire:model.live="product_id" id="select-produk" data-inv-select2
                            data-placeholder="— Semua Produk —"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                            <option value="">— Semua Produk —</option>
                            @foreach (\App\Models\Product::orderBy('name')->get() as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                {{-- Active filter badge --}}
                @if ($warehouse_id || $product_id || ($show_movement_history && $start_date))
                    <div class="mt-4 flex flex-wrap gap-2"
                        style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px;">
                        @if ($warehouse_id)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-700"
                                style="padding: 4px 10px; border-radius: 9999px;">
                                <x-heroicon-m-funnel class="w-3 h-3" />
                                Gudang: {{ \App\Models\Warehouse::find($warehouse_id)?->name }}
                            </span>
                        @endif
                        @if ($product_id)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-700"
                                style="padding: 4px 10px; border-radius: 9999px;">
                                <x-heroicon-m-funnel class="w-3 h-3" />
                                Produk: {{ \App\Models\Product::find($product_id)?->name }}
                            </span>
                        @endif
                        @if ($show_movement_history && $start_date && $end_date)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-700"
                                style="padding: 4px 10px; border-radius: 9999px;">
                                <x-heroicon-m-calendar-days class="w-3 h-3" />
                                {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} —
                                {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
         KPI STAT CARDS
         ═══════════════════════════════════════════════ --}}
        @php
            if ($show_movement_history) {
                $baseMovQ = \App\Models\StockMovement::query()
                    ->when($warehouse_id, fn($q) => $q->where('warehouse_id', $warehouse_id))
                    ->when($product_id, fn($q) => $q->where('product_id', $product_id))
                    ->when($start_date, fn($q) => $q->whereDate('date', '>=', $start_date))
                    ->when($end_date, fn($q) => $q->whereDate('date', '<=', $end_date));
                $totalMovement = (clone $baseMovQ)->count();
                $totalIn = (clone $baseMovQ)->where('type', 'in')->count();
                $totalOut = (clone $baseMovQ)->where('type', 'out')->count();
                $stats = [
                    [
                        'label' => 'Total Movement',
                        'value' => number_format($totalMovement),
                        'sub' => 'entri movement',
                        'icon' => 'arrow-path',
                        'color' => 'teal',
                        'badge' => null,
                    ],
                    [
                        'label' => 'Total Masuk',
                        'value' => number_format($totalIn),
                        'sub' => 'transaksi IN',
                        'icon' => 'arrow-down-tray',
                        'color' => 'emerald',
                        'badge' => 'in',
                    ],
                    [
                        'label' => 'Total Keluar',
                        'value' => number_format($totalOut),
                        'sub' => 'transaksi OUT',
                        'icon' => 'arrow-up-tray',
                        'color' => 'rose',
                        'badge' => 'out',
                    ],
                ];
            } elseif ($show_aging_stock) {
                $baseStockQ = \App\Models\InventoryStock::query()
                    ->when($warehouse_id, fn($q) => $q->where('warehouse_id', $warehouse_id))
                    ->when($product_id, fn($q) => $q->where('product_id', $product_id));
                $totalItems = (clone $baseStockQ)->count();
                // Aging: items without movement in last 90 days = slow/dead
                $slowItems = 0;
                $deadItems = 0;
                foreach ((clone $baseStockQ)->with('product')->get() as $stock) {
                    $lastMov = \App\Models\StockMovement::where('product_id', $stock->product_id)
                        ->where('warehouse_id', $stock->warehouse_id)
                        ->latest('date')
                        ->value('date');
                    if (!$lastMov) {
                        $deadItems++;
                        continue;
                    }
                    $days = now()->diffInDays(\Carbon\Carbon::parse($lastMov));
                    if ($days > 180) {
                        $deadItems++;
                    } elseif ($days > 90) {
                        $slowItems++;
                    }
                }
                $stats = [
                    [
                        'label' => 'Total Item',
                        'value' => number_format($totalItems),
                        'sub' => 'item stok',
                        'icon' => 'archive-box',
                        'color' => 'teal',
                        'badge' => null,
                    ],
                    [
                        'label' => 'Slow Moving',
                        'value' => number_format($slowItems),
                        'sub' => '> 90 hari',
                        'icon' => 'clock',
                        'color' => 'amber',
                        'badge' => 'warning',
                    ],
                    [
                        'label' => 'Dead Stock',
                        'value' => number_format($deadItems),
                        'sub' => '> 180 hari',
                        'icon' => 'exclamation-triangle',
                        'color' => 'rose',
                        'badge' => 'danger',
                    ],
                ];
            } else {
                $baseStockQ = \App\Models\InventoryStock::query()
                    ->when($warehouse_id, fn($q) => $q->where('warehouse_id', $warehouse_id))
                    ->when($product_id, fn($q) => $q->where('product_id', $product_id));
                $totalItems = (clone $baseStockQ)->count();
                $normalItems = (clone $baseStockQ)->whereColumn('qty_available', '>', 'qty_min')->count();
                $lowItems = (clone $baseStockQ)->whereColumn('qty_available', '<=', 'qty_min')->count();
                $stats = [
                    [
                        'label' => 'Total Item Stok',
                        'value' => number_format($totalItems),
                        'sub' => 'item terdaftar',
                        'icon' => 'archive-box',
                        'color' => 'teal',
                        'badge' => null,
                    ],
                    [
                        'label' => 'Stok Normal',
                        'value' => number_format($normalItems),
                        'sub' => 'di atas minimum',
                        'icon' => 'check-circle',
                        'color' => 'emerald',
                        'badge' => 'normal',
                    ],
                    [
                        'label' => 'Stok Rendah',
                        'value' => number_format($lowItems),
                        'sub' => 'perlu restock',
                        'icon' => 'exclamation-triangle',
                        'color' => 'rose',
                        'badge' => 'danger',
                    ],
                ];
            }

            $colorMap = [
                'teal' => [
                    'bg' => 'bg-teal-100 dark:bg-teal-900/40',
                    'text' => 'text-teal-600 dark:text-teal-400',
                    'val' => 'text-teal-700 dark:text-teal-300',
                ],
                'emerald' => [
                    'bg' => 'bg-emerald-100 dark:bg-emerald-900/40',
                    'text' => 'text-emerald-600 dark:text-emerald-400',
                    'val' => 'text-emerald-700 dark:text-emerald-300',
                ],
                'rose' => [
                    'bg' => 'bg-rose-100 dark:bg-rose-900/40',
                    'text' => 'text-rose-600 dark:text-rose-400',
                    'val' => 'text-rose-700 dark:text-rose-300',
                ],
                'amber' => [
                    'bg' => 'bg-amber-100 dark:bg-amber-900/40',
                    'text' => 'text-amber-600 dark:text-amber-400',
                    'val' => 'text-amber-700 dark:text-amber-300',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print" style="margin-bottom: 24px;">
            @foreach ($stats as $stat)
                @php $c = $colorMap[$stat['color']] ?? $colorMap['teal']; @endphp
                <div class="premium-stat-card">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl {{ $c['bg'] }} {{ $c['text'] }} flex-shrink-0"
                        style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                        @switch($stat['icon'])
                            @case('archive-box')
                                <x-heroicon-o-archive-box class="w-6 h-6" />
                            @break

                            @case('arrow-path')
                                <x-heroicon-o-arrow-path class="w-6 h-6" />
                            @break

                            @case('arrow-down-tray')
                                <x-heroicon-o-arrow-down-tray class="w-6 h-6" />
                            @break

                            @case('arrow-up-tray')
                                <x-heroicon-o-arrow-up-tray class="w-6 h-6" />
                            @break

                            @case('clock')
                                <x-heroicon-o-clock class="w-6 h-6" />
                            @break

                            @case('check-circle')
                                <x-heroicon-o-check-circle class="w-6 h-6" />
                            @break

                            @case('exclamation-triangle')
                                <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                            @break

                            @default
                                <x-heroicon-o-chart-bar class="w-6 h-6" />
                        @endswitch
                    </div>
                    <div class="min-w-0">
                        <p class="premium-stat-label">{{ $stat['label'] }}</p>
                        <p class="premium-stat-value {{ $c['val'] }}">{{ $stat['value'] }}</p>
                        <p class="premium-stat-sub">{{ $stat['sub'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════
         TABLE SECTION
         ═══════════════════════════════════════════════ --}}
        <div class="premium-table-card">

            {{-- Table Header Bar ── Bulletproof Layout --}}
            <div class="premium-card-header"
                style="justify-content: space-between; display: flex; align-items: center; width: 100%;">
                <div class="flex items-center gap-3" style="display: flex; align-items: center; gap: 12px;">
                    <div class="premium-card-icon-container">
                        @if ($show_movement_history)
                            <x-heroicon-o-arrow-trending-up class="w-4 h-4" />
                        @elseif($show_aging_stock)
                            <x-heroicon-o-clock class="w-4 h-4" />
                        @else
                            <x-heroicon-o-archive-box class="w-4 h-4" />
                        @endif
                    </div>
                    <div>
                        @if ($show_movement_history)
                            <h3 class="premium-card-title">History Movement Stok</h3>
                            <p class="premium-card-subtitle">
                                Riwayat pergerakan stok
                                @if ($start_date && $end_date)
                                    dari {{ \Carbon\Carbon::parse($start_date)->format('d M Y') }}
                                    sampai {{ \Carbon\Carbon::parse($end_date)->format('d M Y') }}
                                @endif
                            </p>
                        @elseif($show_aging_stock)
                            <h3 class="premium-card-title">Aging Stock Analysis</h3>
                            <p class="premium-card-subtitle">Analisis umur stok untuk deteksi slow-moving & dead stock
                            </p>
                        @else
                            <h3 class="premium-card-title">Stok Barang per Gudang</h3>
                            <p class="premium-card-subtitle">Informasi posisi stok terkini di setiap gudang</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0"
                    style="display: flex; align-items: center; gap: 8px;">
                    {{-- Active mode badge --}}
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border"
                        style="padding: 4px 10px; border-radius: 9999px; font-weight: 700;
                    @if ($show_movement_history) background-color: #f0fdf4; color: #166534; border-color: #bbf7d0;
                    @elseif($show_aging_stock)  background-color: #fef9c3; color: #854d0e; border-color: #fef08a;
                    @else                       background-color: #ecfdf5; color: #065f46; border-color: #a7f3d0; @endif">
                        @if ($show_movement_history)
                            Movement
                        @elseif($show_aging_stock)
                            Aging
                        @else
                            Stok
                        @endif
                    </span>
                    <button onclick="window.print()" class="premium-btn-print no-print">
                        <x-heroicon-o-printer class="w-4 h-4" />
                        Cetak
                    </button>
                </div>
            </div>

            {{-- Filament Table --}}
            {{ $this->table }}

        </div>

    </div>

    {{-- ═══════════════════════════════════════════════
     SELECT2 INITS & SYNC WITH LIVEWIRE
     ═══════════════════════════════════════════════ --}}
    <script>
        (function initSelect2() {
            function buildSelect2(el, opts) {
                $(el).select2(Object.assign({
                    width: '100%',
                    dropdownParent: $('body'),
                }, opts));

                // Sync back to Livewire on change
                $(el).on('change.select2', function() {
                    const wireModel = el.getAttribute('wire:model') || el.getAttribute('wire\\:model');
                    if (wireModel && window.Livewire) {
                        const component = window.Livewire.find(el.closest('[wire\\:id]')?.getAttribute(
                            'wire:id'));
                        if (component) {
                            component.set(wireModel, this.value || null);
                        }
                    }
                });
            }

            function init() {
                if (typeof $ === 'undefined' || !$.fn.select2) {
                    setTimeout(init, 150);
                    return;
                }

                document.querySelectorAll('[data-inv-select2]').forEach(function(el) {
                    if ($(el).data('select2')) return; // already initialised
                    const placeholder = el.getAttribute('data-placeholder') || 'Pilih…';
                    buildSelect2(el, {
                        placeholder: placeholder,
                        allowClear: true,
                    });
                });
            }

            // Run on first load
            init();

            // Re-init after Livewire navigations
            document.addEventListener('livewire:navigated', init);

            // Re-init after Livewire DOM updates (re-rendered component)
            document.addEventListener('livewire:update', function() {
                setTimeout(init, 80);
            });
        })();
    </script>

</x-filament-panels::page>
