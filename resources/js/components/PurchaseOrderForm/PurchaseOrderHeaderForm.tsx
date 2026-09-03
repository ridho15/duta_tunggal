import React, { useMemo } from 'react';
import { RefreshCw, Link as LinkIcon, Building2, Calendar, FileText, CheckCircle2, Lock } from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import {
  PurchaseOrderHeader,
  PurchaseOrderDependencies,
  TopType,
  OrderRequestRefOption,
  SalesOrderRefOption,
} from './types';

interface Props {
  header: PurchaseOrderHeader;
  dependencies: PurchaseOrderDependencies;
  onChange: (header: PurchaseOrderHeader) => void;
  onGenerateNumber: () => void;
  onSelectReference: (type: string | null, id: number | null) => void;
  isGeneratingNumber?: boolean;
  errors?: Record<string, string[]>;
}

export const PurchaseOrderHeaderForm: React.FC<Props> = ({
  header,
  dependencies,
  onChange,
  onGenerateNumber,
  onSelectReference,
  isGeneratingNumber = false,
  errors = {},
}) => {
  const selectedOr = useMemo(() => {
    if (
      (header.refer_model_type === 'App\\Models\\OrderRequest' || header.refer_model_type === 'OrderRequest') &&
      header.refer_model_id
    ) {
      return dependencies.available_order_requests.find((or) => or.id === header.refer_model_id);
    }
    return null;
  }, [header.refer_model_type, header.refer_model_id, dependencies.available_order_requests]);

  const supplierOptions: SelectOption[] = useMemo(() => {
    let list = dependencies.suppliers || [];

    // When an Order Request is selected, restrict supplier options to ONLY the suppliers in that OR
    if (selectedOr && selectedOr.supplier_ids && selectedOr.supplier_ids.length > 0) {
      list = list.filter((s) => selectedOr.supplier_ids.includes(s.id));
    }

    return list
      .map((s) => ({
        value: s.id,
        label: `(${s.code}) ${s.perusahaan}`,
        sublabel: s.phone ? `Tel: ${s.phone}` : undefined,
        badge: s.tempo_hutang ? `Tempo: ${s.tempo_hutang} hari` : 'COD',
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.suppliers, selectedOr]);

  const cabangOptions: SelectOption[] = useMemo(() => {
    return (dependencies.cabangs || [])
      .map((c) => ({
        value: c.id,
        label: `(${c.kode}) ${c.nama}`,
        badge: c.kode,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [dependencies.cabangs]);

  const orderRequestOptions: SelectOption[] = useMemo(() => {
    return (dependencies.available_order_requests || []).map((or) => ({
      value: or.id,
      label: `${or.request_number} (${or.remaining_items} item tersedia)`,
      sublabel: `Tgl: ${or.request_date}${or.supplier_ids?.length > 1 ? ` • ${or.supplier_ids.length} Supplier Berbeda` : ''}`,
      badge: `${or.remaining_items} Items`,
    }));
  }, [dependencies.available_order_requests]);

  const salesOrderOptions: SelectOption[] = useMemo(() => {
    return (dependencies.available_sales_orders || []).map((so) => ({
      value: so.id,
      label: `${so.so_number} - ${so.customer_name}`,
      sublabel: `Tgl: ${so.order_date}`,
      badge: `${so.total_items} Items`,
    }));
  }, [dependencies.available_sales_orders]);

  const handleSupplierChange = (val: number | string | null) => {
    const supplierId = val ? Number(val) : null;
    const selectedSupplier = dependencies.suppliers.find((s) => s.id === supplierId);

    let topType: TopType = header.top_type;
    let tempoHutang = header.tempo_hutang;

    if (selectedSupplier) {
      if (selectedSupplier.tempo_hutang && selectedSupplier.tempo_hutang > 0) {
        topType = 'credit_days';
        tempoHutang = selectedSupplier.tempo_hutang;
      } else {
        topType = 'cod';
        tempoHutang = 0;
      }
    }

    onChange({
      ...header,
      supplier_id: supplierId,
      top_type: topType,
      tempo_hutang: tempoHutang,
    });
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
      <div className="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
        <h3 className="text-base font-semibold text-gray-950 flex items-center gap-2">
          <FileText className="w-5 h-5 text-blue-600" />
          Form Pembelian (Purchase Order)
        </h3>
        {header.status && (
          <span className="px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
            Status: {header.status}
          </span>
        )}
      </div>

      {/* Row 1: Reference Selector */}
      <div className="bg-gray-50/80 p-3.5 rounded-lg border border-gray-200/80 mb-4">
        <div className="text-sm font-medium text-gray-700 mb-2 flex items-center gap-1.5">
          <LinkIcon className="w-4 h-4 text-blue-500" />
          Referensi Dokumen (Opsional)
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
          <div>
            <div className="flex items-center gap-4 text-sm text-gray-700">
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="refer_model_type"
                  checked={!header.refer_model_type}
                  onChange={() => onSelectReference(null, null)}
                  className="text-blue-600 focus:ring-blue-500"
                />
                <span>Tanpa Referensi</span>
              </label>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="refer_model_type"
                  checked={
                    header.refer_model_type === 'App\\Models\\OrderRequest' ||
                    header.refer_model_type === 'OrderRequest'
                  }
                  onChange={() => onSelectReference('OrderRequest', null)}
                  className="text-blue-600 focus:ring-blue-500"
                />
                <span>Order Request (OR)</span>
              </label>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="refer_model_type"
                  checked={
                    header.refer_model_type === 'App\\Models\\SaleOrder' ||
                    header.refer_model_type === 'SaleOrder'
                  }
                  onChange={() => onSelectReference('SaleOrder', null)}
                  className="text-blue-600 focus:ring-blue-500"
                />
                <span>Sales Order (SO)</span>
              </label>
            </div>
          </div>

          {(header.refer_model_type === 'App\\Models\\OrderRequest' ||
            header.refer_model_type === 'OrderRequest') && (
            <div className="md:col-span-2 flex items-center gap-2">
              <div className="flex-1">
                <SearchableSelect
                  options={orderRequestOptions}
                  value={header.refer_model_id}
                  placeholder="-- Pilih Order Request Referensi --"
                  onChange={(val) => onSelectReference('OrderRequest', val ? Number(val) : null)}
                />
              </div>
            </div>
          )}

          {(header.refer_model_type === 'App\\Models\\SaleOrder' ||
            header.refer_model_type === 'SaleOrder') && (
            <div className="md:col-span-2 flex items-center gap-2">
              <div className="flex-1">
                <SearchableSelect
                  options={salesOrderOptions}
                  value={header.refer_model_id}
                  placeholder="-- Pilih Sales Order Referensi --"
                  onChange={(val) => onSelectReference('SaleOrder', val ? Number(val) : null)}
                />
              </div>
            </div>
          )}
        </div>

        {selectedOr && selectedOr.supplier_ids && selectedOr.supplier_ids.length > 1 && (
          <div className="mt-3 px-3.5 py-2.5 bg-amber-50/90 border border-amber-200/80 rounded-lg text-xs text-amber-800 flex items-center gap-2.5 shadow-sm">
            <Building2 className="w-4 h-4 text-amber-600 flex-shrink-0" />
            <span>
              <strong>Perhatian Multi-Supplier:</strong> Dokumen Order Request ini memuat item dari <strong>{selectedOr.supplier_ids.length} Supplier berbeda</strong>. Pilihan Supplier di bawah telah difilter khusus untuk vendor pada OR ini. Silakan pilih salah satu Supplier untuk memuat itemnya.
            </span>
          </div>
        )}
      </div>

      {/* Row 2: PO Number (3), Supplier (5), Cabang (4) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
        {/* PO Number (col-span-3) */}
        <div className="md:col-span-3">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Nomor PO <span className="text-red-500">*</span>
          </label>
          <div className="flex items-center gap-1.5">
            <input
              type="text"
              value={header.po_number}
              onChange={(e) => onChange({ ...header, po_number: e.target.value })}
              className={`w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all ${
                errors['header.po_number'] ? 'border-red-500 bg-red-50/50' : 'border-gray-300'
              }`}
              placeholder="PO-20260902-0001"
            />
            <button
              type="button"
              onClick={onGenerateNumber}
              disabled={isGeneratingNumber}
              title="Generate Nomor Baru"
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors shrink-0"
            >
              <RefreshCw className={`w-4 h-4 ${isGeneratingNumber ? 'animate-spin' : ''}`} />
            </button>
          </div>
          {errors['header.po_number'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.po_number'][0]}</p>
          )}
        </div>

        {/* Supplier (col-span-5 for wide company name and tempo badge) */}
        <div className="md:col-span-5">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Supplier <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={supplierOptions}
            value={header.supplier_id}
            placeholder="-- Pilih Supplier --"
            hasError={!!errors['header.supplier_id']}
            onChange={handleSupplierChange}
          />
          {errors['header.supplier_id'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.supplier_id'][0]}</p>
          )}
        </div>

        {/* Cabang (col-span-4 - Locked when referencing Order Request) */}
        <div className="md:col-span-4">
          <label className="block text-sm font-medium text-gray-700 mb-1 flex items-center justify-between">
            <span>Cabang <span className="text-red-500">*</span></span>
            {selectedOr && (
              <span className="text-[10px] text-blue-600 font-semibold flex items-center gap-0.5">
                <Lock className="w-2.5 h-2.5" /> Terkunci dari OR
              </span>
            )}
          </label>
          <SearchableSelect
            options={cabangOptions}
            value={header.cabang_id}
            placeholder="-- Pilih Cabang --"
            disabled={!!selectedOr}
            onChange={(val) => onChange({ ...header, cabang_id: val ? Number(val) : null })}
          />
        </div>
      </div>

      {/* Row 3: Dates (4), TOP & Tempo (3), Flags (2), Notes (3) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        {/* Tanggal PO (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Tanggal PO <span className="text-red-500">*</span>
          </label>
          <input
            type="date"
            value={header.order_date}
            onChange={(e) => onChange({ ...header, order_date: e.target.value })}
            className="w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        {/* Estimasi Datang (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Estimasi Datang
          </label>
          <input
            type="date"
            value={header.expected_date || ''}
            onChange={(e) => onChange({ ...header, expected_date: e.target.value || null })}
            className="w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        {/* TOP Type (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Terms of Payment (TOP)
          </label>
          <select
            value={header.top_type}
            onChange={(e) =>
              onChange({
                ...header,
                top_type: e.target.value as TopType,
                tempo_hutang: e.target.value === 'credit_days' ? header.tempo_hutang || 30 : 0,
              })
            }
            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            {dependencies.top_types.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
        </div>

        {/* Tempo Hutang (col-span-1) */}
        <div className="col-span-1 md:col-span-1">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Tempo (Hari)
          </label>
          <input
            type="number"
            min="0"
            disabled={header.top_type !== 'credit_days'}
            value={header.tempo_hutang || 0}
            onChange={(e) => onChange({ ...header, tempo_hutang: Number(e.target.value) || 0 })}
            className={`w-full px-2 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none ${
              header.top_type !== 'credit_days' ? 'bg-gray-100 text-gray-500' : 'border-gray-300'
            }`}
            placeholder="30"
          />
        </div>

        {/* Flags: Is Asset / Is Import (col-span-2) */}
        <div className="col-span-1 md:col-span-2 flex items-center gap-3 pt-6">
          <label className="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700 font-medium select-none">
            <input
              type="checkbox"
              checked={header.is_asset}
              onChange={(e) => onChange({ ...header, is_asset: e.target.checked })}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
            />
            <span>Beli Aset</span>
          </label>
          <label className="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700 font-medium select-none">
            <input
              type="checkbox"
              checked={header.is_import}
              onChange={(e) => onChange({ ...header, is_import: e.target.checked })}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
            />
            <span>Impor</span>
          </label>
        </div>

        {/* Notes (col-span-3) */}
        <div className="col-span-1 md:col-span-3">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Catatan Dokumen
          </label>
          <textarea
            rows={1}
            value={header.note}
            onChange={(e) => onChange({ ...header, note: e.target.value })}
            className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
            placeholder="Catatan tambahan untuk pesanan pembelian ini..."
          />
        </div>
      </div>
    </div>
  );
};
