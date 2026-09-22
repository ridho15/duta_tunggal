import React from 'react';
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
}

export const PurchaseOrderGrandTotal: React.FC<Props> = ({ summary }) => {
  return (
    <div className="mt-6 flex flex-col md:flex-row justify-end">
      <div className="w-full md:w-96 bg-gray-50/75 border border-gray-200 rounded-xl p-4 space-y-2 text-xs">
        <div className="font-semibold text-gray-900 text-sm border-b border-gray-200 pb-2 flex items-center justify-between">
          <span>Ringkasan Transaksi</span>
          <span className="text-xs font-normal text-gray-500 tabular-nums">
            {summary.totalItems} item ({summary.totalQuantity.toLocaleString('id-ID')} qty)
          </span>
        </div>

        {/* Subtotal Kotor */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Subtotal (Total Kotor):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatMoney(summary.totalGross)}
          </span>
        </div>

        {/* Total Diskon */}
        {summary.totalDiscount > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total Diskon:</span>
            <span className="font-medium text-red-600 tabular-nums">
              - {formatMoney(summary.totalDiscount)}
            </span>
          </div>
        )}

        {/* Dasar Pengenaan Pajak (DPP) */}
        <div className="flex items-center justify-between text-gray-600">
          <span>Dasar Pengenaan Pajak (DPP):</span>
          <span className="font-medium text-gray-900 tabular-nums">
            {formatMoney(summary.totalDpp)}
          </span>
        </div>

        {/* Total PPN */}
        {summary.totalTax > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span>Total PPN:</span>
            <span className="font-medium text-blue-700 tabular-nums">
              + {formatMoney(summary.totalTax)}
            </span>
          </div>
        )}

        {/* Divider */}
        <div className="border-t border-gray-200 pt-2.5 flex items-baseline justify-between">
          <span className="font-bold text-gray-900 text-sm">Grand Total:</span>
          <span className="font-bold text-base text-gray-900 tabular-nums">
            {formatMoney(summary.grandTotalIdr)}
          </span>
        </div>
      </div>
    </div>
  );
};
