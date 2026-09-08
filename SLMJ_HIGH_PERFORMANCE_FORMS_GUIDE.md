# Panduan Lengkap Implementasi Form Transaksi Kinerja Tinggi (React Hybrid) untuk SLMJ

Dokumen ini merupakan **cetak biru (blueprint) teknis dan panduan implementasi lengkap** untuk menyalin (*copy-paste*) arsitektur form transaksi berkinerja tinggi (**Purchase Order**, **Order Request**, **Quotation**, dan **Sales Order**) yang telah terbukti cepat, ringan, dan berstandar ERP enterprise (**NO AI Slop**) ke proyek **SLMJ**.

---

## DAFTAR ISI
1. [Latar Belakang & Arsitektur Solusi](#1-latar-belakang--arsitektur-solusi)
2. [Prasyarat & Konfigurasi Lingkungan (Vite & Dependencies)](#2-prasyarat--konfigurasi-lingkungan)
3. [Fondasi Komponen & Mesin Perhitungan Bersama (Shared Core)](#3-fondasi-komponen--mesin-perhitungan-bersama)
   - [SearchableSelect.tsx (Combobox Pintar & Cepat)](#31-searchableselecttsx)
   - [calculations.ts (Mesin Rumus Akuntansi Baku)](#32-calculationsts)
   - [Komponen Grand Total Transaksi ("NO AI Slop")](#33-komponen-grand-total-transaksi)
4. [Modul 1: Purchase Order (PO)](#4-modul-1-purchase-order-po)
5. [Modul 2: Order Request (OR)](#5-modul-2-order-request-or)
6. [Modul 3: Quotation (Penawaran Harga)](#6-modul-3-quotation-penawaran-harga)
7. [Modul 4: Sales Order (SO)](#7-modul-4-sales-order-so)
8. [Integrasi Backend Laravel (API Routes & Controllers)](#8-integrasi-backend-laravel)
9. [Integrasi Blade & Filament Mounting](#9-integrasi-blade--filament-mounting)
10. [Checklist Migrasi Bertahap ke SLMJ](#10-checklist-migrasi-bertahap-ke-slmj)

---

## 1. Latar Belakang & Arsitektur Solusi

### Masalah Utama pada Form Filament Tradisional (Livewire Repeater)
- **Latency & Roundtrip Tinggi**: Setiap kali pengguna menambah baris, mengubah kuantitas, harga, atau diskon, Livewire mengirimkan request AJAX utuh ke server dengan serialisasi ribuan baris DOM.
- **Lag & Freeze pada Data Besar**: Ketika transaksi mencapai 10-50+ item, formulir menjadi sangat berat (5-15 detik per ketikan/interaksi).
- **Redundansi & UI Clutter**: Repeater sering memuat field duplikat yang membingungkan staf gudang/keuangan.

### Arsitektur Hybrid React di SLMJ
```
┌────────────────────────────────────────────────────────┐
│               Laravel Filament Admin Shell             │
│  (Auth, Permissions, Navigation, Sidebar, Breadcrumbs) │
└──────────────────────────┬─────────────────────────────┘
                           │ Mounts Blade Template
                           ▼
┌────────────────────────────────────────────────────────┐
│     <div id="{module}-next-app" data-edit-id="...">    │
│  (Pure React 18/19 Client-Side Transaction Workspace)  │
│  - Keystroke tanpa latency (0ms input response)        │
│  - Live calculation (Subtotal, Diskon, DPP, PPN)       │
│  - Virtualized Combobox / Filter & Accordion           │
│  - Grand Total Transaksi baku akuntansi (NO AI Slop)  │
└──────────────────────────┬─────────────────────────────┘
                           │ Axios JSON REST API
                           ▼
┌────────────────────────────────────────────────────────┐
│               Laravel API v1 Controller                │
│  - GET  /dependencies  (Pre-cache master data)         │
│  - GET  /generate-no   (Auto-numbering real-time)      │
│  - POST /              (Atomic DB Transaction CRUD)    │
│  - PUT  /{id}          (Atomic DB Transaction Update)  │
└────────────────────────────────────────────────────────┘
```

---

## 2. Prasyarat & Konfigurasi Lingkungan

### 2.1 Install Dependencies di `package.json`
Jalankan di root folder SLMJ:
```bash
npm install react react-dom @vitejs/plugin-react axios lucide-react clsx tailwind-merge
npm install -D @types/react @types/react-dom
```

### 2.2 Konfigurasi `vite.config.js`
Pastikan plugin React terpasang di `vite.config.js`:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        cors: true,
    },
    build: {
        target: 'esnext',
    },
});
```

### 2.3 Konfigurasi CSS & Font Family (`resources/css/app.css`)
Kunci font family ke Inter dan aktifkan `tabular-nums` agar angka sejajar rapi:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
    html, body, .fi-body, .fi-main, .fi-page,
    #order-request-next-app,
    #purchase-order-next-app,
    #quotation-next-app,
    #sale-order-next-app,
    #order-request-next-app *,
    #purchase-order-next-app *,
    #quotation-next-app *,
    #sale-order-next-app * {
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    }

    .font-mono,
    [data-tabular-num],
    input[type="number"],
    .tabular-nums {
        font-variant-numeric: tabular-nums;
    }
}
```

### 2.4 Entry Point JS (`resources/js/app.js`)
```javascript
import './bootstrap';
import './components/OrderRequestForm';
import './components/PurchaseOrderForm';
import './components/QuotationForm';
import './components/SaleOrderForm';
```

---

## 3. Fondasi Komponen & Mesin Perhitungan Bersama (Shared Core)

### 3.1 `SearchableSelect.tsx`
Komponen dropdown combobox yang sangat cepat dengan pencarian instan, highlight badge SKU, sublabel harga/telepon, dan keyboard navigation:

Simpan di: `resources/js/components/OrderRequestForm/SearchableSelect.tsx` (atau `resources/js/components/Common/SearchableSelect.tsx`)

```tsx
import React, { useState, useRef, useEffect, useMemo } from 'react';
import { ChevronDown, Search, X } from 'lucide-react';

export interface SelectOption {
  value: string | number;
  label: string;
  sublabel?: string;
  badge?: string;
}

interface Props {
  options: SelectOption[];
  value: string | number | null | undefined;
  onChange: (val: string | number | null) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}

export const SearchableSelect: React.FC<Props> = ({
  options,
  value,
  onChange,
  placeholder = '-- Pilih Opsi --',
  disabled = false,
  className = '',
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const selectedOption = useMemo(
    () => options.find((opt) => String(opt.value) === String(value)),
    [options, value]
  );

  const filteredOptions = useMemo(() => {
    if (!search.trim()) return options;
    const q = search.toLowerCase();
    return options.filter(
      (opt) =>
        opt.label.toLowerCase().includes(q) ||
        (opt.sublabel && opt.sublabel.toLowerCase().includes(q)) ||
        (opt.badge && opt.badge.toLowerCase().includes(q))
    );
  }, [options, search]);

  useEffect(() => {
    const handleOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleOutside);
    return () => document.removeEventListener('mousedown', handleOutside);
  }, []);

  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    } else {
      setSearch('');
    }
  }, [isOpen]);

  return (
    <div ref={containerRef} className={`relative w-full ${className}`}>
      <div
        onClick={() => !disabled && setIsOpen(!isOpen)}
        className={`w-full min-h-[38px] px-3 py-1.5 bg-white border rounded-lg text-xs flex items-center justify-between cursor-pointer transition-colors shadow-2xs ${
          disabled
            ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
            : isOpen
            ? 'border-blue-500 ring-2 ring-blue-500/10'
            : 'border-gray-300 hover:border-gray-400 text-gray-900'
        }`}
      >
        <div className="flex-1 truncate mr-2">
          {selectedOption ? (
            <div className="flex items-center gap-1.5 truncate">
              {selectedOption.badge && (
                <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-700 rounded border border-gray-200 tabular-nums">
                  {selectedOption.badge}
                </span>
              )}
              <span className="truncate font-medium">{selectedOption.label}</span>
            </div>
          ) : (
            <span className="text-gray-400">{placeholder}</span>
          )}
        </div>
        <div className="flex items-center gap-1 text-gray-400 shrink-0">
          {value && !disabled && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                onChange(null);
              }}
              className="p-0.5 hover:text-gray-600 rounded"
            >
              <X className="w-3 h-3" />
            </button>
          )}
          <ChevronDown className={`w-3.5 h-3.5 transition-transform ${isOpen ? 'rotate-180 text-blue-600' : ''}`} />
        </div>
      </div>

      {isOpen && !disabled && (
        <div className="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden animate-in fade-in zoom-in-95 duration-100">
          <div className="p-2 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
            <Search className="w-3.5 h-3.5 text-gray-400 shrink-0" />
            <input
              ref={inputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Cari..."
              className="w-full bg-transparent text-xs text-gray-900 focus:outline-none placeholder-gray-400"
            />
          </div>

          <div className="max-h-56 overflow-y-auto divide-y divide-gray-50 p-1">
            {filteredOptions.length > 0 ? (
              filteredOptions.map((opt) => {
                const isSelected = String(opt.value) === String(value);
                return (
                  <div
                    key={String(opt.value)}
                    onClick={() => {
                      onChange(opt.value);
                      setIsOpen(false);
                    }}
                    className={`px-2.5 py-2 text-xs rounded-md cursor-pointer flex flex-col transition-colors ${
                      isSelected ? 'bg-blue-50 text-blue-900 font-semibold' : 'hover:bg-gray-100 text-gray-800'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate">{opt.label}</span>
                      {opt.badge && (
                        <span className="px-1.5 py-0.5 text-[10px] bg-gray-100 text-gray-600 rounded border border-gray-200 tabular-nums shrink-0">
                          {opt.badge}
                        </span>
                      )}
                    </div>
                    {opt.sublabel && <span className="text-[11px] text-gray-500 font-normal">{opt.sublabel}</span>}
                  </div>
                );
              })
            ) : (
              <div className="p-3 text-center text-xs text-gray-400">Tidak ada opsi ditemukan</div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
```

---

### 3.2 `calculations.ts` (Mesin Rumus Akuntansi Baku)
Mesin murni untuk menghitung Subtotal Kotor, Diskon, DPP, PPN Inklusif/Eksklusif, dan Grand Total.

Simpan di: `resources/js/components/PurchaseOrderForm/calculations.ts` (dan modul lainnya):

```typescript
export type TaxType = 'none' | 'inklusif' | 'eklusif' | string;

export interface ItemCalculationResult {
  grossTotal: number;
  discountNominal: number;
  afterDiscount: number;
  dpp: number;
  taxNominal: number;
  subtotal: number;
}

/**
 * Menghitung perincian finansial item transaksi
 */
export function hitungItemCalculations(
  quantity: number,
  unitPrice: number,
  discountPercent: number = 0,
  taxRate: number = 11,
  taxType: TaxType = 'eklusif'
): ItemCalculationResult {
  const qty = Math.max(0, Number(quantity) || 0);
  const price = Math.max(0, Number(unitPrice) || 0);
  const discPct = Math.min(100, Math.max(0, Number(discountPercent) || 0));
  const taxPct = Math.max(0, Number(taxRate) || 0);

  const grossTotal = Math.round(qty * price * 100) / 100;
  const discountNominal = Math.round(grossTotal * (discPct / 100) * 100) / 100;
  const afterDiscount = Math.max(0, grossTotal - discountNominal);

  let dpp = afterDiscount;
  let taxNominal = 0;
  let subtotal = afterDiscount;

  const normalizedTax = String(taxType).toLowerCase();

  if (normalizedTax === 'none' || taxPct === 0) {
    dpp = afterDiscount;
    taxNominal = 0;
    subtotal = afterDiscount;
  } else if (normalizedTax === 'inklusif') {
    // PPN Inklusif: Harga sudah termasuk pajak
    dpp = Math.round((afterDiscount / (1 + taxPct / 100)) * 100) / 100;
    taxNominal = Math.round((afterDiscount - dpp) * 100) / 100;
    subtotal = afterDiscount;
  } else {
    // PPN Eksklusif: Pajak ditambahkan di atas harga
    dpp = afterDiscount;
    taxNominal = Math.round(afterDiscount * (taxPct / 100) * 100) / 100;
    subtotal = Math.round((afterDiscount + taxNominal) * 100) / 100;
  }

  return {
    grossTotal,
    discountNominal,
    afterDiscount,
    dpp,
    taxNominal,
    subtotal,
  };
}

/**
 * Format Rupiah Indonesia standar (e.g. Rp 15.000.000,00)
 */
export function formatMoney(val: number | null | undefined, currencySymbol: string = 'Rp'): string {
  if (val === null || val === undefined || isNaN(val)) {
    return `${currencySymbol} 0,00`;
  }
  return (
    `${currencySymbol} ` +
    Number(val).toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}
```

---

### 3.3 Komponen Grand Total Transaksi ("NO AI Slop")
Struktur kartu rekapitulasi akuntansi resmi yang bersih, diletakkan di **kanan bawah tabel item**:

Simpan di: `resources/js/components/PurchaseOrderForm/PurchaseOrderGrandTotal.tsx`

```tsx
import React from 'react';
import { formatMoney } from './calculations';

interface SummaryData {
  totalItems: number;
  totalQuantity: number;
  totalGross: number;
  totalDiscount: number;
  totalDpp: number;
  totalTax: number;
  grandTotalIdr: number;
}

interface Props {
  summary: SummaryData;
}

export const PurchaseOrderGrandTotal: React.FC<Props> = ({ summary }) => {
  return (
    <div className="mt-6 flex flex-col md:flex-row justify-end">
      <div className="w-full md:w-96 bg-gray-50/75 border border-gray-200 rounded-xl p-4 space-y-2 text-xs">
        <div className="font-semibold text-gray-900 text-sm border-b border-gray-200 pb-2 flex items-center justify-between">
          <span>Ringkasan Transaksi</span>
          <span className="text-xs font-normal text-gray-500 tabular-nums">
            {summary.totalItems} item ({summary.totalQuantity.toLocaleString('id-ID')} qty)
          </span>
        </div>

        {/* Subtotal Kotor */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Subtotal (Total Kotor):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatMoney(summary.totalGross)}
          </span>
        </div>

        {/* Total Diskon (hanya jika ada) */}
        {summary.totalDiscount > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total Diskon:</span>
            <span className="font-medium text-red-600 tabular-nums">
              - {formatMoney(summary.totalDiscount)}
            </span>
          </div>
        )}

        {/* Dasar Pengenaan Pajak (DPP) */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Dasar Pengenaan Pajak (DPP):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatMoney(summary.totalDpp)}
          </span>
        </div>

        {/* Total PPN (hanya jika ada) */}
        {summary.totalTax > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total PPN:</span>
            <span className="font-medium text-blue-700 tabular-nums">
              + {formatMoney(summary.totalTax)}
            </span>
          </div>
        )}

        {/* Divider */}
        <div className="border-t border-gray-200 pt-2.5 flex items-baseline justify-between">
          <span className="font-bold text-gray-900 text-sm">Grand Total:</span>
          <span className="font-bold text-base text-gray-900 tabular-nums">
            {formatMoney(summary.grandTotalIdr)}
          </span>
        </div>
      </div>
    </div>
  );
};
```

---

### 3.4 Komponen Sticky Action Toolbar Bawah (Clean & Minimalis)
Pola bilah aksi melayang (*Sticky Action Toolbar*) yang bersih dan fungsional di bagian bawah formulir (`sticky bottom-4`). Seluruh metrik angka telah dipusatkan pada kartu Grand Total di atasnya, sehingga bar ini murni menangani status dokumen dan tombol aksi eksekusi:

Simpan di: `resources/js/components/PurchaseOrderForm/PurchaseOrderFloatingSummary.tsx` (sesuaikan untuk Quotation & Sales Order)

```tsx
import React from 'react';
import { Save, X, Loader2 } from 'lucide-react';

interface Props {
  summary?: { totalItems?: number };
  onSubmit: () => void;
  onCancel: () => void;
  isSubmitting?: boolean;
  isEditMode?: boolean;
}

export const PurchaseOrderFloatingSummary: React.FC<Props> = ({
  summary,
  onSubmit,
  onCancel,
  isSubmitting = false,
  isEditMode = false,
}) => {
  const isItemsEmpty = summary?.totalItems === 0;

  return (
    <div className="sticky bottom-4 z-20 mt-6 bg-white border border-gray-200 rounded-xl shadow-md p-4 flex items-center justify-end gap-3 transition-all">
      {/* Tombol Batal */}
      <button
        type="button"
        onClick={onCancel}
        disabled={isSubmitting}
        className="flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors disabled:opacity-50"
      >
        <X className="w-4 h-4" />
        <span>Batal</span>
      </button>

      {/* Tombol Submit / Simpan */}
      <button
        type="button"
        onClick={onSubmit}
        disabled={isSubmitting || isItemsEmpty}
        className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all"
      >
        {isSubmitting ? (
          <>
            <Loader2 className="w-4 h-4 animate-spin" />
            <span>Menyimpan...</span>
          </>
        ) : (
          <>
            <Save className="w-4 h-4" />
            <span>{isEditMode ? 'Perbarui PO' : 'Buat PO'}</span>
          </>
        )}
      </button>
    </div>
  );
};
```

---

## 4. Modul 1: Purchase Order (PO)

### 4.1 Struktur Direktori
```
resources/js/components/PurchaseOrderForm/
├── calculations.ts
├── index.tsx
├── PurchaseOrderApp.tsx
├── PurchaseOrderFloatingSummary.tsx
├── PurchaseOrderGrandTotal.tsx
├── PurchaseOrderHeaderForm.tsx
├── PurchaseOrderItemTable.tsx
├── PurchaseOrderToolbar.tsx
└── types.ts
```

### 4.2 Alur Logika Penting di PO
1. **Header Vendor & Cabang Tunggal**: PO hanya memiliki 1 Supplier dan 1 Cabang di header. Baris item tidak perlu memiliki dropdown vendor/cabang ganda.
2. **Selector Produk Penuh (`col-span-12`)**: Memberikan ruang lega untuk nama produk dan SKU.
3. **Referensi OR/SO**: Jika PO dibuat dari referensi Order Request, baris item terkunci otomatis (`Lock`) dan tombol hapus dibatasi sesuai sisa item OR.

### 4.3 Template Blade Mounting (`create-purchase-order.blade.php`)
```html
<x-filament-panels::page>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div id="purchase-order-next-app">
        {{-- Skeleton Loading Animasi Lembut --}}
        <div class="space-y-6 animate-pulse">
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-xs space-y-4">
                <div class="h-5 bg-gray-200 rounded w-1/4"></div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

### 4.4 Template Blade Edit (`edit-purchase-order.blade.php`)
```html
<x-filament-panels::page>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div id="purchase-order-next-app" data-edit-id="{{ $record->id }}">
        {{-- Skeleton Loading --}}
    </div>
</x-filament-panels::page>
```

---

## 5. Modul 2: Order Request (OR)

### 5.1 Karakteristik Penting di OR
- **Rekomendasi Supplier Termurah**: Di baris item Order Request, dropdown menampilkan supplier mitra terkait dengan badge "Termurah" otomatis berdasarkan harga beli terendah.
- **Multicurrency**: Mendukung item valas dengan konversi ke IDR real-time.
- **Approval Flow**: Mendukung status draft, approved, rejected pada baris item.

### 5.2 Komponen Grand Total OR (`OrderRequestGrandTotal.tsx`)
```tsx
import React from 'react';
import { OrderRequestSummary } from './types';
import { formatMoney } from './calculations';

export const OrderRequestGrandTotal: React.FC<{ summary: OrderRequestSummary }> = ({ summary }) => {
  const dpp = Math.max(0, summary.total_raw_amount - summary.total_discount);

  return (
    <div className="mt-6 flex flex-col md:flex-row justify-end">
      <div className="w-full md:w-96 bg-gray-50/75 border border-gray-200 rounded-xl p-4 space-y-2 text-xs">
        <div className="font-semibold text-gray-900 text-sm border-b border-gray-200 pb-2 flex items-center justify-between">
          <span>Ringkasan Transaksi</span>
          <span className="text-xs font-normal text-gray-500 tabular-nums">
            {summary.total_items} item ({summary.total_quantity} qty)
          </span>
        </div>
        <div className="flex items-center justify-between text-gray-600">
          <span>Subtotal (Total Kotor):</span>
          <span className="font-medium text-gray-900 tabular-nums">Rp {formatMoney(summary.total_raw_amount)}</span>
        </div>
        {summary.total_discount > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total Diskon:</span>
            <span className="font-medium text-red-600 tabular-nums">- Rp {formatMoney(summary.total_discount)}</span>
          </div>
        )}
        <div className="flex items-center justify-between text-gray-600">
          <span>Dasar Pengenaan Pajak (DPP):</span>
          <span className="font-medium text-gray-900 tabular-nums">Rp {formatMoney(dpp)}</span>
        </div>
        {summary.total_tax > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total PPN:</span>
            <span className="font-medium text-blue-700 tabular-nums">+ Rp {formatMoney(summary.total_tax)}</span>
          </div>
        )}
        <div className="border-t border-gray-200 pt-2.5 flex items-baseline justify-between">
          <span className="font-bold text-gray-900 text-sm">Grand Total:</span>
          <span className="font-bold text-base text-gray-900 tabular-nums">Rp {formatMoney(summary.grand_subtotal)}</span>
        </div>
      </div>
    </div>
  );
};
```

---

## 6. Modul 3: Quotation (Penawaran Harga)

### 6.1 Karakteristik Penting di Quotation
- **Mata Uang Asing & Setara IDR**: Jika quotation dibuat dalam USD/EUR/SGD, Grand Total menampilkan mata uang asli sekaligus nilai konversi Rupiah berdasarkan kurs:
```tsx
{isForeignCurrency && (
  <span className="text-[11px] text-gray-500 block">
    Setara: {formatCurrency(summary.grand_total_idr, 'Rp')}
  </span>
)}
```
- **Aksi Massal Pajak & Diskon**: Toolbar memiliki fitur apply tax (None/Inklusif/Eksklusif) dan diskon sekaligus ke seluruh item.

---

## 7. Modul 4: Sales Order (SO)

### 7.1 Karakteristik Penting di SO
- **1-Click Import Approved Quotation**: Ketika pengguna memilih opsi "Referensi Quotation", seluruh data Customer, Cabang, Mata Uang, dan Item otomatis ditarik dari Quotation tanpa ketik ulang.
- **Pengecekan Plafon Piutang (Credit Limit)**: Menampilkan sisa limit kredit customer secara real-time.
- **Pengecekan Stok Bebas (Free Stock)**: Menampilkan stok fisik yang belum terikat SO lain.

---

## 8. Integrasi Backend Laravel

### 8.1 Format Rute API (`routes/api.php`)
Tambahkan grup API v1 di SLMJ:
```php
Route::prefix('v1')->middleware(['web', 'auth'])->group(function () {
    // Purchase Orders
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/dependencies', [PurchaseOrderApiController::class, 'dependencies']);
        Route::get('/reference-items', [PurchaseOrderApiController::class, 'referenceItems']);
        Route::get('/generate-number', [PurchaseOrderApiController::class, 'generateNumber']);
        Route::post('/', [PurchaseOrderApiController::class, 'store']);
        Route::get('/{id}', [PurchaseOrderApiController::class, 'show']);
        Route::put('/{id}', [PurchaseOrderApiController::class, 'update']);
    });

    // Order Requests
    Route::prefix('order-requests')->group(function () {
        Route::get('/dependencies', [OrderRequestApiController::class, 'dependencies']);
        Route::get('/generate-number', [OrderRequestApiController::class, 'generateNumber']);
        Route::post('/', [OrderRequestApiController::class, 'store']);
        Route::get('/{id}', [OrderRequestApiController::class, 'show']);
        Route::put('/{id}', [OrderRequestApiController::class, 'update']);
    });

    // Quotations
    Route::prefix('quotations')->group(function () {
        Route::get('/dependencies', [QuotationApiController::class, 'dependencies']);
        Route::get('/generate-number', [QuotationApiController::class, 'generateNumber']);
        Route::post('/', [QuotationApiController::class, 'store']);
        Route::get('/{id}', [QuotationApiController::class, 'show']);
        Route::put('/{id}', [QuotationApiController::class, 'update']);
    });

    // Sales Orders
    Route::prefix('sales-orders')->group(function () {
        Route::get('/dependencies', [SaleOrderApiController::class, 'dependencies']);
        Route::get('/generate-number', [SaleOrderApiController::class, 'generateNumber']);
        Route::get('/quotation/{id}', [SaleOrderApiController::class, 'getQuotation']);
        Route::get('/customer-credit/{id}', [SaleOrderApiController::class, 'getCustomerCredit']);
        Route::post('/', [SaleOrderApiController::class, 'store']);
        Route::get('/{id}', [SaleOrderApiController::class, 'show']);
        Route::put('/{id}', [SaleOrderApiController::class, 'update']);
    });
});
```

### 8.2 Tiga Aturan Emas Controller `dependencies()` Bebas N+1 Query
Untuk menjamin form transaksi di SLMJ selalu terbuka dalam < 50ms:
1. **Ambil Tarif Pajak 1x di Luar Loop Produk**: Jangan panggil helper/kueri pajak di dalam `->map()`.
2. **Filter Produk Aktif Saja**: Tambahkan `->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))`.
3. **Hitung Kredit Limit Secara On-Demand**: Jangan hitung piutang/overdue invoice seluruh customer di `dependencies()`. Cukup hitung ketika customer dipilih via `getCustomerCredit($id)`.

Contoh implementasi controller `dependencies()` yang efisien:
```php
public function dependencies(Request $request): JsonResponse
{
    $cabangs = Cabang::select('id', 'kode', 'nama')->orderBy('nama')->get();
    $suppliers = Supplier::select('id', 'code', 'perusahaan', 'phone')->orderBy('perusahaan')->get();
    $currencies = Currency::select('id', 'code', 'symbol', 'to_rupiah')->get();
    
    // Aturan 1: Ambil tarif pajak aktif 1x di luar loop
    $activeTaxRate = (float) (\App\Models\TaxSetting::activeRate('PPN') ?? 11.0);

    // Aturan 2: Filter produk aktif
    $products = Product::withoutGlobalScope('product_cabang')
        ->where(function ($q) {
            $q->whereNull('is_active')->orWhere('is_active', true);
        })
        ->select('id', 'sku', 'name', 'cost_price', 'uom_id', 'pajak')
        ->with('uom:id,name,abbreviation')
        ->orderBy('name')
        ->get()
        ->map(function ($p) use ($activeTaxRate) {
            $productTax = (float) ($p->pajak ?? 0);
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'cost_price' => (float) $p->cost_price,
                'uom' => $p->uom?->abbreviation ?? $p->uom?->name ?? 'PCS',
                'default_tax_rate' => $productTax > 0 ? $productTax : $activeTaxRate,
            ];
        });

    return response()->json([
        'success' => true,
        'data' => [
            'next_po_number' => PurchaseOrder::generateNextNumber(),
            'default_order_date' => now()->format('Y-m-d'),
            'default_currency_id' => 1,
            'cabangs' => $cabangs,
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'products' => $products,
        ]
    ]);
}
```

---

## 9. Integrasi Blade & Filament Mounting

### 9.1 Mekanisme Mounting Lifecycle (`index.tsx`)
Agar komponen React tetap me-mount dengan sempurna saat navigasi halaman Filament (Livewire SPA mode):
```tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { PurchaseOrderApp } from './PurchaseOrderApp';

export function mountPurchaseOrderApp() {
  const container = document.getElementById('purchase-order-next-app');
  if (container && !(container as any)._reactRoot) {
    const editIdAttr = container.getAttribute('data-edit-id');
    const editId = editIdAttr ? parseInt(editIdAttr, 10) : undefined;

    const root = createRoot(container);
    (container as any)._reactRoot = root;
    root.render(
      <React.StrictMode>
        <PurchaseOrderApp editId={editId} />
      </React.StrictMode>
    );
  }
}

// Jalankan pada load awal maupun navigasi Livewire
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountPurchaseOrderApp);
  } else {
    mountPurchaseOrderApp();
  }
  document.addEventListener('livewire:navigated', mountPurchaseOrderApp);
}
```

---

## 10. Checklist Migrasi Bertahap ke SLMJ

Gunakan urutan ini agar proses penyalinan ke SLMJ berjalan lancar tanpa error:

1. [ ] **Langkah 1 (NPM)**: Jalankan instalasi dependensi React dan Lucide icons di folder SLMJ.
2. [ ] **Langkah 2 (Vite)**: Tambahkan `@vitejs/plugin-react` pada `vite.config.js` SLMJ.
3. [ ] **Langkah 3 (CSS)**: Salin aturan font Inter dan tabular-nums pada `resources/css/app.css`.
4. [ ] **Langkah 4 (Shared Core)**: Salin folder `resources/js/components/OrderRequestForm/SearchableSelect.tsx` dan helper perhitungan ke SLMJ.
5. [ ] **Langkah 5 (Modul Modul Transaksi)**: Salin folder form modul terkait:
   - `resources/js/components/PurchaseOrderForm/`
   - `resources/js/components/OrderRequestForm/`
   - `resources/js/components/QuotationForm/`
   - `resources/js/components/SaleOrderForm/`
6. [ ] **Langkah 6 (Backend API)**: Buat Controller API di `app/Http/Controllers/Api/` dan daftarkan rute di `routes/api.php`.
7. [ ] **Langkah 7 (Blade View)**: Buat file custom blade di Filament Resources SLMJ (e.g. `create-purchase-order.blade.php`) dan arahkan method `getView()` pada kelas Page Filament.
8. [ ] **Langkah 8 (Build & Test)**: Jalankan `npm run build` dan `php artisan test` untuk memastikan 100% build lulus hijau.

---
*Dokumen ini dirancang khusus untuk tim pengembang SLMJ sebagai panduan salin-rekat (*copy-paste*) standar industri dengan performa optimal dan zero AI-slop.*
