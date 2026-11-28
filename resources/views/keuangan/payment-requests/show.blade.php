@extends('layouts.app')

@section('title', $req->number . ' · Permintaan Bayar · Duta Tunggal ERP')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $steps = [
        ['requested', 'Diajukan', $req->requester?->name],
        ['approved', 'Disetujui', $req->approver?->name],
        ['paid', 'Dibayar', $req->payer?->name],
    ];
    $sig = fn ($role) => $req->signatures->firstWhere('role', $role);
    $field = 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none';
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('keuangan.payment-requests.index') }}" class="hover:text-gray-900 hover:underline underline-offset-2">Permintaan Bayar</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#15803d] font-bold">{{ $req->number }}</span>
    </nav>

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $req->number }}</h1>
            <p class="mt-1 text-sm text-gray-700">{{ $req->description }}</p>
            <p class="mt-1 text-sm text-gray-700">Dibayar kepada <b>{{ $req->payee }}</b> · {{ $req->cabang?->nama }} · {{ $req->request_date?->translatedFormat('d M Y') }}</p>
        </div>
        <div class="text-left lg:text-right">
            <p class="text-xs font-semibold text-gray-600">Total</p>
            <p class="text-3xl font-bold tabular-nums text-[#14532d]">{{ $rp($req->amount) }}</p>
            <p class="text-sm font-semibold text-gray-800">{{ $req->statusLabel() }}</p>
        </div>
    </div>

    @if ($req->status === 'rejected' && $req->approval_notes)
        <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert"><b>Ditolak oleh {{ $req->approver?->name }}:</b> {{ $req->approval_notes }}</p>
    @elseif ($req->status === 'cancelled')
        <p class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800" role="status"><b>Dibatalkan:</b> {{ $req->cancel_reason }}</p>
    @elseif ($req->status === 'submitted')
        <p class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="status">Menunggu persetujuan: {{ $approverLabel }}.</p>
    @endif

    {{-- Alur & tanda tangan --}}
    <ol class="grid grid-cols-1 sm:grid-cols-3 gap-3" aria-label="Alur persetujuan">
        @foreach ($steps as [$role, $label, $name])
            @php $s = $sig($role); @endphp
            <li class="rounded-2xl border p-4 {{ $s ? 'border-[#86efac] bg-[#f0fdf4]' : 'border-gray-200 bg-white' }}">
                <p class="text-xs font-semibold {{ $s ? 'text-[#14532d]' : 'text-gray-600' }}">{{ $label }}</p>
                @if ($s)
                    @if ($s->signature_path)
                        <img src="{{ route('signature.image', $s) }}" alt="Tanda tangan {{ $s->signer_name }}" width="160" height="56" class="mt-2 h-14 w-auto max-w-full object-contain">
                    @else
                        <p class="mt-2 text-xs text-gray-600">(tanpa gambar tanda tangan)</p>
                    @endif
                    <p class="mt-1 text-sm font-bold text-gray-900">{{ $s->signer_name }}</p>
                    <p class="text-xs text-gray-700">{{ $s->signer_title }} · {{ $s->signed_at->translatedFormat('d M Y H:i') }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-500">Belum</p>
                @endif
            </li>
        @endforeach
    </ol>

    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden" aria-labelledby="rincian">
        <h2 id="rincian" class="px-4 py-3 text-sm font-bold text-gray-900 border-b border-gray-100">Rincian biaya</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                <tr><th scope="col" class="px-4 py-2.5">Akun</th><th scope="col" class="px-4 py-2.5">Keterangan</th><th scope="col" class="px-4 py-2.5 text-right">Nominal</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($req->lines as $l)
                    <tr>
                        <td class="px-4 py-2.5 text-gray-900"><span class="font-mono text-xs text-gray-600">{{ $l->coa?->code }}</span> {{ $l->coa?->name }}</td>
                        <td class="px-4 py-2.5 text-gray-800">{{ $l->description ?: '-' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ $rp($l->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($req->status === 'paid')
            <p class="px-4 py-3 text-xs text-gray-700 border-t border-gray-100">
                Dibayar dari <b>{{ $req->paymentCoa?->code }} {{ $req->paymentCoa?->name }}</b>{{ $req->paid_at ? ' pada ' . $req->paid_at->translatedFormat('d M Y') : '' }}.
                Jurnal: Debit akun biaya di atas, Kredit {{ $req->paymentCoa?->name }}.
                @if ($req->cashBankTransaction)
                    @accessRoute('keuangan.cashbank.show')
                        <a href="{{ route('keuangan.cashbank.show', $req->cashBankTransaction) }}" class="font-semibold text-[#15803d] underline underline-offset-2">Lihat transaksi {{ $req->cashBankTransaction->number }}</a>
                    @endaccessRoute
                @endif
            </p>
        @endif
    </section>

    {{-- Aksi sesuai status & hak --}}
    <div class="flex flex-wrap gap-2">
        @if ($req->status === 'paid')
            @accessRoute('keuangan.payment-requests.pdf')
                <a href="{{ route('keuangan.payment-requests.pdf', $req) }}" target="_blank" class="min-h-11 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Cetak Voucher</a>
            @endaccessRoute
        @endif
        @if ($mine && $req->isEditable())
            @accessRoute('keuangan.payment-requests.edit')
                <a href="{{ route('keuangan.payment-requests.edit', $req) }}" class="min-h-11 inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50">Ubah</a>
            @endaccessRoute
        @endif
        @if ($mine && $req->status === 'draft')
            @accessRoute('keuangan.payment-requests.submit')
                <form method="POST" action="{{ route('keuangan.payment-requests.submit', $req) }}">@csrf
                    <button class="cursor-pointer min-h-11 px-4 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Ajukan</button>
                </form>
            @endaccessRoute
        @endif
        @if (in_array($req->status, ['draft', 'submitted', 'approved', 'rejected'], true))
            @accessRoute('keuangan.payment-requests.cancel')
                <button type="button" @click="$dispatch('open-modal-cancel-pr')" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-red-300 bg-white text-sm font-bold text-red-700 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Batalkan</button>
            @endaccessRoute
        @endif
    </div>

    @if ($approval['allowed'])
        <section class="bg-white rounded-2xl border border-amber-300 p-4 space-y-3" aria-labelledby="setujui">
            <h2 id="setujui" class="text-sm font-bold text-gray-900">Persetujuan Anda</h2>
            <form method="POST" class="space-y-3">
                @csrf
                <label class="block text-xs font-semibold text-gray-700">Catatan{{ $approval['self'] ? ' (wajib, min. 10 karakter: Anda menyetujui permintaan sendiri)' : ' (wajib diisi bila menolak)' }}
                    <textarea name="approval_notes" rows="2" maxlength="1000" class="mt-1 {{ $field }}">{{ old('approval_notes') }}</textarea>
                </label>
                @error('approval_notes')<p class="text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2">
                    <button formaction="{{ route('keuangan.payment-requests.approve', $req) }}" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Setujui &amp; tanda tangani</button>
                    <button formaction="{{ route('keuangan.payment-requests.reject', $req) }}" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-red-300 bg-white text-sm font-bold text-red-700 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Tolak</button>
                </div>
            </form>
        </section>
    @endif

    @if ($canPay)
        <section class="bg-white rounded-2xl border border-[#86efac] p-4" aria-labelledby="bayar">
            <h2 id="bayar" class="text-sm font-bold text-gray-900">Bayar</h2>
            <p class="mt-1 text-xs text-gray-700">Mencatat Kas / Bank keluar {{ $rp($req->amount) }} dan jurnal Debit akun biaya, Kredit akun Kas / Bank. Tanda tangan Anda masuk ke voucher sebagai pembayar.</p>
            <form method="POST" action="{{ route('keuangan.payment-requests.pay', $req) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <label class="text-xs font-semibold text-gray-700 sm:col-span-1">Dibayar dari
                    <select name="payment_coa_id" required class="cursor-pointer mt-1 {{ $field }}">
                        <option value="">Pilih Kas / Bank</option>
                        @foreach ($cashAccounts as $c)
                            <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-gray-700">Tanggal bayar
                    <input type="date" name="paid_date" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 {{ $field }}">
                </label>
                <button class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Bayar &amp; tanda tangani</button>
                @error('payment_coa_id')<p class="sm:col-span-3 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
            </form>
        </section>
    @endif

    <x-confirmation-modal name="cancel-pr" title="Batalkan permintaan {{ $req->number }}?" variant="danger" confirm-text="Ya, batalkan" :action="route('keuangan.payment-requests.cancel', $req)">
        <label class="block text-xs font-semibold text-gray-700">Alasan
            <input type="text" name="cancel_reason" required maxlength="500" class="mt-1 {{ $field }}">
        </label>
    </x-confirmation-modal>
</div>
@endsection
