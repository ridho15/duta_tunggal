'use client';

import React from 'react';
import { CheckCircle2, AlertCircle, X } from 'lucide-react';

interface ToastProps {
  show: boolean;
  type: 'success' | 'error';
  title: string;
  message: string;
  actionUrl?: string;
  actionLabel?: string;
  onClose: () => void;
}

export const Toast: React.FC<ToastProps> = ({
  show,
  type,
  title,
  message,
  actionUrl,
  actionLabel,
  onClose,
}) => {
  if (!show) return null;

  const isSuccess = type === 'success';

  return (
    <div className="fixed top-5 right-5 z-50 max-w-md w-full animate-in fade-in slide-in-from-top-4 duration-200">
      <div
        className={`p-4 rounded-xl border shadow-lg flex items-start gap-3 bg-white ${
          isSuccess ? 'border-emerald-300 ring-1 ring-emerald-400/30' : 'border-rose-300 ring-1 ring-rose-400/30'
        }`}
      >
        <div className={`p-1.5 rounded-lg shrink-0 ${isSuccess ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
          {isSuccess ? <CheckCircle2 className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
        </div>

        <div className="flex-1 pr-2">
          <h4 className="text-sm font-bold text-slate-800">{title}</h4>
          <p className="text-xs text-slate-600 mt-0.5 leading-relaxed">{message}</p>
          {actionUrl && (
            <div className="mt-2.5">
              <a
                href={actionUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors"
              >
                {actionLabel || 'Buka di Panel Filament'}
              </a>
            </div>
          )}
        </div>

        <button
          onClick={onClose}
          className="text-slate-400 hover:text-slate-600 p-1 rounded-md transition-colors"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
};
