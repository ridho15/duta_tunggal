'use client';

import React from 'react';
import { OrderRequestSummary, CurrencyOption } from '@/types/order-request';
import { formatMoney } from '@/lib/calculations';
import { PlusCircle, Send, Loader2 } from 'lucide-react';

interface Props {
  summary: OrderRequestSummary;
  defaultCurrency: CurrencyOption | undefined;
  onAddItem: () => void;
  onSubmit: () => void;
  isSubmitting: boolean;
  disabled?: boolean;
}

export const OrderRequestFloatingSummary: React.FC<Props> = ({
  summary,
  defaultCurrency,
  onAddItem,
  onSubmit,
  isSubmitting,
  disabled = false,
}) => {
  const symbol = defaultCurrency?.symbol || 'Rp';

  return (
    <div className="sticky bottom-4 z-40 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl shadow-floating p-4 md:px-6 md:py-4 transition-all">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        {/* Left Side: Summary Metrics */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-6 items-center">
          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
              Total Item
            </span>
            <span className="text-sm font-bold text-slate-800 font-mono">
              {summary.total_items} Produk
            </span>
          </div>

          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
              Total Kuantitas
            </span>
            <span className="text-sm font-bold text-slate-800 font-mono">
              {summary.total_quantity.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
            </span>
          </div>

          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
              Total Pajak
            </span>
            <span className="text-sm font-bold text-blue-600 font-mono">
              {formatMoney(summary.total_tax, symbol)}
            </span>
          </div>

          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
              Grand Total
            </span>
            <span className="text-base sm:text-lg font-extrabold text-blue-700 font-mono">
              {formatMoney(summary.grand_subtotal, symbol)}
            </span>
          </div>
        </div>

        {/* Right Side: Action Buttons */}
        <div className="flex items-center gap-3 shrink-0">
          <button
            type="button"
            onClick={onAddItem}
            disabled={isSubmitting || disabled}
            className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs sm:text-sm rounded-xl transition-colors active:scale-98"
          >
            <PlusCircle className="w-4 h-4 text-slate-600" />
            Tambah Baris Item
          </button>

          <button
            type="button"
            onClick={onSubmit}
            disabled={isSubmitting || disabled || summary.total_items === 0}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-xs sm:text-sm rounded-xl shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-98"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Menyimpan...
              </>
            ) : (
              <>
                <Send className="w-4 h-4" />
                Simpan Permintaan Pembelian
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};
