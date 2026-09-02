import React, { useMemo } from 'react';
import { RefreshCw, FileText, Calendar, Building2, User, DollarSign, Clock } from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import { QuotationHeader, QuotationDependencies, CustomerOption, CabangOption, CurrencyOption } from './types';

interface Props {
  header: QuotationHeader;
  dependencies: QuotationDependencies;
  onChange: (header: QuotationHeader) => void;
  onGenerateNumber: () => void;
  isGeneratingNumber: boolean;
  errors?: Record<string, string[]>;
}

export const QuotationHeaderForm: React.FC<Props> = ({
  header,
  dependencies,
  onChange,
  onGenerateNumber,
  isGeneratingNumber,
  errors = {},
}) => {
  // Alphabetically sorted customer options
  const customerOptions: SelectOption[] = useMemo(() => {
    return (dependencies.customers || [])
      .slice()
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((c) => ({
        value: c.id,
        label: c.name,
        badge: c.code,
        sublabel: c.perusahaan ? c.perusahaan : undefined,
      }));
  }, [dependencies.customers]);

  // Alphabetically sorted cabang options
  const cabangOptions: SelectOption[] = useMemo(() => {
    return (dependencies.cabangs || [])
      .slice()
      .sort((a, b) => a.nama.localeCompare(b.nama))
      .map((cb) => ({
        value: cb.id,
        label: cb.nama,
        badge: cb.kode,
        sublabel: cb.alamat || undefined,
      }));
  }, [dependencies.cabangs]);

  // Currency options
  const currencyOptions: SelectOption[] = useMemo(() => {
    return (dependencies.currencies || []).map((curr) => ({
      value: curr.id,
      label: curr.name,
      badge: curr.code,
      sublabel: curr.symbol,
    }));
  }, [dependencies.currencies]);

  // Handle customer selection with automatic payment term auto-fill
  const handleCustomerChange = (customerId: string | number | null) => {
    const id = customerId ? Number(customerId) : null;
    let newTempo = header.tempo_pembayaran;

    if (id) {
      const selectedCustomer = dependencies.customers?.find((c) => c.id === id);
      if (selectedCustomer && selectedCustomer.tempo_kredit !== undefined && selectedCustomer.tempo_kredit > 0) {
        newTempo = selectedCustomer.tempo_kredit;
      }
    }

    onChange({
      ...header,
      customer_id: id,
      tempo_pembayaran: newTempo,
    });
  };

  // Handle currency selection
  const handleCurrencyChange = (currencyId: string | number | null) => {
    const id = currencyId ? Number(currencyId) : dependencies.default_currency_id;
    const selectedCurr = dependencies.currencies?.find((c) => c.id === id);
    const rate = selectedCurr?.to_rupiah ? Number(selectedCurr.to_rupiah) : 1.0;

    onChange({
      ...header,
      currency_id: id,
      exchange_rate: rate,
    });
  };

  const selectedCurrency = dependencies.currencies?.find((c) => c.id === header.currency_id);
  const isForeignCurrency = selectedCurrency && selectedCurrency.code !== 'IDR';

  return (
    <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-xs mb-6">
      {/* Form Header Title */}
      <div className="flex items-center justify-between border-b border-gray-100 pb-4 mb-4">
        <div className="flex items-center gap-2 text-gray-900 font-bold text-base">
          <FileText className="w-5 h-5 text-primary-600" />
          <span>Form Penawaran Harga (Quotation)</span>
        </div>
        {header.status && (
          <span
            className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
              header.status === 'approve'
                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                : header.status === 'reject'
                ? 'bg-rose-50 text-rose-700 border border-rose-200'
                : header.status === 'request_approve'
                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                : 'bg-gray-100 text-gray-700 border border-gray-300'
            }`}
          >
            Status: {header.status}
          </span>
        )}
      </div>

      {/* Row 1: Quotation Number (3), Customer (5), Cabang (4) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
        {/* Quotation Number (col-span-3) */}
        <div className="md:col-span-3">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Nomor Quotation <span className="text-red-500">*</span>
          </label>
          <div className="flex items-center gap-1.5">
            <input
              type="text"
              value={header.quotation_number}
              onChange={(e) => onChange({ ...header, quotation_number: e.target.value })}
              className={`w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all ${
                errors['header.quotation_number'] ? 'border-red-500 bg-red-50/50' : 'border-gray-300'
              }`}
              placeholder="QO-20260902-0001"
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
          {errors['header.quotation_number'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.quotation_number'][0]}</p>
          )}
        </div>

        {/* Customer (col-span-5 for wider customer name, code badge, and corporate info) */}
        <div className="md:col-span-5">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Customer <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={customerOptions}
            value={header.customer_id}
            placeholder="-- Pilih Customer --"
            hasError={!!errors['header.customer_id']}
            onChange={handleCustomerChange}
          />
          {errors['header.customer_id'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.customer_id'][0]}</p>
          )}
        </div>

        {/* Cabang (col-span-4 for branch name and address) */}
        <div className="md:col-span-4">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Cabang <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={cabangOptions}
            value={header.cabang_id}
            placeholder="-- Pilih Cabang --"
            disabled={!dependencies.can_access_all_cabang && !!header.cabang_id}
            hasError={!!errors['header.cabang_id']}
            onChange={(val) => onChange({ ...header, cabang_id: val ? Number(val) : null })}
          />
          {errors['header.cabang_id'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.cabang_id'][0]}</p>
          )}
        </div>
      </div>

      {/* Row 2: Dates (4), Mata Uang (2), Tempo (2), Catatan (4) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        {/* Tanggal Quotation (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tanggal Quotation <span className="text-red-500">*</span>
          </label>
          <input
            type="date"
            value={header.date}
            onChange={(e) => onChange({ ...header, date: e.target.value })}
            className={`w-full px-2.5 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all ${
              errors['header.date'] ? 'border-red-500 bg-red-50/50' : 'border-gray-300'
            }`}
          />
          {errors['header.date'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.date'][0]}</p>
          )}
        </div>

        {/* Valid Until (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Valid Until
          </label>
          <input
            type="date"
            value={header.valid_until || ''}
            onChange={(e) => onChange({ ...header, valid_until: e.target.value })}
            className="w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all"
          />
        </div>

        {/* Currency & Exchange Rate (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Mata Uang <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={currencyOptions}
            value={header.currency_id}
            placeholder="-- Pilih Mata Uang --"
            onChange={handleCurrencyChange}
          />
          {isForeignCurrency && (
            <p className="text-[11px] text-gray-500 mt-1 truncate" title={`Kurs: 1 ${selectedCurrency?.code} = Rp ${header.exchange_rate.toLocaleString('id-ID')}`}>
              Kurs: 1 {selectedCurrency?.code} = Rp {header.exchange_rate.toLocaleString('id-ID')}
            </p>
          )}
        </div>

        {/* Tempo Pembayaran (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tempo Pembayaran (Hari)
          </label>
          <div className="relative">
            <input
              type="number"
              min="0"
              value={header.tempo_pembayaran}
              onChange={(e) => onChange({ ...header, tempo_pembayaran: Math.max(0, parseInt(e.target.value) || 0) })}
              className="w-full px-3 py-2 pr-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all"
              placeholder="0"
            />
            <span className="absolute right-3 top-2 text-xs text-gray-400 font-medium">Hari</span>
          </div>
        </div>

        {/* Catatan Dokumen (col-span-4) */}
        <div className="col-span-1 md:col-span-4">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Catatan Dokumen
          </label>
          <textarea
            rows={1}
            value={header.notes}
            onChange={(e) => onChange({ ...header, notes: e.target.value })}
            className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all resize-none"
            placeholder="Catatan tambahan untuk penawaran harga..."
          />
        </div>
      </div>
    </div>
  );
};
