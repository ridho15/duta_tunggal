<x-filament-panels::page>
@php
    $accentColor = '#ea580c';
    $accentLight = '#fdba74';
    $iconBg      = '#fff7ed';
    $heroFrom    = '#fff7ed';
    $heroTo      = '#fed7aa';
    $sections = [
        [
            'title' => 'Transaksi Pembelian',
            'items' => [
                ['label' => 'Permintaan Pembelian',      'url' => \App\Filament\Resources\OrderRequestResource::getUrl(),          'icon' => 'document-text',    'desc' => 'Ajukan permintaan bahan/barang ke supplier'],
                ['label' => 'Pesanan Pembelian',          'url' => \App\Filament\Resources\PurchaseOrderResource::getUrl(),         'icon' => 'shopping-cart',    'desc' => 'Buat & kelola purchase order ke vendor'],
                ['label' => 'Kontrol Kualitas Pembelian', 'url' => \App\Filament\Resources\QualityControlPurchaseResource::getUrl(),'icon' => 'beaker',           'desc' => 'Inspeksi kualitas barang yang diterima'],
                ['label' => 'Penerimaan Pembelian',       'url' => \App\Filament\Resources\PurchaseReceiptResource::getUrl(),       'icon' => 'inbox-arrow-down', 'desc' => 'Konfirmasi penerimaan barang dari supplier'],
                ['label' => 'Retur Pembelian',            'url' => \App\Filament\Resources\PurchaseReturnResource::getUrl(),        'icon' => 'arrow-uturn-left', 'desc' => 'Kembalikan barang tidak sesuai ke supplier'],
            ],
        ],
        [
            'title' => 'Keuangan Pembelian',
            'items' => [
                ['label' => 'Utang Usaha',                'url' => \App\Filament\Resources\AccountPayableResource::getUrl(),        'icon' => 'credit-card',      'desc' => 'Kelola kewajiban pembayaran ke supplier'],
                ['label' => 'Invoice Pembelian',          'url' => \App\Filament\Resources\PurchaseInvoiceResource::getUrl(),       'icon' => 'document-text',    'desc' => 'Tagihan pembelian dari vendor/supplier'],
            ],
        ],
        [
            'title' => 'Pembayaran & Kas Bank',
            'items' => [
                ['label' => 'Permintaan Pembayaran',      'url' => \App\Filament\Resources\PaymentRequestResource::getUrl(),        'icon' => 'document-text',    'desc' => 'Ajukan permintaan dana ke keuangan'],
                ['label' => 'Penerimaan Pelanggan',       'url' => \App\Filament\Resources\CustomerReceiptResource::getUrl(),       'icon' => 'inbox-arrow-down', 'desc' => 'Catat pembayaran masuk dari pelanggan'],
                ['label' => 'Pembayaran Vendor',          'url' => \App\Filament\Resources\VendorPaymentResource::getUrl(),         'icon' => 'arrow-up-circle',  'desc' => 'Proses pelunasan ke vendor/supplier'],
                ['label' => 'Transaksi Kas & Bank',       'url' => \App\Filament\Resources\CashBankTransactionResource::getUrl(),   'icon' => 'banknotes',        'desc' => 'Catat penerimaan & pengeluaran kas/bank'],
                ['label' => 'Deposit',                    'url' => \App\Filament\Resources\DepositResource::getUrl(),               'icon' => 'circle-stack',     'desc' => 'Kelola deposit & uang muka'],
                ['label' => 'Transfer Kas & Bank',        'url' => \App\Filament\Resources\CashBankTransferResource::getUrl(),      'icon' => 'arrows-right-left','desc' => 'Transfer antar akun kas & bank'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="purchase-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-shopping-bag class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Pembelian</div>
            <h1 class="hubv2-hero-title">Pembelian</h1>
            <p class="hubv2-hero-subtitle">Kelola transaksi pembelian dan pintu masuk ke keuangan serta pembayaran dari satu halaman.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ $totalItems }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    {{-- Sections --}}
    @foreach($sections as $section)
    <div class="hubv2-sh">
        <span class="hubv2-sh-dot"></span>
        <span class="hubv2-sh-name">{{ $section['title'] }}</span>
        <span class="hubv2-sh-rule"></span>
    </div>
    <div class="hubv2-grid">
        @foreach($section['items'] as $item)
        <a href="{{ $item['url'] }}" class="hubv2-card" data-hub-card>
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
    @endforeach

</div>
</x-filament-panels::page>