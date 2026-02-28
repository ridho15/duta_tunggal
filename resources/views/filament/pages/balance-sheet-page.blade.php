<x-filament-panels::page>
    @php
        if ($this->showPreview) {
            $data = $this->getBalanceSheetData();
            $comparison = $this->getComparisonData();
            $multiPeriodData = $this->getMultiPeriodData();
            $drillDownData = $this->getDrillDownData();
        } else {
            $data = null;
            $comparison = null;
            $multiPeriodData = [];
            $drillDownData = null;
        }
    @endphp

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .fi-topbar,
            .fi-sidebar,
            .fi-page-header,
            .filter-section,
            button {
                display: none !important;
            }

            .balance-sheet-table {
                page-break-inside: avoid;
            }

            body {
                margin: 0;
                padding: 20px;
            }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
        }

        .print-header {
            display: none;
        }

        /* Modern Card Design */
        .modern-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        /* Summary Cards with Icons */
        .summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .summary-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .assets-icon { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .liabilities-icon { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .equity-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .ratio-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }

        /* Section Headers */
        .section-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 1.25rem 2rem;
            font-weight: 700;
            font-size: 1.125rem;
            border-radius: 12px;
            margin: 2rem 0 1.5rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .section-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .section-header.assets {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .section-header.liabilities {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .section-header.equity {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        /* Subsection Headers */
        .subsection-header {
            font-weight: 700;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0 1rem 0;
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            color: #1e293b;
            font-size: 1rem;
        }

        /* Account Rows */
        .account-row {
            padding: 0.875rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            border-radius: 6px;
            margin: 0.25rem 0;
        }

        .account-row:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateX(4px);
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }

        .account-name {
            flex: 1;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }

        .account-code {
            color: #6b7280;
            font-weight: 600;
            margin-right: 0.75rem;
            font-family: 'Monaco', 'Menlo', monospace;
        }

        .account-balance {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-weight: 600;
            font-size: 0.95rem;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .account-balance:hover {
            background: #dbeafe;
            color: #1d4ed8;
            transform: scale(1.02);
        }

        .account-balance.negative {
            color: #dc2626;
        }

        /* Total Rows */
        .total-row {
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 800;
            font-size: 1.1rem;
            border-top: 3px solid #374151;
            border-bottom: 3px solid #374151;
            margin: 1rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .subtotal-row {
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 1rem;
            border-top: 2px solid #6b7280;
            margin: 1rem 0;
            background: #f8fafc;
            border-radius: 6px;
        }

        /* Balance Check */
        .balance-check {
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .balance-check.balanced {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 2px solid #16a34a;
        }

        .balance-check.unbalanced {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        /* Filter Section */
        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-input {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .filter-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .filter-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        /* Multi-Period Styles */
        .multi-period-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .period-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-input {
            flex: 1;
        }

        .remove-period-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }

        .remove-period-btn:hover {
            background: #dc2626;
        }

        .add-period-btn {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .add-period-btn:hover {
            background: #059669;
        }

        /* Display Options Styles */
        .display-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .option-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .option-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .radio-group {
            display: flex;
            gap: 1rem;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: #374151;
        }

        .radio-label input[type="radio"] {
            width: auto;
            margin: 0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: #374151;
        }

        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        /* Period Summary Styles */
        .period-summary-group {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .period-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 8px;
        }

        .period-cards {
            gap: 1rem;
        }

        /* Period Section Styles */
        .period-section {
            margin-bottom: 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .period-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 1.5rem 2rem;
            font-weight: 700;
            font-size: 1.25rem;
            text-align: center;
            margin: 0;
        }

        /* Comparison Section Styles */
        .comparison-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #0ea5e9;
            border-radius: 12px;
        }

        .comparison-header {
            font-size: 1.125rem;
            font-weight: 700;
            color: #0c4a6e;
            margin-bottom: 1rem;
            text-align: center;
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .comparison-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e0f2fe;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .comparison-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .comparison-values {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .current-value {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.1rem;
        }

        .comparison-value {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .difference {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .difference.positive {
            color: #059669;
        }

        .difference.negative {
            color: #dc2626;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        }

        .btn-export {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .account-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .account-balance {
                align-self: flex-end;
            }
        }

        /* Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Focus States for Form Inputs - focus: border-color: ring */
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            ring: 2px;
            ring-color: rgba(59, 130, 246, 0.2);
        }

        .filter-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            ring: 2px;
            ring-color: rgba(59, 130, 246, 0.2);
        }
    </style>

    <div class="print-header">
        <h1 style="margin: 0; font-size: 1.5rem;">NERACA (BALANCE SHEET)</h1>
        <p style="margin: 0.5rem 0;">Per Tanggal: {{ \Carbon\Carbon::parse($this->as_of_date)->format('d F Y') }}</p>
        @if($this->cabang_id)
            <p style="margin: 0;">Cabang: {{ $this->getCabangOptions()[$this->cabang_id] ?? 'N/A' }}</p>
        @else
            <p style="margin: 0;">Semua Cabang</p>
        @endif
    </div>

    <!-- Filter Section -->
    <div class="filter-section no-print">
        <div class="filter-grid">
            <!-- Single Date Mode -->
            <div class="filter-group" x-show="!$wire.use_multi_period">
                <label class="filter-label">📅 Tanggal Neraca</label>
                <input type="date"
                       wire:model="as_of_date"
                       class="filter-input">
            </div>

            <!-- Multi-Period Mode -->
            <div class="filter-group" x-show="$wire.use_multi_period">
                <label class="filter-label">📊 Periode Multi</label>
                <div class="multi-period-container">
                    @foreach($selected_periods as $index => $period)
                        <div class="period-item">
                            <input type="date"
                                   wire:model="selected_periods.{{ $index }}"
                                   wire:change="updatePeriod({{ $index }}, $event.target.value)"
                                   class="filter-input period-input">
                            <button type="button"
                                    wire:click="removePeriod({{ $index }})"
                                    class="remove-period-btn"
                                    x-show="$wire.selected_periods.length > 1">
                                ❌
                            </button>
                        </div>
                    @endforeach
                    <button type="button"
                            wire:click="addPeriod"
                            class="add-period-btn">
                        ➕ Tambah Periode
                    </button>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">🏢 Cabang</label>
                <select wire:model="cabang_id"
                        class="filter-input filter-select">
                    <option value="">🌐 Semua Cabang</option>
                    @foreach($this->getCabangOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">⚙️ Opsi Tampilan</label>
                <div class="display-options">
                    <div class="option-group">
                        <label class="option-label">Mode Periode:</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" wire:model.live="use_multi_period" value="0">
                                <span>Single Periode</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" wire:model.live="use_multi_period" value="1">
                                <span>Multi Periode</span>
                            </label>
                        </div>
                    </div>

                    <div class="option-group">
                        <label class="option-label">Level Tampilan:</label>
                        <select wire:model.live="display_level" class="filter-input filter-select">
                            <option value="all">📋 Semua Akun</option>
                            <option value="parent_only">🏠 Hanya Akun Induk</option>
                            <option value="totals_only">💰 Hanya Total</option>
                        </select>
                    </div>

                    <div class="option-group">
                        <label class="checkbox-label">
                            <input type="checkbox" wire:model.live="show_zero_balance">
                            <span>📊 Tampilkan Saldo 0</span>
                        </label>
                    </div>

                    <div class="option-group">
                        <label class="checkbox-label">
                            <input type="checkbox" wire:model.live="show_comparison" x-show="!$wire.use_multi_period">
                            <span>🔄 Bandingkan Periode</span>
                        </label>
                        <input type="date"
                               wire:model="comparison_date"
                               class="filter-input"
                               x-show="$wire.show_comparison && !$wire.use_multi_period"
                               style="margin-top: 0.5rem;">
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button"
                    wire:click="generateReport"
                    class="btn-primary">
                🔄 Perbarui Laporan
            </button>

            <button type="button"
                    wire:click="exportPdf"
                    class="btn-secondary btn-export">
                📄 Export PDF
            </button>

            <button type="button"
                    wire:click="exportExcel"
                    class="btn-secondary btn-export">
                📊 Export Excel
            </button>

            <button type="button"
                    onclick="window.print()"
                    class="btn-secondary btn-export">
                🖨️ Print
            </button>
        </div>
    </div>

    @if($this->showPreview)
    <!-- Summary Cards -->
    <div class="summary-grid no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        @if($use_multi_period && !empty($multiPeriodData))
            @foreach($multiPeriodData as $period => $periodData)
                <div class="period-summary-group">
                    <h3 class="period-title">{{ \Carbon\Carbon::parse($period)->format('M Y') }}</h3>
                    <div class="period-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="summary-card">
                            <div class="summary-icon assets-icon">💰</div>
                            <div class="summary-label">Total Aset</div>
                            <div class="summary-value text-green-600">
                                Rp {{ number_format($periodData['total_assets'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon liabilities-icon">💰</div>
                            <div class="summary-label">Total Kewajiban</div>
                            <div class="summary-value text-yellow-600">
                                Rp {{ number_format($periodData['total_liabilities'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon equity-icon">🏛️</div>
                            <div class="summary-label">Total Ekuitas</div>
                            <div class="summary-value text-blue-600">
                                Rp {{ number_format($periodData['total_equity'], 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="summary-card">
                <div class="summary-icon assets-icon">
                    💰
                </div>
                <div class="summary-label">Total Aset</div>
                <div class="summary-value text-green-600">
                    Rp {{ number_format($data['total_assets'], 0, ',', '.') }}
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon liabilities-icon">
                    📊
                </div>
                <div class="summary-label">Total Kewajiban</div>
                <div class="summary-value text-yellow-600">
                    Rp {{ number_format($data['total_liabilities'], 0, ',', '.') }}
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon equity-icon">
                    🏛️
                </div>
                <div class="summary-label">Total Ekuitas</div>
                <div class="summary-value text-blue-600">
                    Rp {{ number_format($data['total_equity'], 0, ',', '.') }}
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon ratio-icon">
                    📈
                </div>
                <div class="summary-label">Rasio Lancar</div>
                <div class="summary-value text-purple-600">
                    {{ number_format($data['current_assets']['total'] / max($data['current_liabilities']['total'], 1), 2) }}
                </div>
            </div>
        @endif
    </div>

    <!-- Balance Sheet Table -->
    <div class="modern-card balance-sheet-table" style="padding: 2rem;">
        @if($use_multi_period && !empty($multiPeriodData))
            <!-- Multi-Period Display -->
            @foreach($multiPeriodData as $period => $periodData)
                <div class="period-section">
                    <h2 class="period-header">{{ \Carbon\Carbon::parse($period)->format('F Y') }}</h2>
                    @include('filament.pages.partials.balance-sheet-table', ['data' => $periodData, 'show_comparison' => false, 'comparison' => null])
                </div>
            @endforeach
        @else
            <!-- Single Period Display -->
            @include('filament.pages.partials.balance-sheet-table', ['data' => $data, 'show_comparison' => $show_comparison, 'comparison' => $comparison ?? null])
        @endif
    </div>

    <!-- Drill-down Modal -->
    @if($show_drill_down)
        <div wire:click="closeDrillDown"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-y-auto m-4"
                 wire:click.stop>
                
                <div class="p-6 border-b border-gray-200 sticky top-0 bg-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold">{{ $drillDownData['account']->kode }} - {{ $drillDownData['account']->nama }}</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Per {{ \Carbon\Carbon::parse($this->as_of_date)->format('d F Y') }}
                            </p>
                        </div>
                        <button wire:click="closeDrillDown"
                                class="text-gray-400 hover:text-gray-600 text-2xl font-bold">
                            ×
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Summary -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded">
                            <div class="text-sm text-gray-600">Total Debit</div>
                            <div class="text-lg font-bold text-blue-600">
                                Rp {{ number_format($drillDownData['total_debit'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="bg-red-50 p-4 rounded">
                            <div class="text-sm text-gray-600">Total Kredit</div>
                            <div class="text-lg font-bold text-red-600">
                                Rp {{ number_format($drillDownData['total_credit'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="bg-green-50 p-4 rounded">
                            <div class="text-sm text-gray-600">Saldo</div>
                            <div class="text-lg font-bold text-green-600">
                                Rp {{ number_format($drillDownData['balance'], 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Referensi</th>
                                    <th class="px-3 py-2 text-left">Deskripsi</th>
                                    <th class="px-3 py-2 text-left">Cabang</th>
                                    <th class="px-3 py-2 text-right">Debit</th>
                                    <th class="px-3 py-2 text-right">Kredit</th>
                                    <th class="px-3 py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $runningBalance = 0; @endphp
                                @foreach($drillDownData['entries'] as $entry)
                                    @php
                                        $runningBalance += ($entry->debit - $entry->credit);
                                    @endphp
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-3 py-2">{{ \Carbon\Carbon::parse($entry->transaction_date)->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2">{{ $entry->reference }}</td>
                                        <td class="px-3 py-2">{{ $entry->description }}</td>
                                        <td class="px-3 py-2">{{ $entry->cabang->kode ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            @if($entry->debit > 0)
                                                Rp {{ number_format($entry->debit, 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if($entry->credit > 0)
                                                Rp {{ number_format($entry->credit, 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium">
                                            Rp {{ number_format($runningBalance, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600">
                        Total {{ $drillDownData['entries']->count() }} transaksi
                    </div>
                </div>
            </div>
        </div>
    @endif

    @else
    <div class="p-10 text-center text-gray-500 dark:text-gray-400">
        <svg class="mx-auto mb-3 h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
        </svg>
        <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
    </div>
    @endif

    <script>
        window.addEventListener('print-report', () => {
            window.print();
        });
    </script>
</x-filament-panels::page>
