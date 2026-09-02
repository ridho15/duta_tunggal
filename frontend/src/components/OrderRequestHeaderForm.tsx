'use client';

import React from 'react';
import { OrderRequestHeader, FormDependencies } from '@/types/order-request';
import { RefreshCw, FileText } from 'lucide-react';

interface Props {
  header: OrderRequestHeader;
  dependencies: FormDependencies | null;
  errors: Record<string, string[]>;
  onChange: (field: keyof OrderRequestHeader, value: string | number) => void;
  onGenerateNumber: () => void;
  disabled?: boolean;
}

export const OrderRequestHeaderForm: React.FC<Props> = ({
  header,
  dependencies,
  errors,
  onChange,
  onGenerateNumber,
  disabled = false,
}) => {
  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 md:p-6 mb-6">
      <div className="flex items-center gap-2 pb-4 mb-5 border-b border-slate-100">
        <div className="p-2 rounded-lg bg-blue-50 text-blue-600">
          <FileText className="w-5 h-5" />
        </div>
        <div>
          <h2 className="text-base font-bold text-slate-800">Informasi Permintaan Pembelian</h2>
          <p className="text-xs text-slate-500">Nomor dokumen, tanggal, dan ketentuan umum</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Nomor Request */}
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Nomor Request <span className="text-rose-500">*</span>
          </label>
          <div className="relative flex rounded-lg shadow-sm">
            <input
              type="text"
              value={header.request_number}
              onChange={(e) => onChange('request_number', e.target.value)}
              disabled={disabled}
              placeholder="OR-YYYYMMDD-XXXX"
              className={`w-full px-3 py-2 text-sm font-mono border rounded-l-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors ${
                errors.request_number ? 'border-rose-400 text-rose-800' : 'border-slate-300 text-slate-900'
              }`}
            />
            <button
              type="button"
              onClick={onGenerateNumber}
              disabled={disabled}
              title="Generate nomor baru"
              className="inline-flex items-center px-3 py-2 border border-l-0 border-slate-300 rounded-r-lg bg-slate-100 hover:bg-slate-200 text-slate-600 active:bg-slate-300 transition-colors"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>
          {errors.request_number && (
            <p className="mt-1 text-xs text-rose-600">{errors.request_number[0]}</p>
          )}
        </div>

        {/* Tanggal Request */}
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Tanggal Request <span className="text-rose-500">*</span>
          </label>
          <div className="relative">
            <input
              type="date"
              value={header.request_date}
              onChange={(e) => onChange('request_date', e.target.value)}
              disabled={disabled}
              className={`w-full px-3 py-2 text-sm border rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors ${
                errors.request_date ? 'border-rose-400 text-rose-800' : 'border-slate-300 text-slate-900'
              }`}
            />
          </div>
          {errors.request_date && (
            <p className="mt-1 text-xs text-rose-600">{errors.request_date[0]}</p>
          )}
        </div>

        {/* Mata Uang Default */}
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Mata Uang Default
          </label>
          <div className="relative">
            <select
              value={header.currency_id}
              onChange={(e) => onChange('currency_id', Number(e.target.value))}
              disabled={disabled}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors"
            >
              {dependencies?.currencies.map((curr) => (
                <option key={curr.id} value={curr.id}>
                  {curr.code} - {curr.name} ({curr.symbol})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Catatan / Keterangan */}
        <div className="md:col-span-2 lg:col-span-1">
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Catatan Dokumen
          </label>
          <textarea
            value={header.note}
            onChange={(e) => onChange('note', e.target.value)}
            disabled={disabled}
            placeholder="Catatan umum permohonan..."
            rows={1}
            className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none transition-colors"
          />
        </div>
      </div>
    </div>
  );
};
