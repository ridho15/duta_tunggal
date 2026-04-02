<x-filament-panels::page>
<style>
    .fr-page { font-family:'Inter',ui-sans-serif,system-ui,sans-serif; }
    .fr-report-header { background:linear-gradient(135deg,#14532d,#16a34a); color:#fff; border-radius:16px; padding:1.5rem 2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 6px 20px rgba(22,163,74,.2); }
    .fr-company-name { font-size:1.4rem; font-weight:800; }
    .fr-report-type { font-size:1rem; font-weight:600; opacity:.9; margin-top:.2rem; }
    .fr-notes { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:.85rem 1.25rem; font-size:.82rem; color:#166534; margin-bottom:1rem; }
    .fr-notes-title { font-weight:700; margin-bottom:.25rem; }
</style>
    <div class="fr-page space-y-4">
        {{-- Report Header --}}
        <div class="fr-report-header">
            <div class="fr-company-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fr-report-type">LAPORAN LABA RUGI — RINCIAN (INCOME STATEMENT DETAIL)</div>
        </div>

        {{-- Notes --}}
        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Cara Penggunaan:</div>
            <span>Pilih periode dan filter di bawah, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat rincian akun laba rugi.</span>
        </div>

        {{ $this->form }}

        {{ $this->table }}
    </div>
</x-filament-panels::page>