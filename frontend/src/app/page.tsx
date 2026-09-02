'use client';

import React, { useState } from 'react';
import { useOrderRequestForm } from '@/hooks/useOrderRequestForm';
import { OrderRequestHeaderForm } from '@/components/OrderRequestHeaderForm';
import { OrderRequestItemCard } from '@/components/OrderRequestItemCard';
import { OrderRequestFloatingSummary } from '@/components/OrderRequestFloatingSummary';
import { Toast } from '@/components/Toast';
import {
  Boxes,
  Plus,
  ArrowLeft,
  Zap,
  ShieldCheck,
  AlertTriangle,
  RotateCcw,
} from 'lucide-react';

export default function OrderRequestCreatePage() {
  const {
    dependencies,
    isLoading,
    isSubmitting,
    error,
    validationErrors,
    header,
    items,
    summary,
    updateHeader,
    generateNewRequestNumber,
    addRow,
    removeRow,
    duplicateRow,
    updateRow,
    applyRecommendedSupplier,
    submitForm,
  } = useOrderRequestForm();

  const [toast, setToast] = useState<{
    show: boolean;
    type: 'success' | 'error';
    title: string;
    message: string;
    actionUrl?: string;
  }>({
    show: false,
    type: 'success',
    title: '',
    message: '',
  });

  const handleSubmit = async () => {
    const res = await submitForm();
    if (res.success && res.data) {
      setToast({
        show: true,
        type: 'success',
        title: 'Berhasil Disimpan!',
        message: res.message || 'Order Request berhasil dibuat.',
        actionUrl: res.data.redirect_url,
      });
    } else {
      setToast({
        show: true,
        type: 'error',
        title: 'Gagal Menyimpan',
        message: res.message || 'Periksa kembali kelengkapan field yang ditandai merah.',
      });
    }
  };

  const defaultCurrency = dependencies?.currencies.find((c) => c.id === header.currency_id);

  return (
    <div className="min-h-screen pb-32">
      {/* Toast Notification */}
      <Toast
        show={toast.show}
        type={toast.type}
        title={toast.title}
        message={toast.message}
        actionUrl={toast.actionUrl}
        onClose={() => setToast((prev) => ({ ...prev, show: false }))}
      />

      {/* Top Navigation Bar */}
      <header className="sticky top-0 z-30 bg-white border-b border-slate-200/90 shadow-2xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <a
              href="http://localhost:8009/admin/order-requests"
              className="p-2 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
              title="Kembali ke Panel Filament"
            >
              <ArrowLeft className="w-5 h-5" />
            </a>

            <div>
              <div className="flex items-center gap-2">
                <span className="text-xs font-semibold text-slate-400">Pembelian</span>
                <span className="text-xs text-slate-300">/</span>
                <span className="text-xs font-semibold text-slate-600">Permintaan Pembelian</span>
                <span className="text-xs text-slate-300">/</span>
                <span className="text-xs font-bold text-blue-600">Buat Baru (Fast Mode)</span>
              </div>
              <h1 className="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                Permintaan Pembelian (Order Request)
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold">
                  <Zap className="w-3 h-3 fill-emerald-600" />
                  0ms Latency
                </span>
              </h1>
            </div>
          </div>

          <div className="flex items-center gap-2 sm:gap-3">
            <span className="hidden md:inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-medium border border-slate-200">
              <ShieldCheck className="w-4 h-4 text-blue-600" />
              Duta Tunggal ERP
            </span>
          </div>
        </div>
      </header>

      {/* Main Form Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8">
        {/* Loading Skeleton */}
        {isLoading ? (
          <div className="space-y-6">
            <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm animate-pulse space-y-4">
              <div className="h-5 bg-slate-200 rounded w-1/4" />
              <div className="grid grid-cols-4 gap-4">
                <div className="h-10 bg-slate-100 rounded" />
                <div className="h-10 bg-slate-100 rounded" />
                <div className="h-10 bg-slate-100 rounded" />
                <div className="h-10 bg-slate-100 rounded" />
              </div>
            </div>

            <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm animate-pulse space-y-4">
              <div className="h-5 bg-slate-200 rounded w-1/3" />
              <div className="h-24 bg-slate-100 rounded" />
              <div className="h-24 bg-slate-100 rounded" />
            </div>
          </div>
        ) : error && !dependencies ? (
          /* Error State */
          <div className="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center max-w-lg mx-auto mt-10">
            <AlertTriangle className="w-10 h-10 text-rose-500 mx-auto mb-3" />
            <h3 className="text-base font-bold text-rose-900">Gagal Memuat Data</h3>
            <p className="text-xs text-rose-700 mt-1 mb-4">{error}</p>
            <button
              onClick={() => window.location.reload()}
              className="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow transition-colors"
            >
              <RotateCcw className="w-4 h-4" />
              Coba Muat Ulang
            </button>
          </div>
        ) : (
          /* Form Content */
          <>
            {/* Header Document Form */}
            <OrderRequestHeaderForm
              header={header}
              dependencies={dependencies}
              errors={validationErrors}
              onChange={updateHeader}
              onGenerateNumber={generateNewRequestNumber}
              disabled={isSubmitting}
            />

            {/* Item List Header */}
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <div className="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                  <Boxes className="w-4 h-4" />
                </div>
                <h3 className="text-sm font-bold text-slate-800">
                  Daftar Item Produk Permintaan ({items.length})
                </h3>
              </div>

              <button
                type="button"
                onClick={addRow}
                disabled={isSubmitting}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-xs rounded-lg border border-blue-200 transition-colors"
              >
                <Plus className="w-3.5 h-3.5" />
                Tambah Baris Item
              </button>
            </div>

            {/* Item Cards List */}
            {items.map((item, index) => (
              <OrderRequestItemCard
                key={item.rowId}
                item={item}
                index={index}
                dependencies={dependencies}
                errors={validationErrors}
                onUpdate={(field, val) => updateRow(item.rowId, field, val)}
                onRemove={() => removeRow(item.rowId)}
                onDuplicate={() => duplicateRow(item.rowId)}
                onApplyRecommendedSupplier={() => applyRecommendedSupplier(item.rowId)}
                canRemove={items.length > 1}
              />
            ))}

            {/* Floating Summary Bar */}
            <OrderRequestFloatingSummary
              summary={summary}
              defaultCurrency={defaultCurrency}
              onAddItem={addRow}
              onSubmit={handleSubmit}
              isSubmitting={isSubmitting}
            />
          </>
        )}
      </main>
    </div>
  );
}
