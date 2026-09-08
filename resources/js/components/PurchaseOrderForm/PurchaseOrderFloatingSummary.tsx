import React from 'react';
import { Save, X, Loader2 } from 'lucide-react';

interface SummaryData {
  totalItems?: number;
  totalQuantity?: number;
  totalGross?: number;
  totalDiscount?: number;
  totalDpp?: number;
  totalTax?: number;
  grandTotalIdr?: number;
}

interface Props {
  summary?: SummaryData;
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
  const isItemsEmpty = summary?.totalItems === 0;

  return (
    <div className="sticky bottom-4 z-20 mt-6 bg-white border border-gray-200 rounded-xl shadow-md p-4 flex items-center justify-end gap-3 transition-all">
      {/* Batal Button */}
      <button
        type="button"
        onClick={onCancel}
        disabled={isSubmitting}
        className="flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors disabled:opacity-50"
      >
        <X className="w-4 h-4" />
        <span>Batal</span>
      </button>

      {/* Submit Button */}
      <button
        type="button"
        onClick={onSubmit}
        disabled={isSubmitting || isItemsEmpty}
        className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all"
      >
        {isSubmitting ? (
          <>
            <Loader2 className="w-4 h-4 animate-spin" />
            <span>Menyimpan...</span>
          </>
        ) : (
          <>
            <Save className="w-4 h-4" />
            <span>{isEditMode ? 'Perbarui PO' : 'Buat PO'}</span>
          </>
        )}
      </button>
    </div>
  );
};
