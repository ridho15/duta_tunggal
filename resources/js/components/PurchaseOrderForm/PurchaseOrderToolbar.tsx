import React, { useState, useMemo } from 'react';
import {
  Search,
  Filter,
  Plus,
  ChevronsUpDown,
  X,
  Layers,
  Building2,
  Users,
} from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import { PurchaseOrderDependencies } from './types';

interface Props {
  searchQuery: string;
  onSearchChange: (q: string) => void;
  dependencies: PurchaseOrderDependencies;
  onAddItem: () => void;
  onToggleCollapseAll: () => void;
  isAllCollapsed: boolean;
  onBulkSetSupplier: (supplierId: number | null) => void;
  onBulkSetCabang: (cabangId: number | null) => void;
  selectedCount?: number;
  totalCount: number;
  isOrderRequestReference?: boolean;
}

export const PurchaseOrderToolbar: React.FC<Props> = ({
  searchQuery,
  onSearchChange,
  dependencies,
  onAddItem,
  onToggleCollapseAll,
  isAllCollapsed,
  onBulkSetSupplier,
  onBulkSetCabang,
  selectedCount = 0,
  totalCount,
  isOrderRequestReference = false,
}) => {
  const [showFilters, setShowFilters] = useState(false);
  const [bulkSupplierTarget, setBulkSupplierTarget] = useState<number | null>(null);
  const [bulkCabangTarget, setBulkCabangTarget] = useState<number | null>(null);

  const supplierOptions: SelectOption[] = useMemo(() => {
    return (dependencies.suppliers || [])
      .map((s) => ({
        value: s.id,
        label: `(${s.code}) ${s.perusahaan}`,
        sublabel: s.phone ? `Tel: ${s.phone}` : undefined,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.suppliers]);

  const cabangOptions: SelectOption[] = useMemo(() => {
    return (dependencies.cabangs || [])
      .map((c) => ({
        value: c.id,
        label: `(${c.kode}) ${c.nama}`,
        badge: c.kode,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.cabangs]);

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4 space-y-3">
      {/* Top Row: Search, Collapse All, Tambah Item */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2 flex-1 min-w-[260px]">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder="Cari item / produk / supplier..."
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

        {isOrderRequestReference ? (
          <span className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-xs font-semibold shadow-xs">
            Item Terkunci dari Order Request
          </span>
        ) : (
          <button
            type="button"
            onClick={onAddItem}
            className="flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all shrink-0"
          >
            <Plus className="w-4 h-4" />
            <span>Tambah Item</span>
          </button>
        )}
      </div>

      {/* Bulk Action Bar */}
      <div className="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 text-xs">
        <span className="font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-1">
          <Layers className="w-3.5 h-3.5" /> Aksi Massal Item:
        </span>

        {/* Bulk Supplier */}
        <div className="flex items-center gap-1.5 min-w-[320px]">
          <div className="w-60 min-w-[240px]">
            <SearchableSelect
              options={supplierOptions}
              value={bulkSupplierTarget}
              placeholder="Pilih supplier target"
              onChange={(val) => setBulkSupplierTarget(val ? Number(val) : null)}
            />
          </div>
          <button
            type="button"
            disabled={!bulkSupplierTarget}
            onClick={() => {
              if (bulkSupplierTarget) {
                onBulkSetSupplier(bulkSupplierTarget);
                setBulkSupplierTarget(null);
              }
            }}
            className="px-2.5 py-1.5 bg-gray-100 hover:bg-blue-50 hover:text-blue-600 disabled:opacity-40 border border-gray-300 rounded-lg font-medium text-gray-700 transition-colors shrink-0"
          >
            Terapkan Supplier
          </button>
        </div>

        {/* Bulk Cabang */}
        <div className="flex items-center gap-1.5 min-w-[320px]">
          <div className="w-60 min-w-[240px]">
            <SearchableSelect
              options={cabangOptions}
              value={bulkCabangTarget}
              placeholder="Pilih cabang target"
              onChange={(val) => setBulkCabangTarget(val ? Number(val) : null)}
            />
          </div>
          <button
            type="button"
            disabled={!bulkCabangTarget}
            onClick={() => {
              if (bulkCabangTarget) {
                onBulkSetCabang(bulkCabangTarget);
                setBulkCabangTarget(null);
              }
            }}
            className="px-2.5 py-1.5 bg-gray-100 hover:bg-blue-50 hover:text-blue-600 disabled:opacity-40 border border-gray-300 rounded-lg font-medium text-gray-700 transition-colors shrink-0"
          >
            Terapkan Cabang
          </button>
        </div>

        <div className="ml-auto text-gray-500 shrink-0">
          Total: <strong className="text-gray-900">{totalCount}</strong> item
        </div>
      </div>
    </div>
  );
};
