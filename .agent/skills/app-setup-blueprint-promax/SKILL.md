---
name: app-setup-blueprint-promax
description: Full-cycle SDLC (Software Development Life Cycle) orchestrator for both greenfield (new) and brownfield (existing/ongoing) web applications. Covers codebase audit & reverse-engineering, interactive discovery, PRD ingestion or generation, Graphify system architecture blueprint, phased execution roadmap, Ponytail modular architecture, Antislop zero-placeholder coding, UI/UX ProMax with Tailwind CSS, strict data/alert/modal standards, No Happy Path defensive engineering, Playwright browser E2E testing, and QA/UAT verification, plus portable CLI/GitHub repository export.
---

# Full-Cycle SDLC & App Setup Blueprint ProMax (Greenfield & Brownfield)

Orchestrate the entire Software Development Life Cycle (SDLC) for both **new applications from scratch (Greenfield)** and **ongoing/existing projects (Brownfield)**. Covers codebase auditing, requirement discovery, PRD formalization, architectural blueprints, zero-slop implementation, progressive refactoring, defensive edge-case engineering, and automated Playwright QA/UAT sign-off.

## When to Use

- **Greenfield (New Project):** Initializing a new application from scratch, parsing a client PRD, or generating a new PRD, blueprint, and roadmap.
- **Brownfield (Continuing/Existing Project):** Taking over, continuing, adding features to, or refactoring an ongoing codebase while aligning it with strict engineering standards without breaking existing production flows.
- Designing or reverse-engineering system architecture blueprints (Graphify) and phased execution roadmaps.
- Enforcing strict UI/UX ProMax (Tailwind CSS), Antislop, Ponytail, and No Happy Path engineering standards.
- Auditing or building data lists, dynamic searchable selects, deduplicated alerts, and confirmation modals.
- Generating QA test matrices, UAT sign-off checklists, and Playwright browser test suites.

---

## Core Engineering Paradigms (Sub-Skills)

Every phase, artifact, new feature, and refactored module must strictly obey these thirteen paradigms:

1. **Ponytail (Modular Context & State Binding):** Organize code into clean, unidirectional dependency chains. Separate UI presentation, domain state/hooks, API service layers, and validation schemas. Never mix database/API logic directly inside view markup.
2. **Antislop (Zero AI-Slop Policy):** Strictly forbid lazy placeholders (`// TODO`, `// rest of code here`, `...`), mock unimplemented handlers, or generic `lorem ipsum` data. Every function, migration, component, and test must be complete, domain-specific, and production-ready.
3. **UI/UX ProMax (Tailwind CSS Design System):** Build high-craft, responsive interfaces using Tailwind CSS. Enforce semantic color tokens (`primary`, `emerald` for success, `amber` for warning, `rose`/`red` for danger), consistent spacing/typography scales, focus-visible accessibility rings, skeleton loading states, and informative empty states.
4. **Graphify (Architecture & Entity Graph Mapping):** Represent all data models, service dependencies, and complex transactional workflows using structured Mermaid diagrams (`erDiagram`, `sequenceDiagram`, `graph TD`) before writing or modifying implementation code.
5. **No Happy Path (Defensive & Edge-Case Engineering):** Never assume network requests, database writes, or user inputs succeed. Engineer explicitly for timeouts, `400/401/403/404/409/422/500` errors, race conditions, rapid double-clicks, expired auth sessions, malformed payloads, and empty datasets.
6. **Verification Data (Two-Way Schema Integrity):** Enforce strict schema validation on both client forms and server endpoints (e.g., Zod, Joi, Valibot, FormRequest). Sanitize all query parameters and verify actual database state persistence after mutations.
7. **Security by Default (OWASP-Based):** Security is a requirement of every phase, not a final audit. Authorization is enforced **on the server** for every endpoint and object ID (hiding a button is not access control). Output is escaped by default (no `innerHTML` / `dangerouslySetInnerHTML` / `v-html` without a sanitizer). Queries are parameterized. Session tokens live in `HttpOnly` + `Secure` + `SameSite` cookies. Secrets never enter the repo or the client bundle. Responses carry baseline security headers. Every module is checked against the **SEC-01..SEC-10** checklist in the PRD (section 6.1), and a control that does not apply must be marked "Tidak berlaku" with a reason, never skipped silently.
8. **Evidence Before Claims:** Never state that work is done, fixed, passing, secure, or compliant until the verification command has been run **in the current state of the code** and its real output has been read. Show the command and the result; "should work", "looks correct", and "tests pass" without output are not evidence. If something was not run or could not be verified, say so explicitly and mark it **"belum diverifikasi"** with the reason. Failing or skipped checks are reported as they are, never rounded up. A QA/UAT row may be marked as passed only with a filled **Bukti** cell (command output, screenshot, trace, or report path); an empty Bukti means the row is not passed. Evidence must also be **verified before it is reported** (see the Evidence Verification Gate in Phase 6): a result that was attached but never checked is not yet evidence.
9. **Focused Change, No Guessing:** Every addition, fix, or adjustment is scoped to its module or feature and follows the **Focused Change Protocol** below. Read the relevant code before editing it, prove every name you use exists, map who depends on it, reproduce and find the root cause before fixing a bug, and mark anything unverifiable as an unproven assumption instead of guessing. Never change files outside the agreed scope.
10. **Verified Plan & Goal:** Before building, the goal must be measurable with provable completion criteria, and the plan must be checked against the real state of the project, traced to tests, and approved on record. When reality diverges from the plan, stop, re-verify, and get re-approval. Before claiming completion, match the result against the original completion criteria. See the **Plan & Goal Verification Gate**.
11. **Right Tool, Lean Context:** Pick the right skill for each job when it is installed (never claim one that is not), keep a project map so the whole project is not re-scanned every task, read only what the task needs, and keep the diff minimal and free of AI slop. See the **Skill Routing & Token Efficiency Protocol**.
12. **Proportional Complexity:** The project must stay as simple as its goal requires. Periodically check for scope drift and overengineering against the PRD, back every finding with evidence from the code, recommend (never silently perform) simplification, finish approved simplifications completely, and leave alone any complexity the developer has intentionally recorded. See the **Complexity & Scope Drift Protocol**.
13. **Staged Delivery, Nothing Missed:** Work that is too broad (several modules, many files, several independent goals, or "all/everything") must first be mapped from evidence into a closed inventory, then broken into small stages with provable exit criteria and a tracker, so every item is completed, deferred, or cancelled on record. Applies to features, updates, fixes, and adjustments. See the **Work Decomposition & Staged Execution Protocol**.

---

## Mandatory UI, Data & Interaction Standards

Every new module—and any existing module being touched or refactored—must implement these four non-negotiable UI/data contracts:

### 1. Data Lists & Tables
- **Mandatory Pagination or Lazy Loading:** Unbounded queries (`SELECT *` without limits) are strictly prohibited. Every list, table, or grid must implement **Server-Side Pagination** (page number, page size selector, total count) or **Cursor-Based Lazy Loading / Infinite Scroll**.
- **Debounced Search & Multi-Filter:** Every list view must include a debounced search bar (`300ms-500ms`) and contextual multi-filters (status, date range, category, role) synchronized with URL query parameters (`?page=1&limit=10&search=...&status=...`).

### 2. Dynamic Select & Dropdown Inputs
- **Async Searchable Dropdown (Combobox) Only:** Any dropdown/select populated from a database or API (or containing more than 5 options) must use a **Searchable Dropdown / Combobox** supporting async server-side search, loading indicator, clear button, keyboard navigation, and a clear `"Data tidak ditemukan"` state. Native HTML `<select>` is strictly forbidden for dynamic relational data.

### 3. Alert & Notification System
- **Unified Notification Manager:** Centralize all toast and banner alerts using consistent Tailwind CSS semantic styling and icons.
- **Anti-Double Alert (Deduplication):** Assign deterministic deduplication IDs (e.g., hashing HTTP method + endpoint + status code) or state locks so global API interceptors and local component `catch` blocks never trigger duplicate alerts for the same event. Disable submit buttons with a spinner during in-flight requests.
- **Explicit & Actionable Error Messages:** Generic errors like `"Terjadi kesalahan"`, `"Server Error"`, or `"Gagal menyimpan data"` are forbidden. Every error alert must follow the formula: **[Konteks Aksi yang Gagal] + [Penyebab Spesifik dari Server/Validasi] + [Langkah Perbaikan]** (e.g., *"Gagal menyimpan pengguna: Email 'budi@mail.com' sudah terdaftar. Gunakan alamat email lain atau pulihkan akun."*).

### 4. Confirmation Modals
- **Standardized Confirmation Modal:** All destructive or state-critical actions (Delete, Cancel Transaction, Approve/Reject, Bulk Update, Role Change) must pass through a single reusable Tailwind CSS Confirmation Modal.
- **Modal Anatomy:** Must clearly display the action title, explicit impact description naming the exact entity/ID affected, a secondary Cancel button (`Esc` key bound), and a primary Confirm button (styled `bg-red-600 hover:bg-red-700` for destructive actions) that locks into a loading state until the async operation finishes.

---

## Work Decomposition & Staged Execution Protocol (pekerjaan luas: fitur, pembaruan, perbaikan, penyesuaian)

Tujuan: pekerjaan yang terlalu luas dipetakan dan dipecah menjadi tahap yang kecil dan terverifikasi, sehingga hasilnya tepat, tuntas, dan tidak ada yang terlewatkan. Berlaku untuk fitur baru, pembaruan, perbaikan bug, dan penyesuaian (termasuk refaktor dan migrasi). Catat di `docs/sdlc/templates/ROADMAP-TEMPLATE.md`.

1. **Ukur dulu (Work Size Check):** pekerjaan **wajib dipecah** bila salah satu terpenuhi: menyentuh lebih dari satu modul atau domain; diperkirakan mengubah lebih dari ~8 file atau tidak muat dalam satu sesi; memuat lebih dari satu tujuan yang bisa berdiri sendiri; menggabungkan migrasi data, API, dan UI; berbunyi "semua" atau "seluruh" tanpa daftar; atau tidak bisa diverifikasi tuntas dalam satu kali jalan. Ambang bersifat indikatif dan boleh disesuaikan dengan alasan tertulis. Jika tidak ada yang terpenuhi, Focused Change Protocol sudah cukup.
2. **Petakan seluruh item (inventaris) dari bukti,** bukan ingatan: pakai peta proyek, pencarian referensi, daftar rute, skema, atau daftar halaman. Catat cara menemukan tiap item dan **tutup inventaris dengan hitungan** (jumlah item pada hash commit tertentu).
3. **Susun roadmap bertahap:** setiap tahap kecil dengan tujuan terukur, item yang dicakup, ketergantungan, **kriteria keluar yang dapat dibuktikan**, tes, rollback, dan ukuran. Urutkan menurut ketergantungan dan risiko (fondasi dan risiko tinggi lebih dulu; potong vertikal bila memungkinkan). Satu tahap berjalan pada satu waktu kecuali benar-benar independen. Roadmap yang berlapis-lapis tanpa perlu melanggar Proportional Complexity: buat tahap sesedikit yang dibutuhkan.
4. **Tracker keterlacakan:** setiap item inventaris wajib punya tahap dan status (Belum / Berjalan / Tuntas / Ditunda dengan alasan / Dibatalkan dengan alasan dan persetujuan) dan bukti. Item tanpa tahap adalah celah dan dilaporkan. Roadmap disetujui dan dicatat sebelum tahap 1 dimulai (Plan & Goal Verification Gate).
5. **Gerbang per tahap:** tahap ditutup hanya jika kriteria keluarnya terbukti (Evidence Verification Gate, Data Verification Gates, dan kontrol keamanan bila relevan) dan tes regresi tahap-tahap sebelumnya lulus. Tahap berikutnya tidak dimulai selama ada kegagalan kritis yang terbuka.
6. **Penutupan tahap dan carry-over:** setelah tiap tahap, pindai ulang inventaris. Temuan baru **ditambahkan ke inventaris dan roadmap**, tidak dikerjakan diam-diam dan tidak dibuang. Sisa pekerjaan dibawa eksplisit ke tahap berikutnya. Perbarui peta proyek dan roadmap (hash terbaru).
7. **Penutupan roadmap:** cocokkan inventaris dengan hasil. Roadmap tuntas hanya jika **setiap item** berstatus Tuntas berbukti, atau Ditunda/Dibatalkan dengan alasan dan persetujuan tercatat, inventaris dipindai ulang tanpa item baru, dan Gerbang Tujuan Tercapai lulus. Sisa dicatat sebagai roadmap lanjutan, dan pekerjaan dilaporkan **belum tuntas** bila masih ada.
8. **Perubahan rencana:** jika kenyataan berbeda dari roadmap, hentikan, catat di log, nilai dampaknya, dan minta persetujuan ulang (Plan & Goal Verification Gate).

### Format Laporan Tahap
```text
Tahap: <S-xx> | tujuan: <...>
Kriteria keluar: <kriteria> -> <terpenuhi / belum> | bukti: <...>
Item: <n tuntas, n sisa> | Temuan baru: <ditambahkan ke inventaris: ...>
Regresi tahap sebelumnya: <perintah -> hasil nyata>
Status roadmap: <n/N item tuntas, n ditunda, n dibatalkan>
```

---

## Complexity & Scope Drift Protocol (jaga proyek tetap fokus pada tujuannya)

Tujuan: mendeteksi proyek yang tumbuh melebihi tujuannya (misalnya aplikasi sederhana yang pelan-pelan menjadi sistem keuangan atau perbankan dengan lapisan yang tidak diminta), agar pengembangan tetap terfokus, tidak berlapis-lapis, dan tidak melambat. **Kompleksitas yang disengaja developer dibiarkan.** Catat di `docs/sdlc/templates/COMPLEXITY-DECISIONS-TEMPLATE.md` dan `SIMPLIFICATION-REPORT-TEMPLATE.md`.

1. **Kapan diperiksa:** di akhir setiap milestone atau fase, sebelum Fase 6, saat diminta, dan saat sebuah perubahan menambah domain atau lapisan baru yang tidak ada di PRD.
2. **Drift dua arah** terhadap tujuan dan non-goals di PRD: (a) butir PRD yang belum dikerjakan, dan (b) fitur, modul, tabel, atau dependensi yang **tidak punya butir PRD** atau melanggar non-goals.
3. **Indikator overengineering harus berbukti dari kode**, bukan perasaan (hitung dengan pencarian referensi atau perintah, dan sebutkan hasilnya): abstraksi atau antarmuka dengan satu implementasi; modul atau layanan dengan satu pemanggil; opsi, konfigurasi, atau feature flag yang tidak pernah dipakai; dependensi besar untuk fungsi kecil; kode mati; fitur tanpa butir PRD; serta fitur sekelas domain lain yang tidak diminta tujuan aplikasi (ledger, rekonsiliasi, audit trail ketat, multi-tenant, workflow persetujuan berlapis, dan sejenisnya).
4. **Daftar Kompleksitas Disengaja** (`docs/sdlc/COMPLEXITY-DECISIONS.md`): developer atau pemilik produk mencatat kompleksitas yang memang diinginkan beserta alasan konkret, penyetuju, tanggal, versi tujuan, dan tanggal tinjau ulang. Yang tercatat **tidak dipersoalkan** dan tidak direkomendasikan untuk dirombak, kecuali tujuan proyek berubah atau tanggal tinjau terlewat. Agen **tidak boleh mengisi register sendiri**.
5. **Rekomendasi, bukan eksekusi sepihak:** untuk temuan yang tidak tercatat, buat Laporan Kompleksitas & Drift (bukti, dampak pada waktu build/jumlah file/risiko, usulan penyederhanaan, prioritas, rollback) lalu minta keputusan developer: **Setuju dirombak / Tolak / Catat sebagai disengaja**. Tidak ada perombakan sebelum keputusan tercatat.
6. **Perombakan yang disetujui dikerjakan sampai tuntas:** kode lama dihapus (tanpa dua implementasi berdampingan), tidak ada adapter, flag, atau shim sementara yang tertinggal, referensi dan dokumentasi diperbarui, tes dan regresi lulus dengan bukti, dan metrik sebelum -> sesudah dilaporkan (file, baris, dependensi, lapisan). Kerjakan dalam langkah kecil yang masing-masing bisa dipulihkan (Focused Change Protocol berlaku). Bila ada sisa, laporkan **"belum tuntas"**, jangan menyatakan selesai.
7. **Proporsional terhadap profil proyek:** tetapkan **Profil Proyek** di PRD (Prototipe, Internal, Produksi, atau Terregulasi/keuangan). Profil menentukan *kedalaman* kontrol: butir SEC yang berstatus "Tidak berlaku" beserta alasannya, dan luas matriks QA. Gerbang bukti, verifikasi data, dan keamanan dasar tetap berlaku untuk semua profil. Jangan menambah lapisan hanya karena kit memilikinya.

### Format Laporan Kompleksitas & Drift
```text
Diperiksa: <hash, tanggal> | profil: <...> | register kompleksitas dibaca: <ya/tidak>
Drift: <n butir PRD belum dikerjakan, n fitur tanpa butir PRD, n melanggar non-goals>
Temuan: <n> (tercatat disengaja: <n>) | direkomendasikan: <n> | disetujui: <n> | tuntas: <n>
Metrik: <file/baris/dependensi/lapisan sebelum -> sesudah>
Belum tuntas: <daftar atau "tidak ada">
```

---

## Skill Routing & Token Efficiency Protocol (wajib di setiap tugas)

Tujuan: memakai alat yang tepat untuk tiap pekerjaan, menghemat token dengan tidak memeriksa ulang seluruh proyek, dan mencegah sampah buatan AI. Skill lain **tidak dibundel** kit ini; semua baris di bawah berlaku **jika terpasang**, dan selalu ada cadangan berupa aturan kit.

### Matriks Pemilihan Skill
| Pekerjaan | Skill (jika terpasang) | Jika tidak terpasang |
| :--- | :--- | :--- |
| Orkestrasi fase SDLC | `sdlc-promax-kit` (skill `app-setup-blueprint-promax`) | `SKILL.md` ini |
| Peta struktur dan dependensi proyek | `graphify` (`graphify-out/`, query/path/explain) | `docs/sdlc/PROJECT-MAP.md` + diagram Mermaid |
| Diff terkecil, tanpa bloat | `ponytail` | Lean Diff Rules di bawah |
| Mencegah sampah AI pada kode, UI, dan teks | `antislop` (`antislop-code`, `antislop-ui`, `antislop-copywriting`, `antislop-human`, `antislop-layoutmobile`) | Slop Rules di bawah |
| Desain UI, Tailwind, komponen | `ui-ux-pro-max`, `21stdev` | Standar UI di `SKILL.md` dan `ui-standards-reference` |
| API library atau framework terbaru | `context7` | Versi dari lockfile + dokumentasi resmi; tandai "belum diverifikasi" jika tidak bisa dibuka |
| Skema, query, indeks, migrasi database | `database-design` | PRD bagian 5 dan SEC-02 |
| Uji peramban dan E2E | `playwright` | Spec di `playwright-reference` |
| Matriks QA dan tingkat keparahan bug (P0-P3) | `qa` | `QA-UAT-MATRIX.md` |
| Strategi tes unit, integrasi, kontrak | `testing` | Evidence Verification Gate |
| UAT dan Berita Acara | `uat` | `QA-UAT-MATRIX.md` bagian B dan C |

Aturan penggunaan:
1. **Periksa dulu, jangan menebak:** sebelum tugas, tentukan baris matriks yang relevan dan cek apakah skill-nya benar-benar tersedia (daftar skill/alat sesi). Dilarang mengaku memakai skill yang tidak terpasang.
2. **Muat seperlunya:** hanya skill yang relevan untuk tugas ini; memuat semuanya memboroskan token.
3. **Pembagian wewenang:** gerbang proses di `SKILL.md` ini (bukti, keamanan, data, rencana) selalu menang dan tidak boleh dilemahkan skill lain; skill spesialis menang untuk teknik di bidangnya.
4. **Laporkan** skill yang dipakai dan yang tidak tersedia (lihat Format Laporan Skill & Konteks).

### Protokol Peta Proyek (hemat token)
1. **Satu peta per proyek:** `graphify-out/` bila graphify terpasang, atau `docs/sdlc/PROJECT-MAP.md` (`PROJECT-MAP-TEMPLATE.md`). Header memuat tanggal, hash commit, cakupan, dan perintah tes. Peta berisi modul, tanggung jawab, rute, tabel, pemanggil utama, tes, dan titik rawan, **tanpa salinan kode**.
2. **Urutan kerja:** baca peta -> tentukan file relevan -> buka hanya file itu (per rentang baris) -> buktikan nama di file aslinya. Peta adalah petunjuk, bukan bukti.
3. **Keusangan:** bandingkan hash di peta dengan `git rev-parse --short HEAD`. Jika berbeda, jalankan `git diff --name-only <hash>..HEAD` dan petakan ulang **hanya modul yang berubah**. Peta usang tidak boleh menjadi dasar klaim.
4. **Dilarang memindai ulang seluruh proyek** tanpa alasan tertulis (mis. peta belum ada, atau sebagian besar modul berubah).
5. **Perbarui inkremental** setelah setiap perubahan: entri modul yang disentuh dan hash saja. Fase 0 Brownfield membuat peta pertama.

### Aturan Hemat Token
- Baca per rentang baris dan cari dengan pencarian terarah (nama fungsi, tabel, route); jangan membuka file besar utuh tanpa alasan.
- Jangan membaca ulang file yang sudah dibaca di sesi yang sama kecuali sudah berubah.
- Jalankan tes terfokus (modul dan pemanggil terdampak) selama bekerja, tes penuh sekali di akhir.
- Ringkas keluaran sukses yang panjang, tetapi **kegagalan ditampilkan utuh** (Evidence Verification Gate langkah 4).
- Catat keputusan di `CHANGE-SCOPE-TEMPLATE.md` atau `PLAN-AND-GOAL-TEMPLATE.md`, bukan mengulang penjelasan panjang.

### Lean Diff Rules (Ponytail) dan Slop Rules (Antislop)
- **Diff terkecil** yang memenuhi tujuan. Pakai fitur bawaan bahasa, framework, dan library yang sudah terpasang sebelum menambah dependensi; dependensi baru wajib justifikasi dan persetujuan.
- Tanpa abstraksi, file, opsi, atau konfigurasi yang tidak diminta, dan tanpa refaktor di luar sasaran (YAGNI).
- Dilarang: komentar yang hanya mengulang kode atau berbunyi seperti obrolan AI, kode mati atau kode yang dikomentari, log debug yang tertinggal, placeholder dan `lorem ipsum`, `try/catch` yang menelan error, teks UI generik ("Terjadi kesalahan"), serta berkas dokumentasi tambahan yang tidak diminta.
- **Gerbang diff sebelum klaim selesai:** jalankan `git diff --stat` dan baca diff-nya. Hanya file di batas ruang lingkup yang berubah, tidak ada file atau dependensi tak terduga, dan sisa debug sudah dihapus.

### Format Laporan Skill & Konteks
```text
Skill dipakai: <nama -> untuk apa>
Skill tidak terpasang (fallback): <nama -> aturan kit yang dipakai>
Peta proyek: <path>, hash <hash>, status <terkini | diperbarui: modul ... | tidak ada: dibuat>
Pemindaian ulang penuh: <tidak | ya, alasan>
Diff: <n file, +/-> | semua dalam ruang lingkup: <ya/tidak> | dependensi baru: <tidak / ada, alasan>
```

---

## Plan & Goal Verification Gate (wajib sebelum pekerjaan dimulai, saat rencana berubah, dan sebelum klaim selesai)

Tujuan: memastikan yang dikerjakan adalah hal yang benar sebelum mengerjakannya dengan benar. Catat di `docs/sdlc/templates/PLAN-AND-GOAL-TEMPLATE.md`.

1. **Gerbang Tujuan** (sebelum PRD atau perubahan dimulai): tujuan harus memuat (a) hasil terukur, (b) kriteria selesai yang bisa dibuktikan dengan tes atau pengamatan, (c) ruang lingkup, dan (d) non-goals. Tujuan kabur ("buat lebih baik", "rapikan") ditolak dan ditanyakan balik ke pemilik produk, bukan ditafsirkan sendiri.
2. **Gerbang Rencana** (sebelum implementasi): rencana diperiksa terhadap keadaan nyata proyek (kode, skema, data, versi terpasang, batasan teknis) dengan pembuktian seperti Focused Change Protocol langkah 2-5. Setiap langkah rencana mengacu pada hal yang terbukti ada atau ditandai sebagai hal baru. Rencana memuat urutan tugas, ketergantungan, risiko dan mitigasi, serta **rencana mundur (rollback)** untuk migrasi dan perubahan berisiko.
3. **Keterlacakan:** setiap butir PRD atau kriteria selesai dipetakan ke tugas, tes, baris QA, dan bukti. Butir tanpa tugas atau tanpa tes dilaporkan sebagai celah dan tidak boleh dibiarkan.
4. **Persetujuan tercatat:** nama penyetuju, tanggal, dan versi (nomor revisi atau hash commit) dokumen yang disetujui. Kode tidak ditulis sebelum persetujuan tercatat; persetujuan lewat chat ditulis ulang dengan kutipan.
5. **Verifikasi ulang saat rencana berubah:** jika kenyataan berbeda dari rencana (Focused Change Protocol langkah 9), hentikan pekerjaan, catat di Log Perubahan Rencana, nilai dampaknya terhadap tujuan, keterlacakan, dan jadwal, lalu minta persetujuan ulang.
6. **Gerbang Tujuan Tercapai** (sebelum klaim selesai): cocokkan hasil akhir dengan kriteria selesai di tujuan awal satu per satu, dengan bukti dari Evidence Verification Gate. Lulus tes saja belum berarti tujuan tercapai; jika hasil menjawab hal lain dari tujuan, laporkan selisihnya.

### Format Laporan Rencana dan Tujuan
```text
Tujuan: <hasil terukur>
Kriteria selesai: <kriteria> -> <terpenuhi / belum / selisih> | bukti: <...>
Keterlacakan: <n> butir, <n> punya tugas, <n> punya tes, <n> tanpa tes (daftar)
Persetujuan: <nama, tanggal, versi>
Perubahan rencana: <jumlah, ringkas, sudah disetujui ulang?>
Kesimpulan: <tujuan tercapai | tercapai sebagian | belum>
```

---

## Focused Change Protocol (wajib untuk setiap fitur, perbaikan bug, atau penyesuaian)

Tujuan: mencegah halusinasi (menebak nama, struktur, atau perilaku kode) dan perubahan di luar sasaran. Berlaku untuk Greenfield dan Brownfield, besar maupun kecil. Catat dengan `docs/sdlc/templates/CHANGE-SCOPE-TEMPLATE.md`.

1. **Tetapkan batas** (jika pekerjaan terlalu luas menurut Work Size Check, pecah dulu dengan Work Decomposition & Staged Execution Protocol; setiap tahap lalu mengikuti protokol ini): modul atau fitur sasaran, file yang boleh diubah, dan file yang dilarang disentuh. Dilarang "sekalian merapikan" di luar batas; temuan lain dilaporkan, bukan diperbaiki diam-diam.
2. **Baca sebelum menulis:** buka file, fungsi, tipe, skema, dan pemanggil yang relevan. Jangan mengedit file yang belum dibaca pada sesi ini.
3. **Fakta, bukan tebakan:** setiap nama yang dipakai (fungsi, kelas, route, tabel, kolom, env var, prop komponen) harus terbukti ada saat ini lewat pencarian atau pembacaan. Klaim tentang perilaku kode menyebut `file:baris`. Jika sesuatu tidak ditemukan, jangan mengarangnya: tanyakan, atau buat sebagai hal baru dengan alasan yang jelas.
4. **Peta dampak:** cari semua pemanggil dan dependensi (referensi, rute, komponen bersama, migrasi, tes terkait) dan daftarkan sebelum mengubah.
5. **Versi terpasang:** baca versi library dari lockfile dan dokumentasi yang sesuai; jangan memakai API dari ingatan.
6. **Bug dan anomali:** (a) reproduksi dan catat hasil sebenarnya vs seharusnya dengan bukti kegagalan, (b) temukan akar masalah dengan bukti (log, stack trace, query, debugger) dan bedakan penyebab dari gejala, (c) tulis tes regresi yang **gagal** sebelum perbaikan, (d) perbaiki seminimal mungkin, (e) buktikan tes itu **lulus**, (f) cari pola yang sama di tempat lain dan laporkan.
7. **Tandai asumsi:** hal yang tidak bisa diverifikasi ditulis sebagai **"asumsi belum terbukti"** dan ditanyakan jika memengaruhi perilaku; jangan diisi dengan tebakan.
8. **Regresi terfokus:** jalankan tes modul itu dan tes pemanggil yang terdampak, lalu laporkan hasil nyatanya (lihat Evidence Verification Gate).
9. **Berhenti dan baca ulang** bila kenyataan berbeda dari dugaan (file tidak ada, tanda tangan fungsi berbeda, hasil tes tak terduga) atau bila jumlah file yang perlu diubah melebihi rencana; jangan menambal dengan tebakan.

### Format Laporan Ruang Lingkup (akhir setiap perubahan)
```text
Tujuan perubahan: <satu kalimat>
Dibaca: <file/fungsi/skema yang benar-benar dibuka>
Diubah: <file -> alasan>
Sengaja tidak disentuh: <file/modul -> alasan>
Peta dampak yang diperiksa: <pemanggil/dependensi>
Akar masalah (bug): <penyebab + bukti>; tes regresi: <nama> gagal sebelum, lulus sesudah
Asumsi belum terbukti: <daftar atau "tidak ada">
Regresi terfokus: <perintah -> hasil nyata>
```

---

## 7-Phase Full-Cycle SDLC Workflow

### Phase 0: Project Mode Detection & Existing Codebase Audit (Brownfield Mode)
First, check `docs/sdlc/sdlc-config.json` (if generated by the CLI) or ask whether the project is **Greenfield (Setup Baru)** or **Brownfield (Melanjutkan Project)**.

If continuing an existing project (**Brownfield Mode**), execute this mandatory **4-Step Technical Audit** before writing any new code:
1. **Architecture & Dependency Scan:** (Save the result as the project map in `docs/sdlc/PROJECT-MAP.md`; see the Skill Routing & Token Efficiency Protocol.) Inspect `package.json` / `composer.json` / `go.mod`, directory structure, routing patterns, database migrations/schema, and current state management.
2. **Standards Compliance Gap Analysis:** Scan existing modules against the Mandatory Standards and generate a **Technical Debt & Compliance Report** (unpaginated tables, native `<select>` elements, double-alert risks, missing confirmation modals).
3. **Regression & Impact Mapping (Graphify):** (Repeat this per change using the Focused Change Protocol.) Map how the requested new feature or bugfix interacts with existing tables, API endpoints, and shared components so existing production features do not break.
4. **Progressive Refactoring Strategy ("Boy Scout Rule"):** Inject missing core primitives (`AlertManager`, `ConfirmModal`, `AsyncSearchSelect`, `DataTable`) as shared components, enforce 100% strict compliance on all **new features**, and progressively upgrade **existing modules** touched during the current task.

**Security posture scan (part of the audit, report findings in the same Technical Debt & Compliance Report):** search the repo and git history for committed secrets (`.env`, API keys, tokens); run the package manager's audit (`npm audit`, `composer audit`, `pip-audit`, `govulncheck`); list endpoints and check each for a server-side authorization check; look for raw SQL string concatenation and unsafe HTML rendering; check response headers and session cookie flags. Rank findings P0-P3 and do not start feature work on a P0 without telling the user.

### Phase 1: Discovery Questionnaire & PRD / Delta-PRD Management
**Gerbang Tujuan:** sebelum PRD dibuat atau dianalisis, terapkan Gerbang Tujuan (hasil terukur, kriteria selesai, ruang lingkup, non-goals); tujuan kabur ditanyakan balik.
- **Mode A - Ingesti PRD Utuh:** Bedah PRD klien dan lakukan *Gap & No-Happy-Path Audit*.
- **Mode B - Buat PRD Baru:** Susun PRD enterprise dari nol lengkap dengan RBAC, User Stories, dan *Matriks Kepatuhan Standar*.
- **Mode C - Delta-PRD & Change Request (Khusus Melanjutkan Project):** Susun spesifikasi perubahan yang memetakan *Existing vs. Target State*, *Safe Database Schema Diff (Zero Data-Loss)*, dan *Regression Guardrails*.

### Phase 2: Architecture Blueprint (Graphify) & Execution Roadmap
Hasilkan diagram Mermaid (`erDiagram`, `sequenceDiagram`, `graph TD`) beserta *Milestone Roadmap* bertahap. Untuk pekerjaan luas, roadmap dibuat dengan Work Decomposition & Staged Execution Protocol (inventaris tertutup, tahap berkriteria keluar, tracker).

**Gerbang Rencana & Keterlacakan:** (rencana tidak memuat lapisan, modul, atau fitur di luar tujuan; kompleksitas yang disengaja dicatat di register, lihat Complexity & Scope Drift Protocol) roadmap diperiksa terhadap keadaan nyata proyek, memuat urutan, ketergantungan, risiko, dan rollback, serta setiap butir PRD dipetakan ke tugas, tes, dan baris QA (Matriks Keterlacakan). Implementasi tidak dimulai sebelum persetujuan tercatat (nama, tanggal, versi).

**Security Gate (Threat Model Ringan):** tandai pada diagram *trust boundary* (browser, API, database, layanan pihak ketiga), lalu untuk setiap aliran data lintas batas tuliskan ancamannya (spoofing, tampering, kebocoran data, akses tanpa hak) dan kontrol SEC yang menutupnya. Tentukan juga model autentikasi dan sesi, tempat penyimpanan secret, dan peran yang boleh mengakses tiap endpoint sebelum implementasi dimulai.

### Phase 3: Design System & Reusable Core Components
Bangun atau pasang 4 komponen fondasi berbasis Tailwind CSS yang tersedia di `docs/sdlc/ui-standards-reference/` (`DataTablePagination.tsx`, `AsyncSearchSelect.tsx`, `AlertContainer.tsx` + `alert-manager.ts`, dan `ConfirmModal.tsx`) secara utuh (*Antislop*) dan terikat rapi (*Ponytail*).

### Phase 4: Feature Implementation & Defensive Coding
Bangun atau perbarui modul aplikasi sesuai urutan *Roadmap* dengan mematuhi aturan *Server-side Pagination/Lazy Loading*, *Searchable Dropdown*, *Two-Way Data Verification*, dan *No Happy Path*.

**Evidence Report (wajib di akhir setiap modul):** gunakan format di bagian "Format Laporan Bukti" di bawah sebelum menyatakan modul selesai.

**Security Gate per modul (wajib sebelum modul dinyatakan selesai):**
1. Otorisasi diperiksa di server untuk setiap endpoint dan setiap ID objek (SEC-01); jawab `401` untuk tanpa sesi dan `403`/`404` untuk akses ke data milik pihak lain.
2. Input divalidasi dengan skema di server dan field tak dikenal ditolak; query memakai parameter atau ORM (SEC-02).
3. Tidak ada rendering HTML mentah dari data pengguna; terapkan CSP (SEC-03). Mutasi berbasis cookie dilindungi CSRF (SEC-04).
4. Cookie sesi `HttpOnly`, `Secure`, `SameSite`; login dan reset password dibatasi rate limit (SEC-05).
5. Tidak ada secret di kode atau bundle klien; konfigurasi lewat environment (SEC-07). Aksi sensitif dicatat di audit log tanpa menyimpan password atau token (SEC-09).
6. Tulis status tiap butir SEC (Ya / Tidak berlaku + alasan) di PRD bagian 6.1 untuk modul tersebut.

**Data Verification Gates (wajib sebelum modul dinyatakan selesai DAN sebelum tes modul dinyatakan berhasil; tes yang lulus tanpa verifikasi data belum dihitung berhasil):**
1. **Daftarkan dulu:** setiap informasi yang akan tampil di layar masuk ke *Matriks Verifikasi Data* (PRD 4.1) beserta sumber, aturan hitung, format, dan cara verifikasinya. Jangan menampilkan angka yang sumbernya belum ditulis.
2. **Periksa di setiap tahap** (PRD 4.2): input (validasi dua sisi), proses (rumus acuan, tipe desimal untuk uang), simpan (baca ulang dari database setelah mutasi), tampil (bandingkan layar dengan respons API), ekspor (rekonsiliasi total).
3. **Jangan mengasumsikan keberhasilan:** respons sukses dari API bukan bukti data tersimpan; baca ulang rekamannya.
4. **Nilai tepi harus punya tampilan:** null, kosong, nol, negatif, teks panjang, dan data lama tidak boleh tampil sebagai `null`, `NaN`, atau `Invalid Date`.
5. **Lacak alur data ujung ke ujung:** untuk setiap modul, pilih satu data uji dan telusuri dari input (form/impor/API) -> simpan (baca ulang dari database) -> tampil (daftar, detail, dashboard) -> ekspor/laporan. Nilai harus sama di setiap titik; catat selisih sekecil apa pun.
6. **Cocokkan dengan sumber, bukan dengan dirinya sendiri:** angka, status, dan format yang tampil dibandingkan dengan sumber independen (query database langsung, perhitungan manual, atau dokumen sumber), bukan hanya dengan respons API yang sama yang dipakai layar. Total dan jumlah baris dihitung ulang.
7. **Tes otomatis wajib:** setiap modul punya tes pelacakan alur data (tulis lewat UI, baca di daftar dan API) dan tes rekonsiliasi untuk setiap halaman yang menampilkan total atau angka turunan.

### Phase 5: Edge-Case Hardening & Regression Verification
**Pemeriksaan Kompleksitas & Drift:** jalankan Complexity & Scope Drift Protocol di akhir tiap milestone dan sebelum Fase 6; hentikan penambahan lapisan atau fitur baru bila temuan belum diputuskan developer.

Uji paksa terhadap *race condition* (anti-*double submit* & anti-*double alert*), input ekstrem, serta pastikan kompatibilitas data lama (*legacy data*) pada project lanjutan. Lakukan **rekonsiliasi data**: bandingkan total, jumlah baris, dan nilai di layar dengan hitungan langsung dari database, serta muat ulang halaman untuk membuktikan persistensi (QA-13..QA-16). Lakukan **pengujian keamanan dasar**: payload XSS dan injeksi di semua input dan parameter URL, akses endpoint tanpa sesi dan dengan sesi pengguna lain (IDOR), pemeriksaan header dan cookie, serta percobaan login berulang untuk rate limit (QA-09..QA-12).

### Phase 6: Automated Playwright Browser Testing, QA & UAT
Hasilkan skrip **Playwright E2E & Regression (`*.spec.ts`)**, **QA Test Case Matrix**, dan dokumen **UAT Sign-off**. **Gerbang tujuan tercapai:** hasil akhir dicocokkan satu per satu dengan kriteria selesai di tujuan awal, dengan bukti; tes lulus saja belum berarti tujuan tercapai. **Gerbang data:** klaim "tes berhasil" hanya sah jika **QA-13..QA-16 lulus dengan bukti** (UAT-04 bila ada angka bisnis), *Matriks Verifikasi Data* (PRD 4.1) terisi untuk setiap halaman yang menampilkan data, dan alur data satu data uji sudah dilacak dari input sampai tampil dan ekspor. Tes UI yang lulus tanpa verifikasi data dilaporkan sebagai "belum diverifikasi". **Gerbang keamanan:** QA-09..QA-12 wajib lulus, checklist SEC-01..SEC-10 terisi, `npm audit` (atau setara) tanpa temuan tinggi/kritis pada dependensi produksi, dan pemindaian secret bersih. Jika ada yang gagal, laporkan apa adanya dan jangan menyatakan rilis siap. Setiap baris QA/UAT yang dicentang wajib punya isian **Bukti**; Berita Acara tidak boleh berstatus DITERIMA PENUH bila ada baris tanpa bukti atau berstatus gagal.

### Evidence Verification Gate (wajib setelah tes fitur dan sebelum hasil dilaporkan ke tim atau klien)
Bukti yang dilampirkan belum cukup; **bukti itu sendiri harus diperiksa**. Lakukan semua langkah berikut untuk setiap fitur yang diuji:
1. **Kesegaran:** bukti berasal dari commit/build yang sama dengan yang akan dilaporkan atau diserahkan. Catat hash commit (`git rev-parse --short HEAD`) dan waktu run. Kode berubah setelah tes dijalankan = tes diulang; bukti lama tidak dipakai.
2. **Relevansi:** tuliskan apa yang dibuktikan oleh tiap bukti dan asersi atau langkah mana yang memeriksanya. Buka setiap screenshot dan trace: halaman benar, data benar, tampilan tidak rusak, dan kondisi yang diuji memang terlihat.
3. **Kontrol negatif:** untuk setiap tes baru atau yang diubah, rusak fiturnya secara sengaja (misalnya matikan validasi, hapus otorisasi, ubah rumus), buktikan tesnya **gagal**, kembalikan, lalu buktikan tesnya **lulus**. Tes yang belum pernah terbukti bisa gagal tidak dihitung sebagai bukti. Catat perubahan yang dipakai dan hasilnya.
4. **Kelengkapan keluaran:** laporkan angka nyata (dijalankan, lulus, gagal, dilewati, proyek/browser/viewport yang dipakai). Jangan memotong atau meringkas kegagalan; tes yang dilewati (`skip`) harus dijelaskan.
5. **Reproduksibilitas:** catat perintah, versi, data uji, dan lingkungan sehingga orang lain bisa mengulang dan mendapat hasil yang sama. Bukti dari data produksi atau lingkungan yang tidak bisa diulang ditandai sebagai tidak dapat direproduksi.
6. **Peninjau independen untuk risiko tinggi:** butir keamanan, uang, dan data pribadi diperiksa oleh pihak lain (QA Lead, rekan, atau sesi agen terpisah yang tidak menulis kodenya). Isi kolom **Terverifikasi oleh** dengan nama dan tanggal.
7. **Data diverifikasi:** data yang tampil dan alur datanya sudah diperiksa sesuai Data Verification Gates di Fase 4 (alur ujung ke ujung, dicocokkan dengan sumber independen, rekonsiliasi total). Hasil tes UI saja tidak membuktikan data benar.
Bukti yang gagal salah satu langkah di atas dilaporkan sebagai **"belum diverifikasi"**, bukan lulus.

### Format Laporan Bukti (dipakai di akhir Fase 4, 5, dan 6)
```text
Versi yang diuji: <hash commit / build>, <tanggal-waktu run>
Diverifikasi (dijalankan sekarang):
- <perintah> -> <hasil nyata: dijalankan/lulus/gagal/dilewati, kode keluar>  | bukti: <path laporan/screenshot/trace>
Verifikasi bukti:
- Kontrol negatif: <perubahan yang dipakai> -> <tes gagal> ; dikembalikan -> <tes lulus>
- Screenshot/trace diperiksa: <ya, yang dilihat> | Peninjau independen: <nama atau "tidak ada">
Verifikasi data:
- Alur data dilacak: <data uji> input -> simpan -> tampil -> ekspor | hasil: <cocok / selisih ...>
- Dicocokkan dengan sumber: <angka/status/format yang dibandingkan> vs <sumber independen> | selisih: <tidak ada / ...>
Gagal atau dilewati:
- <item> -> <pesan error / alasan dilewati>
Belum diverifikasi:
- <item> -> <alasan, mis. butuh akses produksi / tidak ada lingkungan uji>
Kesimpulan: <siap | siap dengan catatan | belum siap>, hanya berdasarkan bagian di atas
```
Dilarang memakai frasa "seharusnya sudah berfungsi", "kemungkinan lulus", atau "sudah diuji" tanpa menyertakan perintah dan hasilnya.
