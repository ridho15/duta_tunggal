@extends('layouts.app')

@section('title', 'Aturan Persetujuan – Duta Tunggal ERP')

@section('content')
@php
    $types = \App\Models\ApprovalRule::TYPES;
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Pengaturan</div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Aturan Persetujuan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Batas nilai dan peran yang boleh menyetujui Quotation, Sales Order, dan Nota Kredit. Bila beberapa aturan cocok, yang batas bawahnya paling tinggi dipakai.</p>
        </div>
        <a href="{{ route('settings.approval-rules.create') }}"
           class="px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition whitespace-nowrap">Tambah Aturan</a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        @if ($rules->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-semibold text-gray-700">Belum ada aturan persetujuan.</p>
                <p class="text-xs text-gray-500 mt-1">Tanpa aturan, persetujuan tidak dibatasi oleh nilai dokumen.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Dokumen</th>
                            <th scope="col" class="px-4 py-3 font-bold">Aturan</th>
                            <th scope="col" class="px-4 py-3 font-bold text-right">Di atas</th>
                            <th scope="col" class="px-4 py-3 font-bold text-right">Sampai</th>
                            <th scope="col" class="px-4 py-3 font-bold">Peran penyetuju</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rules as $rule)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ $types[$rule->document_type] ?? $rule->document_type }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $rule->label }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-gray-700">{{ $rule->above_amount !== null ? \App\Helpers\MoneyHelper::rupiah($rule->above_amount) : 'Dari Rp 0' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-gray-700">{{ $rule->up_to_amount !== null ? \App\Helpers\MoneyHelper::rupiah($rule->up_to_amount) : 'Tanpa batas' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ implode(', ', $rule->roles ?? []) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $rule->is_active ? 'text-[#15803d]' : 'text-gray-500' }}">
                                        <span class="w-2 h-2 rounded-full {{ $rule->is_active ? 'bg-[#15803d]' : 'bg-gray-300' }}" aria-hidden="true"></span>
                                        {{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('settings.approval-rules.edit', $rule) }}" class="text-xs font-semibold text-[#15803d] hover:text-[#166534] underline-offset-2 hover:underline">Ubah<span class="sr-only"> {{ $rule->label }}</span></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
