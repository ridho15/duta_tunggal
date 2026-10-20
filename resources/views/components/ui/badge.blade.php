@props([
    'variant' => 'neutral',
    'size' => 'sm',
])

@php
    $variantClasses = match ($variant) {
        'success', 'approved', 'selesai', 'aman' => 'bg-[#f0fdf4] text-[#15803d] border-[#86efac]',
        'warning', 'perlu_tindakan', 'menipis', 'pending' => 'bg-[#fffbeb] text-[#92400e] border-[#fcd34d]',
        'danger', 'habis', 'ditolak', 'batal' => 'bg-[#fef2f2] text-[#b91c1c] border-[#fecaca]',
        'info', 'proses', 'sebagian' => 'bg-[#eff6ff] text-[#1d4ed8] border-[#bfdbfe]',
        default => 'bg-[#f3f4f6] text-[#374151] border-[#e5e7eb]',
    };

    $sizeClasses = match ($size) {
        'xs' => 'text-[10px] px-1.5 py-0.5',
        'md' => 'text-xs px-2.5 py-1',
        default => 'text-[11.5px] px-2 py-0.5',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 font-bold border rounded-md tracking-tight {$variantClasses} {$sizeClasses}"]) }}>
    {{ $slot }}
</span>
