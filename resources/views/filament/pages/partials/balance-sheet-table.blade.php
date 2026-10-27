@php
    $displayLevel = $data['display_level'] ?? 'all';
    $showZeroBalance = $data['show_zero_balance'] ?? false;
@endphp

<!-- ASSETS Section -->
@if($displayLevel !== 'totals_only')
<div class="section-header assets">
    ASET (ASSETS)
</div>
@endif

@if($displayLevel === 'all' || $displayLevel === 'parent_only')
<!-- Current Assets -->
@if($displayLevel !== 'totals_only')
<div class="subsection-header">
    Aset Lancar (Current Assets)
</div>
@endif
@if($displayLevel !== 'totals_only')
@foreach($data['current_assets']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            Rp {{ number_format($account->balance, 0, ',', '.') }}
        </div>
    </div>
@endforeach
@endif
<div class="subtotal-row">
    <div>Total Aset Lancar</div>
    <div>Rp {{ number_format($data['current_assets']['total'], 0, ',', '.') }}</div>
</div>

<!-- Fixed Assets -->
@if($displayLevel !== 'totals_only')
<div class="subsection-header">
    Aset Tetap (Fixed Assets)
</div>
@endif
@if($displayLevel !== 'totals_only')
@foreach($data['fixed_assets']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            Rp {{ number_format($account->balance, 0, ',', '.') }}
        </div>
    </div>
@endforeach
@endif
<div class="subtotal-row">
    <div>Total Aset Tetap</div>
    <div>Rp {{ number_format($data['fixed_assets']['total'], 0, ',', '.') }}</div>
</div>

<!-- Contra Assets (Accumulated Depreciation) -->
@if($data['contra_assets']['accounts']->count() > 0 && $displayLevel !== 'totals_only')
<div class="subsection-header">
    Aset Kontra (Contra Assets)
</div>
@foreach($data['contra_assets']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            (Rp {{ number_format(abs($account->balance), 0, ',', '.') }})
        </div>
    </div>
@endforeach
<div class="subtotal-row">
    <div>Total Aset Kontra</div>
    <div>(Rp {{ number_format(abs($data['contra_assets']['total']), 0, ',', '.') }})</div>
</div>
@endif
@endif

<!-- Total Assets -->
<div class="total-row assets">
    <div>TOTAL ASET</div>
    <div>Rp {{ number_format($data['total_assets'], 0, ',', '.') }}</div>
</div>

<!-- LIABILITIES & EQUITY Section -->
@if($displayLevel !== 'totals_only')
<div class="section-header liabilities">
    KEWAJIBAN (LIABILITIES)
</div>
@endif

@if($displayLevel === 'all' || $displayLevel === 'parent_only')
<!-- Current Liabilities -->
@if($displayLevel !== 'totals_only')
<div class="subsection-header">
    Kewajiban Lancar (Current Liabilities)
</div>
@endif
@if($displayLevel !== 'totals_only')
@foreach($data['current_liabilities']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            Rp {{ number_format($account->balance, 0, ',', '.') }}
        </div>
    </div>
@endforeach
@endif
<div class="subtotal-row">
    <div>Total Kewajiban Lancar</div>
    <div>Rp {{ number_format($data['current_liabilities']['total'], 0, ',', '.') }}</div>
</div>

<!-- Long-term Liabilities -->
@if($displayLevel !== 'totals_only')
<div class="subsection-header">
    Kewajiban Jangka Panjang (Long-term Liabilities)
</div>
@endif
@if($displayLevel !== 'totals_only')
@foreach($data['long_term_liabilities']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            Rp {{ number_format($account->balance, 0, ',', '.') }}
        </div>
    </div>
@endforeach
@endif
<div class="subtotal-row">
    <div>Total Kewajiban Jangka Panjang</div>
    <div>Rp {{ number_format($data['long_term_liabilities']['total'], 0, ',', '.') }}</div>
</div>
@endif

<!-- Total Liabilities -->
<div class="subtotal-row">
    <div>TOTAL KEWAJIBAN</div>
    <div>Rp {{ number_format($data['total_liabilities'], 0, ',', '.') }}</div>
</div>

@if($displayLevel === 'all' || $displayLevel === 'parent_only')
<!-- Equity -->
@if($displayLevel !== 'totals_only')
<div class="section-header equity">
    EKUITAS (EQUITY)
</div>
<div class="subsection-header">
    Ekuitas (Equity)
</div>
@endif
@if($displayLevel !== 'totals_only')
@foreach($data['equity']['accounts'] as $account)
    <div class="account-row">
        <div class="account-name">
            <span class="account-code">{{ $account->kode }}</span>
            {{ $account->nama }}
        </div>
        <div class="account-balance"
             wire:click="showAccountDetails({{ $account->id }})"
             title="Klik untuk detail transaksi">
            Rp {{ number_format($account->balance, 0, ',', '.') }}
        </div>
    </div>
@endforeach
@endif
<div class="subtotal-row">
    <div>Total Ekuitas</div>
    <div>Rp {{ number_format($data['total_equity'], 0, ',', '.') }}</div>
</div>

<!-- Retained Earnings -->
@if($displayLevel !== 'totals_only')
<div class="account-row">
    <div class="account-name">
        <span class="account-code">RE</span>
        Laba Ditahan (Retained Earnings)
    </div>
    <div class="account-balance">
        Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}
    </div>
</div>
@endif
@endif

<!-- Total Liabilities & Equity -->
<div class="total-row equity">
    <div>TOTAL KEWAJIBAN & EKUITAS</div>
    <div>Rp {{ number_format($data['total_liabilities_and_equity'], 0, ',', '.') }}</div>
</div>

<!-- Balance Check -->
@if($displayLevel !== 'totals_only')
<div class="balance-check {{ $data['is_balanced'] ? 'balanced' : 'unbalanced' }}">
    <div class="balance-icon">
        @if($data['is_balanced'])
            <svg class="w-5 h-5 text-emerald-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        @else
            <svg class="w-5 h-5 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        @endif
    </div>
    <div class="balance-text">
        <div class="balance-status">
            {{ $data['is_balanced'] ? 'Neraca Seimbang' : 'Neraca Tidak Seimbang' }}
        </div>
        <div class="balance-equation">
            Aset = Kewajiban + Ekuitas
        </div>
        @if(!$data['is_balanced'])
            <div class="balance-difference">
                Selisih: Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}
            </div>
        @endif
    </div>
</div>
@endif

<!-- Comparison Data -->
@if(isset($show_comparison) && $show_comparison && isset($comparison))
<div class="comparison-section">
    <div class="comparison-header">
        Perbandingan dengan {{ \Carbon\Carbon::parse($comparison['as_of_date'])->format('M Y') }}
    </div>
    <div class="comparison-grid">
        <div class="comparison-item">
            <div class="comparison-label">Total Aset</div>
            <div class="comparison-values">
                <div class="current-value">Rp {{ number_format($data['total_assets'], 0, ',', '.') }}</div>
                <div class="comparison-value">Rp {{ number_format($comparison['total_assets']['previous'], 0, ',', '.') }}</div>
                <div class="difference {{ $comparison['total_assets']['change'] >= 0 ? 'positive' : 'negative' }}">
                    {{ $comparison['total_assets']['change'] >= 0 ? '+' : '' }}Rp {{ number_format($comparison['total_assets']['change'], 0, ',', '.') }}
                    @if($comparison['total_assets']['percentage'] != 0)
                        ({{ $comparison['total_assets']['percentage'] >= 0 ? '+' : '' }}{{ number_format($comparison['total_assets']['percentage'], 2) }}%)
                    @endif
                </div>
            </div>
        </div>
        <div class="comparison-item">
            <div class="comparison-label">Total Kewajiban</div>
            <div class="comparison-values">
                <div class="current-value">Rp {{ number_format($data['total_liabilities'], 0, ',', '.') }}</div>
                <div class="comparison-value">Rp {{ number_format($comparison['total_liabilities']['previous'], 0, ',', '.') }}</div>
                <div class="difference {{ $comparison['total_liabilities']['change'] >= 0 ? 'positive' : 'negative' }}">
                    {{ $comparison['total_liabilities']['change'] >= 0 ? '+' : '' }}Rp {{ number_format($comparison['total_liabilities']['change'], 0, ',', '.') }}
                    @if($comparison['total_liabilities']['percentage'] != 0)
                        ({{ $comparison['total_liabilities']['percentage'] >= 0 ? '+' : '' }}{{ number_format($comparison['total_liabilities']['percentage'], 2) }}%)
                    @endif
                </div>
            </div>
        </div>
        <div class="comparison-item">
            <div class="comparison-label">Total Ekuitas</div>
            <div class="comparison-values">
                <div class="current-value">Rp {{ number_format($data['total_equity'], 0, ',', '.') }}</div>
                <div class="comparison-value">Rp {{ number_format($comparison['total_equity']['previous'], 0, ',', '.') }}</div>
                <div class="difference {{ $comparison['total_equity']['change'] >= 0 ? 'positive' : 'negative' }}">
                    {{ $comparison['total_equity']['change'] >= 0 ? '+' : '' }}Rp {{ number_format($comparison['total_equity']['change'], 0, ',', '.') }}
                    @if($comparison['total_equity']['percentage'] != 0)
                        ({{ $comparison['total_equity']['percentage'] >= 0 ? '+' : '' }}{{ number_format($comparison['total_equity']['percentage'], 2) }}%)
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif