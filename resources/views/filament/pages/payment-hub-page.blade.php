<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Permintaan Pembayaran', 'url' => \App\Filament\Resources\PaymentRequestResource::getUrl()],
            ['label' => 'Penerimaan Pelanggan', 'url' => \App\Filament\Resources\CustomerReceiptResource::getUrl()],
            ['label' => 'Pembayaran Vendor', 'url' => \App\Filament\Resources\VendorPaymentResource::getUrl()],
            ['label' => 'Transaksi Kas & Bank', 'url' => \App\Filament\Resources\CashBankTransactionResource::getUrl()],
            ['label' => 'Deposit', 'url' => \App\Filament\Resources\DepositResource::getUrl()],
            ['label' => 'Transfer Kas & Bank', 'url' => \App\Filament\Resources\CashBankTransferResource::getUrl()],
        ];
    @endphp

    <style>
        .payment-hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .payment-hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .payment-hub-note { color:#4b5563; font-size:.875rem; }
        .payment-hub-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .payment-hub-link { display:block; border:1px solid #c7d2fe; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#eef2ff,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .payment-hub-link:hover { border-color:#818cf8; background:linear-gradient(135deg,#e0e7ff,#eef2ff); }
    </style>

    <div class="space-y-4" id="payment-hub">
        <section class="payment-hub-card">
            <div class="payment-hub-title">Pusat Pembayaran</div>
            <div class="payment-hub-note">Seluruh transaksi pembayaran dan kas bank dipusatkan di sini agar group pembayaran lebih pendek.</div>
            <div class="payment-hub-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="payment-hub-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>