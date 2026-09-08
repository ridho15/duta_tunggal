import React from 'react';
import { Save, X, Loader2 } from 'lucide-react';
import { SaleOrderSummary } from './types';

interface Props {
  summary?: SaleOrderSummary;
  isSaving: boolean;
  isEditMode: boolean;
  onSubmit: () => void;
  onCancel: () => void;
}

export const SaleOrderFloatingSummary: React.FC<Props> = ({
  summary,
  isSaving,
  isEditMode,
  onSubmit,
  onCancel,
}) => {
  const isItemsEmpty = summary?.total_items === 0;

  return (
    <div className="sticky bottom-4 z-40 mt-8 bg-white border border-gray-200 rounded-xl p-4 shadow-md flex items-center justify-end gap-3">
      {/* Batal Button */}
      <button
        type="button"
        onClick={onCancel}
        disabled={isSaving}
        className="px-4 py-2 text-sm font-semibold text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl border border-gray-300 transition-colors flex items-center gap-1.5 disabled:opacity-50"
      >
        <X className="w-4 h-4" />
        <span>Batal</span>
      </button>

      {/* Submit Button */}
      <button
        type="button"
        onClick={onSubmit}
        disabled={isSaving || isItemsEmpty}
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
            <span>{isEditMode ? 'Perbarui Sales Order' : 'Buat Sales Order'}</span>
          </>
        )}
      </button>
    </div>
  );
};
