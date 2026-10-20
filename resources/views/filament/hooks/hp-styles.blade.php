<style>
/*
 * Tampilan HP (Spesifikasi UI/UX 6.5, 6.8, 6.12). Ditaruh inline agar tetap berlaku walau
 * tema Vite belum di-build. Kelas berawalan dt- supaya tidak bentrok dengan Filament.
 */
[x-cloak] { display: none !important; }

/* ── Navigasi bawah (< 768px) ─────────────────────────── */
.dt-mobile-nav-bar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
    height: 70px; padding-bottom: env(safe-area-inset-bottom);
    background: #fff; border-top: 1px solid #E5E7EB;
    display: grid; grid-template-columns: repeat(5, 1fr); align-items: center;
}
.dt-mobile-nav-item {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
    min-height: 56px; font-size: 11px; font-weight: 500; color: #6B7280; background: none; border: 0;
}
.dt-mobile-nav-item.is-active { color: #15803D; font-weight: 700; }
.dt-mobile-nav-create {
    justify-self: center; width: 48px; height: 48px; border-radius: 16px; border: 0;
    background: #15803D; color: #fff; display: flex; align-items: center; justify-content: center;
}
@media (max-width: 767px) {
    body.fi-body { padding-bottom: 84px; }
}
.dt-sheet-backdrop { position: fixed; inset: 0; z-index: 40; background: rgba(17, 24, 39, .45); }
.dt-sheet {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 50; background: #fff;
    border-radius: 20px 20px 0 0; padding: 10px 16px calc(16px + env(safe-area-inset-bottom));
    display: flex; flex-direction: column; gap: 8px;
}
.dt-sheet-handle { width: 40px; height: 4px; border-radius: 4px; background: #D1D5DB; align-self: center; margin-bottom: 6px; }
.dt-sheet-title { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 4px; }
.dt-sheet-option {
    display: flex; gap: 12px; align-items: center; min-height: 56px; padding: 10px 12px;
    border: 1px solid #E5E7EB; border-radius: 12px; color: #111827;
}
.dt-sheet-option-icon {
    width: 36px; height: 36px; border-radius: 10px; background: #F0FDF4; color: #15803D;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.dt-sheet-option-label { display: block; font-size: 15px; font-weight: 700; }
.dt-sheet-option-desc { display: block; font-size: 12px; color: #6B7280; }
.dt-sheet-cancel { min-height: 48px; border-radius: 12px; border: 1px solid #D1D5DB; background: #fff; font-weight: 600; color: #374151; }

/* ── Komponen halaman HP ──────────────────────────────── */
.dt-hp { width: 100%; max-width: 560px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; color: #111827; }
.dt-hp-pill {
    align-self: flex-start; font-size: 11px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase;
    color: #15803D; background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 6px; padding: 4px 9px;
}
.dt-hp-pill.is-warning { color: #92400E; background: #FFFBEB; border-color: #FCD34D; }
.dt-hp-pill.is-info { color: #1D4ED8; background: #EFF6FF; border-color: #BFDBFE; }
.dt-hp-title { margin: 0; font-size: 24px; font-weight: 800; line-height: 1.2; }
.dt-hp-sub { font-size: 13px; color: #6B7280; }
.dt-hp-card {
    background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 14px;
    display: flex; flex-direction: column; gap: 6px;
}
.dt-hp-card.is-dashed { border-style: dashed; border-color: #D1D5DB; }
.dt-hp-card.is-done { background: #F0FDF4; border-color: #86EFAC; }
.dt-hp-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.dt-hp-name { font-size: 16px; font-weight: 700; }
.dt-hp-code { font-size: 12px; color: #6B7280; }
.dt-hp-qty { font-size: 24px; font-weight: 800; white-space: nowrap; }
.dt-hp-qty small { font-size: 13px; font-weight: 500; color: #6B7280; }
.dt-hp-check { width: 26px; height: 26px; accent-color: #15803D; flex-shrink: 0; }
.dt-hp-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    min-height: 56px; padding: 14px; border-radius: 14px; font-size: 17px; font-weight: 700;
    border: 1px solid transparent; text-align: center;
}
.dt-hp-btn.is-primary { background: #15803D; color: #fff; }
.dt-hp-btn.is-primary:disabled, .dt-hp-btn.is-disabled {
    background: #F3F4F6; color: #9CA3AF; border: 1px dashed #D1D5DB; cursor: not-allowed;
}
.dt-hp-btn.is-warning { background: #FFFBEB; color: #92400E; border-color: #FCD34D; font-size: 15px; min-height: 52px; }
.dt-hp-btn.is-secondary { background: #fff; color: #374151; border-color: #D1D5DB; font-size: 15px; min-height: 52px; }
.dt-hp-btn.is-small { min-height: 44px; font-size: 14px; width: auto; padding: 10px 16px; border-radius: 10px; }
.dt-hp-reason { font-size: 12px; color: #6B7280; text-align: center; margin-top: -4px; }
.dt-hp-stepper { display: flex; align-items: center; gap: 8px; }
.dt-hp-stepper button {
    width: 52px; height: 52px; border-radius: 12px; border: 1px solid #D1D5DB; background: #fff;
    font-size: 24px; color: #111827; flex-shrink: 0;
}
.dt-hp-stepper input {
    flex-grow: 1; min-width: 0; height: 56px; border-radius: 12px; border: 2px solid #15803D;
    text-align: center; font-size: 28px; font-weight: 800;
}
.dt-hp-stepper.is-small { gap: 6px; }
.dt-hp-stepper.is-small button { width: 40px; height: 44px; font-size: 20px; }
.dt-hp-stepper.is-small input { height: 44px; font-size: 20px; border-width: 1px; border-color: #D1D5DB; }
.dt-hp-chips { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.dt-hp-chip {
    white-space: nowrap; padding: 7px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
    background: #fff; border: 1px solid #E5E7EB; color: #374151;
}
.dt-hp-chip.is-active { background: #15803D; border-color: #15803D; color: #fff; }
.dt-hp-search { display: flex; gap: 8px; }
.dt-hp-search input {
    flex-grow: 1; min-width: 0; height: 48px; border-radius: 10px; border: 1px solid #D1D5DB; padding: 0 12px; font-size: 15px;
}
.dt-hp-search button {
    width: 48px; height: 48px; border-radius: 10px; border: 0; background: #15803D; color: #fff;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.dt-hp-progress { height: 4px; background: #E5E7EB; border-radius: 4px; overflow: hidden; }
.dt-hp-progress > span { display: block; height: 100%; background: #15803D; }
.dt-hp-note {
    font-size: 12px; color: #6B7280; text-align: center; padding: 8px 10px;
    border: 1px dashed #D1D5DB; border-radius: 10px; background: #F9FAFB;
}
.dt-hp-alert { font-size: 13px; border-radius: 12px; padding: 12px; }
.dt-hp-alert.is-info { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; }
.dt-hp-alert.is-warning { background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E; }
.dt-hp-alert.is-danger { background: #FEF2F2; border: 1px solid #FCA5A5; color: #B91C1C; }
.dt-hp-empty {
    border: 1px dashed #D1D5DB; border-radius: 14px; padding: 24px 16px; text-align: center; color: #6B7280; font-size: 14px; background: #fff;
}
.dt-hp-list-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #EEF0F2; font-size: 14px; }
.dt-hp-list-row:last-child { border-bottom: 0; }
.dt-hp-bar { height: 8px; border-radius: 6px; background: #E5E7EB; overflow: hidden; }
.dt-hp-bar > span { display: block; height: 100%; background: #22C55E; }
.dt-hp-big { font-size: 40px; font-weight: 800; line-height: 1; }
.dt-hp-link { color: #15803D; font-weight: 700; font-size: 13px; }
.dt-hp-sticky { position: sticky; bottom: 78px; display: flex; flex-direction: column; gap: 8px; padding-top: 8px; background: linear-gradient(to bottom, rgba(246,247,248,0), #F6F7F8 30%); }
@media (min-width: 768px) { .dt-hp-sticky { bottom: 0; } }

/* ── Scan barcode ─────────────────────────────────────── */
.dt-hp-scan-trigger {
    width: 48px; height: 48px; border-radius: 10px; border: 0; background: #15803D; color: #fff;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.dt-scan-overlay { position: fixed; inset: 0; z-index: 60; background: #111827; display: flex; flex-direction: column; color: #fff; }
.dt-scan-head { height: 52px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; font-weight: 700; }
.dt-scan-view { position: relative; flex-grow: 1; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.dt-scan-view video { width: 100%; height: 100%; object-fit: cover; }
.dt-scan-frame { position: absolute; width: 78%; max-width: 320px; height: 170px; border: 3px solid #22C55E; border-radius: 16px; box-shadow: 0 0 0 2000px rgba(0,0,0,.45); }
.dt-scan-foot { background: #fff; color: #111827; border-radius: 18px 18px 0 0; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.dt-scan-foot input { height: 48px; border-radius: 10px; border: 1px solid #D1D5DB; padding: 0 12px; font-size: 15px; color: #111827; }

/* ── Mode latihan ─────────────────────────────────────── */
.dt-training-banner {
    position: sticky; top: 0; z-index: 35; background: #FEF3C7; color: #92400E; border-bottom: 1px solid #FCD34D;
    text-align: center; font-size: 13px; font-weight: 700; padding: 6px 12px;
}
</style>
