<x-filament-panels::page>
@php
    $accentColor = '#4f46e5';
    $accentLight = '#a5b4fc';
    $iconBg      = '#eef2ff';
    $heroFrom    = '#eef2ff';
    $heroTo      = '#c7d2fe';
    $items = [
        ['label' => 'Permintaan Pembayaran', 'url' => \App\Filament\Resources\PaymentRequestResource::getUrl(),     'icon' => 'document-text',    'desc' => 'Ajukan permintaan dana ke keuangan'],
        ['label' => 'Penerimaan Pelanggan',  'url' => \App\Filament\Resources\CustomerReceiptResource::getUrl(),    'icon' => 'inbox-arrow-down', 'desc' => 'Catat pembayaran masuk dari pelanggan'],
        ['label' => 'Pembayaran Vendor',     'url' => \App\Filament\Resources\VendorPaymentResource::getUrl(),      'icon' => 'arrow-up-circle',  'desc' => 'Proses pelunasan ke vendor/supplier'],
        ['label' => 'Transaksi Kas & Bank',  'url' => \App\Filament\Resources\CashBankTransactionResource::getUrl(),'icon' => 'banknotes',        'desc' => 'Catat penerimaan & pengeluaran kas/bank'],
        ['label' => 'Deposit',               'url' => \App\Filament\Resources\DepositResource::getUrl(),            'icon' => 'circle-stack',     'desc' => 'Kelola deposit & uang muka'],
        ['label' => 'Transfer Kas & Bank',   'url' => \App\Filament\Resources\CashBankTransferResource::getUrl(),  'icon' => 'arrows-right-left','desc' => 'Transfer antar akun kas & bank'],
    ];
@endphp

@include('filament.pages.partials.hub-styles')

<div id="payment-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-credit-card class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Pembayaran</div>
            <h1 class="hubv2-hero-title">Pusat Pembayaran</h1>
            <p class="hubv2-hero-subtitle">Kelola pembayaran, penerimaan pelanggan, dan seluruh transaksi kas &amp; bank.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ count($items) }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    {{-- Cards --}}
    <div class="hubv2-grid">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="hubv2-card" data-hub-card target="_blank" rel="noopener noreferrer">
            <div class="hubv2-ci" style="background:{{ $iconBg }};color:{{ $accentColor }};">
                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="w-5 h-5" />
            </div>
            <div class="hubv2-cb">
                <span class="hubv2-cl">{{ $item['label'] }}</span>
                <span class="hubv2-cd">{{ $item['desc'] }}</span>
            </div>
            <div class="hubv2-ca">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </div>
        </a>
        @endforeach
    </div>

</div>
</x-filament-panels::page>