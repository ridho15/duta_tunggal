{{-- Ringkasan angka di atas tabel. $kpis = [[label, nilai, keterangan|null, 'accent'|'warn'|null], ...]; angka pertama = fokus utama --}}
<dl class="grid grid-cols-2 {{ count($kpis) === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }} gap-px overflow-hidden rounded-2xl border border-gray-200 bg-gray-200">
    @foreach ($kpis as $i => [$label, $value, $hint, $tone])
        {{-- HP: angka utama selebar penuh; sisa ganjil di baris terakhir ikut melebar agar tidak ada sel kosong --}}
        <div class="bg-white px-4 py-3.5 {{ $i === 0 || ($loop->last && (count($kpis) - 1) % 2 === 1) ? 'col-span-2 lg:col-span-1' : '' }}">
            <dt class="text-xs font-semibold text-gray-600">{{ $label }}</dt>
            <dd class="mt-1 font-bold tabular-nums tracking-tight {{ $i === 0 ? 'text-2xl text-[#14532d]' : 'text-xl text-gray-900' }} {{ $tone === 'warn' ? '!text-amber-700' : '' }}">{{ $value }}</dd>
            @if ($hint)
                <p class="mt-0.5 text-xs text-gray-600">{{ $hint }}</p>
            @endif
        </div>
    @endforeach
</dl>
