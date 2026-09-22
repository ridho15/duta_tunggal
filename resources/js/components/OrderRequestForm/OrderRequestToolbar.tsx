import React, { useState } from 'react';
import { FormDependencies, TaxType } from './types';
import { SearchableSelect } from './SearchableSelect';
import {
  Search,
  Filter,
  ChevronsDownUp,
  ChevronsUpDown,
  ShieldCheck,
  XCircle,
  FileEdit,
  X,
} from 'lucide-react';

interface Props {
  dependencies: FormDependencies | null;
  searchQuery: string;
  onSearchChange: (val: string) => void;
  supplierFilter: number | null;
  onSupplierFilterChange: (val: number | null) => void;
  cabangFilter: number | null;
  onCabangFilterChange: (val: number | null) => void;
  taxFilter: TaxType | 'all';
  onTaxFilterChange: (val: TaxType | 'all') => void;
  allCollapsed: boolean;
  onToggleCollapseAll: () => void;
  onAddItem?: () => void;
  selectedCount: number;
  onBulkSetSupplier: (supplierId: number) => void;
  onBulkSetCabang: (cabangId: number) => void;
  onBulkApprove: () => void;
  onBulkReject: () => void;
  onBulkSetDraft: () => void;
  onClearSelection: () => void;
  disabled?: boolean;
}

export const OrderRequestToolbar: React.FC<Props> = ({
  dependencies,
  searchQuery,
  onSearchChange,
  supplierFilter,
  onSupplierFilterChange,
  cabangFilter,
  onCabangFilterChange,
  taxFilter,
  onTaxFilterChange,
  allCollapsed,
  onToggleCollapseAll,
  onAddItem,
  selectedCount,
  onBulkSetSupplier,
  onBulkSetCabang,
  onBulkApprove,
  onBulkReject,
  onBulkSetDraft,
  onClearSelection,
  disabled = false,
}) => {
  const [showFilterDrawer, setShowFilterDrawer] = useState(false);
  const [bulkSupplierTarget, setBulkSupplierTarget] = useState<number | null>(null);
  const [bulkCabangTarget, setBulkCabangTarget] = useState<number | null>(null);

  const activeFilterCount =
    (searchQuery ? 1 : 0) +
    (supplierFilter ? 1 : 0) +
    (cabangFilter ? 1 : 0) +
    (taxFilter !== 'all' ? 1 : 0);

  const handleApplyBulkSupplier = () => {
    if (bulkSupplierTarget) {
      onBulkSetSupplier(Number(bulkSupplierTarget));
      setBulkSupplierTarget(null);
    }
  };

  const handleApplyBulkCabang = () => {
    if (bulkCabangTarget) {
      onBulkSetCabang(Number(bulkCabangTarget));
      setBulkCabangTarget(null);
    }
  };

  const supplierOptions = (dependencies?.suppliers || [])
    .slice()
    .sort((a, b) => a.perusahaan.localeCompare(b.perusahaan, 'id'))
    .map((s) => ({
      value: s.id,
      label: `(${s.code}) ${s.perusahaan}`,
    }));

  const cabangOptions = (dependencies?.cabangs || [])
    .slice()
    .sort((a, b) => a.nama.localeCompare(b.nama, 'id'))
    .map((c) => ({
      value: c.id,
      label: `(${c.kode}) ${c.nama}`,
      badge: c.kode,
    }));

  return (
    <div className="space-y-3">
      {/* 1. Panel Header & Main Toolbar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2">
        <h3 className="text-sm font-bold text-gray-900 flex items-center gap-1">
          Order request Item <span className="text-rose-500">*</span>
        </h3>

        <div className="flex items-center gap-2 flex-wrap">
          {/* Search Box */}
          <div className="relative min-w-[240px] sm:w-72">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder="Search item / product / supplier"
              disabled={disabled}
              className="w-full h-[36px] pl-9 pr-3 text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 placeholder-gray-400 transition-colors"
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

          {/* Filter Drawer Toggle Button */}
          <button
            type="button"
            onClick={() => setShowFilterDrawer(!showFilterDrawer)}
            disabled={disabled}
            className={`inline-flex items-center gap-1.5 h-[36px] px-3 border rounded-lg text-xs font-semibold transition-colors ${
              showFilterDrawer || activeFilterCount > 0
                ? 'bg-blue-50 border-blue-300 text-blue-700'
                : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            <Filter className="w-3.5 h-3.5 text-gray-500" />
            <span>Filter</span>
            <span
              className={`inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold ${
                activeFilterCount > 0
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-600 border border-gray-200'
              }`}
            >
              {activeFilterCount}
            </span>
          </button>

          {/* Collapse/Expand All Button */}
          <button
            type="button"
            onClick={onToggleCollapseAll}
            disabled={disabled}
            className="inline-flex items-center gap-1.5 h-[36px] px-3 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-xs font-semibold transition-colors"
          >
            {allCollapsed ? (
              <>
                <ChevronsUpDown className="w-3.5 h-3.5 text-gray-500" />
                <span>Expand All</span>
              </>
            ) : (
              <>
                <ChevronsDownUp className="w-3.5 h-3.5 text-gray-500" />
                <span>Collapse All</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* 2. Collapsible Filter Drawer */}
      {showFilterDrawer && (
        <div className="p-3.5 bg-gray-50 border border-gray-200 rounded-xl grid grid-cols-1 sm:grid-cols-3 gap-3 animate-in fade-in slide-in-from-top-1">
          <div>
            <label className="block text-[11px] font-bold text-gray-700 mb-1">Filter Supplier</label>
            <SearchableSelect
              options={supplierOptions}
              value={supplierFilter}
              placeholder="Semua Supplier"
              onChange={(val) => onSupplierFilterChange(val ? Number(val) : null)}
              disabled={disabled}
            />
          </div>

          <div>
            <label className="block text-[11px] font-bold text-gray-700 mb-1">Filter Cabang</label>
            <SearchableSelect
              options={cabangOptions}
              value={cabangFilter}
              placeholder="Semua Cabang"
              onChange={(val) => onCabangFilterChange(val ? Number(val) : null)}
              disabled={disabled}
            />
          </div>

          <div>
            <label className="block text-[11px] font-bold text-gray-700 mb-1">Filter Tipe Pajak</label>
            <select
              value={taxFilter}
              onChange={(e) => onTaxFilterChange(e.target.value as TaxType | 'all')}
              className="w-full h-[38px] px-2.5 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="all">Semua Tipe Pajak</option>
              <option value="eklusif">PPN Excluded (Eksklusif)</option>
              <option value="inklusif">PPN Included (Inklusif)</option>
              <option value="none">Non PPN (0%)</option>
            </select>
          </div>
        </div>
      )}

      {/* 3. Bulk Actions Toolbar */}
      <div className="pt-2 pb-1 border-t border-gray-100 flex flex-wrap items-center gap-2">
        {/* Set Supplier with SearchableSelect */}
        <div className="flex items-center gap-1.5">
          <div className="w-56">
            <SearchableSelect
              options={supplierOptions}
              value={bulkSupplierTarget}
              placeholder="Pilih supplier target"
              searchPlaceholder="Cari supplier target..."
              onChange={(val) => setBulkSupplierTarget(val ? Number(val) : null)}
              disabled={disabled || selectedCount === 0}
            />
          </div>
          <button
            type="button"
            onClick={handleApplyBulkSupplier}
            disabled={disabled || selectedCount === 0 || !bulkSupplierTarget}
            className="h-[38px] px-3 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold disabled:opacity-50 transition-colors shadow-2xs whitespace-nowrap"
          >
            Set Supplier
          </button>
        </div>

        {/* Set Cabang with SearchableSelect */}
        <div className="flex items-center gap-1.5">
          <div className="w-56">
            <SearchableSelect
              options={cabangOptions}
              value={bulkCabangTarget}
              placeholder="Pilih cabang target"
              searchPlaceholder="Cari cabang target..."
              onChange={(val) => setBulkCabangTarget(val ? Number(val) : null)}
              disabled={disabled || selectedCount === 0}
            />
          </div>
          <button
            type="button"
            onClick={handleApplyBulkCabang}
            disabled={disabled || selectedCount === 0 || !bulkCabangTarget}
            className="h-[38px] px-3 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold disabled:opacity-50 transition-colors shadow-2xs whitespace-nowrap"
          >
            Set Cabang
          </button>
        </div>

        {/* Action Buttons */}
        <button
          type="button"
          onClick={onBulkApprove}
          disabled={disabled || selectedCount === 0}
          className="inline-flex items-center gap-1 h-[32px] px-2.5 bg-white hover:bg-emerald-50 border border-gray-300 text-emerald-700 rounded-lg text-xs font-semibold disabled:opacity-50 transition-colors"
        >
          <ShieldCheck className="w-3.5 h-3.5 text-blue-600" />
          <span>Approve Selected</span>
        </button>

        <button
          type="button"
          onClick={onBulkReject}
          disabled={disabled || selectedCount === 0}
          className="h-[32px] px-2.5 bg-white hover:bg-rose-50 border border-gray-300 text-rose-700 rounded-lg text-xs font-semibold disabled:opacity-50 transition-colors"
        >
          Reject Selected
        </button>

        <button
          type="button"
          onClick={onBulkSetDraft}
          disabled={disabled || selectedCount === 0}
          className="h-[32px] px-2.5 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold disabled:opacity-50 transition-colors"
        >
          Set Draft
        </button>

        {selectedCount > 0 && (
          <button
            type="button"
            onClick={onClearSelection}
            disabled={disabled}
            className="h-[32px] px-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition-colors"
          >
            Clear Selection ({selectedCount})
          </button>
        )}
      </div>

      {/* 4. Active Filters Status Row */}
      <div className="text-[11px] text-gray-500 flex items-center gap-2 py-1">
        <span className="font-semibold text-gray-600">Active filters:</span>
        {activeFilterCount === 0 ? (
          <span className="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
            Tidak ada filter aktif
          </span>
        ) : (
          <div className="flex items-center gap-1.5 flex-wrap">
            {searchQuery && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                Pencarian: &quot;{searchQuery}&quot;
                <button type="button" onClick={() => onSearchChange('')}>
                  <X className="w-3 h-3" />
                </button>
              </span>
            )}
            {supplierFilter && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                Supplier: {dependencies?.suppliers.find((s) => s.id === supplierFilter)?.perusahaan}
                <button type="button" onClick={() => onSupplierFilterChange(null)}>
                  <X className="w-3 h-3" />
                </button>
              </span>
            )}
            {cabangFilter && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                Cabang: {dependencies?.cabangs.find((c) => c.id === cabangFilter)?.nama}
                <button type="button" onClick={() => onCabangFilterChange(null)}>
                  <X className="w-3 h-3" />
                </button>
              </span>
            )}
            {taxFilter !== 'all' && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                Tipe Pajak: {taxFilter.toUpperCase()}
                <button type="button" onClick={() => onTaxFilterChange('all')}>
                  <X className="w-3 h-3" />
                </button>
              </span>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
