<x-filament-panels::page>
    <style>
        .rekon-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .rekon-filters {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .rekon-filter-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .rekon-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .rekon-filter-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .rekon-filter-label {
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rekon-input, .rekon-select {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .rekon-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .rekon-input:focus, .rekon-select:focus {
            outline: none;
            border-color: white;
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .rekon-select option {
            background: #1f2937;
            color: white;
        }

        .rekon-toggle-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .rekon-toggle-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rekon-toggle-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .rekon-toggle-btn.inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .rekon-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .rekon-table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .rekon-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rekon-table thead {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }

        .rekon-table th {
            padding: 1.25rem 1rem;
            text-align: left;
            color: white;
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rekon-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .rekon-table tbody tr:hover {
            background: #f9fafb;
            transform: scale(1.01);
        }

        .rekon-table tbody tr.unconfirmed {
            background: #fee2e2 !important; /* Highlight merah untuk yang belum dikonfirmasi */
            border-left: 4px solid #ef4444;
        }

        .rekon-table tbody tr.unconfirmed:hover {
            background: #fecaca !important;
        }

        .rekon-table tbody tr.confirmed {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
        }

        .rekon-table tbody tr.confirmed:hover {
            background: #dcfce7 !important;
        }

        .rekon-table td {
            padding: 1.25rem 1rem;
            color: #374151;
            font-size: 0.875rem;
        }

        .rekon-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: #22c55e;
        }

        .rekon-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rekon-badge.confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .rekon-badge.unconfirmed {
            background: #fee2e2;
            color: #991b1b;
        }

        .rekon-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .rekon-empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .rekon-empty-text {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .rekon-empty-subtext {
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .rekon-filter-grid {
                grid-template-columns: 1fr;
            }

            .rekon-table-container {
                overflow-x: auto;
            }

            .rekon-table {
                min-width: 800px;
            }
        }
    </style>

    <div class="rekon-container">
        <!-- Filter Section -->
        <div class="rekon-filters">
            <h2 class="rekon-filter-title">
                <span>🔍</span>
                <span>Filter Transaksi</span>
            </h2>

            <div class="rekon-filter-grid">
                <div class="rekon-filter-item">
                    <label class="rekon-filter-label">Akun Bank / Kas</label>
                    <select wire:model.live="selectedCoaId" class="rekon-select">
                        <option value="">-- Pilih Akun --</option>
                        @foreach($this->getCoaOptions() as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rekon-filter-item">
                    <label class="rekon-filter-label">Tanggal Mulai</label>
                    <input type="date" wire:model.live="startDate" class="rekon-input">
                </div>

                <div class="rekon-filter-item">
                    <label class="rekon-filter-label">Tanggal Akhir</label>
                    <input type="date" wire:model.live="endDate" class="rekon-input">
                </div>
            </div>
        </div>

        <!-- Toggle Show/Hide Confirmed -->
        <div class="rekon-toggle-container">
            <span style="font-weight: 600; color: #374151;">Tampilkan Data:</span>
            <button 
                wire:click="toggleShowConfirmed" 
                class="rekon-toggle-btn {{ $showConfirmed ? 'active' : 'inactive' }}"
            >
                @if($showConfirmed)
                    <span>👁️</span>
                    <span>Semua Data (Termasuk yang Sudah Dikonfirmasi)</span>
                @else
                    <span>🚫</span>
                    <span>Hanya yang Belum Dikonfirmasi</span>
                @endif
            </button>
        </div>

        <!-- Table -->
        <div class="rekon-table-container">
            @if(count($this->getEntries()) > 0)
                <table class="rekon-table">
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">✓ Ada di Rekening</th>
                            <th style="width: 120px;">Tanggal</th>
                            <th style="width: 150px;">Referensi</th>
                            <th>Deskripsi</th>
                            <th style="width: 150px; text-align: right;">Debit</th>
                            <th style="width: 150px; text-align: right;">Kredit</th>
                            <th style="width: 130px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getEntries() as $entry)
                            <tr class="{{ $entry['is_confirmed'] ? 'confirmed' : 'unconfirmed' }}">
                                <td style="text-align: center;">
                                    <input 
                                        type="checkbox" 
                                        class="rekon-checkbox"
                                        wire:click="toggleConfirmation({{ $entry['id'] }})"
                                        {{ $entry['is_confirmed'] ? 'checked' : '' }}
                                    >
                                </td>
                                <td>{{ \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') }}</td>
                                <td>{{ $entry['reference'] }}</td>
                                <td>{{ $entry['description'] }}</td>
                                <td style="text-align: right; font-family: monospace;">
                                    {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0, ',', '.') : '-' }}
                                </td>
                                <td style="text-align: right; font-family: monospace;">
                                    {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0, ',', '.') : '-' }}
                                </td>
                                <td style="text-align: center;">
                                    <span class="rekon-badge {{ $entry['is_confirmed'] ? 'confirmed' : 'unconfirmed' }}">
                                        {{ $entry['is_confirmed'] ? '✓ Dikonfirmasi' : '⚠ Belum' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rekon-empty">
                    <div class="rekon-empty-icon">📋</div>
                    <div class="rekon-empty-text">Tidak Ada Data</div>
                    <div class="rekon-empty-subtext">
                        Pilih akun bank/kas dan periode tanggal untuk menampilkan transaksi
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
