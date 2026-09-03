import React from 'react';
import { OrderRequestSummary, CurrencyOption } from './types';
import { formatMoney } from './calculations';

interface Props {
  summary: OrderRequestSummary;
  defaultCurrency?: CurrencyOption;
  isEditMode: boolean;
  isSubmitting: boolean;
  onSubmit: (stayOnPage?: boolean) => void;
  onCancel: () => void;
}

export const OrderRequestBottomSection: React.FC<Props> = ({
  summary,
  isEditMode,
  isSubmitting,
  onSubmit,
  onCancel,
}) => {
  return (
    <div className="pt-4 flex items-center gap-3">
      {/* Primary Submit Button */}
      <button
        type="button"
        onClick={() => onSubmit(false)}
        disabled={isSubmitting}
        className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-xs rounded-lg shadow-2xs transition-colors disabled:opacity-50"
      >
        {isSubmitting
          ? isEditMode
            ? 'Menyimpan...'
            : 'Membuat...'
          : isEditMode
          ? 'Perbarui'
          : 'Buat'}
      </button>

      {/* Buat & Buat Lainnya (only on Create) */}
      {!isEditMode && (
        <button
          type="button"
          onClick={() => onSubmit(true)}
          disabled={isSubmitting}
          className="inline-flex items-center justify-center px-4 py-2 bg-white hover:bg-gray-50 active:bg-gray-100 border border-gray-300 text-gray-700 font-semibold text-xs rounded-lg shadow-2xs transition-colors disabled:opacity-50"
        >
          Buat & buat lainnya
        </button>
      )}

      {/* Batal Button */}
      <button
        type="button"
        onClick={onCancel}
        disabled={isSubmitting}
        className="inline-flex items-center justify-center px-4 py-2 bg-white hover:bg-gray-50 active:bg-gray-100 border border-gray-300 text-gray-700 font-semibold text-xs rounded-lg shadow-2xs transition-colors disabled:opacity-50"
      >
        Batal
      </button>
    </div>
  );
};

export const OrderRequestItemSummaryBox: React.FC<{
  totalItems: number;
  totalSubtotalIdr: number;
}> = ({ totalItems, totalSubtotalIdr }) => {
  return (
    <div className="pt-3 border-t border-gray-200 flex items-center justify-between text-xs font-bold text-gray-900 flex-wrap gap-2">
      <div>Total Items: {totalItems}</div>
      <div className="flex items-center gap-2">
        <span>Total Subtotal (IDR)</span>
        <span className="px-2.5 py-1 bg-gray-50 border border-gray-200 rounded-md tabular-nums text-gray-900 font-semibold">
          Rp {formatMoney(totalSubtotalIdr)}
        </span>
      </div>
    </div>
  );
};
