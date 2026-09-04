import React, { useState, useMemo, useEffect } from 'react';
import { ChevronDown, Trash2, Tag, Percent, DollarSign, StickyNote, Package, Plus } from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import { QuotationItemRow, QuotationDependencies, ProductOption } from './types';
import { calculateItemPreview, formatCurrency, formatNumber } from './calculations';

interface Props {
  items: QuotationItemRow[];
  currencySymbol: string;
  dependencies: QuotationDependencies;
  onChangeItems: (items: QuotationItemRow[]) => void;
  onAddItem?: () => void;
  errors?: Record<string, string[]>;
}

export const QuotationItemTable: React.FC<Props> = ({
  items,
  currencySymbol,
  dependencies,
  onChangeItems,
  onAddItem,
  errors = {},
}) => {
  const [activeItemRowId, setActiveItemRowId] = useState<string | null>(items[0]?.row_id || null);
  const [focusedPriceRowId, setFocusedPriceRowId] = useState<string | null>(null);

  // Auto-expand first item or active item when list changes
  useEffect(() => {
    if ((!activeItemRowId || !items.some((i) => i.row_id === activeItemRowId)) && items.length > 0) {
      setActiveItemRowId(items[0].row_id);
    }
  }, [items, activeItemRowId]);

  // Alphabetically sorted products
  const productOptions: SelectOption[] = useMemo(() => {
    return (dependencies.products || [])
      .slice()
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((p) => ({
        value: p.id,
        label: p.name,
        badge: p.sku,
        sublabel: p.uom?.abbreviation ? `UOM: ${p.uom.abbreviation} | Std: ${formatCurrency(p.sell_price, currencySymbol)}` : undefined,
      }));
  }, [dependencies.products, currencySymbol]);

  // Handle item updates
  const handleItemChange = (index: number, updatedFields: Partial<QuotationItemRow>) => {
    const newItems = [...items];
    const current = newItems[index];

    // If product changed, auto-fill unit, sell_price, etc.
    if (updatedFields.product_id !== undefined && updatedFields.product_id !== current.product_id) {
      const prod = dependencies.products?.find((p) => p.id === Number(updatedFields.product_id));
      if (prod) {
        updatedFields.product_sku = prod.sku;
        updatedFields.product_name = prod.name;
        updatedFields.unit = prod.uom?.abbreviation || 'PCS';
        if (current.unit_price === 0 || !current.unit_price) {
          updatedFields.unit_price = prod.sell_price || 0;
        }
      }
    }

    newItems[index] = { ...current, ...updatedFields };
    onChangeItems(newItems);
  };

  // Handle item deletion
  const handleDeleteItem = (index: number) => {
    if (items.length <= 1) {
      alert('Minimal harus ada 1 item dalam quotation.');
      return;
    }
    const newItems = items.filter((_, i) => i !== index);
    onChangeItems(newItems);
    if (activeItemRowId === items[index]?.row_id) {
      setActiveItemRowId(newItems[0]?.row_id || null);
    }
  };

  const toggleAccordion = (rowId: string) => {
    setActiveItemRowId(activeItemRowId === rowId ? null : rowId);
  };

  return (
    <div className="space-y-3 mb-8">
      {items.map((item, index) => {
        const isOpen = activeItemRowId === item.row_id;
        const preview = calculateItemPreview(
          item.quantity,
          item.unit_price,
          item.discount,
          item.tax,
          item.tax_type
        );

        const rowErrorKey = `items.${index}`;
        const hasRowError = Object.keys(errors).some((k) => k.startsWith(rowErrorKey));

        return (
          <div
            key={item.row_id}
            className={`border rounded-xl transition-all duration-200 overflow-hidden ${
              isOpen
                ? 'border-primary-400 ring-2 ring-primary-500/10 shadow-md bg-white'
                : 'border-gray-200 bg-white hover:border-gray-300 shadow-xs'
            } ${hasRowError ? 'border-red-400 ring-1 ring-red-400' : ''}`}
          >
            {/* Accordion Row Header */}
            <div
              onClick={() => toggleAccordion(item.row_id)}
              className="px-4 py-3 bg-gray-50/70 hover:bg-gray-100/60 cursor-pointer flex items-center justify-between transition-colors select-none"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div
                  className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold transition-colors ${
                    isOpen ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700'
                  }`}
                >
                  <ChevronDown
                    className={`w-4 h-4 transition-transform duration-200 ${
                      isOpen ? 'rotate-180' : ''
                    }`}
                  />
                </div>

                <div className="flex items-center gap-2 truncate">
                  <span className="w-5 h-5 rounded bg-primary-50 text-primary-700 text-xs font-bold flex items-center justify-center">
                    {index + 1}
                  </span>
                  <span className="font-semibold text-sm text-gray-900 truncate">
                    {item.product_name ? (
                      <>
                        <span className="tabular-nums text-gray-500 mr-1.5">({item.product_sku})</span>
                        {item.product_name}
                      </>
                    ) : (
                      <span className="text-gray-400 italic">Belum memilih produk</span>
                    )}
                  </span>
                </div>
              </div>

              {/* Header Right: Qty, Price, Subtotal & Delete */}
              <div className="flex items-center gap-4 shrink-0">
                <div className="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                  <span>
                    Qty: <b className="text-gray-800">{item.quantity} {item.unit || 'PCS'}</b>
                  </span>
                  <span>•</span>
                  <span>
                    Harga: <b className="text-gray-800">{formatCurrency(item.unit_price, currencySymbol)}</b>
                  </span>
                  {item.discount > 0 && (
                    <>
                      <span>•</span>
                      <span className="text-amber-600 font-semibold">Disc: {item.discount}%</span>
                    </>
                  )}
                </div>

                <div className="text-right">
                  <span className="text-xs text-gray-400 block font-normal">Subtotal</span>
                  <span className="text-sm font-bold text-primary-700">
                    {formatCurrency(preview.subtotal, currencySymbol)}
                  </span>
                </div>

                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleDeleteItem(index);
                  }}
                  className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                  title="Hapus Item"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Accordion Body: Interactive Form Fields */}
            {isOpen && (
              <div className="p-4 border-t border-gray-100 bg-white space-y-4 animate-in fade-in-50 duration-150">
                {/* Product Selection */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Produk <span className="text-red-500">*</span>
                  </label>
                  <SearchableSelect
                    options={productOptions}
                    value={item.product_id}
                    placeholder="-- Pilih Produk --"
                    hasError={!!errors[`items.${index}.product_id`]}
                    onChange={(val) => handleItemChange(index, { product_id: val ? Number(val) : null })}
                  />
                  {errors[`items.${index}.product_id`] && (
                    <p className="text-xs text-red-500 mt-1">{errors[`items.${index}.product_id`][0]}</p>
                  )}
                </div>

                {/* Numbers Grid: Qty (2), Satuan (1), Harga Satuan (3), Diskon (2), Tipe Pajak (4) - 12-Col Responsive Grid */}
                <div className="grid grid-cols-2 md:grid-cols-12 gap-3 items-end">
                  {/* Qty (col-span-2) */}
                  <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Qty <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      min="0.001"
                      step="any"
                      value={item.quantity === 0 ? '' : item.quantity}
                      onChange={(e) =>
                        handleItemChange(index, {
                          quantity: parseFloat(e.target.value) || 0,
                        })
                      }
                      className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none"
                      placeholder="1"
                    />
                  </div>

                  {/* Satuan UOM (col-span-1 - Read-only text badge) */}
                  <div className="col-span-1 md:col-span-1">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Satuan
                    </label>
                    <div className="px-2.5 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 font-medium text-center truncate">
                      {item.unit || 'PCS'}
                    </div>
                  </div>

                  {/* Harga Satuan (col-span-3 - Live Rupiah Currency formatting) */}
                  <div className="col-span-2 md:col-span-3">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Harga Satuan ({currencySymbol}) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={
                        focusedPriceRowId === item.row_id
                          ? item.unit_price || ''
                          : item.unit_price ? formatNumber(item.unit_price, 2) : ''
                      }
                      onFocus={() => setFocusedPriceRowId(item.row_id)}
                      onBlur={() => setFocusedPriceRowId(null)}
                      onChange={(e) => {
                        const raw = e.target.value.replace(/[^0-9.]/g, '');
                        handleItemChange(index, { unit_price: parseFloat(raw) || 0 });
                      }}
                      className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none tabular-nums"
                      placeholder="0,00"
                    />
                  </div>

                  {/* Diskon (%) (col-span-2) */}
                  <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Diskon (%)
                    </label>
                    <div className="relative">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={item.discount === 0 ? '' : item.discount}
                        onChange={(e) =>
                          handleItemChange(index, {
                            discount: Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)),
                          })
                        }
                        className="w-full px-3 py-2 pr-7 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none"
                        placeholder="0"
                      />
                      <span className="absolute right-2.5 top-2 text-xs text-gray-400">%</span>
                    </div>
                  </div>

                  {/* Tipe Pajak (col-span-4 - wide container so PPN text is never clipped) */}
                  <div className="col-span-1 md:col-span-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Tipe Pajak
                    </label>
                    <select
                      value={item.tax_type}
                      onChange={(e) => {
                        const newType = e.target.value;
                        const newTax = newType === 'None' ? 0 : 11;
                        handleItemChange(index, { tax_type: newType, tax: newTax });
                      }}
                      className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none bg-white"
                    >
                      <option value="None">None (0%)</option>
                      <option value="Inklusif">PPN Inklusif (11%)</option>
                      <option value="Eksklusif">PPN Eksklusif (11%)</option>
                    </select>
                  </div>
                </div>

                {/* Pure Text Calculation Breakdown (Strictly NO fake input boxes) */}
                <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                  <div>
                    <span className="text-gray-500 block mb-0.5">Total Kotor:</span>
                    <span className="text-gray-900 font-semibold tabular-nums text-sm">
                      {formatCurrency(preview.total, currencySymbol)}
                    </span>
                  </div>

                  {item.discount > 0 && (
                    <div>
                      <span className="text-gray-500 block mb-0.5">Potongan Diskon ({item.discount}%):</span>
                      <span className="text-amber-700 font-semibold tabular-nums text-sm">
                        - {formatCurrency(preview.discount_nominal, currencySymbol)}
                      </span>
                    </div>
                  )}

                  {item.tax_type !== 'None' && preview.tax_nominal > 0 && (
                    <div>
                      <span className="text-gray-500 block mb-0.5">Nominal PPN ({item.tax}%):</span>
                      <span className="text-blue-700 font-semibold tabular-nums text-sm">
                        + {formatCurrency(preview.tax_nominal, currencySymbol)}
                      </span>
                    </div>
                  )}
                </div>

                {/* Item Notes */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Catatan Item (Opsional)
                  </label>
                  <input
                    type="text"
                    value={item.notes || ''}
                    onChange={(e) => handleItemChange(index, { notes: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none"
                    placeholder="Contoh: Spesifikasi khusus, garansi, dsb."
                  />
                </div>
              </div>
            )}
          </div>
        );
      })}

      {/* Tombol Tambah Item di Bawah Card Item */}
      {onAddItem && (
        <div className="pt-2">
          <button
            type="button"
            onClick={onAddItem}
            className="w-full py-2.5 px-4 bg-white hover:bg-blue-50/50 active:bg-blue-100/60 border border-dashed border-gray-300 hover:border-blue-400 text-gray-700 hover:text-blue-700 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs group"
          >
            <div className="w-5 h-5 rounded-full bg-blue-50 group-hover:bg-blue-600 text-blue-600 group-hover:text-white flex items-center justify-center transition-colors">
              <Plus className="w-3.5 h-3.5" />
            </div>
            <span>Tambah Item Penawaran Baru</span>
          </button>
        </div>
      )}
    </div>
  );
};
