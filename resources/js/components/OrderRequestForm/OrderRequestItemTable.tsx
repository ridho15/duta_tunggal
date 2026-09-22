import React, { useState } from 'react';
import {
  OrderRequestItemRow,
  FormDependencies,
} from './types';
import { formatMoney } from './calculations';
import { SearchableSelect } from './SearchableSelect';
import {
  ChevronRight,
  ChevronDown,
  Edit3,
  Check,
  X,
  Trash2,
  TrendingDown,
} from 'lucide-react';

interface Props {
  items: OrderRequestItemRow[];
  dependencies: FormDependencies | null;
  errors: Record<string, string[]>;
  onUpdateRow: (rowId: string, field: keyof OrderRequestItemRow, value: any) => void;
  onToggleExpandRow: (rowId: string) => void;
  onToggleSelectRow: (rowId: string) => void;
  onSelectAllRows: (selected: boolean) => void;
  onRemoveRow: (rowId: string) => void;
  onApproveRow: (rowId: string) => void;
  onRejectRow: (rowId: string) => void;
  canRemove: boolean;
  disabled?: boolean;
}

export const OrderRequestItemTable: React.FC<Props> = ({
  items,
  dependencies,
  errors,
  onUpdateRow,
  onToggleExpandRow,
  onToggleSelectRow,
  onSelectAllRows,
  onRemoveRow,
  onApproveRow,
  onRejectRow,
  canRemove,
  disabled = false,
}) => {
  const allSelected = items.length > 0 && items.every((i) => i.isSelected);

  // Track which item row's Harga Satuan is focused (to show raw number vs formatted)
  const [focusedPriceRowId, setFocusedPriceRowId] = useState<string | null>(null);

  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-2xs">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs border-collapse">
          {/* Table Header */}
          <thead className="bg-gray-50/80 border-b border-gray-200 text-gray-700 font-semibold">
            <tr>
              <th className="py-2.5 px-3 w-10 text-center">
                <input
                  type="checkbox"
                  checked={allSelected}
                  onChange={(e) => onSelectAllRows(e.target.checked)}
                  disabled={disabled}
                  className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                />
              </th>
              <th className="py-2.5 px-2 w-10 text-center">No</th>
              <th className="py-2.5 px-3 min-w-[200px]">Product</th>
              <th className="py-2.5 px-3 min-w-[150px]">Supplier</th>
              <th className="py-2.5 px-3 text-right w-20">Qty</th>
              <th className="py-2.5 px-2 text-center w-16">UOM</th>
              <th className="py-2.5 px-3 text-right min-w-[110px]">Price</th>
              <th className="py-2.5 px-3 text-right min-w-[120px]">Subtotal</th>
              <th className="py-2.5 px-2 text-center w-20">Status</th>
              <th className="py-2.5 px-3 text-center w-24">Action</th>
            </tr>
          </thead>

          {/* Table Body */}
          <tbody className="divide-y divide-gray-200 text-gray-800">
            {items.map((item, index) => {
              const selectedProduct = dependencies?.products.find((p) => p.id === item.product_id);
              const selectedSupplier = dependencies?.suppliers.find((s) => s.id === item.supplier_id);
              const selectedCabang = dependencies?.cabangs.find((c) => c.id === item.cabang_id);
              const currentCurrency = dependencies?.currencies.find((c) => c.id === item.currency_id);
              const currencySymbol = currentCurrency?.symbol || 'Rp';

              const productDisplay = selectedProduct
                ? `(${selectedProduct.sku}) ${selectedProduct.name}`
                : '-';
              const supplierDisplay = selectedSupplier
                ? `(${selectedSupplier.code}) ${selectedSupplier.perusahaan}`
                : '-';

              const itemErrorPrefix = `items.${index}`;
              const hasError =
                Boolean(errors[`${itemErrorPrefix}.product_id`]) ||
                Boolean(errors[`${itemErrorPrefix}.cabang_id`]) ||
                Boolean(errors[`${itemErrorPrefix}.quantity`]);

              const fulfilledQty = item.fulfilled_quantity || 0;
              const remainingQty = Math.max(0, item.quantity - fulfilledQty);

              return (
                <React.Fragment key={item.rowId}>
                  {/* Summary Row */}
                  <tr
                    className={`transition-colors ${
                      item.isExpanded
                        ? 'bg-blue-50/40 font-medium'
                        : index % 2 === 1
                        ? 'bg-gray-50/40 hover:bg-gray-50'
                        : 'bg-white hover:bg-gray-50/80'
                    } ${hasError ? 'bg-rose-50/60' : ''}`}
                  >
                    {/* Checkbox & Chevron */}
                    <td className="py-2 px-3 text-center whitespace-nowrap">
                      <div className="flex items-center justify-center gap-1.5">
                        <button
                          type="button"
                          onClick={() => onToggleExpandRow(item.rowId)}
                          className="p-0.5 text-gray-400 hover:text-blue-600 rounded transition-colors"
                          title={item.isExpanded ? 'Collapse baris' : 'Buka editor baris'}
                        >
                          {item.isExpanded ? (
                            <ChevronDown className="w-3.5 h-3.5 text-blue-600" />
                          ) : (
                            <ChevronRight className="w-3.5 h-3.5" />
                          )}
                        </button>
                        <input
                          type="checkbox"
                          checked={Boolean(item.isSelected)}
                          onChange={() => onToggleSelectRow(item.rowId)}
                          disabled={disabled}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        />
                      </div>
                    </td>

                    {/* No */}
                    <td className="py-2 px-2 text-center text-xs font-semibold text-gray-600">
                      {index + 1}
                    </td>

                    {/* Product */}
                    <td className="py-2 px-3 text-xs text-gray-900">
                      <span className="line-clamp-1" title={productDisplay}>
                        {productDisplay}
                      </span>
                    </td>

                    {/* Supplier */}
                    <td className="py-2 px-3 text-xs text-gray-600">
                      <span className="line-clamp-1" title={supplierDisplay}>
                        {supplierDisplay}
                      </span>
                    </td>

                    {/* Qty */}
                    <td className="py-2 px-3 text-xs text-right font-semibold text-gray-900">
                      {item.quantity.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                    </td>

                    {/* UOM */}
                    <td className="py-2 px-2 text-xs text-center text-gray-500">
                      {item.unit || '-'}
                    </td>

                    {/* Price */}
                    <td className="py-2 px-3 text-xs text-right tabular-nums text-gray-800">
                      {currencySymbol} {formatMoney(item.unit_price)}
                    </td>

                    {/* Subtotal */}
                    <td className="py-2 px-3 text-xs text-right tabular-nums font-semibold text-gray-900">
                      {currencySymbol} {formatMoney(item.subtotal)}
                    </td>

                    {/* Status Badge */}
                    <td className="py-2 px-2 text-center">
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                          item.status === 'approved'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                            : item.status === 'rejected'
                            ? 'bg-rose-50 text-rose-700 border border-rose-200'
                            : 'bg-gray-100 text-gray-700 border border-gray-200'
                        }`}
                      >
                        {item.status || 'Draft'}
                      </span>
                    </td>

                    {/* Actions */}
                    <td className="py-2 px-3 text-center whitespace-nowrap">
                      <div className="inline-flex items-center gap-1">
                        <button
                          type="button"
                          onClick={() => onToggleExpandRow(item.rowId)}
                          className="p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                          title="Edit baris item"
                        >
                          <Edit3 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onApproveRow(item.rowId)}
                          className="p-1 text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                          title="Set Approved"
                        >
                          <Check className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onRejectRow(item.rowId)}
                          className="p-1 text-rose-600 hover:bg-rose-50 rounded transition-colors"
                          title="Set Rejected"
                        >
                          <X className="w-3.5 h-3.5" />
                        </button>
                        {canRemove && (
                          <button
                            type="button"
                            onClick={() => onRemoveRow(item.rowId)}
                            disabled={disabled}
                            className="p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded transition-colors"
                            title="Hapus baris item"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>

                  {/* Accordion Expanded Inline Editor (Restructured & Balanced) */}
                  {item.isExpanded && (
                    <tr className="bg-slate-50/60">
                      <td colSpan={10} className="p-4 border-t border-b border-blue-200/80">
                        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-4">
                          {/* Editor Top Header Bar */}
                          <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="text-sm font-semibold text-gray-900">
                                Editor Item #{index + 1}
                              </span>
                              <span className="px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                {item.status || 'Draft'}
                              </span>
                              <span className="text-xs text-gray-500 font-normal">
                                Perubahan data otomatis tersimpan langsung ke form.
                              </span>
                            </div>

                            <div className="flex items-center gap-3">
                              <span className="text-xs text-gray-600 font-medium">
                                Cabang: <strong className="text-gray-900">{selectedCabang?.nama || '-'}</strong>
                              </span>
                              {canRemove && (
                                <button
                                  type="button"
                                  onClick={() => onRemoveRow(item.rowId)}
                                  className="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-xs font-bold transition-colors shadow-2xs"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                  Hapus Item
                                </button>
                              )}
                            </div>
                          </div>

                          {/* 1. Form Inputs Section */}
                          <div className="space-y-3.5">
                            {/* Baris 1: Product, Supplier (with Recommended Chip), and Cabang (Searchable) */}
                            <div className="flex flex-wrap items-start gap-4">
                              {/* Product — sorted alphabetically */}
                              <div className="flex-1 min-w-[240px]">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Product <span className="text-rose-500">*</span>
                                </label>
                                <SearchableSelect
                                  options={[...(dependencies?.products || [])]
                                    .sort((a, b) => a.name.localeCompare(b.name, 'id'))
                                    .map((p) => ({
                                      value: p.id,
                                      label: `(${p.sku}) ${p.name}`,
                                      badge: p.uom,
                                    }))}
                                  value={item.product_id}
                                  placeholder="Cari SKU atau nama product"
                                  hasError={Boolean(errors[`${itemErrorPrefix}.product_id`])}
                                  onChange={(val) => onUpdateRow(item.rowId, 'product_id', val ? Number(val) : null)}
                                  disabled={disabled}
                                />
                              </div>

                              {/* Supplier — 1. Recommended (with price & badge), 2. Partners with price, 3. All other suppliers alphabetically */}
                              <div className="flex-1 min-w-[240px]">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Supplier
                                </label>
                                <SearchableSelect
                                  options={(() => {
                                    const allMasterSuppliers = dependencies?.suppliers || [];
                                    const productSuppliers = item.product_suppliers || [];
                                    const recommendedId = item.recommended_supplier?.id;

                                    // Map of product-specific prices: supplier_id => price
                                    const priceMap = new Map<number, number | null>();
                                    productSuppliers.forEach((ps) => {
                                      priceMap.set(ps.id, ps.supplier_price);
                                    });

                                    // Categorize suppliers into 3 tiers
                                    const recommendedList: typeof allMasterSuppliers = [];
                                    const linkedList: typeof allMasterSuppliers = [];
                                    const generalList: typeof allMasterSuppliers = [];

                                    allMasterSuppliers.forEach((s) => {
                                      if (s.id === recommendedId) {
                                        recommendedList.push(s);
                                      } else if (priceMap.has(s.id)) {
                                        linkedList.push(s);
                                      } else {
                                        generalList.push(s);
                                      }
                                    });

                                    // Sort linked and general tiers alphabetically
                                    linkedList.sort((a, b) => a.perusahaan.localeCompare(b.perusahaan, 'id'));
                                    generalList.sort((a, b) => a.perusahaan.localeCompare(b.perusahaan, 'id'));

                                    const orderedSuppliers = [...recommendedList, ...linkedList, ...generalList];

                                    return orderedSuppliers.map((s) => {
                                      const isRecommended = s.id === recommendedId;
                                      const hasPrice = priceMap.has(s.id) && priceMap.get(s.id) !== null && priceMap.get(s.id) !== undefined;
                                      const priceVal = hasPrice ? priceMap.get(s.id)! : null;

                                      let priceText = '';
                                      if (priceVal !== null) {
                                        priceText = ` — ${currencySymbol} ${formatMoney(priceVal)}`;
                                      }

                                      return {
                                        value: s.id,
                                        label: `(${s.code}) ${s.perusahaan}${priceText}`,
                                        badge: isRecommended ? 'Termurah' : (hasPrice ? 'Partner' : undefined),
                                      };
                                    });
                                  })()}
                                  value={item.supplier_id}
                                  placeholder="Cari kode atau perusahaan supplier"
                                  onChange={(val) => onUpdateRow(item.rowId, 'supplier_id', val ? Number(val) : null)}
                                  disabled={disabled}
                                />
                                {/* Recommended Supplier Interactive Chip */}
                                {item.recommended_supplier && (
                                  <div className="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                    <button
                                      type="button"
                                      onClick={() => {
                                        if (item.recommended_supplier) {
                                          onUpdateRow(item.rowId, 'supplier_id', item.recommended_supplier.id);
                                        }
                                      }}
                                      className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] transition-colors cursor-pointer ${
                                        item.supplier_id === item.recommended_supplier.id
                                          ? 'bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold'
                                          : 'bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-medium'
                                      }`}
                                      title="Klik untuk menerapkan supplier rekomendasi ini"
                                    >
                                      <span className="flex items-center gap-1 font-semibold">
                                        <TrendingDown className="w-3 h-3 text-emerald-600 shrink-0" />
                                        <span>Harga Termurah:</span>
                                      </span>
                                      <span className="underline">({item.recommended_supplier.code}) {item.recommended_supplier.perusahaan}</span>
                                      <span className="tabular-nums font-semibold">({currencySymbol} {formatMoney(item.recommended_supplier.price)})</span>
                                    </button>
                                  </div>
                                )}
                              </div>

                              {/* Cabang (SearchableSelect) — sorted alphabetically, wider to prevent text truncation */}
                              <div className="flex-1 min-w-[280px]">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Cabang <span className="text-rose-500">*</span>
                                </label>
                                <SearchableSelect
                                  options={[...(dependencies?.cabangs || [])]
                                    .sort((a, b) => a.nama.localeCompare(b.nama, 'id'))
                                    .map((c) => ({
                                      value: c.id,
                                      label: `(${c.kode}) ${c.nama}`,
                                      badge: c.kode,
                                    }))}
                                  value={item.cabang_id}
                                  placeholder="Pilih Cabang"
                                  hasError={Boolean(errors[`${itemErrorPrefix}.cabang_id`])}
                                  onChange={(val) => onUpdateRow(item.rowId, 'cabang_id', val ? Number(val) : null)}
                                  disabled={disabled}
                                />
                              </div>
                            </div>

                            {/* Baris 2: Qty, Mata Uang, Harga Satuan, Diskon, Pajak (Compact & Balanced) */}
                            <div className="flex flex-wrap items-start gap-4">
                              {/* Qty with attached unit text */}
                              <div className="w-32">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Qty <span className="text-rose-500">*</span>
                                </label>
                                <div className="flex rounded-lg shadow-2xs">
                                  <input
                                    type="number"
                                    min="0.01"
                                    step="any"
                                    value={item.quantity === 0 ? '' : item.quantity}
                                    onChange={(e) => onUpdateRow(item.rowId, 'quantity', e.target.value)}
                                    disabled={disabled}
                                    placeholder="1"
                                    className={`w-full h-[38px] px-2.5 text-xs text-right border rounded-l-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 ${
                                      errors[`${itemErrorPrefix}.quantity`] ? 'border-rose-400 bg-rose-50/50' : 'border-gray-300'
                                    }`}
                                  />
                                  <span className="inline-flex items-center px-2 text-xs font-semibold bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-700">
                                    {item.unit || '-'}
                                  </span>
                                </div>
                              </div>

                              {/* Mata Uang Item */}
                              <div className="w-40">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Mata Uang Item
                                </label>
                                <select
                                  value={item.currency_id}
                                  onChange={(e) => onUpdateRow(item.rowId, 'currency_id', Number(e.target.value))}
                                  disabled={disabled}
                                  className="w-full h-[38px] px-2.5 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                  {dependencies?.currencies.map((curr) => (
                                    <option key={curr.id} value={curr.id}>
                                      {curr.name} ({curr.symbol})
                                    </option>
                                  ))}
                                </select>
                              </div>

                              {/* Harga Satuan — formatted Rupiah input (format on blur, raw on focus) */}
                              <div className="w-48">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Harga Satuan <span className="text-rose-500">*</span>
                                </label>
                                <div className="flex rounded-lg shadow-2xs">
                                  <span className="inline-flex items-center px-2 text-xs font-semibold bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-600">
                                    {currencySymbol}
                                  </span>
                                  <input
                                    type="text"
                                    inputMode="numeric"
                                    value={
                                      focusedPriceRowId === item.rowId
                                        ? (item.unit_price === 0 ? '' : String(item.unit_price))
                                        : (item.unit_price === 0 ? '' : formatMoney(item.unit_price))
                                    }
                                    onFocus={() => setFocusedPriceRowId(item.rowId)}
                                    onBlur={(e) => {
                                      setFocusedPriceRowId(null);
                                      // parse raw numeric value on blur
                                      const raw = e.target.value.replace(/[^0-9.]/g, '');
                                      onUpdateRow(item.rowId, 'unit_price', raw === '' ? 0 : parseFloat(raw));
                                    }}
                                    onChange={(e) => {
                                      if (focusedPriceRowId === item.rowId) {
                                        const raw = e.target.value.replace(/[^0-9.]/g, '');
                                        onUpdateRow(item.rowId, 'unit_price', raw === '' ? 0 : parseFloat(raw));
                                      }
                                    }}
                                    disabled={disabled}
                                    placeholder="0"
                                    className="w-full h-[38px] px-2.5 text-xs text-right tabular-nums border border-gray-300 rounded-r-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-900"
                                  />
                                </div>
                                <div className="mt-1 text-[11px] text-gray-500 truncate" title={`Harga Asli: ${currencySymbol} ${formatMoney(item.original_price)}`}>
                                  Harga Asli: <span className="tabular-nums text-gray-700 font-medium">{currencySymbol} {formatMoney(item.original_price)}</span>
                                </div>
                              </div>

                              {/* Diskon (%) */}
                              <div className="w-28">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Diskon (%)
                                </label>
                                <div className="flex rounded-lg shadow-2xs">
                                  <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={item.discount === 0 ? '' : item.discount}
                                    onChange={(e) => onUpdateRow(item.rowId, 'discount', e.target.value)}
                                    disabled={disabled}
                                    placeholder="0"
                                    className="w-full h-[38px] px-2 text-xs text-right border border-gray-300 rounded-l-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-900"
                                  />
                                  <span className="inline-flex items-center px-2 text-xs font-semibold bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600">
                                    %
                                  </span>
                                </div>
                              </div>

                              {/* Pajak (PPN) */}
                              <div className="flex flex-col">
                                <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                  Pajak (PPN)
                                </label>
                                <div className="flex items-center gap-2">
                                  <div className="flex items-center gap-2 h-[38px] px-2.5 border border-gray-300 rounded-lg bg-white shadow-2xs">
                                    <label className="inline-flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                                      <input
                                        type="radio"
                                        name={`tax_type_${item.rowId}`}
                                        value="inklusif"
                                        checked={item.tipe_pajak === 'inklusif'}
                                        onChange={() => onUpdateRow(item.rowId, 'tipe_pajak', 'inklusif')}
                                        disabled={disabled}
                                        className="text-blue-600 focus:ring-blue-500"
                                      />
                                      <span>Inklusif</span>
                                    </label>
                                    <label className="inline-flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                                      <input
                                        type="radio"
                                        name={`tax_type_${item.rowId}`}
                                        value="eklusif"
                                        checked={item.tipe_pajak === 'eklusif'}
                                        onChange={() => onUpdateRow(item.rowId, 'tipe_pajak', 'eklusif')}
                                        disabled={disabled}
                                        className="text-blue-600 focus:ring-blue-500"
                                      />
                                      <span>Eksklusif</span>
                                    </label>
                                    <label className="inline-flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                                      <input
                                        type="radio"
                                        name={`tax_type_${item.rowId}`}
                                        value="none"
                                        checked={item.tipe_pajak === 'none'}
                                        onChange={() => onUpdateRow(item.rowId, 'tipe_pajak', 'none')}
                                        disabled={disabled}
                                        className="text-blue-600 focus:ring-blue-500"
                                      />
                                      <span>Non PPN</span>
                                    </label>
                                  </div>

                                  <div className="w-20 flex rounded-lg shadow-2xs">
                                    <input
                                      type="number"
                                      min="0"
                                      max="100"
                                      value={item.tipe_pajak === 'none' ? 0 : item.tax}
                                      onChange={(e) => onUpdateRow(item.rowId, 'tax', e.target.value)}
                                      disabled={disabled || item.tipe_pajak === 'none'}
                                      className="w-full h-[38px] px-2 text-xs text-right border border-gray-300 rounded-l-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    />
                                    <span className="inline-flex items-center px-1.5 text-xs font-semibold bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600">
                                      %
                                    </span>
                                  </div>
                                </div>
                              </div>
                            </div>

                            {/* Baris 3: Catatan */}
                            <div>
                              <label className="block text-[11px] font-bold text-gray-700 mb-1">
                                Catatan
                              </label>
                              <textarea
                                value={item.note || ''}
                                onChange={(e) => onUpdateRow(item.rowId, 'note', e.target.value)}
                                disabled={disabled}
                                placeholder="Catatan khusus baris produk ini (opsional)..."
                                rows={2}
                                className="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-900"
                              />
                            </div>
                          </div>

                          {/* 2. Pure Text Live Calculation Breakdown Panel */}
                          <div className="mt-4 pt-3 border-t border-gray-100 bg-gray-50/80 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-4">
                            {/* Calculation metrics list in clean text */}
                            <div className="flex items-center gap-6 flex-wrap text-xs">
                              <div>
                                <span className="text-gray-500 block text-[11px]">Total Kotor:</span>
                                <span className="tabular-nums text-gray-800 font-medium">
                                  {currencySymbol} {formatMoney(item.total_cost)}
                                </span>
                              </div>
                              {item.discount > 0 && (
                                <>
                                  <div>
                                    <span className="text-gray-500 block text-[11px]">Diskon:</span>
                                    <span className="tabular-nums text-rose-600 font-medium">
                                      -{currencySymbol} {formatMoney(item.discount_nominal)}
                                    </span>
                                  </div>
                                  <div>
                                    <span className="text-gray-500 block text-[11px]">Total Setelah Diskon:</span>
                                    <span className="tabular-nums text-gray-800 font-medium">
                                      {currencySymbol} {formatMoney(item.after_discount)}
                                    </span>
                                  </div>
                                </>
                              )}
                              {item.tipe_pajak !== 'none' && item.tax > 0 && (
                                <div>
                                  <span className="text-gray-500 block text-[11px]">
                                    Pajak ({item.tax}%):
                                  </span>
                                  <span className="tabular-nums text-gray-800 font-medium">
                                    +{currencySymbol} {formatMoney(item.tax_nominal)}
                                  </span>
                                </div>
                              )}
                            </div>

                            {/* Subtotal in prominent text */}
                            <div className="text-right">
                              <span className="text-gray-500 block text-[11px] font-semibold uppercase tracking-wider">
                                Subtotal Baris Item
                              </span>
                              <span className="tabular-nums font-bold text-base text-blue-900">
                                {currencySymbol} {formatMoney(item.subtotal)}
                              </span>
                            </div>
                          </div>

                          {/* 3. Bottom Meta Status Footer */}
                          <div className="pt-2 border-t border-gray-100 flex flex-wrap items-center justify-between text-[11px] text-gray-500 gap-2">
                            <div className="flex items-center gap-4 flex-wrap">
                              <span>
                                Stok Tersedia: <strong className="text-gray-700">{(item.available_stock || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })} {item.unit || '-'}</strong>
                              </span>
                              <span>
                                Terpenuhi: <strong className="text-gray-700">{fulfilledQty.toLocaleString('id-ID', { minimumFractionDigits: 2 })} {item.unit || '-'}</strong>
                              </span>
                              <span>
                                Sisa: <strong className="text-gray-700">{remainingQty.toLocaleString('id-ID', { minimumFractionDigits: 2 })} {item.unit || '-'}</strong>
                              </span>
                            </div>
                            <div>
                              Status Item: <strong className="text-gray-800 uppercase">{item.status || 'Draft'}</strong>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};
