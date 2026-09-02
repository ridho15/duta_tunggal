import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import {
  QuotationDependencies,
  QuotationHeader,
  QuotationItemRow,
  ProductOption,
} from './types';
import { QuotationHeaderForm } from './QuotationHeaderForm';
import { QuotationItemTable } from './QuotationItemTable';
import { QuotationToolbar } from './QuotationToolbar';
import { QuotationFloatingSummary } from './QuotationFloatingSummary';
import { calculateQuotationSummary } from './calculations';

interface Props {
  recordId?: number;
}

export const QuotationApp: React.FC<Props> = ({ recordId }) => {
  const isEditMode = !!recordId;

  // Global State
  const [dependencies, setDependencies] = useState<QuotationDependencies | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [isGeneratingNumber, setIsGeneratingNumber] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Form Header State
  const [header, setHeader] = useState<QuotationHeader>({
    quotation_number: '',
    customer_id: null,
    cabang_id: null,
    date: new Date().toISOString().split('T')[0],
    valid_until: '',
    currency_id: 1,
    exchange_rate: 1.0,
    tempo_pembayaran: 0,
    notes: '',
    status: 'draft',
  });

  // Form Items State
  const [items, setItems] = useState<QuotationItemRow[]>([]);

  // 1. Fetch Dependencies & Existing Data on Mount
  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setGeneralError(null);

      try {
        const depRes = await axios.get('/api/v1/quotations/dependencies');
        if (!depRes.data.success) {
          throw new Error(depRes.data.message || 'Gagal memuat master data');
        }

        const dep: QuotationDependencies = depRes.data.data;
        setDependencies(dep);

        if (isEditMode) {
          // Edit Mode: Fetch Quotation Data
          const editRes = await axios.get(`/api/v1/quotations/${recordId}`);
          if (!editRes.data.success) {
            throw new Error(editRes.data.message || 'Gagal memuat data quotation');
          }

          const qData = editRes.data.data;
          setHeader(qData.header);
          setItems(qData.items || []);
        } else {
          // Create Mode: Set Defaults
          setHeader((prev) => ({
            ...prev,
            quotation_number: dep.next_quotation_number || '',
            date: dep.default_date || new Date().toISOString().split('T')[0],
            valid_until: dep.default_valid_until || '',
            currency_id: dep.default_currency_id || 1,
            cabang_id: dep.default_cabang_id || null,
          }));

          // Default initial item
          const initialRowId = 'row_' + Date.now();
          setItems([
            {
              row_id: initialRowId,
              product_id: null,
              product_sku: '',
              product_name: '',
              unit: 'PCS',
              quantity: 1,
              unit_price: 0,
              discount: 0,
              tax_type: 'None',
              tax: 0,
              notes: '',
            },
          ]);
        }
      } catch (err: any) {
        console.error('Error fetching quotation dependencies:', err);
        setGeneralError(err.response?.data?.message || err.message || 'Terjadi kesalahan sistem.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, [recordId, isEditMode]);

  // Handle number generation
  const handleGenerateNumber = async () => {
    setIsGeneratingNumber(true);
    try {
      const res = await axios.get('/api/v1/quotations/generate-number');
      if (res.data.success && res.data.data?.quotation_number) {
        setHeader((prev) => ({ ...prev, quotation_number: res.data.data.quotation_number }));
      }
    } catch (err) {
      console.error('Failed to generate quotation number:', err);
    } finally {
      setIsGeneratingNumber(false);
    }
  };

  // Add Item
  const handleAddItem = () => {
    const newRowId = 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    const newItem: QuotationItemRow = {
      row_id: newRowId,
      product_id: null,
      product_sku: '',
      product_name: '',
      unit: 'PCS',
      quantity: 1,
      unit_price: 0,
      discount: 0,
      tax_type: 'None',
      tax: 0,
      notes: '',
    };
    setItems((prev) => [...prev, newItem]);
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
    return calculateQuotationSummary(items, header.exchange_rate, currencySymbol);
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
        res = await axios.put(`/api/v1/quotations/${recordId}`, payload);
      } else {
        res = await axios.post('/api/v1/quotations', payload);
      }

      if (res.data.success) {
        const redirectUrl = res.data.data?.redirect_url || '/admin/quotations';
        window.location.href = redirectUrl;
      } else {
        setGeneralError(res.data.message || 'Gagal menyimpan Quotation.');
      }
    } catch (err: any) {
      console.error('Error saving quotation:', err);
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
    window.location.href = '/admin/quotations';
  };

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-3 bg-white rounded-xl border border-gray-200 p-8">
        <Loader2 className="w-8 h-8 text-primary-600 animate-spin" />
        <p className="text-sm text-gray-500 font-medium">Memuat form quotation...</p>
      </div>
    );
  }

  if (!dependencies) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-xl p-6 text-center text-red-800">
        <AlertCircle className="w-8 h-8 mx-auto mb-2 text-red-500" />
        <p className="font-semibold">Gagal memuat master data Quotation.</p>
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
      <QuotationHeaderForm
        header={header}
        dependencies={dependencies}
        onChange={setHeader}
        onGenerateNumber={handleGenerateNumber}
        isGeneratingNumber={isGeneratingNumber}
        errors={errors}
      />

      {/* Toolbar */}
      <QuotationToolbar
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onAddItem={handleAddItem}
        onBulkSetTaxType={handleBulkSetTaxType}
        onBulkSetDiscount={handleBulkSetDiscount}
        itemCount={items.length}
      />

      {/* Item Table */}
      <QuotationItemTable
        items={filteredItems}
        currencySymbol={currencySymbol}
        dependencies={dependencies}
        onChangeItems={setItems}
        errors={errors}
      />

      {/* Floating Summary Bar */}
      <QuotationFloatingSummary
        summary={summary}
        isSaving={isSaving}
        isEditMode={isEditMode}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  );
};
