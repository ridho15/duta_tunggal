import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { Loader2, AlertCircle } from 'lucide-react';
import {
  SaleOrderDependencies,
  SaleOrderHeader,
  SaleOrderItemRow,
  ApprovedQuotationOption,
} from './types';
import { SaleOrderHeaderForm } from './SaleOrderHeaderForm';
import { SaleOrderItemTable } from './SaleOrderItemTable';
import { SaleOrderToolbar } from './SaleOrderToolbar';
import { SaleOrderFloatingSummary } from './SaleOrderFloatingSummary';
import { calculateSaleOrderSummary } from './calculations';

interface Props {
  recordId?: number;
  initialQuotationId?: number;
}

export const SaleOrderApp: React.FC<Props> = ({ recordId, initialQuotationId }) => {
  const isEditMode = !!recordId;

  // Global State
  const [dependencies, setDependencies] = useState<SaleOrderDependencies | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [isGeneratingNumber, setIsGeneratingNumber] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Form Header State
  const [header, setHeader] = useState<SaleOrderHeader>({
    options_form: initialQuotationId ? 2 : 0,
    quotation_id: initialQuotationId || null,
    so_number: '',
    customer_id: null,
    cabang_id: null,
    order_date: new Date().toISOString().split('T')[0],
    delivery_date: '',
    tipe_pengiriman: 'Kirim Langsung',
    shipped_to: '',
    currency_id: 1,
    exchange_rate: 1.0,
    tempo_pembayaran: 0,
    status: 'draft',
  });

  // Form Items State
  const [items, setItems] = useState<SaleOrderItemRow[]>([]);

  // 1. Fetch Dependencies & Existing Data on Mount
  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setGeneralError(null);

      try {
        const depRes = await axios.get('/api/v1/sales-orders/dependencies');
        if (!depRes.data.success) {
          throw new Error(depRes.data.message || 'Gagal memuat master data');
        }

        const dep: SaleOrderDependencies = depRes.data.data;
        setDependencies(dep);

        if (isEditMode) {
          // Edit Mode: Fetch Sales Order Data
          const editRes = await axios.get(`/api/v1/sales-orders/${recordId}`);
          if (!editRes.data.success) {
            throw new Error(editRes.data.message || 'Gagal memuat data sales order');
          }

          const soData = editRes.data.data;
          setHeader(soData.header);
          setItems(soData.items || []);
        } else if (initialQuotationId) {
          // Create Mode from Quotation ID parameter
          const qRes = await axios.get(`/api/v1/sales-orders/quotation/${initialQuotationId}`);
          if (qRes.data.success && qRes.data.data) {
            const q = qRes.data.data;
            setHeader((prev) => ({
              ...prev,
              options_form: 2,
              quotation_id: q.id,
              so_number: dep.next_so_number || '',
              customer_id: q.customer_id,
              cabang_id: q.cabang_id,
              order_date: dep.default_order_date || new Date().toISOString().split('T')[0],
              delivery_date: dep.default_delivery_date || '',
              currency_id: q.currency_id || dep.default_currency_id,
              exchange_rate: q.exchange_rate || 1.0,
              tempo_pembayaran: q.tempo_pembayaran || 0,
              shipped_to: q.shipped_to || '',
            }));

            const importedItems: SaleOrderItemRow[] = (q.items || []).map((item: any, idx: number) => ({
              row_id: 'row_' + Date.now() + '_' + idx,
              product_id: item.product_id,
              product_sku: item.product_sku,
              product_name: item.product_name,
              unit: item.unit || 'PCS',
              quantity: item.quantity,
              unit_price: item.unit_price,
              discount: item.discount,
              tax_type: item.tax_type,
              tax: item.tax,
              notes: item.notes || '',
            }));

            setItems(importedItems.length > 0 ? importedItems : [createEmptyItem()]);
          }
        } else {
          // Normal Create Mode: Set Defaults
          setHeader((prev) => ({
            ...prev,
            so_number: dep.next_so_number || '',
            order_date: dep.default_order_date || new Date().toISOString().split('T')[0],
            delivery_date: dep.default_delivery_date || '',
            currency_id: dep.default_currency_id || 1,
            cabang_id: dep.default_cabang_id || null,
          }));

          setItems([createEmptyItem()]);
        }
      } catch (err: any) {
        console.error('Error fetching sales order dependencies:', err);
        setGeneralError(err.response?.data?.message || err.message || 'Terjadi kesalahan sistem.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, [recordId, isEditMode, initialQuotationId]);

  const createEmptyItem = (): SaleOrderItemRow => ({
    row_id: 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
    product_id: null,
    product_sku: '',
    product_name: '',
    unit: 'PCS',
    free_stock: 0,
    quantity: 1,
    unit_price: 0,
    discount: 0,
    tax_type: 'None',
    tax: 0,
    notes: '',
  });

  // Handle number generation
  const handleGenerateNumber = async () => {
    setIsGeneratingNumber(true);
    try {
      const res = await axios.get('/api/v1/sales-orders/generate-number');
      if (res.data.success && res.data.data?.so_number) {
        setHeader((prev) => ({ ...prev, so_number: res.data.data.so_number }));
      }
    } catch (err) {
      console.error('Failed to generate SO number:', err);
    } finally {
      setIsGeneratingNumber(false);
    }
  };

  // Handle Quotation Selection (Refer Quotation Mode)
  const handleSelectQuotation = async (quotationId: number | null) => {
    if (!quotationId) {
      setHeader((prev) => ({ ...prev, quotation_id: null }));
      return;
    }

    try {
      const res = await axios.get(`/api/v1/sales-orders/quotation/${quotationId}`);
      if (res.data.success && res.data.data) {
        const q = res.data.data;
        setHeader((prev) => ({
          ...prev,
          quotation_id: q.id,
          customer_id: q.customer_id,
          cabang_id: q.cabang_id,
          currency_id: q.currency_id,
          exchange_rate: q.exchange_rate || 1.0,
          tempo_pembayaran: q.tempo_pembayaran || 0,
          shipped_to: q.shipped_to || prev.shipped_to,
        }));

        const importedItems: SaleOrderItemRow[] = (q.items || []).map((item: any, idx: number) => ({
          row_id: 'row_' + Date.now() + '_' + idx,
          product_id: item.product_id,
          product_sku: item.product_sku,
          product_name: item.product_name,
          unit: item.unit || 'PCS',
          quantity: item.quantity,
          unit_price: item.unit_price,
          discount: item.discount,
          tax_type: item.tax_type,
          tax: item.tax,
          notes: item.notes || '',
        }));

        setItems(importedItems.length > 0 ? importedItems : [createEmptyItem()]);
      }
    } catch (err) {
      console.error('Failed to fetch quotation details:', err);
    }
  };

  // Add Item
  const handleAddItem = () => {
    setItems((prev) => [...prev, createEmptyItem()]);
  };

  // Bulk Set Tax Type
  const handleBulkSetTaxType = (taxType: string) => {
    const rate = taxType === 'None' ? 0 : 11;
    setItems((prev) =>
      prev.map((item) => ({
        ...item,
        tax_type: taxType,
        tax: rate,
      }))
    );
  };

  // Bulk Set Discount
  const handleBulkSetDiscount = (discount: number) => {
    setItems((prev) =>
      prev.map((item) => ({
        ...item,
        discount,
      }))
    );
  };

  // Filter items by search query
  const filteredItems = useMemo(() => {
    if (!searchQuery.trim()) return items;
    const query = searchQuery.toLowerCase();
    return items.filter(
      (item) =>
        item.product_name?.toLowerCase().includes(query) ||
        item.product_sku?.toLowerCase().includes(query) ||
        item.notes?.toLowerCase().includes(query)
    );
  }, [items, searchQuery]);

  // Selected Currency Info
  const selectedCurrency = dependencies?.currencies?.find((c) => c.id === header.currency_id);
  const currencySymbol = selectedCurrency?.symbol || 'Rp';

  // Document Summary
  const summary = useMemo(() => {
    return calculateSaleOrderSummary(items, header.exchange_rate, currencySymbol);
  }, [items, header.exchange_rate, currencySymbol]);

  // Submit Handler
  const handleSubmit = async () => {
    setIsSaving(true);
    setErrors({});
    setGeneralError(null);

    const payload = {
      header,
      items,
    };

    try {
      let res;
      if (isEditMode) {
        res = await axios.put(`/api/v1/sales-orders/${recordId}`, payload);
      } else {
        res = await axios.post('/api/v1/sales-orders', payload);
      }

      if (res.data.success) {
        const redirectUrl = res.data.data?.redirect_url || '/admin/sale-orders';
        window.location.href = redirectUrl;
      } else {
        setGeneralError(res.data.message || 'Gagal menyimpan Sales Order.');
      }
    } catch (err: any) {
      console.error('Error saving sales order:', err);
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setErrors(err.response.data.errors);
        setGeneralError('Validasi gagal. Periksa kembali form input Anda.');
      } else {
        setGeneralError(err.response?.data?.message || err.message || 'Terjadi kesalahan sistem saat menyimpan data.');
      }
    } finally {
      setIsSaving(false);
    }
  };

  const handleCancel = () => {
    window.location.href = '/admin/sale-orders';
  };

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-3 bg-white rounded-xl border border-gray-200 p-8">
        <Loader2 className="w-8 h-8 text-primary-600 animate-spin" />
        <p className="text-sm text-gray-500 font-medium">Memuat form sales order...</p>
      </div>
    );
  }

  if (!dependencies) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-xl p-6 text-center text-red-800">
        <AlertCircle className="w-8 h-8 mx-auto mb-2 text-red-500" />
        <p className="font-semibold">Gagal memuat master data Sales Order.</p>
        <p className="text-xs text-red-600 mt-1">{generalError}</p>
        <button
          onClick={() => window.location.reload()}
          className="mt-4 px-4 py-2 bg-white border border-red-300 rounded-lg text-xs font-semibold text-red-700 hover:bg-red-50"
        >
          Muat Ulang Halaman
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-4 max-w-7xl mx-auto pb-12 font-sans antialiased text-gray-900">
      {/* General Alert */}
      {generalError && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3 text-red-800 text-sm">
          <AlertCircle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="font-semibold">{generalError}</p>
            {Object.keys(errors).length > 0 && (
              <ul className="list-disc list-inside mt-2 text-xs space-y-1">
                {Object.entries(errors).map(([key, errList]) => (
                  <li key={key}>{errList[0]}</li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}

      {/* Header Form */}
      <SaleOrderHeaderForm
        header={header}
        dependencies={dependencies}
        onChange={setHeader}
        onSelectQuotation={handleSelectQuotation}
        onGenerateNumber={handleGenerateNumber}
        isGeneratingNumber={isGeneratingNumber}
        isEditMode={isEditMode}
        errors={errors}
      />

      {/* Toolbar */}
      <SaleOrderToolbar
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onAddItem={handleAddItem}
        onBulkSetTaxType={handleBulkSetTaxType}
        onBulkSetDiscount={handleBulkSetDiscount}
        itemCount={items.length}
      />

      {/* Item Table */}
      <SaleOrderItemTable
        items={filteredItems}
        currencySymbol={currencySymbol}
        dependencies={dependencies}
        onChangeItems={setItems}
        isReferQuotation={header.options_form === 2 && !!header.quotation_id}
        onAddItem={handleAddItem}
        errors={errors}
      />

      {/* Floating Summary Bar */}
      <SaleOrderFloatingSummary
        summary={summary}
        isSaving={isSaving}
        isEditMode={isEditMode}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  );
};
