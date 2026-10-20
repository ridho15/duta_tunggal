<x-filament-panels::page>
    <div class="dt-hp">
        <x-hp.warehouse-picker :warehouse-id="$warehouseId" :warehouse-label="$warehouseLabel" :options="$warehouseOptions" />

        @if ($warehouseId)
            <div class="dt-hp-search">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari no. DO / SO / customer">
                <x-hp.scan-button label="Scan DO atau barang" />
            </div>

            <div class="dt-hp-chips">
                @foreach ([
                    \App\Services\Hp\CheckerQueue::TAB_READY => 'Siap dicek',
                    \App\Services\Hp\CheckerQueue::TAB_WAITING_SJ => 'Menunggu SJ',
                    \App\Services\Hp\CheckerQueue::TAB_SENT_TODAY => 'Terkirim hari ini',
                ] as $key => $label)
                    @if ($key !== \App\Services\Hp\CheckerQueue::TAB_WAITING_SJ || ($counts[$key] ?? 0) > 0 || $tab === $key)
                        <button type="button" wire:click="setTab('{{ $key }}')" @class(['dt-hp-chip', 'is-active' => $tab === $key])>
                            {{ $label }} · {{ $counts[$key] ?? 0 }}
                        </button>
                    @endif
                @endforeach
            </div>

            @forelse ($orders as $order)
                @php
                    $items = $queue->items($order);
                    $checked = $queue->checkedItemIds($order);
                    $racks = $items->flatMap(fn ($item) => $item->warehouseSources->pluck('rak.code'))->filter()->unique()->take(4)->implode(', ');
                    $customer = $order->salesOrders->first()?->customer?->name;
                    $ageDays = $order->updated_at ? (int) $order->updated_at->startOfDay()->diffInDays(today()) : 0;
                    $doneCount = $items->filter(fn ($item) => in_array((int) $item->id, $checked, true))->count();
                @endphp
                <a href="{{ \App\Filament\Pages\Hp\KirimanDetailPage::getUrl(['record' => $order->id]) }}"
                   @class(['dt-hp-card', 'is-dashed' => $tab === \App\Services\Hp\CheckerQueue::TAB_WAITING_SJ, 'is-done' => $tab === \App\Services\Hp\CheckerQueue::TAB_SENT_TODAY])>
                    <div class="dt-hp-row">
                        <span class="dt-hp-name">{{ $order->do_number }}</span>
                        <span @class(['dt-hp-pill', 'is-info' => $tab === 'siap', 'is-warning' => $tab === 'menunggu_sj'])>
                            {{ match ($tab) { 'menunggu_sj' => 'Menunggu SJ', 'terkirim' => 'Terkirim', default => 'Siap dicek' } }}
                        </span>
                    </div>
                    <div class="dt-hp-sub">{{ $customer ?? '-' }} · {{ $items->count() }} barang · {{ rtrim(rtrim(number_format($items->sum('quantity'), 2, ',', '.'), '0'), ',') }} pcs</div>
                    <div class="dt-hp-row">
                        <span class="dt-hp-code">
                            @if ($tab === 'siap' && $ageDays > 1)
                                <span style="color:#92400E;font-weight:600">! Tertahan {{ $ageDays }} hari</span>
                            @elseif ($racks)
                                Rak {{ $racks }}
                            @endif
                            @if ($doneCount > 0 && $tab !== 'terkirim')
                                · {{ $doneCount }}/{{ $items->count() }} dicek
                            @endif
                        </span>
                        @if ($tab !== 'terkirim')
                            <span class="dt-hp-btn is-primary is-small">{{ $doneCount > 0 ? 'Lanjutkan' : 'Mulai cek' }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="dt-hp-empty">
                    @if ($tab === 'terkirim')
                        Belum ada DO yang dikirim hari ini.
                    @else
                        Tidak ada DO yang menunggu di gudang ini. DO akan muncul di sini setelah dikonfirmasi gudang.
                    @endif
                </div>
            @endforelse

            @if ($pickingListUrl && ($counts['siap'] ?? 0) + ($counts['menunggu_sj'] ?? 0) > 0)
                <a href="{{ $pickingListUrl }}" target="_blank" class="dt-hp-link" style="text-align:center">⎙ Cetak picking list gudang ini</a>
            @endif
        @endif
    </div>
</x-filament-panels::page>
