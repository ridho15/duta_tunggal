{{-- Riwayat perubahan akses ($history dari AccessManager::history) --}}
<section class="bg-white rounded-2xl border border-gray-200 p-4" aria-labelledby="riwayat-akses">
    <h2 id="riwayat-akses" class="text-sm font-bold text-gray-900">Riwayat perubahan akses</h2>
    @if ($history->isEmpty())
        <p class="mt-2 text-sm text-gray-700">Belum ada perubahan yang tercatat.</p>
    @else
        <ol class="mt-3 space-y-3">
            @foreach ($history as $log)
                @php
                    $actionLabel = [
                        'update' => 'Mengubah centang', 'create' => 'Membuat peran', 'create_from_template' => 'Membuat peran dari template',
                        'clone' => 'Menduplikat peran', 'delete' => 'Menghapus peran', 'copy_from_user' => 'Menyalin dari user lain',
                        'reset' => 'Mengembalikan ke akses peran',
                    ][$log->action] ?? $log->action;
                    $fmt = function (string $k) {
                        $sign = in_array($k[0], ['+', '−'], true) ? mb_substr($k, 0, 1) : '';
                        $key = $sign !== '' ? mb_substr($k, 1) : $k;
                        return ($sign === '+' ? 'Tambah ' : ($sign === '−' ? 'Cabut ' : '')) . \App\Support\Access::label($key);
                    };
                @endphp
                <li class="text-xs text-gray-800 border-l-2 border-gray-200 pl-3">
                    <p>
                        <span class="font-bold">{{ $log->actor_name ?? 'Sistem' }}</span>
                        {{ \Illuminate\Support\Str::lower($actionLabel) }}
                        <span class="text-gray-600">· {{ \Carbon\Carbon::parse($log->created_at)->translatedFormat('d M Y H:i') }}</span>
                    </p>
                    @if ($log->note)
                        <p class="text-gray-700">{{ $log->note }}</p>
                    @endif
                    @if ($log->added)
                        <p class="mt-1 text-[#14532d]"><span class="font-semibold">{{ $log->target_type === 'user' ? 'Akses khusus baru:' : 'Diizinkan:' }}</span> {{ collect($log->added)->take(8)->map($fmt)->implode(', ') }}@if (count($log->added) > 8), dan {{ count($log->added) - 8 }} lainnya @endif</p>
                    @endif
                    @if ($log->removed)
                        <p class="mt-1 text-red-800"><span class="font-semibold">{{ $log->target_type === 'user' ? 'Kembali ikut peran:' : 'Dilepas:' }}</span> {{ collect($log->removed)->take(8)->map($fmt)->implode(', ') }}@if (count($log->removed) > 8), dan {{ count($log->removed) - 8 }} lainnya @endif</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
