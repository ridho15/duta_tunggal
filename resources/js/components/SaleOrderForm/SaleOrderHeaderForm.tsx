import React, { useMemo } from 'react';
import {
  RefreshCw,
  FileSpreadsheet,
  Calendar,
  Building2,
  User,
  Truck,
  MapPin,
  Clock,
  AlertTriangle,
  Wallet,
  CreditCard,
  Layers,
} from 'lucide-react';
import { SearchableSelect, SelectOption } from '../OrderRequestForm/SearchableSelect';
import {
  SaleOrderHeader,
  SaleOrderDependencies,
  CustomerOption,
  ApprovedQuotationOption,
  CabangOption,
  CurrencyOption,
} from './types';
import { formatCurrency } from './calculations';

interface Props {
  header: SaleOrderHeader;
  dependencies: SaleOrderDependencies;
  onChange: (header: SaleOrderHeader) => void;
  onSelectQuotation: (quotationId: number | null) => void;
  onGenerateNumber: () => void;
  isGeneratingNumber: boolean;
  isEditMode: boolean;
  errors?: Record<string, string[]>;
}

export const SaleOrderHeaderForm: React.FC<Props> = ({
  header,
  dependencies,
  onChange,
  onSelectQuotation,
  onGenerateNumber,
  isGeneratingNumber,
  isEditMode,
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

  // Approved Quotation options
  const quotationOptions: SelectOption[] = useMemo(() => {
    return (dependencies.approved_quotations || []).map((q) => ({
      value: q.id,
      label: `${q.quotation_number} - ${q.customer_name || 'Customer'}`,
      badge: 'APPROVED',
      sublabel: `Total: ${formatCurrency(q.total_amount, 'Rp')}`,
    }));
  }, [dependencies.approved_quotations]);

  // Currency options
  const currencyOptions: SelectOption[] = useMemo(() => {
    return (dependencies.currencies || []).map((curr) => ({
      value: curr.id,
      label: curr.name,
      badge: curr.code,
      sublabel: curr.symbol,
    }));
  }, [dependencies.currencies]);

  // Find selected customer details for credit & deposit badge
  const selectedCustomer = useMemo(() => {
    if (!header.customer_id) return null;
    return dependencies.customers?.find((c) => c.id === header.customer_id);
  }, [header.customer_id, dependencies.customers]);

  // Handle Customer Selection
  const handleCustomerChange = (customerId: string | number | null) => {
    const id = customerId ? Number(customerId) : null;
    let newShippedTo = header.shipped_to;
    let newTempo = header.tempo_pembayaran;

    if (id) {
      const cust = dependencies.customers?.find((c) => c.id === id);
      if (cust) {
        if (!newShippedTo && cust.address) {
          newShippedTo = cust.address;
        }
        if (cust.tempo_kredit !== undefined && cust.tempo_kredit > 0) {
          newTempo = cust.tempo_kredit;
        }
      }
    }

    onChange({
      ...header,
      customer_id: id,
      shipped_to: newShippedTo,
      tempo_pembayaran: newTempo,
    });
  };

  // Handle Currency Selection
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
      {/* Top Header: Title, Status, & Reference Options */}
      <div className="flex flex-col md:flex-row items-start md:items-center justify-between border-b border-gray-100 pb-4 mb-4 gap-3">
        <div className="flex items-center gap-2 text-gray-900 font-bold text-base">
          <FileSpreadsheet className="w-5 h-5 text-primary-600" />
          <span>Form Pesanan Penjualan (Sales Order)</span>
        </div>

        {/* Reference Mode Selector (Only on Create mode) */}
        {!isEditMode && (
          <div className="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl text-xs font-semibold">
            <button
              type="button"
              onClick={() => {
                onChange({ ...header, options_form: 0, quotation_id: null });
                onSelectQuotation(null);
              }}
              className={`px-3 py-1.5 rounded-lg transition-all ${
                header.options_form === 0
                  ? 'bg-white text-primary-700 shadow-xs'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              SO Mandiri (None)
            </button>
            <button
              type="button"
              onClick={() => onChange({ ...header, options_form: 2 })}
              className={`px-3 py-1.5 rounded-lg transition-all ${
                header.options_form === 2
                  ? 'bg-white text-primary-700 shadow-xs'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Refer Quotation (Disetujui)
            </button>
          </div>
        )}

        {header.status && (
          <span
            className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
              header.status === 'approved' || header.status === 'completed'
                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                : header.status === 'reject' || header.status === 'canceled'
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

      {/* Refer Quotation Dropdown (When Mode 2 is Active) */}
      {header.options_form === 2 && !isEditMode && (
        <div className="p-3.5 mb-4 bg-primary-50/60 border border-primary-200 rounded-xl space-y-1.5">
          <label className="block text-xs font-bold text-primary-900">
            Pilih Dokumen Quotation yang Disetujui <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={quotationOptions}
            value={header.quotation_id}
            placeholder="-- Cari dan Pilih Nomor Quotation --"
            onChange={(val) => onSelectQuotation(val ? Number(val) : null)}
          />
          <p className="text-[11px] text-primary-700">
            Memilih quotation akan otomatis mengisi customer, cabang, mata uang, alamat kirim, tempo pembayaran, dan seluruh daftar item produk.
          </p>
        </div>
      )}

      {/* Row 1: SO Number (3), Customer (5), Cabang (4) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
        {/* SO Number (col-span-3) */}
        <div className="md:col-span-3">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Nomor SO <span className="text-red-500">*</span>
          </label>
          <div className="flex items-center gap-1.5">
            <input
              type="text"
              value={header.so_number}
              onChange={(e) => onChange({ ...header, so_number: e.target.value })}
              className={`w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all ${
                errors['header.so_number'] ? 'border-red-500 bg-red-50/50' : 'border-gray-300'
              }`}
              placeholder="SO-00001"
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
          {errors['header.so_number'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.so_number'][0]}</p>
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
            disabled={header.options_form === 2 && !!header.quotation_id}
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
            disabled={
              (!dependencies.can_access_all_cabang && !!header.cabang_id) ||
              (header.options_form === 2 && !!header.quotation_id)
            }
            hasError={!!errors['header.cabang_id']}
            onChange={(val) => onChange({ ...header, cabang_id: val ? Number(val) : null })}
          />
          {errors['header.cabang_id'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.cabang_id'][0]}</p>
          )}
        </div>
      </div>

      {/* Customer Credit & Deposit Badge (If selected customer has credit/deposit info) */}
      {selectedCustomer && (
        <div className="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-xl flex flex-wrap items-center gap-3 text-xs">
          <div className="flex items-center gap-1.5 font-bold text-gray-800">
            <User className="w-4 h-4 text-primary-600" />
            <span>Info Customer:</span>
          </div>

          {/* Deposit */}
          {selectedCustomer.deposit_balance !== undefined && selectedCustomer.deposit_balance > 0 && (
            <div className="px-2.5 py-1 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 font-medium flex items-center gap-1">
              <Wallet className="w-3.5 h-3.5 text-emerald-600" />
              <span>Saldo Deposit: <b>{formatCurrency(selectedCustomer.deposit_balance, 'Rp')}</b></span>
            </div>
          )}

          {/* Credit limit */}
          {selectedCustomer.tipe_pembayaran === 'Kredit' && selectedCustomer.credit_summary && (
            <>
              <div className="px-2.5 py-1 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 font-medium flex items-center gap-1">
                <CreditCard className="w-3.5 h-3.5 text-blue-600" />
                <span>Limit: <b>{formatCurrency(selectedCustomer.credit_summary.credit_limit, 'Rp')}</b></span>
                <span className="text-gray-400">|</span>
                <span>Terpakai: <b>{formatCurrency(selectedCustomer.credit_summary.current_usage, 'Rp')}</b> ({selectedCustomer.credit_summary.usage_percentage}%)</span>
                <span className="text-gray-400">|</span>
                <span>Sisa: <b>{formatCurrency(selectedCustomer.credit_summary.available_credit, 'Rp')}</b></span>
              </div>

              {selectedCustomer.credit_summary.overdue_count > 0 && (
                <div className="px-2.5 py-1 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 font-semibold flex items-center gap-1">
                  <AlertTriangle className="w-3.5 h-3.5 text-rose-600" />
                  <span>⚠️ {selectedCustomer.credit_summary.overdue_count} tagihan jatuh tempo ({formatCurrency(selectedCustomer.credit_summary.overdue_total, 'Rp')})</span>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {/* Row 2: Dates (4), Tipe Kirim (2), Mata Uang (2), Tempo (1), Shipped To (3) - 12-Col Responsive Grid */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        {/* Tanggal Order (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tanggal Order <span className="text-red-500">*</span>
          </label>
          <input
            type="date"
            value={header.order_date}
            onChange={(e) => onChange({ ...header, order_date: e.target.value })}
            className={`w-full px-2.5 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all ${
              errors['header.order_date'] ? 'border-red-500 bg-red-50/50' : 'border-gray-300'
            }`}
          />
          {errors['header.order_date'] && (
            <p className="text-xs text-red-500 mt-1">{errors['header.order_date'][0]}</p>
          )}
        </div>

        {/* Tanggal Pengiriman (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tanggal Kirim
          </label>
          <input
            type="date"
            value={header.delivery_date || ''}
            onChange={(e) => onChange({ ...header, delivery_date: e.target.value })}
            className="w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all"
          />
        </div>

        {/* Tipe Pengiriman (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tipe Pengiriman <span className="text-red-500">*</span>
          </label>
          <select
            value={header.tipe_pengiriman}
            onChange={(e) =>
              onChange({
                ...header,
                tipe_pengiriman: e.target.value as 'Ambil Sendiri' | 'Kirim Langsung',
              })
            }
            className="w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none bg-white"
          >
            <option value="Kirim Langsung">Kirim Ke Customer</option>
            <option value="Ambil Sendiri">Customer Ambil Sendiri</option>
          </select>
        </div>

        {/* Mata Uang (col-span-2) */}
        <div className="col-span-1 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Mata Uang <span className="text-red-500">*</span>
          </label>
          <SearchableSelect
            options={currencyOptions}
            value={header.currency_id}
            placeholder="-- Mata Uang --"
            onChange={handleCurrencyChange}
          />
          {isForeignCurrency && (
            <p className="text-[11px] text-gray-500 mt-1 truncate" title={`Kurs: 1 ${selectedCurrency?.code} = Rp ${header.exchange_rate.toLocaleString('id-ID')}`}>
              Kurs: 1 {selectedCurrency?.code} = Rp {header.exchange_rate.toLocaleString('id-ID')}
            </p>
          )}
        </div>

        {/* Tempo Pembayaran (col-span-1) */}
        <div className="col-span-1 md:col-span-1">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Tempo (Hari)
          </label>
          <input
            type="number"
            min="0"
            value={header.tempo_pembayaran}
            onChange={(e) =>
              onChange({
                ...header,
                tempo_pembayaran: Math.max(0, parseInt(e.target.value) || 0),
              })
            }
            className="w-full px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all"
            placeholder="0"
          />
        </div>

        {/* Alamat Pengiriman (Shipped To) (col-span-3) */}
        <div className="col-span-1 md:col-span-3">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Alamat Pengiriman (Shipped To)
          </label>
          <input
            type="text"
            value={header.shipped_to || ''}
            onChange={(e) => onChange({ ...header, shipped_to: e.target.value })}
            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none transition-all"
            placeholder="Alamat tujuan pengiriman..."
          />
        </div>
      </div>
    </div>
  );
};
