'use client';

import React from 'react';
import {
  OrderRequestItemRow,
  FormDependencies,
  TaxType,
} from '@/types/order-request';
import { formatMoney } from '@/lib/calculations';
import {
  Trash2,
  Copy,
  Sparkles,
  CheckCircle2,
} from 'lucide-react';

interface Props {
  item: OrderRequestItemRow;
  index: number;
  dependencies: FormDependencies | null;
  errors: Record<string, string[]>;
  onUpdate: (field: keyof OrderRequestItemRow, value: string | number | null | TaxType) => void;
  onRemove: () => void;
  onDuplicate: () => void;
  onApplyRecommendedSupplier: () => void;
  canRemove: boolean;
}

export const OrderRequestItemCard: React.FC<Props> = ({
  item,
  index,
  dependencies,
  errors,
  onUpdate,
  onRemove,
  onDuplicate,
  onApplyRecommendedSupplier,
  canRemove,
}) => {
  const currentCurrency = dependencies?.currencies.find((c) => c.id === item.currency_id);
  const currencySymbol = currentCurrency?.symbol || 'Rp';

  const selectedProduct = dependencies?.products.find((p) => p.id === item.product_id);

  const itemErrorPrefix = `items.${index}`;
  const productError = errors[`${itemErrorPrefix}.product_id`];
  const cabangError = errors[`${itemErrorPrefix}.cabang_id`];
  const qtyError = errors[`${itemErrorPrefix}.quantity`];

  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm hover:border-slate-300 transition-all mb-4 overflow-hidden">
      {/* Top Bar of Item Card */}
      <div className="bg-slate-50/80 px-4 py-3 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2.5">
          <span className="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold shadow-sm">
            {index + 1}
          </span>
          <span className="text-sm font-semibold text-slate-800">
            {selectedProduct ? (
              <>
                <span className="text-slate-500 font-mono text-xs mr-1.5">[{selectedProduct.sku}]</span>
                {selectedProduct.name}
              </>
            ) : (
              <span className="text-slate-400 font-normal italic">Pilih produk untuk baris ini...</span>
            )}
          </span>
        </div>

        <div className="flex items-center gap-3">
          {/* Live Top Subtotal Pill */}
          <div className="text-right">
            <span className="text-[11px] uppercase tracking-wider text-slate-400 font-bold block">Subtotal</span>
            <span className="text-sm font-bold font-mono text-blue-700">
              {formatMoney(item.subtotal, currencySymbol)}
            </span>
          </div>

          <div className="h-6 w-px bg-slate-200 mx-1 hidden sm:block" />

          {/* Action buttons */}
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={onDuplicate}
              title="Duplikasi Item"
              className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
            >
              <Copy className="w-4 h-4" />
            </button>
            {canRemove && (
              <button
                type="button"
                onClick={onRemove}
                title="Hapus Item"
                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Main Form Fields */}
      <div className="p-4 sm:p-5 space-y-4">
        {/* Row 1: Produk, Satuan (Live Text), Quantity, Cabang */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 sm:gap-4 items-start">
          {/* Produk Select */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Produk <span className="text-rose-500">*</span>
            </label>
            <div className="relative">
              <select
                value={item.product_id || ''}
                onChange={(e) => onUpdate('product_id', e.target.value ? Number(e.target.value) : null)}
                className={`w-full px-3 py-2 text-sm border rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors ${
                  productError ? 'border-rose-400 text-rose-800' : 'border-slate-300 text-slate-900'
                }`}
              >
                <option value="">-- Pilih Produk --</option>
                {dependencies?.products.map((prod) => (
                  <option key={prod.id} value={prod.id}>
                    ({prod.sku}) {prod.name}
                  </option>
                ))}
              </select>
            </div>
            {productError && <p className="mt-1 text-xs text-rose-600">{productError[0]}</p>}
          </div>

          {/* Satuan: LIVE BADGE (No Disabled Input) */}
          <div className="md:col-span-1">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Satuan
            </label>
            <div className="h-[38px] flex items-center justify-center px-2.5 py-1 bg-slate-100/80 border border-slate-200 rounded-lg text-xs font-bold font-mono text-slate-700 tracking-wider">
              {item.unit || '-'}
            </div>
          </div>

          {/* Quantity Input */}
          <div className="md:col-span-2">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Qty <span className="text-rose-500">*</span>
            </label>
            <div className="relative">
              <input
                type="number"
                step="any"
                min="0.01"
                value={item.quantity === 0 ? '' : item.quantity}
                onChange={(e) => onUpdate('quantity', parseFloat(e.target.value) || 0)}
                placeholder="0"
                className={`w-full px-3 py-2 text-sm font-mono border rounded-lg bg-white text-right focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors ${
                  qtyError ? 'border-rose-400 text-rose-800' : 'border-slate-300 text-slate-900'
                }`}
              />
            </div>
            {qtyError && <p className="mt-1 text-xs text-rose-600">{qtyError[0]}</p>}
          </div>

          {/* Cabang Tujuan */}
          <div className="md:col-span-4">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Cabang Tujuan <span className="text-rose-500">*</span>
            </label>
            <select
              value={item.cabang_id || ''}
              onChange={(e) => onUpdate('cabang_id', e.target.value ? Number(e.target.value) : null)}
              className={`w-full px-3 py-2 text-sm border rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors ${
                cabangError ? 'border-rose-400 text-rose-800' : 'border-slate-300 text-slate-900'
              }`}
            >
              <option value="">-- Pilih Cabang --</option>
              {dependencies?.cabangs.map((c) => (
                <option key={c.id} value={c.id}>
                  ({c.kode}) {c.nama}
                </option>
              ))}
            </select>
            {cabangError && <p className="mt-1 text-xs text-rose-600">{cabangError[0]}</p>}
          </div>
        </div>

        {/* Row 2: Supplier, Smart Recommendation Chip, Currency */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 sm:gap-4 items-center pt-2 border-t border-slate-100">
          {/* Supplier Select */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Supplier (Pemasok)
            </label>
            <select
              value={item.supplier_id || ''}
              onChange={(e) => onUpdate('supplier_id', e.target.value ? Number(e.target.value) : null)}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors"
            >
              <option value="">-- Tanpa Supplier Tertentu --</option>
              {dependencies?.suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  ({s.code}) {s.perusahaan}
                </option>
              ))}
            </select>
          </div>

          {/* Smart Recommendation Chip: LIVE WIDGET */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1">
              <Sparkles className="w-3.5 h-3.5 text-amber-500" />
              Rekomendasi Supplier Termurah
            </label>
            {item.recommended_supplier ? (
              <div className="flex items-center justify-between px-3 py-1.5 bg-emerald-50/80 border border-emerald-200/80 rounded-lg text-xs">
                <div className="truncate pr-2">
                  <span className="font-semibold text-emerald-900">{item.recommended_supplier.perusahaan}</span>
                  <span className="text-emerald-700 font-mono ml-1.5">
                    ({formatMoney(item.recommended_supplier.price, currencySymbol)})
                  </span>
                </div>
                {item.supplier_id !== item.recommended_supplier.id && (
                  <button
                    type="button"
                    onClick={onApplyRecommendedSupplier}
                    className="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded shadow-sm transition-colors"
                  >
                    <CheckCircle2 className="w-3 h-3" />
                    Terapkan
                  </button>
                )}
              </div>
            ) : (
              <div className="px-3 py-2 bg-slate-50 border border-slate-200/60 rounded-lg text-xs text-slate-400 italic">
                Pilih produk untuk melihat rekomendasi supplier.
              </div>
            )}
          </div>

          {/* Mata Uang Item */}
          <div className="md:col-span-2">
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Valas Item
            </label>
            <select
              value={item.currency_id}
              onChange={(e) => onUpdate('currency_id', Number(e.target.value))}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors"
            >
              {dependencies?.currencies.map((curr) => (
                <option key={curr.id} value={curr.id}>
                  {curr.code} ({curr.symbol})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Row 3: Pricing Cards & Inputs */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 p-3.5 bg-slate-50/60 rounded-xl border border-slate-200/80">
          {/* Master Price: LIVE TYPOGRAPHY (No Disabled Input) */}
          <div className="bg-white p-3 rounded-lg border border-slate-200/80 shadow-2xs">
            <span className="text-[11px] font-semibold text-slate-500 block mb-0.5">Harga Master</span>
            <span className="text-sm font-bold font-mono text-slate-700 block truncate">
              {formatMoney(item.original_price, currencySymbol)}
            </span>
            <span className="text-[10px] text-slate-400 block mt-0.5">Patokan database</span>
          </div>

          {/* Override Price Input */}
          <div className="bg-white p-3 rounded-lg border border-slate-200/80 shadow-2xs">
            <label className="block text-[11px] font-semibold text-slate-700 mb-1">
              Harga Satuan <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex rounded-md">
              <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-slate-300 bg-slate-50 text-slate-500 text-xs font-mono">
                {currencySymbol}
              </span>
              <input
                type="number"
                step="any"
                min="0"
                value={item.unit_price === 0 ? '' : item.unit_price}
                onChange={(e) => onUpdate('unit_price', parseFloat(e.target.value) || 0)}
                placeholder="0"
                className="w-full px-2 py-1 text-sm font-mono border border-slate-300 rounded-r-md text-right focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          {/* Diskon % & LIVE DISCOUNT TAG */}
          <div className="bg-white p-3 rounded-lg border border-slate-200/80 shadow-2xs">
            <label className="block text-[11px] font-semibold text-slate-700 mb-1 flex items-center justify-between">
              <span>Diskon (%)</span>
              {item.discount > 0 && (
                <span className="text-[10px] font-mono font-semibold text-amber-600 bg-amber-50 px-1.5 rounded">
                  -{formatMoney(item.discount_nominal, currencySymbol)}
                </span>
              )}
            </label>
            <div className="relative flex rounded-md">
              <input
                type="number"
                step="any"
                min="0"
                max="100"
                value={item.discount === 0 ? '' : item.discount}
                onChange={(e) => onUpdate('discount', parseFloat(e.target.value) || 0)}
                placeholder="0"
                className="w-full px-2 py-1 text-sm font-mono border border-slate-300 rounded-l-md text-right focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
              <span className="inline-flex items-center px-2 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 text-slate-500 text-xs">
                %
              </span>
            </div>
          </div>

          {/* Total Kotor (Qty x Price): LIVE METRIC */}
          <div className="bg-white p-3 rounded-lg border border-slate-200/80 shadow-2xs">
            <span className="text-[11px] font-semibold text-slate-500 block mb-0.5">Total Kotor (Qty × Harga)</span>
            <span className="text-sm font-bold font-mono text-slate-800 block truncate">
              {formatMoney(item.total_cost, currencySymbol)}
            </span>
            <span className="text-[10px] text-slate-400 block mt-0.5">Sebelum diskon & pajak</span>
          </div>
        </div>

        {/* Row 4: Tipe Pajak Radio, Tax Chip (Live Text), Highlighted Subtotal Card */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4 items-center">
          {/* Tipe Pajak Radio Options */}
          <div className="lg:col-span-5">
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Tipe Pajak
            </label>
            <div className="flex items-center gap-2">
              {[
                { value: 'none', label: 'Non PPN (0%)' },
                { value: 'eklusif', label: 'PPN Excluded (+11%)' },
                { value: 'inklusif', label: 'PPN Included' },
              ].map((opt) => (
                <label
                  key={opt.value}
                  className={`flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs font-medium cursor-pointer transition-all ${
                    item.tipe_pajak === opt.value
                      ? 'bg-blue-50/80 border-blue-500 text-blue-700 font-semibold shadow-2xs'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <input
                    type="radio"
                    name={`tipe_pajak_${item.rowId}`}
                    value={opt.value}
                    checked={item.tipe_pajak === opt.value}
                    onChange={() => onUpdate('tipe_pajak', opt.value as TaxType)}
                    className="sr-only"
                  />
                  {opt.label}
                </label>
              ))}
            </div>
          </div>

          {/* Live Tax Breakdown Chip */}
          <div className="lg:col-span-3">
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Pajak Terhitung
            </label>
            <div className="flex items-center justify-between px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
              <span className="font-semibold text-slate-600">Rate: {item.tax}%</span>
              <span className="font-mono font-bold text-blue-600">
                +{formatMoney(item.tax_nominal, currencySymbol)}
              </span>
            </div>
          </div>

          {/* Subtotal Highlight Box */}
          <div className="lg:col-span-4 bg-gradient-to-r from-blue-50 to-indigo-50/60 p-3 rounded-xl border border-blue-200/80 flex items-center justify-between">
            <div>
              <span className="text-[11px] uppercase tracking-wider text-blue-800/80 font-bold block">
                Subtotal Akhir Item
              </span>
              <span className="text-[10px] text-blue-600">Setelah diskon & pajak</span>
            </div>
            <div className="text-right">
              <span className="text-base font-extrabold font-mono text-blue-800">
                {formatMoney(item.subtotal, currencySymbol)}
              </span>
            </div>
          </div>
        </div>

        {/* Row 5: Catatan Baris */}
        <div>
          <input
            type="text"
            value={item.note}
            onChange={(e) => onUpdate('note', e.target.value)}
            placeholder="Catatan khusus baris produk ini (opsional)..."
            className="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors"
          />
        </div>
      </div>
    </div>
  );
};
