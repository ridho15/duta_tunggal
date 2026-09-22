import React from 'react';
import { QuotationSummary } from './types';
import { formatCurrency, formatNumber } from './calculations';

interface Props {
  summary: QuotationSummary;
}

export const QuotationGrandTotal: React.FC<Props> = ({ summary }) => {
  const isForeignCurrency = summary.currency_symbol !== 'Rp';

  return (
    <div className="mt-6 flex flex-col md:flex-row justify-end">
      <div className="w-full md:w-96 bg-gray-50/75 border border-gray-200 rounded-xl p-4 space-y-2 text-xs">
        <div className="font-semibold text-gray-900 text-sm border-b border-gray-200 pb-2 flex items-center justify-between">
          <span>Ringkasan Transaksi</span>
          <span className="text-xs font-normal text-gray-500 tabular-nums">
            {summary.total_items} item ({formatNumber(summary.total_qty)} qty)
          </span>
        </div>

        {/* Subtotal Kotor */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Subtotal (Total Kotor):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatCurrency(summary.total_gross, summary.currency_symbol)}
          </span>
        </div>

        {/* Total Diskon */}
        {summary.total_discount > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total Diskon:</span>
            <span className="font-medium text-red-600 tabular-nums">
              - {formatCurrency(summary.total_discount, summary.currency_symbol)}
            </span>
          </div>
        )}

        {/* Dasar Pengenaan Pajak (DPP) */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Dasar Pengenaan Pajak (DPP):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatCurrency(summary.dpp, summary.currency_symbol)}
          </span>
        </div>

        {/* Total PPN */}
        {summary.ppn > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total PPN:</span>
            <span className="font-medium text-blue-700 tabular-nums">
              + {formatCurrency(summary.ppn, summary.currency_symbol)}
            </span>
          </div>
        )}

        {/* Divider */}
        <div className="border-t border-gray-200 pt-2.5 flex items-baseline justify-between">
          <span className="font-bold text-gray-900 text-sm">Grand Total:</span>
          <div className="text-right">
            <span className="font-bold text-base text-gray-900 tabular-nums block">
              {formatCurrency(summary.grand_total, summary.currency_symbol)}
            </span>
            {isForeignCurrency && (
              <span className="text-[11px] text-gray-500 block">
                Setara: {formatCurrency(summary.grand_total_idr, 'Rp')}
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
