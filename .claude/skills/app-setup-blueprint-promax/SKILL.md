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

Every phase, artifact, new feature, and refactored module must strictly obey these six paradigms:

1. **Ponytail (Modular Context & State Binding):** Organize code into clean, unidirectional dependency chains. Separate UI presentation, domain state/hooks, API service layers, and validation schemas. Never mix database/API logic directly inside view markup.
2. **Antislop (Zero AI-Slop Policy):** Strictly forbid lazy placeholders (`// TODO`, `// rest of code here`, `...`), mock unimplemented handlers, or generic `lorem ipsum` data. Every function, migration, component, and test must be complete, domain-specific, and production-ready.
3. **UI/UX ProMax (Tailwind CSS Design System):** Build high-craft, responsive interfaces using Tailwind CSS. Enforce semantic color tokens (`primary`, `emerald` for success, `amber` for warning, `rose`/`red` for danger), consistent spacing/typography scales, focus-visible accessibility rings, skeleton loading states, and informative empty states.
4. **Graphify (Architecture & Entity Graph Mapping):** Represent all data models, service dependencies, and complex transactional workflows using structured Mermaid diagrams (`erDiagram`, `sequenceDiagram`, `graph TD`) before writing or modifying implementation code.
5. **No Happy Path (Defensive & Edge-Case Engineering):** Never assume network requests, database writes, or user inputs succeed. Engineer explicitly for timeouts, `400/401/403/404/409/422/500` errors, race conditions, rapid double-clicks, expired auth sessions, malformed payloads, and empty datasets.
6. **Verification Data (Two-Way Schema Integrity):** Enforce strict schema validation on both client forms and server endpoints (e.g., Zod, Joi, Valibot, FormRequest). Sanitize all query parameters and verify actual database state persistence after mutations.

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

## 7-Phase Full-Cycle SDLC Workflow

### Phase 0: Project Mode Detection & Existing Codebase Audit (Brownfield Mode)
First, check `docs/sdlc/sdlc-config.json` (if generated by the CLI) or ask whether the project is **Greenfield (Setup Baru)** or **Brownfield (Melanjutkan Project)**.

If continuing an existing project (**Brownfield Mode**), execute this mandatory **4-Step Technical Audit** before writing any new code:
1. **Architecture & Dependency Scan:** Inspect `package.json` / `composer.json` / `go.mod`, directory structure, routing patterns, database migrations/schema, and current state management.
2. **Standards Compliance Gap Analysis:** Scan existing modules against the Mandatory Standards and generate a **Technical Debt & Compliance Report** (unpaginated tables, native `<select>` elements, double-alert risks, missing confirmation modals).
3. **Regression & Impact Mapping (Graphify):** Map how the requested new feature or bugfix interacts with existing tables, API endpoints, and shared components so existing production features do not break.
4. **Progressive Refactoring Strategy ("Boy Scout Rule"):** Inject missing core primitives (`AlertManager`, `ConfirmModal`, `AsyncSearchSelect`, `DataTable`) as shared components, enforce 100% strict compliance on all **new features**, and progressively upgrade **existing modules** touched during the current task.

### Phase 1: Discovery Questionnaire & PRD / Delta-PRD Management
- **Mode A - Ingesti PRD Utuh:** Bedah PRD klien dan lakukan *Gap & No-Happy-Path Audit*.
- **Mode B - Buat PRD Baru:** Susun PRD enterprise dari nol lengkap dengan RBAC, User Stories, dan *Matriks Kepatuhan Standar*.
- **Mode C - Delta-PRD & Change Request (Khusus Melanjutkan Project):** Susun spesifikasi perubahan yang memetakan *Existing vs. Target State*, *Safe Database Schema Diff (Zero Data-Loss)*, dan *Regression Guardrails*.

### Phase 2: Architecture Blueprint (Graphify) & Execution Roadmap
Hasilkan diagram Mermaid (`erDiagram`, `sequenceDiagram`, `graph TD`) beserta *Milestone Roadmap* bertahap.

### Phase 3: Design System & Reusable Core Components
Bangun atau pasang 4 komponen fondasi berbasis Tailwind CSS yang tersedia di `docs/sdlc/ui-standards-reference/` (`DataTablePagination.tsx`, `AsyncSearchSelect.tsx`, `AlertContainer.tsx` + `alert-manager.ts`, dan `ConfirmModal.tsx`) secara utuh (*Antislop*) dan terikat rapi (*Ponytail*).

### Phase 4: Feature Implementation & Defensive Coding
Bangun atau perbarui modul aplikasi sesuai urutan *Roadmap* dengan mematuhi aturan *Server-side Pagination/Lazy Loading*, *Searchable Dropdown*, *Two-Way Data Verification*, dan *No Happy Path*.

### Phase 5: Edge-Case Hardening & Regression Verification
Uji paksa terhadap *race condition* (anti-*double submit* & anti-*double alert*), input ekstrem, serta pastikan kompatibilitas data lama (*legacy data*) pada project lanjutan.

### Phase 6: Automated Playwright Browser Testing, QA & UAT
Hasilkan skrip **Playwright E2E & Regression (`*.spec.ts`)**, **QA Test Case Matrix**, dan dokumen **UAT Sign-off**.
