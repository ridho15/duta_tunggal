import React, { useMemo } from 'react';
import {
  OrderRequestItemRow,
  FormDependencies,
  TaxType,
} from './types';
import { formatMoney } from './calculations';
import { SearchableSelect, SelectOption } from './SearchableSelect';
import { Trash2, Copy, Sparkles, Check } from 'lucide-react';

interface Props {
  item: OrderRequestItemRow;
  index: number;
  dependencies: FormDependencies | null;
  errors: Record<string, string[]>;
  canRemove: boolean;
  onUpdate: (field: keyof OrderRequestItemRow, value: string | number | null | TaxType) => void;
  onRemove: () => void;
  onDuplicate: () => void;
  onApplyRecommendedSupplier: () => void;
}

export const OrderRequestItemCard: React.FC<Props> = ({
  item,
  index,
  dependencies,
  errors,
  canRemove,
  onUpdate,
  onRemove,
  onDuplicate,
  onApplyRecommendedSupplier,
}) => {
  const currentCurrency = dependencies?.currencies.find((c) => c.id === item.currency_id);
  const currencySymbol = currentCurrency?.symbol || 'Rp';

  const selectedProduct = dependencies?.products.find((p) => p.id === item.product_id);

  const itemErrorPrefix = `items.${index}`;
  const productError = errors[`${itemErrorPrefix}.product_id`];
  const cabangError = errors[`${itemErrorPrefix}.cabang_id`];
  const qtyError = errors[`${itemErrorPrefix}.quantity`];

  // Product select options for SearchableSelect
  const productOptions: SelectOption[] = useMemo(() => {
    if (!dependencies?.products) return [];
    return dependencies.products.map((p) => ({
      value: p.id,
      label: p.name,
      badge: p.sku,
      sublabel: p.uom,
    }));
  }, [dependencies?.products]);

  // Supplier select options for SearchableSelect
  const supplierOptions: SelectOption[] = useMemo(() => {
    if (!dependencies?.suppliers) return [];
    return dependencies.suppliers.map((s) => ({
      value: s.id,
      label: s.perusahaan,
      badge: s.code,
    }));
  }, [dependencies?.suppliers]);

  // Cabang select options for SearchableSelect
  const cabangOptions: SelectOption[] = useMemo(() => {
    if (!dependencies?.cabangs) return [];
    return dependencies.cabangs.map((c) => ({
      value: c.id,
      label: c.nama,
      badge: c.kode,
    }));
  }, [dependencies?.cabangs]);

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-xs hover:border-gray-300 transition-all overflow-hidden mb-4">
      {/* Top Bar of Item Card (Clean Light Mode, No Subtotal Duplication) */}
      <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between gap-2">
        <div className="flex items-center gap-2.5">
          <span className="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold shadow-xs">
            {index + 1}
          </span>
          <span className="text-sm font-semibold text-gray-800">
            {selectedProduct ? (
              <>
                <span className="text-gray-500 font-mono text-xs mr-1.5 font-bold">
                  [{selectedProduct.sku}]
                </span>
                {selectedProduct.name}
              </>
            ) : (
              <span className="text-gray-400 font-normal italic">
                Pilih produk untuk baris ini...
              </span>
            )}
          </span>
        </div>

        {/* Action buttons */}
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={onDuplicate}
            title="Duplikasi Item"
            className="p-1.5 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
          >
            <Copy className="w-4 h-4" />
          </button>
          {canRemove && (
            <button
              type="button"
              onClick={onRemove}
              title="Hapus Item"
              className="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Main Form Fields */}
      <div className="p-4 sm:p-5 space-y-4">
        {/* Row 1: Produk (Searchable), Satuan (Live Pill), Quantity, Cabang */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 sm:gap-4 items-start">
          {/* Produk Select with Searchable Combobox */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Produk <span className="text-rose-500">*</span>
            </label>
            <SearchableSelect
              options={productOptions}
              value={item.product_id}
              placeholder="-- Cari Produk / SKU --"
              searchPlaceholder="Ketik nama atau SKU produk..."
              hasError={Boolean(productError)}
              onChange={(val) => onUpdate('product_id', val ? Number(val) : null)}
            />
            {productError && (
              <p className="mt-1 text-xs text-rose-600">{productError[0]}</p>
            )}
          </div>

          {/* Satuan: LIVE BADGE PILL (Clean 38px Standard) */}
          <div className="md:col-span-1">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Satuan
            </label>
            <div className="h-[38px] flex items-center justify-center px-2.5 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold font-mono text-gray-700 tracking-wider">
              {item.unit || '-'}
            </div>
          </div>

          {/* Quantity Input */}
          <div className="md:col-span-2">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Qty <span className="text-rose-500">*</span>
            </label>
            <input
              type="number"
              step="any"
              min="0.01"
              value={item.quantity === 0 ? '' : item.quantity}
              onChange={(e) => onUpdate('quantity', parseFloat(e.target.value) || 0)}
              placeholder="0"
              className={`w-full h-[38px] px-3 text-sm font-mono border rounded-lg bg-white text-gray-900 text-right focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors shadow-xs ${
                qtyError
                  ? 'border-rose-400 text-rose-800'
                  : 'border-gray-300'
              }`}
            />
            {qtyError && (
              <p className="mt-1 text-xs text-rose-600">{qtyError[0]}</p>
            )}
          </div>

          {/* Cabang Tujuan */}
          <div className="md:col-span-4">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Cabang Tujuan <span className="text-rose-500">*</span>
            </label>
            <SearchableSelect
              options={cabangOptions}
              value={item.cabang_id}
              placeholder="-- Pilih Cabang --"
              searchPlaceholder="Cari cabang..."
              hasError={Boolean(cabangError)}
              onChange={(val) => onUpdate('cabang_id', val ? Number(val) : null)}
            />
            {cabangError && (
              <p className="mt-1 text-xs text-rose-600">{cabangError[0]}</p>
            )}
          </div>
        </div>

        {/* Row 2: Supplier, Smart Recommendation Chip, Currency */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 sm:gap-4 items-center pt-2 border-t border-gray-100">
          {/* Supplier Select */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Supplier (Pemasok)
            </label>
            <SearchableSelect
              options={supplierOptions}
              value={item.supplier_id}
              placeholder="-- Tanpa Supplier Tertentu --"
              searchPlaceholder="Cari supplier..."
              onChange={(val) => onUpdate('supplier_id', val ? Number(val) : null)}
            />
          </div>

          {/* Smart Recommendation Chip: LIVE WIDGET */}
          <div className="md:col-span-5">
            <label className="block text-xs font-semibold text-gray-700 mb-1 flex items-center gap-1">
              <Sparkles className="w-3.5 h-3.5 text-amber-500" />
              Rekomendasi Supplier Termurah
            </label>
            {item.recommended_supplier ? (
              <div className="h-[38px] flex items-center justify-between px-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs shadow-2xs">
                <div className="truncate pr-2">
                  <span className="font-semibold text-emerald-900">
                    {item.recommended_supplier.perusahaan}
                  </span>
                  <span className="text-emerald-700 font-mono ml-1.5">
                    ({formatMoney(item.recommended_supplier.price, currencySymbol)})
                  </span>
                </div>
                {item.supplier_id !== item.recommended_supplier.id && (
                  <button
                    type="button"
                    onClick={onApplyRecommendedSupplier}
                    className="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-medium text-[11px] rounded shadow-2xs transition-colors"
                  >
                    <Check className="w-3 h-3" />
                    Terapkan
                  </button>
                )}
              </div>
            ) : (
              <div className="h-[38px] flex items-center px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-400 italic">
                Pilih produk untuk melihat rekomendasi supplier.
              </div>
            )}
          </div>

          {/* Mata Uang Item */}
          <div className="md:col-span-2">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Valas Item
            </label>
            <select
              value={item.currency_id}
              onChange={(e) => onUpdate('currency_id', Number(e.target.value))}
              className="w-full h-[38px] px-3 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors shadow-xs"
            >
              {dependencies?.currencies.map((curr) => (
                <option key={curr.id} value={curr.id}>
                  {curr.code} ({curr.symbol})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Row 3: Pricing Cards & Live Typography Metrics */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 p-3.5 bg-gray-50 rounded-xl border border-gray-200">
          {/* Master Price: LIVE TYPOGRAPHY */}
          <div className="bg-white p-2.5 rounded-lg border border-gray-200 shadow-2xs">
            <span className="text-[11px] font-semibold text-gray-500 block mb-0.5">
              Harga Master
            </span>
            <span className="text-sm font-bold font-mono text-gray-800 block truncate">
              {formatMoney(item.original_price, currencySymbol)}
            </span>
            <span className="text-[10px] text-gray-400 block mt-0.5">
              Patokan database
            </span>
          </div>

          {/* Override Price Input (Seamless Prefix Alignment) */}
          <div className="bg-white p-2.5 rounded-lg border border-gray-200 shadow-2xs">
            <label className="block text-[11px] font-semibold text-gray-700 mb-1">
              Harga Satuan <span className="text-rose-500">*</span>
            </label>
            <div className="flex rounded-lg shadow-xs">
              <span className="inline-flex items-center px-2.5 rounded-l-lg border border-r-0 border-gray-300 bg-gray-100 text-gray-700 text-xs font-mono font-bold h-[34px]">
                {currencySymbol}
              </span>
              <input
                type="number"
                step="any"
                min="0"
                value={item.unit_price === 0 ? '' : item.unit_price}
                onChange={(e) => onUpdate('unit_price', parseFloat(e.target.value) || 0)}
                placeholder="0"
                className="w-full h-[34px] px-2 text-sm font-mono border border-gray-300 bg-white text-gray-900 rounded-r-lg text-right focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          {/* Diskon % & LIVE DISCOUNT TAG */}
          <div className="bg-white p-2.5 rounded-lg border border-gray-200 shadow-2xs">
            <label className="block text-[11px] font-semibold text-gray-700 mb-1 flex items-center justify-between">
              <span>Diskon (%)</span>
              {item.discount > 0 && (
                <span className="text-[10px] font-mono font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 rounded">
                  -{formatMoney(item.discount_nominal, currencySymbol)}
                </span>
              )}
            </label>
            <div className="flex rounded-lg shadow-xs">
              <input
                type="number"
                step="any"
                min="0"
                max="100"
                value={item.discount === 0 ? '' : item.discount}
                onChange={(e) => onUpdate('discount', parseFloat(e.target.value) || 0)}
                placeholder="0"
                className="w-full h-[34px] px-2 text-sm font-mono border border-r-0 border-gray-300 bg-white text-gray-900 rounded-l-lg text-right focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
              />
              <span className="inline-flex items-center px-2.5 rounded-r-lg border border-gray-300 bg-gray-100 text-gray-700 text-xs font-semibold h-[34px]">
                %
              </span>
            </div>
          </div>

          {/* Total Kotor (Qty x Price): LIVE METRIC */}
          <div className="bg-white p-2.5 rounded-lg border border-gray-200 shadow-2xs">
            <span className="text-[11px] font-semibold text-gray-500 block mb-0.5">
              Total Kotor
            </span>
            <span className="text-sm font-bold font-mono text-gray-800 block truncate">
              {formatMoney(item.total_cost, currencySymbol)}
            </span>
            <span className="text-[10px] text-gray-400 block mt-0.5">
              Sebelum diskon & pajak
            </span>
          </div>
        </div>

        {/* Row 4: Tipe Pajak Radio, Tax Chip (Live Text), Subtotal Card */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4 items-center">
          {/* Tipe Pajak Radio Options */}
          <div className="lg:col-span-5">
            <label className="block text-xs font-semibold text-gray-700 mb-1.5">
              Tipe Pajak
            </label>
            <div className="flex items-center gap-2">
              {[
                { value: 'none', label: 'Non PPN (0%)' },
                { value: 'eklusif', label: 'PPN Excluded' },
                { value: 'inklusif', label: 'PPN Included' },
              ].map((opt) => (
                <label
                  key={opt.value}
                  className={`flex-1 h-[38px] flex items-center justify-center gap-1.5 px-2.5 rounded-lg border text-xs font-medium cursor-pointer transition-all ${
                    item.tipe_pajak === opt.value
                      ? 'bg-primary-50 border-primary-500 text-primary-700 font-semibold shadow-2xs'
                      : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'
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
            <label className="block text-xs font-semibold text-gray-700 mb-1.5">
              Pajak Terhitung
            </label>
            <div className="h-[38px] flex items-center justify-between px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs shadow-2xs">
              <span className="font-semibold text-gray-600">Rate: {item.tax}%</span>
              <span className="font-mono font-bold text-primary-600">
                +{formatMoney(item.tax_nominal, currencySymbol)}
              </span>
            </div>
          </div>

          {/* Subtotal Highlight Box (Pure Light Accent) */}
          <div className="lg:col-span-4 h-[38px] bg-blue-50/80 px-3.5 rounded-lg border border-blue-200 flex items-center justify-between shadow-2xs">
            <div>
              <span className="text-[11px] uppercase tracking-wider text-blue-900 font-bold block">
                Subtotal Item
              </span>
            </div>
            <div className="text-right">
              <span className="text-base font-extrabold font-mono text-blue-800">
                {formatMoney(item.subtotal, currencySymbol)}
              </span>
            </div>
          </div>
        </div>

        {/* Row 5: Catatan Baris Item */}
        <div>
          <input
            type="text"
            value={item.note}
            onChange={(e) => onUpdate('note', e.target.value)}
            placeholder="Catatan khusus baris produk ini (opsional)..."
            className="w-full h-[36px] px-3 text-xs border border-gray-200 rounded-lg bg-gray-50 text-gray-900 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-colors shadow-2xs"
          />
        </div>
      </div>
    </div>
  );
};
