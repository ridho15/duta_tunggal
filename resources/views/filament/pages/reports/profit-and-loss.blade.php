<x-filament::page>
    <div>
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        @php
            try {
                $data = method_exists($this, 'getReportData') ? $this->getReportData() : null;
            } catch (\Throwable $e) {
                // Log and fallback to zeroed dataset to avoid blade errors
                \Illuminate\Support\Facades\Log::error('[profit-and-loss] getReportData error: ' . $e->getMessage());
                $data = null;
            }

            if (!is_array($data)) {
                $data = [
                    'revenue' => 0,
                    'expense' => 0,
                    'gross_profit' => 0,
                    'operating_profit' => 0,
                    'other_net' => 0,
                    'profit_before_tax' => 0,
                    'tax' => 0,
                    'net_profit' => 0,
                ];
            }
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <div class="p-4 border rounded">
                <h2 class="font-semibold mb-2">Ringkasan</h2>
                <div class="flex justify-between"><span>Pendapatan</span><span>Rp {{ number_format($data['revenue']) }}</span></div>
                <div class="flex justify-between"><span>Beban</span><span>Rp {{ number_format($data['expense']) }}</span></div>
                <div class="flex justify-between"><span>Laba Kotor</span><span>Rp {{ number_format($data['gross_profit']) }}</span></div>
                <div class="flex justify-between"><span>Laba Operasional</span><span>Rp {{ number_format($data['operating_profit']) }}</span></div>
                <div class="flex justify-between"><span>Pendapatan/Beban Lain</span><span>Rp {{ number_format($data['other_net']) }}</span></div>
                <div class="flex justify-between"><span>Laba Sebelum Pajak</span><span>Rp {{ number_format($data['profit_before_tax']) }}</span></div>
                <div class="flex justify-between"><span>Pajak</span><span>Rp {{ number_format($data['tax']) }}</span></div>
                <div class="flex justify-between font-bold"><span>Laba Bersih</span><span>Rp {{ number_format($data['net_profit']) }}</span></div>
            </div>
            <div class="p-4 border rounded">
                <h2 class="font-semibold mb-2">Grafik Sederhana</h2>
                <div class="space-y-2">
                    @php $max = max(1, abs($data['revenue']), abs($data['expense']), abs($data['net_profit'])); @endphp
                    <div>
                        <div class="text-sm">Pendapatan</div>
                        <div class="h-3 bg-green-500" style="width: {{ (abs($data['revenue'])/$max)*100 }}%"></div>
                    </div>
                    <div>
                        <div class="text-sm">Beban</div>
                        <div class="h-3 bg-red-500" style="width: {{ (abs($data['expense'])/$max)*100 }}%"></div>
                    </div>
                    <div>
                        <div class="text-sm">Laba Bersih</div>
                        <div class="h-3 bg-blue-500" style="width: {{ (abs($data['net_profit'])/$max)*100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <x-filament::button wire:click="$refresh">Refresh</x-filament::button>
        </div>
    </div>
</x-filament::page>
