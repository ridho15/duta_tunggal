## Ringkasan Singkat — Improvement Plan (18 Mar 2026)

**Tanggal ringkasan:** 15 Mei 2026

Tujuan: menyajikan keputusan, status verifikasi, dan rekomendasi tindakan lanjutan dalam format singkat untuk manajemen prioritas.

1) Pencapaian utama
- Hardening `vendor payment deposit` (service + observer) — selesai.
- Perbaikan `CashFlowReportService` dan stabilisasi test cash-flow — blocker ditutup.
- Balance sheet: tidak lagi di-mask; UI menampilkan perbedaan aktual — selesai.
- Playwright/PHPUnit: scope audit terkait telah terverifikasi lulus dan stabil pada run terakhir.

2) Bukti singkat
- Regression vendor payment & balance sheet: suite terfokus lulus (metrik terlampir di dokumen penuh di `docs/legacy`).
- Playwright rerun pada scope audit: lulus pada run terakhir (angka lulus tinggi, no-skip pada scope yang diaudit).

3) Perhatian
- Beberapa spec legacy masih mengandalkan fixture/data dan perlu determinisasi agar `test.skip` benar-benar bisa dihilangkan.
- Kebutuhan provisioning data (contoh: `surat_jalan`) untuk beberapa skenario E2E.

4) Rekomendasi singkat
- Pertahankan dokumen penuh di `docs/legacy/IMPROVEMENT_PLAN_2026-03-18_FULL.md` (sudah dipindah).
- Prioritaskan refactor fixture deterministik untuk spec-legacy yang sering flaky (top 3).
- Jika diminta, saya bisa buat `IMPROVEMENT_PLAN_SUMMARY.md` berformat action list (top-5, owner, due-date).

— akhir ringkasan —
