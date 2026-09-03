import React from 'react';
import { Save, X, Calculator, ShieldCheck } from 'lucide-react';
import { formatMoney } from './calculations';

interface SummaryData {
  totalItems: number;
  totalQuantity: number;
  totalGross: number;
  totalDiscount: number;
  totalDpp: number;
  totalTax: number;
  grandTotalIdr: number;
}

interface Props {
  summary: SummaryData;
  onSubmit: () => void;
  onCancel: () => void;
  isSubmitting?: boolean;
  isEditMode?: boolean;
}

export const PurchaseOrderFloatingSummary: React.FC<Props> = ({
  summary,
  onSubmit,
  onCancel,
  isSubmitting = false,
  isEditMode = false,
}) => {
  return (
    <div className="sticky bottom-4 z-20 mt-6 bg-white border border-gray-200 rounded-xl shadow-md p-4 transition-all">
      <div className="flex flex-wrap items-center justify-between gap-4">
        {/* Metric Badges */}
        <div className="flex flex-wrap items-center gap-4 text-xs">
          <div className="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
            <span className="text-gray-500">Items:</span>
            <strong className="text-gray-900 text-sm">{summary.totalItems}</strong>
          </div>

          <div className="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
            <span className="text-gray-500">Total Qty:</span>
            <strong className="text-gray-900 text-sm">{summary.totalQuantity.toLocaleString('id-ID')}</strong>
          </div>

          <div className="hidden lg:flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
            <span className="text-gray-500">DPP:</span>
            <strong className="text-gray-900">{formatMoney(summary.totalDpp)}</strong>
          </div>

          <div className="hidden sm:flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
            <span className="text-blue-700">PPN:</span>
            <strong className="text-blue-800">{formatMoney(summary.totalTax)}</strong>
          </div>

          <div className="flex items-center gap-2 bg-emerald-50 px-3.5 py-1.5 rounded-lg border border-emerald-200">
            <span className="text-emerald-700 font-semibold">Grand Total (IDR):</span>
            <strong className="text-emerald-800 text-base font-bold">
              {formatMoney(summary.grandTotalIdr)}
            </strong>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-2.5">
          <button
            type="button"
            onClick={onCancel}
            disabled={isSubmitting}
            className="flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors"
          >
            <X className="w-4 h-4" />
            <span>Batal</span>
          </button>

          <button
            type="button"
            onClick={onSubmit}
            disabled={isSubmitting || summary.totalItems === 0}
            className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all"
          >
            <Save className={`w-4 h-4 ${isSubmitting ? 'animate-spin' : ''}`} />
            <span>{isSubmitting ? 'Menyimpan...' : isEditMode ? 'Perbarui PO' : 'Buat PO'}</span>
          </button>
        </div>
      </div>
    </div>
  );
};
