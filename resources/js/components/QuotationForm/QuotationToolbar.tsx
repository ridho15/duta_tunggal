import React, { useState } from 'react';
import { Search, Plus, ChevronsUpDown, Layers, CheckCircle2 } from 'lucide-react';

interface Props {
  searchQuery: string;
  onSearchChange: (query: string) => void;
  onAddItem: () => void;
  onBulkSetTaxType: (taxType: string) => void;
  onBulkSetDiscount: (discount: number) => void;
  itemCount: number;
}

export const QuotationToolbar: React.FC<Props> = ({
  searchQuery,
  onSearchChange,
  onAddItem,
  onBulkSetTaxType,
  onBulkSetDiscount,
  itemCount,
}) => {
  const [bulkTaxType, setBulkTaxType] = useState<string>('None');
  const [bulkDiscount, setBulkDiscount] = useState<number>(0);

  return (
    <div className="space-y-3 mb-4">
      {/* Top Bar: Search & Action Buttons */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        {/* Search Bar */}
        <div className="relative flex-1">
          <Search className="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder="Cari nama produk, SKU..."
            className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none bg-white"
          />
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            type="button"
            onClick={onAddItem}
            className="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg flex items-center gap-1.5 shadow-xs transition-colors"
          >
            <Plus className="w-4 h-4" />
            <span>Tambah Item</span>
          </button>
        </div>
      </div>

      {/* Bulk Action Bar */}
      <div className="p-3 bg-gray-50 border border-gray-200 rounded-xl flex flex-wrap items-center justify-between gap-3 text-xs">
        <div className="flex items-center gap-2 text-gray-700 font-medium">
          <Layers className="w-4 h-4 text-primary-600" />
          <span>Aksi Massal Item:</span>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          {/* Bulk Tax Type */}
          <div className="flex items-center gap-1.5">
            <select
              value={bulkTaxType}
              onChange={(e) => setBulkTaxType(e.target.value)}
              className="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none"
            >
              <option value="None">Pajak: None (0%)</option>
              <option value="Inklusif">Pajak: PPN Inklusif (11%)</option>
              <option value="Eksklusif">Pajak: PPN Eksklusif (11%)</option>
            </select>
            <button
              type="button"
              onClick={() => onBulkSetTaxType(bulkTaxType)}
              className="px-2.5 py-1.5 bg-white border border-gray-300 hover:bg-gray-100 rounded-lg font-medium text-gray-700 transition-colors"
            >
              Terapkan
            </button>
          </div>

          {/* Bulk Discount */}
          <div className="flex items-center gap-1.5">
            <input
              type="number"
              min="0"
              max="100"
              value={bulkDiscount}
              onChange={(e) => setBulkDiscount(Math.min(100, Math.max(0, parseInt(e.target.value) || 0)))}
              className="w-16 px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white text-center focus:outline-none"
              placeholder="0%"
            />
            <button
              type="button"
              onClick={() => onBulkSetDiscount(bulkDiscount)}
              className="px-2.5 py-1.5 bg-white border border-gray-300 hover:bg-gray-100 rounded-lg font-medium text-gray-700 transition-colors"
            >
              Set Diskon
            </button>
          </div>

          <span className="text-gray-400">|</span>
          <span className="text-gray-500 font-semibold">
            Total: <b className="text-gray-900">{itemCount}</b> item
          </span>
        </div>
      </div>
    </div>
  );
};
