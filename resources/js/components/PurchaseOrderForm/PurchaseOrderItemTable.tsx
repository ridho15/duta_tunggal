import React, { useState, useMemo } from 'react';
import {
  Trash2,
  Edit2,
  ChevronDown,
  ChevronRight,
  TrendingDown,
  Info,
  Check,
  Building2,
  Layers,
  Lock,
  Plus,
} from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import {
  PurchaseOrderItemRow,
  PurchaseOrderDependencies,
  TaxType,
  ProductOption,
} from './types';
import { hitungItemCalculations, formatMoney, parseMoneyInput } from './calculations';

interface Props {
  items: PurchaseOrderItemRow[];
  dependencies: PurchaseOrderDependencies;
  onChangeItems: (items: PurchaseOrderItemRow[]) => void;
  onAddItem?: () => void;
  isOrderRequestReference?: boolean;
  errors?: Record<string, string[]>;
}

export const PurchaseOrderItemTable: React.FC<Props> = ({
  items,
  dependencies,
  onChangeItems,
  onAddItem,
  isOrderRequestReference = false,
  errors = {},
}) => {
  const [activeItemRowId, setActiveItemRowId] = useState<string | null>(items[0]?.row_id || null);
  const [focusedPriceRowId, setFocusedPriceRowId] = useState<string | null>(null);

  React.useEffect(() => {
    if (!activeItemRowId && items.length > 0) {
      setActiveItemRowId(items[0].row_id);
    }
  }, [items]);

  // Alphabetically sorted products
  const productOptions: SelectOption[] = useMemo(() => {
    return (dependencies.products || [])
      .map((p) => ({
        value: p.id,
        label: `(${p.sku || 'NO-SKU'}) ${p.name}`,
        sublabel: `UOM: ${p.uom} | Standar: ${formatMoney(p.cost_price)}`,
        badge: p.uom,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.products]);

  // Alphabetically sorted branches
  const cabangOptions: SelectOption[] = useMemo(() => {
    return (dependencies.cabangs || [])
      .map((c) => ({
        value: c.id,
        label: `(${c.kode}) ${c.nama}`,
        badge: c.kode,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.cabangs]);

  const toggleAccordion = (rowId: string) => {
    setActiveItemRowId((prev) => (prev === rowId ? null : rowId));
  };

  const handleUpdateItem = (rowId: string, updates: Partial<PurchaseOrderItemRow>) => {
    const updated = items.map((item) => {
      if (item.row_id === rowId) {
        return { ...item, ...updates };
      }
      return item;
    });
    onChangeItems(updated);
  };

  const handleRemoveItem = (rowId: string) => {
    if (items.length <= 1) {
      // Don't remove last row, just reset it
      onChangeItems([
        {
          row_id: `row-${Date.now()}-1`,
          product_id: null,
          quantity: 1,
          unit_price: 0,
          discount: 0,
          tax: 11,
          tipe_pajak: 'eklusif',
          currency_id: dependencies.currencies[0]?.id || 1,
          uom: 'PCS',
          product_name: '',
          product_sku: '',
          product_suppliers: [],
        },
      ]);
      return;
    }
    const filtered = items.filter((i) => i.row_id !== rowId);
    onChangeItems(filtered);
    if (activeItemRowId === rowId) {
      setActiveItemRowId(filtered[0]?.row_id || null);
    }
  };

  const handleSelectProduct = (rowId: string, productId: number | string | null) => {
    const pid = productId ? Number(productId) : null;
    const prod = dependencies.products.find((p) => p.id === pid);

    if (!prod) {
      handleUpdateItem(rowId, {
        product_id: null,
        product_name: '',
        product_sku: '',
        uom: 'PCS',
        product_suppliers: [],
      });
      return;
    }

    const recSupplier = prod.recommended_supplier;
    const defaultPrice = recSupplier ? recSupplier.price : prod.cost_price;

    handleUpdateItem(rowId, {
      product_id: prod.id,
      product_name: prod.name,
      product_sku: prod.sku,
      uom: prod.uom,
      unit_price: defaultPrice,
      tax: prod.default_tax_rate ?? 11,
      supplier_id: recSupplier ? recSupplier.id : null,
      product_suppliers: prod.suppliers || [],
    });
  };

  const getSupplierOptionsForItem = (item: PurchaseOrderItemRow): SelectOption[] => {
    const linkedSuppliers = item.product_suppliers || [];
    const prod = dependencies.products.find((p) => p.id === item.product_id);
    const recSupplier = prod?.recommended_supplier;

    const options: SelectOption[] = [];
    const addedIds = new Set<number>();

    // Tier 1: Recommended Supplier (Top)
    if (recSupplier) {
      options.push({
        value: recSupplier.id,
        label: `(Termurah) (${recSupplier.code}) ${recSupplier.perusahaan}`,
        sublabel: `Harga Termurah: ${formatMoney(recSupplier.price)}`,
        badge: 'Termurah',
      });
      addedIds.add(recSupplier.id);
    }

    // Tier 2: Other Linked Partner Suppliers
    linkedSuppliers.forEach((s) => {
      if (!addedIds.has(s.id)) {
        options.push({
          value: s.id,
          label: `(${s.code}) ${s.perusahaan}`,
          sublabel: s.supplier_price !== null ? `Harga Partner: ${formatMoney(s.supplier_price)}` : undefined,
          badge: 'Partner',
        });
        addedIds.add(s.id);
      }
    });

    // Tier 3: All 24 master suppliers fallback sorted alphabetically
    const otherSuppliers = dependencies.suppliers
      .filter((s) => !addedIds.has(s.id))
      .map((s) => ({
        value: s.id,
        label: `(${s.code}) ${s.perusahaan}`,
        sublabel: s.phone ? `Tel: ${s.phone}` : undefined,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));

    return [...options, ...otherSuppliers];
  };

  return (
    <div className="space-y-4">
      {items.map((item, index) => {
        const isOpen = activeItemRowId === item.row_id;
        const isOrBacked = !!(
          item.refer_item_model_type === 'App\\Models\\OrderRequestItem' ||
          item.refer_item_model_type === 'OrderRequestItem' ||
          item.refer_item_model_id
        );
        const calc = hitungItemCalculations(
          item.quantity,
          item.unit_price,
          item.discount,
          item.tax,
          item.tipe_pajak
        );
        const supplierOptions = getSupplierOptionsForItem(item);
        const prod = dependencies.products.find((p) => p.id === item.product_id);
        const recSupplier = prod?.recommended_supplier;
        const currencyObj = dependencies.currencies.find((c) => c.id === item.currency_id);
        const currSymbol = currencyObj?.symbol || 'Rp';

        return (
          <div
            key={item.row_id}
            className={`bg-white rounded-xl border transition-all duration-200 overflow-hidden ${
              isOpen
                ? 'border-blue-500 shadow-md ring-1 ring-blue-500/20'
                : 'border-gray-200 hover:border-gray-300 shadow-sm'
            }`}
          >
            {/* Accordion Summary Row */}
            <div
              onClick={() => toggleAccordion(item.row_id)}
              className="px-4 py-3 bg-gray-50/70 hover:bg-gray-100/70 cursor-pointer flex items-center justify-between gap-3 border-b border-gray-100 select-none"
            >
              <div className="flex items-center gap-3 min-w-0">
                <button
                  type="button"
                  className="p-1 rounded text-gray-400 hover:text-gray-600 focus:outline-none"
                >
                  {isOpen ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
                </button>
                <div className="w-6 h-6 rounded-full bg-blue-50 text-blue-700 font-bold text-xs flex items-center justify-center shrink-0">
                  {index + 1}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="font-semibold text-sm text-gray-900 truncate flex items-center gap-2">
                    {item.product_name ? (
                      <span>
                        <span className="text-gray-500 font-normal">({item.product_sku})</span>{' '}
                        {item.product_name}
                      </span>
                    ) : (
                      <span className="text-gray-400 italic">Belum memilih produk</span>
                    )}
                    {isOrBacked && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 shrink-0">
                        <Lock className="w-2.5 h-2.5" /> OR Ref
                      </span>
                    )}
                  </div>
                  <div className="text-xs text-gray-500 flex items-center gap-3 mt-0.5">
                    <span>
                      Qty: <strong className="text-gray-700">{item.quantity}</strong> {item.uom}
                    </span>
                    <span>•</span>
                    <span>
                      Harga: <strong className="text-gray-700">{formatMoney(item.unit_price, currSymbol)}</strong>
                    </span>
                    {item.discount > 0 && (
                      <>
                        <span>•</span>
                        <span className="text-amber-600">Diskon: {item.discount}%</span>
                      </>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-4 shrink-0">
                <div className="text-right">
                  <div className="text-xs text-gray-400">Subtotal</div>
                  <div className="text-sm font-semibold text-gray-900 tabular-nums">
                    {formatMoney(calc.subtotal, currSymbol)}
                  </div>
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleRemoveItem(item.row_id);
                  }}
                  className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  title="Hapus baris item"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Accordion Expanded Detail Form */}
            {isOpen && (
              <div className="p-4 bg-white space-y-4">
                {/* Row 1: Product (5), Supplier (4), Cabang (3) - 12-Col Grid */}
                <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
                  {/* Product (col-span-5 for wider SKU & Name) */}
                  <div className="md:col-span-5">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Produk <span className="text-red-500">*</span>
                    </label>
                    {isOrBacked ? (
                      <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm flex items-center justify-between text-gray-700 font-medium">
                        <span className="truncate">
                          <span className="text-gray-500 font-normal">({item.product_sku})</span> {item.product_name}
                        </span>
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 shrink-0">
                          <Lock className="w-3 h-3" /> Terkunci
                        </span>
                      </div>
                    ) : (
                      <SearchableSelect
                        options={productOptions}
                        value={item.product_id}
                        placeholder="-- Pilih Produk --"
                        onChange={(val) => handleSelectProduct(item.row_id, val)}
                      />
                    )}
                  </div>

                  {/* Supplier (col-span-4 for recommendation badge & vendor name) */}
                  <div className="md:col-span-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Supplier Pemasok
                    </label>
                    <SearchableSelect
                      options={supplierOptions}
                      value={item.supplier_id}
                      placeholder="-- Pilih Supplier --"
                      disabled={isOrBacked}
                      onChange={(val) => {
                        if (isOrBacked) return;
                        const supId = val ? Number(val) : null;
                        const linked = item.product_suppliers?.find((s) => s.id === supId);
                        const newPrice = linked && linked.supplier_price !== null ? linked.supplier_price : item.unit_price;
                        handleUpdateItem(item.row_id, {
                          supplier_id: supId,
                          unit_price: newPrice,
                        });
                      }}
                    />
                    {!isOrBacked && recSupplier && (
                      <button
                        type="button"
                        onClick={() =>
                          handleUpdateItem(item.row_id, {
                            supplier_id: recSupplier.id,
                            unit_price: recSupplier.price,
                          })
                        }
                        className="mt-1.5 w-full flex items-center justify-between px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded text-xs transition-colors"
                      >
                        <span className="flex items-center gap-1 font-medium truncate mr-1">
                          <TrendingDown className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                          <span className="truncate">Termurah: ({recSupplier.code}) {recSupplier.perusahaan}</span>
                        </span>
                        <strong className="text-emerald-700 shrink-0">{formatMoney(recSupplier.price)}</strong>
                      </button>
                    )}
                  </div>

                  {/* Cabang (col-span-3) */}
                  <div className="md:col-span-3">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Cabang Penerima
                    </label>
                    <SearchableSelect
                      options={cabangOptions}
                      value={item.cabang_id}
                      placeholder="-- Pilih Cabang --"
                      disabled={isOrBacked}
                      onChange={(val) => {
                        if (isOrBacked) return;
                        handleUpdateItem(item.row_id, { cabang_id: val ? Number(val) : null });
                      }}
                    />
                  </div>
                </div>

                {/* Row 2: Qty (2), Currency (2), Harga Satuan (3), Diskon (2), Pajak (3) - 12-Col Grid */}
                <div className="grid grid-cols-2 md:grid-cols-12 gap-3 items-end">
                  {/* Qty (col-span-2) */}
                  <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Qty ({item.uom}) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      min="0.0001"
                      step="any"
                      value={item.quantity || ''}
                      onChange={(e) => {
                        let val = Number(e.target.value) || 0;
                        if (item.max_quantity && val > item.max_quantity) {
                          val = item.max_quantity;
                        }
                        handleUpdateItem(item.row_id, { quantity: val });
                      }}
                      className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                      placeholder="1"
                    />
                    {item.max_quantity && (
                      <span className="text-[10px] text-blue-600 font-semibold block mt-0.5">
                        Maks OR: {item.max_quantity} {item.uom}
                      </span>
                    )}
                  </div>

                  {/* Currency (col-span-2) */}
                  <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Mata Uang</label>
                    <select
                      value={item.currency_id}
                      disabled={isOrBacked}
                      onChange={(e) => {
                        if (isOrBacked) return;
                        handleUpdateItem(item.row_id, { currency_id: Number(e.target.value) });
                      }}
                      className={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none ${
                        isOrBacked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'
                      }`}
                    >
                      {dependencies.currencies.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.code} ({c.symbol})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Harga Satuan (col-span-3 - Live Rupiah formatting on blur, readOnly when OR-backed) */}
                  <div className="col-span-2 md:col-span-3">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Harga Satuan <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      readOnly={isOrBacked}
                      disabled={isOrBacked}
                      value={
                        focusedPriceRowId === item.row_id
                          ? item.unit_price === 0 ? '' : item.unit_price
                          : formatMoney(item.unit_price, currSymbol)
                      }
                      onFocus={() => !isOrBacked && setFocusedPriceRowId(item.row_id)}
                      onBlur={() => setFocusedPriceRowId(null)}
                      onChange={(e) => {
                        if (isOrBacked) return;
                        const raw = parseMoneyInput(e.target.value);
                        handleUpdateItem(item.row_id, { unit_price: raw });
                      }}
                      className={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-medium text-gray-900 ${
                        isOrBacked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'
                      }`}
                      placeholder="0"
                    />
                  </div>

                  {/* Diskon % (col-span-2 - readOnly when OR-backed) */}
                  <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Diskon (%)</label>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      step="any"
                      readOnly={isOrBacked}
                      disabled={isOrBacked}
                      value={item.discount || 0}
                      onChange={(e) => {
                        if (isOrBacked) return;
                        handleUpdateItem(item.row_id, { discount: Number(e.target.value) || 0 });
                      }}
                      className={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none ${
                        isOrBacked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'
                      }`}
                      placeholder="0"
                    />
                  </div>

                  {/* Pajak (PPN) & Tipe Pajak (col-span-3 - disabled when OR-backed) */}
                  <div className="col-span-1 md:col-span-3">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Pajak (PPN {item.tax}%)
                    </label>
                    <div className={`flex items-center gap-1 text-xs p-1 rounded-lg border border-gray-200 ${
                      isOrBacked ? 'bg-gray-100 opacity-90' : 'bg-gray-50'
                    }`}>
                      <label className={`flex items-center gap-1 ${isOrBacked ? 'cursor-not-allowed' : 'cursor-pointer'} flex-1 justify-center py-1 px-0.5 rounded hover:bg-white transition-colors`}>
                        <input
                          type="radio"
                          name={`pajak-${item.row_id}`}
                          value="eklusif"
                          disabled={isOrBacked}
                          checked={item.tipe_pajak === 'eklusif'}
                          onChange={() => !isOrBacked && handleUpdateItem(item.row_id, { tipe_pajak: 'eklusif', tax: 11 })}
                          className="text-blue-600 focus:ring-blue-500"
                        />
                        <span className="truncate">Eks</span>
                      </label>
                      <label className={`flex items-center gap-1 ${isOrBacked ? 'cursor-not-allowed' : 'cursor-pointer'} flex-1 justify-center py-1 px-0.5 rounded hover:bg-white transition-colors`}>
                        <input
                          type="radio"
                          name={`pajak-${item.row_id}`}
                          value="inklusif"
                          disabled={isOrBacked}
                          checked={item.tipe_pajak === 'inklusif'}
                          onChange={() => !isOrBacked && handleUpdateItem(item.row_id, { tipe_pajak: 'inklusif', tax: 11 })}
                          className="text-blue-600 focus:ring-blue-500"
                        />
                        <span className="truncate">Ink</span>
                      </label>
                      <label className={`flex items-center gap-1 ${isOrBacked ? 'cursor-not-allowed' : 'cursor-pointer'} flex-1 justify-center py-1 px-0.5 rounded hover:bg-white transition-colors`}>
                        <input
                          type="radio"
                          name={`pajak-${item.row_id}`}
                          value="none"
                          disabled={isOrBacked}
                          checked={item.tipe_pajak === 'none'}
                          onChange={() => !isOrBacked && handleUpdateItem(item.row_id, { tipe_pajak: 'none', tax: 0 })}
                          className="text-blue-600 focus:ring-blue-500"
                        />
                        <span className="truncate">Non</span>
                      </label>
                    </div>
                  </div>
                </div>

                {/* Row 3: Notes & Item Calculation Breakdown (Pure Text) */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">
                      Catatan Baris Item
                    </label>
                    <textarea
                      rows={2}
                      value={item.note || ''}
                      onChange={(e) => handleUpdateItem(item.row_id, { note: e.target.value })}
                      className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
                      placeholder="Catatan spesifikasi atau pengiriman untuk item ini..."
                    />
                  </div>

                  {/* Pure-Text Live Calculations Panel */}
                  <div className="bg-gray-50/90 rounded-lg p-3 border border-gray-200 flex flex-col justify-between text-xs">
                    <div className="grid grid-cols-2 gap-y-1 text-gray-600">
                      <span>Total Kotor:</span>
                      <span className="text-right font-medium text-gray-900">
                        {formatMoney(calc.grossTotal, currSymbol)}
                      </span>

                      <span>Diskon ({item.discount}%):</span>
                      <span className="text-right font-medium text-red-600">
                        - {formatMoney(calc.discountNominal, currSymbol)}
                      </span>

                      <span>DPP (Dasar Pengenaan Pajak):</span>
                      <span className="text-right font-medium text-gray-900">
                        {formatMoney(calc.dpp, currSymbol)}
                      </span>

                      <span>PPN ({item.tipe_pajak === 'none' ? '0%' : `${item.tax}%`}):</span>
                      <span className="text-right font-medium text-blue-700">
                        + {formatMoney(calc.taxNominal, currSymbol)}
                      </span>
                    </div>

                    <div className="pt-2 mt-2 border-t border-gray-200/80 flex items-center justify-between font-bold text-sm">
                      <span className="text-gray-900">Subtotal Baris Item:</span>
                      <span className="text-blue-600 text-base font-bold tabular-nums">
                        {formatMoney(calc.subtotal, currSymbol)}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        );
      })}

      {/* Tombol Tambah Item di Bawah Card Item */}
      {!isOrderRequestReference && onAddItem && (
        <div className="pt-2">
          <button
            type="button"
            onClick={onAddItem}
            className="w-full py-2.5 px-4 bg-white hover:bg-blue-50/50 active:bg-blue-100/60 border border-dashed border-gray-300 hover:border-blue-400 text-gray-700 hover:text-blue-700 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs group"
          >
            <div className="w-5 h-5 rounded-full bg-blue-50 group-hover:bg-blue-600 text-blue-600 group-hover:text-white flex items-center justify-center transition-colors">
              <Plus className="w-3.5 h-3.5" />
            </div>
            <span>Tambah Item Pembelian Baru</span>
          </button>
        </div>
      )}
    </div>
  );
};
