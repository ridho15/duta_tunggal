import React from 'react';
import { Save, X, Loader2 } from 'lucide-react';
import { QuotationSummary } from './types';
import { formatCurrency, formatNumber } from './calculations';

interface Props {
  summary: QuotationSummary;
  isSaving: boolean;
  isEditMode: boolean;
  onSubmit: () => void;
  onCancel: () => void;
}

export const QuotationFloatingSummary: React.FC<Props> = ({
  summary,
  isSaving,
  isEditMode,
  onSubmit,
  onCancel,
}) => {
  return (
    <div className="sticky bottom-4 z-40 mt-8 bg-white/95 backdrop-blur-md border border-gray-200 rounded-2xl p-4 shadow-xl flex flex-col md:flex-row items-center justify-between gap-4">
      {/* Summary KPI Pills */}
      <div className="flex flex-wrap items-center gap-2 text-xs">
        {/* Total Items */}
        <div className="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
          <span className="text-gray-400 mr-1">Items:</span>
          <b>{summary.total_items}</b>
        </div>

        {/* Total Qty */}
        <div className="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
          <span className="text-gray-400 mr-1">Total Qty:</span>
          <b>{formatNumber(summary.total_qty)}</b>
        </div>

        {/* DPP (Dasar Pengenaan Pajak) */}
        <div className="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
          <span className="text-gray-400 mr-1">DPP:</span>
          <b>{formatCurrency(summary.dpp, summary.currency_symbol)}</b>
        </div>

        {/* PPN */}
        <div className="px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-lg text-blue-800">
          <span className="text-blue-500 mr-1">PPN:</span>
          <b>{formatCurrency(summary.ppn, summary.currency_symbol)}</b>
        </div>

        {/* Grand Total */}
        <div className="px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-900 font-bold">
          <span className="text-emerald-600 mr-1">Grand Total:</span>
          <span className="font-mono text-sm">{formatCurrency(summary.grand_total, summary.currency_symbol)}</span>
        </div>

        {/* Foreign currency IDR equivalent */}
        {summary.currency_symbol !== 'Rp' && (
          <div className="px-3 py-1.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-900">
            <span className="text-amber-600 mr-1">Setara IDR:</span>
            <span className="font-mono font-semibold">{formatCurrency(summary.grand_total_idr, 'Rp')}</span>
          </div>
        )}
      </div>

      {/* Action Buttons */}
      <div className="flex items-center gap-2 w-full md:w-auto justify-end">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSaving}
          className="px-4 py-2 text-sm font-semibold text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl border border-gray-300 transition-colors flex items-center gap-1.5"
        >
          <X className="w-4 h-4" />
          <span>Batal</span>
        </button>

        <button
          type="button"
          onClick={onSubmit}
          disabled={isSaving}
          className="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isSaving ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Menyimpan...</span>
            </>
          ) : (
            <>
              <Save className="w-4 h-4" />
              <span>{isEditMode ? 'Perbarui Quotation' : 'Buat Quotation'}</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
};
