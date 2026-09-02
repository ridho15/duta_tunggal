import React from 'react';
import { OrderRequestHeader, FormDependencies } from './types';
import { RefreshCw } from 'lucide-react';

interface Props {
  header: OrderRequestHeader;
  dependencies: FormDependencies | null;
  errors: Record<string, string[]>;
  isGeneratingNumber: boolean;
  disabled?: boolean;
  onChange: (field: keyof OrderRequestHeader, value: string | number) => void;
  onGenerateNumber: () => void;
}

export const OrderRequestHeaderForm: React.FC<Props> = ({
  header,
  errors,
  isGeneratingNumber,
  disabled = false,
  onChange,
  onGenerateNumber,
}) => {
  return (
    <div className="mb-6">
      <h2 className="text-sm font-bold text-gray-900 mb-3">
        Form Order Request
      </h2>

      <div className="bg-white rounded-xl border border-gray-200/90 shadow-2xs p-4">
        <div className="flex flex-wrap items-start gap-4">
          {/* Request number (compact width) */}
          <div className="w-64">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Request number <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex rounded-lg shadow-2xs">
              <input
                type="text"
                value={header.request_number}
                onChange={(e) => onChange('request_number', e.target.value)}
                disabled={disabled}
                placeholder="OR-YYYYMMDD-XXXX"
                className={`w-full h-[38px] px-3 text-sm font-mono border rounded-l-lg bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors ${
                  errors.request_number
                    ? 'border-rose-400 text-rose-900 bg-rose-50/50'
                    : 'border-gray-300 text-gray-900'
                }`}
              />
              <button
                type="button"
                onClick={onGenerateNumber}
                disabled={disabled || isGeneratingNumber}
                title="Generate nomor request baru"
                className="inline-flex items-center justify-center px-3.5 h-[38px] border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 hover:bg-gray-100 text-gray-600 active:bg-gray-200 transition-colors disabled:opacity-50"
              >
                <RefreshCw className={`w-4 h-4 ${isGeneratingNumber ? 'animate-spin text-blue-600' : ''}`} />
              </button>
            </div>
            {errors.request_number && (
              <p className="mt-1 text-xs text-rose-600 font-medium">{errors.request_number[0]}</p>
            )}
          </div>

          {/* Request date (compact width) */}
          <div className="w-44">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Request date <span className="text-rose-500">*</span>
            </label>
            <input
              type="date"
              value={header.request_date}
              onChange={(e) => onChange('request_date', e.target.value)}
              disabled={disabled}
              className={`w-full h-[38px] px-3 text-sm border rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors ${
                errors.request_date
                  ? 'border-rose-400 text-rose-900 bg-rose-50/50'
                  : 'border-gray-300 text-gray-900'
              }`}
            />
            {errors.request_date && (
              <p className="mt-1 text-xs text-rose-600 font-medium">{errors.request_date[0]}</p>
            )}
          </div>

          {/* Note (fills remaining space) */}
          <div className="flex-1 min-w-[280px]">
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Note
            </label>
            <input
              type="text"
              value={header.note}
              onChange={(e) => onChange('note', e.target.value)}
              disabled={disabled}
              placeholder="Catatan umum permohonan..."
              className="w-full h-[38px] px-3 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 placeholder-gray-400"
            />
          </div>
        </div>
      </div>
    </div>
  );
};
