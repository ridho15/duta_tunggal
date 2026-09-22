import React, { useState } from 'react';
import {
  Search,
  ChevronsUpDown,
  X,
  Layers,
} from 'lucide-react';
import { PurchaseOrderDependencies, TaxType } from './types';

interface Props {
  searchQuery: string;
  onSearchChange: (q: string) => void;
  dependencies: PurchaseOrderDependencies;
  onAddItem?: () => void;
  onToggleCollapseAll: () => void;
  isAllCollapsed: boolean;
  onBulkSetTaxType?: (taxType: string) => void;
  onBulkSetDiscount?: (discount: number) => void;
  isOrderRequestReference?: boolean;
}

export const PurchaseOrderToolbar: React.FC<Props> = ({
  searchQuery,
  onSearchChange,
  dependencies,
  onAddItem,
  onToggleCollapseAll,
  isAllCollapsed,
  onBulkSetTaxType,
  onBulkSetDiscount,
  isOrderRequestReference = false,
}) => {
  const [bulkTaxType, setBulkTaxType] = useState<string>('eklusif');
  const [bulkDiscount, setBulkDiscount] = useState<number>(0);

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4 space-y-3">
      {/* Top Row: Search, Collapse All, Locked Indicator */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2 flex-1 min-w-[260px]">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder="Cari item / produk..."
              className="w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />
            {searchQuery && (
              <button
                type="button"
                onClick={() => onSearchChange('')}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={onToggleCollapseAll}
            className="flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors shrink-0"
          >
            <ChevronsUpDown className="w-4 h-4 text-gray-500" />
            <span>{isAllCollapsed ? 'Buka Semua' : 'Tutup Semua'}</span>
          </button>
        </div>

        {isOrderRequestReference && (
          <span className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-xs font-semibold shadow-xs shrink-0">
            Item Terkunci dari Order Request
          </span>
        )}
      </div>

      {/* Bulk Action Bar (Tax & Discount, hidden if OR locked) */}
      {!isOrderRequestReference && onBulkSetTaxType && onBulkSetDiscount && (
        <div className="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 text-xs">
          <span className="font-medium text-gray-700 flex items-center gap-1">
            <Layers className="w-3.5 h-3.5 text-blue-600" /> Aksi Massal Item:
          </span>

          <div className="flex flex-wrap items-center gap-3">
            {/* Bulk Tax Type */}
            <div className="flex items-center gap-1.5">
              <select
                value={bulkTaxType}
                onChange={(e) => setBulkTaxType(e.target.value)}
                className="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none"
              >
                <option value="none">Pajak: None (0%)</option>
                <option value="inklusif">Pajak: PPN Inklusif (11%)</option>
                <option value="eklusif">Pajak: PPN Eksklusif (11%)</option>
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
          </div>
        </div>
      )}
    </div>
  );
};
