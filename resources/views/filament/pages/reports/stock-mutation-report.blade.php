<x-filament-panels::page>
    @php($report = $this->getReportData())

    <div style="margin: 0 auto; max-width: 1200px; padding: 20px;">
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="font-size: 28px; font-weight: bold; color: #1f2937; margin-bottom: 10px;">Laporan Mutasi Barang Per Gudang</h1>
            <p style="font-size: 16px; color: #6b7280;">Detail pergerakan stock barang per gudang dalam periode tertentu</p>
        </div>

        <!-- Filter Form -->
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Tanggal Mulai</label>
                    <input type="date" name="start" value="{{ request('start', \Carbon\Carbon::parse($report['period']['start'])->format('Y-m-d')) }}" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;" />
                </div>
                <div>
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Tanggal Selesai</label>
                    <input type="date" name="end" value="{{ request('end', \Carbon\Carbon::parse($report['period']['end'])->format('Y-m-d')) }}" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;" />
                </div>
                <button type="submit" style="background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s;">
                    <svg style="width: 16px; height: 16px; display: inline; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Terapkan Filter
                </button>
                <button wire:click="exportExcel" style="background: #16a34a; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s;">
                    <svg style="width: 16px; height: 16px; display: inline; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Excel
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 14px; font-weight: 500; color: #2563eb; margin-bottom: 10px;">Periode</div>
                <div style="font-size: 18px; font-weight: 600; color: #1e40af;">
                    {{ \Carbon\Carbon::parse($report['period']['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['period']['end'])->format('d/m/Y') }}
                </div>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 14px; font-weight: 500; color: #16a34a; margin-bottom: 10px;">Total Gudang</div>
                <div style="font-size: 18px; font-weight: 600; color: #166534;">
                    {{ count($report['warehouseData']) }} gudang
                </div>
            </div>
            <div style="background: #faf5ff; border: 1px solid #f3e8ff; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 14px; font-weight: 500; color: #9333ea; margin-bottom: 10px;">Total Transaksi</div>
                <div style="font-size: 18px; font-weight: 600; color: #7c2d12;">
                    {{ number_format($report['totals']['total_movements']) }}
                </div>
            </div>
            <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 14px; font-weight: 500; color: #ea580c; margin-bottom: 10px;">Net Quantity</div>
                <div style="font-size: 18px; font-weight: 600; color: #9a3412;">
                    {{ number_format($report['totals']['total_qty_in'] - $report['totals']['total_qty_out'], 2) }}
                </div>
            </div>
        </div>

        @if(empty($report['warehouseData']))
            <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                <svg style="width: 48px; height: 48px; color: #9ca3af; margin: 0 auto 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 style="font-size: 20px; font-weight: 500; color: #111827; margin-bottom: 10px;">Tidak ada data mutasi</h3>
                <p style="font-size: 16px; color: #6b7280;">Tidak ditemukan transaksi stock movement dalam periode yang dipilih.</p>
            </div>
        @else
            @foreach($report['warehouseData'] as $warehouse)
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 30px; overflow: hidden;">
                    <div style="background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 20px;">
                        <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin: 0;">
                            Gudang: {{ $warehouse['warehouse_name'] }}
                            @if($warehouse['warehouse_code'])
                                <span style="font-size: 14px; color: #6b7280;">({{ $warehouse['warehouse_code'] }})</span>
                            @endif
                        </h2>
                    </div>

                    <!-- Warehouse Summary -->
                    <div style="padding: 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                            <div style="text-align: center;">
                                <div style="font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Qty Masuk</div>
                                <div style="font-size: 18px; font-weight: 600; color: #16a34a;">{{ number_format($warehouse['summary']['qty_in'], 2) }}</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Qty Keluar</div>
                                <div style="font-size: 18px; font-weight: 600; color: #dc2626;">{{ number_format($warehouse['summary']['qty_out'], 2) }}</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Net Qty</div>
                                <div style="font-size: 18px; font-weight: 600; color: {{ $warehouse['summary']['net_qty'] >= 0 ? '#16a34a' : '#dc2626' }};">
                                    {{ number_format($warehouse['summary']['net_qty'], 2) }}
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 5px;">Total Transaksi</div>
                                <div style="font-size: 18px; font-weight: 600; color: #2563eb;">{{ count($warehouse['movements']) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Movements Table -->
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <thead style="background: #f9fafb;">
                                <tr>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Tanggal</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Produk</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Tipe</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Qty Masuk</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Qty Keluar</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Nilai</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Referensi</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Rak</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($warehouse['movements'] as $movement)
                                    <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 16px; font-weight: 500; color: #111827;">
                                            {{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <div style="font-weight: 500; color: #111827;">{{ $movement['product_name'] }}</div>
                                            @if($movement['product_sku'])
                                                <div style="font-size: 12px; color: #6b7280;">{{ $movement['product_sku'] }}</div>
                                            @endif
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500;
                                                @if(str_contains($movement['type'], 'Masuk'))
                                                    background: #dcfce7; color: #166534;
                                                @elseif(str_contains($movement['type'], 'Keluar'))
                                                    background: #fee2e2; color: #991b1b;
                                                @else
                                                    background: #dbeafe; color: #1e40af;
                                                @endif">
                                                {{ $movement['type'] }}
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; font-weight: 500; color: #16a34a;">
                                            {{ $movement['qty_in'] > 0 ? number_format($movement['qty_in'], 2) : '-' }}
                                        </td>
                                        <td style="padding: 12px 16px; font-weight: 500; color: #dc2626;">
                                            {{ $movement['qty_out'] > 0 ? number_format($movement['qty_out'], 2) : '-' }}
                                        </td>
                                        <td style="padding: 12px 16px; color: #374151;">
                                            {{ $movement['value'] ? 'Rp ' . number_format($movement['value'], 0) : '-' }}
                                        </td>
                                        <td style="padding: 12px 16px; color: #6b7280;">
                                            {{ $movement['reference'] ?: '-' }}
                                        </td>
                                        <td style="padding: 12px 16px; color: #6b7280;">
                                            {{ $movement['rak_name'] ?: '-' }}
                                        </td>
                                        <td style="padding: 12px 16px; color: #6b7280; max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="{{ $movement['notes'] }}">
                                            {{ $movement['notes'] ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <!-- Grand Total Summary -->
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 20px; text-align: center;">Ringkasan Total Keseluruhan</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 20px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 500; color: #166534; margin-bottom: 10px;">Total Qty Masuk</div>
                        <div style="font-size: 24px; font-weight: 700; color: #166534;">
                            {{ number_format($report['totals']['total_qty_in'], 2) }}
                        </div>
                    </div>
                    <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 500; color: #991b1b; margin-bottom: 10px;">Total Qty Keluar</div>
                        <div style="font-size: 24px; font-weight: 700; color: #991b1b;">
                            {{ number_format($report['totals']['total_qty_out'], 2) }}
                        </div>
                    </div>
                    <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 20px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 500; color: #1e40af; margin-bottom: 10px;">Net Quantity</div>
                        <div style="font-size: 24px; font-weight: 700; color: #1e40af;">
                            {{ number_format($report['totals']['total_qty_in'] - $report['totals']['total_qty_out'], 2) }}
                        </div>
                    </div>
                    <div style="background: #faf5ff; border: 1px solid #f3e8ff; border-radius: 8px; padding: 20px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 500; color: #7c2d12; margin-bottom: 10px;">Total Transaksi</div>
                        <div style="font-size: 24px; font-weight: 700; color: #7c2d12;">
                            {{ number_format($report['totals']['total_movements']) }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>