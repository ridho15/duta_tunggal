@extends('layouts.app')

@section('title', ($req ? 'Ubah ' . $req->number : 'Buat Permintaan Bayar') . ' · Duta Tunggal ERP')

@php
    $field = 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none';
    $initialLines = old('lines', $req ? $req->lines->map(fn ($l) => ['coa_id' => (string) $l->coa_id, 'description' => $l->description, 'amount' => (float) $l->amount])->all() : [['coa_id' => '', 'description' => '', 'amount' => '']]);
    $accountOptions = $accounts->map(fn ($a) => ['id' => (string) $a->id, 'label' => $a->code . ' · ' . $a->name . ($a->type === 'Expense' ? '' : ' (aset)'), 'type' => $a->type])->values();
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('keuangan.payment-requests.index') }}" class="hover:text-gray-900 hover:underline underline-offset-2">Permintaan Bayar</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#15803d] font-bold">{{ $req ? $req->number : 'Buat baru' }}</span>
    </nav>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $req ? 'Ubah permintaan ' . $req->number : 'Buat permintaan bayar' }}</h1>
        <p class="mt-1 text-sm text-gray-700">Pilih akun biaya untuk tiap baris (mis. Biaya ATK, Biaya Asuransi, Biaya Perbaikan Kendaraan). Total dihitung otomatis.</p>
        @if ($req && $req->status === 'rejected' && $req->approval_notes)
            <p class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert"><b>Ditolak:</b> {{ $req->approval_notes }}. Perbaiki lalu ajukan ulang.</p>
        @endif
    </div>

    <form method="POST" action="{{ $req ? route('keuangan.payment-requests.update', $req) : route('keuangan.payment-requests.store') }}" enctype="multipart/form-data"
          x-data="expenseForm(@js($initialLines), @js($accountOptions))" class="space-y-5">
        @csrf
        @if ($req) @method('PUT') @endif

        <section class="bg-white rounded-2xl border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 gap-4" aria-label="Data permintaan">
            <label class="text-xs font-semibold text-gray-700">Tanggal permintaan
                <input type="date" name="request_date" required value="{{ old('request_date', $req?->request_date?->toDateString() ?? now()->toDateString()) }}" class="mt-1 {{ $field }}">
            </label>
            <label class="text-xs font-semibold text-gray-700">Cabang
                <select name="cabang_id" class="cursor-pointer mt-1 {{ $field }}">
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected((string) old('cabang_id', $req?->cabang_id ?? auth()->user()->cabang_id) === (string) $b->id)>{{ $b->nama }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-gray-700 sm:col-span-2">Dibayar kepada
                <input type="text" name="payee" required maxlength="255" value="{{ old('payee', $req?->payee) }}" placeholder="Contoh: Toko ATK Sinar, Bengkel Jaya Motor, PT Asuransi ABC" class="mt-1 {{ $field }}">
                @error('payee')<span class="block mt-1 text-xs font-semibold text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="text-xs font-semibold text-gray-700 sm:col-span-2">Keperluan
                <textarea name="description" required maxlength="1000" rows="2" placeholder="Contoh: Ganti ban & servis truk B 9123 XY" class="mt-1 {{ $field }}">{{ old('description', $req?->description) }}</textarea>
                @error('description')<span class="block mt-1 text-xs font-semibold text-red-700">{{ $message }}</span>@enderror
            </label>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden" aria-labelledby="rincian">
            <header class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
                <h2 id="rincian" class="text-sm font-bold text-gray-900">Rincian biaya</h2>
                <button type="button" @click="add()" class="cursor-pointer min-h-9 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Tambah baris</button>
            </header>
            <div class="divide-y divide-gray-100">
                <template x-for="(line, i) in lines" :key="line.key">
                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <label class="md:col-span-5 text-xs font-semibold text-gray-700">Akun biaya
                            <select :name="`lines[${i}][coa_id]`" x-model="line.coa_id" required data-searchable class="cursor-pointer mt-1 {{ $field }}">
                                <option value="">Pilih akun</option>
                                <template x-for="a in accounts" :key="a.id"><option :value="a.id" x-text="a.label" :selected="a.id === line.coa_id"></option></template>
                            </select>
                        </label>
                        <label class="md:col-span-4 text-xs font-semibold text-gray-700">Keterangan baris
                            <input type="text" :name="`lines[${i}][description]`" x-model="line.description" maxlength="255" placeholder="Opsional" class="mt-1 {{ $field }}">
                        </label>
                        <label class="md:col-span-2 text-xs font-semibold text-gray-700">Nominal (Rp)
                            <input type="number" min="0" step="1" :name="`lines[${i}][amount]`" x-model.number="line.amount" required class="mt-1 {{ $field }} text-right tabular-nums">
                        </label>
                        <div class="md:col-span-1 flex md:justify-end">
                            <button type="button" @click="remove(i)" x-show="lines.length > 1" :aria-label="`Hapus baris ${i + 1}`"
                                    class="cursor-pointer min-h-10 px-3 py-2 rounded-lg text-xs font-bold text-red-700 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Hapus</button>
                        </div>
                    </div>
                </template>
            </div>
            <footer class="flex items-center justify-between px-4 py-3 bg-gray-50 border-t border-gray-100">
                <span class="text-sm font-semibold text-gray-700">Total permintaan</span>
                <span class="text-xl font-bold tabular-nums text-[#14532d]" x-text="'Rp ' + total().toLocaleString('id-ID')"></span>
            </footer>
            @error('lines')<p class="px-4 pb-3 text-xs font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-4" aria-label="Lampiran">
            <label class="text-xs font-semibold text-gray-700">Lampiran (nota / penawaran, PDF / JPG / PNG, maks. 5 MB)
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="cursor-pointer mt-1 block w-full text-sm text-gray-800 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200">
            </label>
            @if ($req?->attachment_path)<p class="mt-1 text-xs text-gray-600">Lampiran sudah ada; pilih file baru hanya bila ingin mengganti.</p>@endif
            @error('attachment')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
        </section>

        <div class="sticky bottom-0 z-10 -mx-4 px-4 py-3 bg-white/95 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <a href="{{ $req ? route('keuangan.payment-requests.show', $req) : route('keuangan.payment-requests.index') }}" class="min-h-11 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50">Batal</a>
            <button type="submit" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Simpan draf</button>
            <button type="submit" name="submit_now" value="1" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Simpan &amp; ajukan</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function expenseForm(initial, accounts) {
        let seq = 0;
        return {
            accounts,
            lines: initial.map(l => ({ ...l, coa_id: String(l.coa_id ?? ''), key: ++seq })),
            add() { this.lines.push({ coa_id: '', description: '', amount: '', key: ++seq }); },
            remove(i) { this.lines.splice(i, 1); },
            total() { return this.lines.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0); },
        };
    }
</script>
@endpush
