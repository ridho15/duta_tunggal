import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { Loader2, AlertCircle, ShoppingCart } from 'lucide-react';
import {
  PurchaseOrderHeader,
  PurchaseOrderItemRow,
  PurchaseOrderDependencies,
} from './types';
import { PurchaseOrderHeaderForm } from './PurchaseOrderHeaderForm';
import { PurchaseOrderItemTable } from './PurchaseOrderItemTable';
import { PurchaseOrderToolbar } from './PurchaseOrderToolbar';
import { PurchaseOrderFloatingSummary } from './PurchaseOrderFloatingSummary';
import { calculatePurchaseOrderSummary } from './calculations';

interface Props {
  initialData?: any;
  editId?: number;
}

export const PurchaseOrderApp: React.FC<Props> = ({ initialData, editId }) => {
  const isEditMode = !!editId;

  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isGeneratingNumber, setIsGeneratingNumber] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [dependencies, setDependencies] = useState<PurchaseOrderDependencies>({
    next_po_number: '',
    default_order_date: new Date().toISOString().split('T')[0],
    default_expected_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
    default_currency_id: 1,
    default_cabang_id: null,
    cabangs: [],
    currencies: [],
    suppliers: [],
    products: [],
    tax_types: [],
    top_types: [],
    available_order_requests: [],
    available_sales_orders: [],
  });

  const [header, setHeader] = useState<PurchaseOrderHeader>({
    po_number: '',
    supplier_id: null,
    cabang_id: null,
    order_date: new Date().toISOString().split('T')[0],
    expected_date: null,
    top_type: 'cod',
    tempo_hutang: 0,
    is_asset: false,
    is_import: false,
    refer_model_type: null,
    refer_model_id: null,
    note: '',
  });

  const [items, setItems] = useState<PurchaseOrderItemRow[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isAllCollapsed, setIsAllCollapsed] = useState(false);

  // 1. Fetch Dependencies & Hydrate Edit Data
  useEffect(() => {
    const init = async () => {
      try {
        setIsLoading(true);
        const res = await axios.get('/api/v1/purchase-orders/dependencies');
        if (res.data.success) {
          const deps = res.data.data;
          setDependencies(deps);

          if (!isEditMode) {
            // New PO Mode
            setHeader((prev) => ({
              ...prev,
              po_number: deps.next_po_number,
              order_date: deps.default_order_date,
              expected_date: deps.default_expected_date,
              cabang_id: deps.default_cabang_id,
            }));

            // Add initial empty item
            setItems([
              {
                row_id: `row-${Date.now()}-1`,
                product_id: null,
                quantity: 1,
                unit_price: 0,
                discount: 0,
                tax: 11,
                tipe_pajak: 'eklusif',
                currency_id: deps.default_currency_id,
                uom: 'PCS',
              },
            ]);
          } else if (editId) {
            // Edit PO Mode
            const editRes = await axios.get(`/api/v1/purchase-orders/${editId}`);
            if (editRes.data.success) {
              const poData = editRes.data.data;
              setHeader({
                po_number: poData.po_number,
                supplier_id: poData.supplier_id,
                cabang_id: poData.cabang_id,
                order_date: poData.order_date,
                expected_date: poData.expected_date || null,
                status: poData.status,
                top_type: poData.top_type || 'cod',
                tempo_hutang: poData.tempo_hutang || 0,
                is_asset: !!poData.is_asset,
                is_import: !!poData.is_import,
                refer_model_type: poData.refer_model_type,
                refer_model_id: poData.refer_model_id,
                note: poData.note || '',
              });

              if (poData.purchase_order_item && poData.purchase_order_item.length > 0) {
                setItems(
                  poData.purchase_order_item.map((item: any, idx: number) => ({
                    row_id: `row-edit-${item.id || idx}`,
                    id: item.id,
                    product_id: item.product_id,
                    product_name: item.product?.name,
                    product_sku: item.product?.sku,
                    uom: item.product?.uom?.abbreviation || item.product?.uom?.name || 'PCS',
                    quantity: Number(item.quantity) || 0,
                    unit_price: Number(item.unit_price) || 0,
                    discount: Number(item.discount) || 0,
                    tax: Number(item.tax) || 11,
                    tipe_pajak: item.tipe_pajak || 'eklusif',
                    currency_id: item.currency_id || deps.default_currency_id,
                    refer_item_model_type: item.refer_item_model_type,
                    refer_item_model_id: item.refer_item_model_id,
                    product_suppliers: item.product?.suppliers || [],
                  }))
                );
              }
            }
          }
        }
      } catch (err: any) {
        console.error('Failed to init Purchase Order dependencies:', err);
        setErrorMessage(err.response?.data?.message || 'Gagal memuat data formulir.');
      } finally {
        setIsLoading(false);
      }
    };

    init();
  }, [editId, isEditMode]);

  // 2. Generate New PO Number
  const handleGenerateNumber = async () => {
    try {
      setIsGeneratingNumber(true);
      const res = await axios.get('/api/v1/purchase-orders/generate-number');
      if (res.data.success) {
        setHeader((prev) => ({ ...prev, po_number: res.data.po_number }));
      }
    } catch (err) {
      console.error('Failed to generate PO number:', err);
    } finally {
      setIsGeneratingNumber(false);
    }
  };

  // 3. Handle Reference Selection & Auto-fill Items
  const handleSelectReference = async (type: string | null, id: number | null, supplierIdOverride?: number | null) => {
    setHeader((prev) => ({
      ...prev,
      refer_model_type: type,
      refer_model_id: id,
    }));

    if (!type || !id) {
      return;
    }

    let targetSupplierId = supplierIdOverride !== undefined ? supplierIdOverride : header.supplier_id;

    if (type === 'OrderRequest' || type === 'App\\Models\\OrderRequest') {
      const selectedOr = dependencies.available_order_requests.find((or) => or.id === id);
      if (selectedOr) {
        if (selectedOr.supplier_ids && selectedOr.supplier_ids.length === 1) {
          targetSupplierId = selectedOr.supplier_ids[0];
        } else if (selectedOr.supplier_ids && selectedOr.supplier_ids.length > 1 && supplierIdOverride === undefined) {
          // Multi-supplier OR: prompt user to choose a specific supplier first
          targetSupplierId = null;
          setHeader((prev) => ({
            ...prev,
            refer_model_type: type,
            refer_model_id: id,
            supplier_id: null,
          }));
          setItems([]);
          return;
        }
      }
    }

    try {
      setIsLoading(true);
      const url = `/api/v1/purchase-orders/reference-items?type=${encodeURIComponent(type)}&id=${id}${
        targetSupplierId ? `&supplier_id=${targetSupplierId}` : ''
      }`;
      const res = await axios.get(url);
      if (res.data.success && res.data.items) {
        const refItems = res.data.items.map((ref: any, idx: number) => {
          const prod = dependencies.products.find((p) => p.id === ref.product_id);
          return {
            row_id: `row-ref-${Date.now()}-${idx}`,
            product_id: ref.product_id,
            product_name: prod?.name,
            product_sku: prod?.sku,
            uom: ref.unit || prod?.uom || 'PCS',
            quantity: ref.quantity,
            max_quantity: ref.max_quantity,
            unit_price: ref.unit_price,
            discount: ref.discount,
            tax: ref.tax,
            tipe_pajak: ref.tipe_pajak,
            currency_id: ref.currency_id || dependencies.default_currency_id,
            cabang_id: ref.cabang_id,
            supplier_id: ref.supplier_id,
            note: ref.note,
            refer_item_model_type: ref.refer_item_model_type,
            refer_item_model_id: ref.refer_item_model_id,
            product_suppliers: prod?.suppliers || [],
          };
        });

        setItems(refItems);

        if (res.data.header_defaults) {
          setHeader((prev) => ({
            ...prev,
            cabang_id: res.data.header_defaults.cabang_id || prev.cabang_id,
            supplier_id: targetSupplierId !== null && targetSupplierId !== undefined ? targetSupplierId : res.data.header_defaults.supplier_id || prev.supplier_id,
            top_type: res.data.header_defaults.top_type || prev.top_type,
            tempo_hutang: res.data.header_defaults.tempo_hutang !== undefined ? res.data.header_defaults.tempo_hutang : prev.tempo_hutang,
            note: res.data.header_defaults.note || prev.note,
          }));
        }
      }
    } catch (err) {
      console.error('Failed to fetch reference items:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const handleHeaderChange = (newHeader: PurchaseOrderHeader) => {
    const prevSupplierId = header.supplier_id;
    setHeader(newHeader);

    // If referencing an Order Request and supplier changed, re-fetch items for that supplier
    if (
      (newHeader.refer_model_type === 'OrderRequest' || newHeader.refer_model_type === 'App\\Models\\OrderRequest') &&
      newHeader.refer_model_id &&
      newHeader.supplier_id !== prevSupplierId
    ) {
      handleSelectReference(newHeader.refer_model_type, newHeader.refer_model_id, newHeader.supplier_id);
    }
  };

  // 4. Add Item
  const handleAddItem = () => {
    const newItem: PurchaseOrderItemRow = {
      row_id: `row-${Date.now()}-${items.length + 1}`,
      product_id: null,
      quantity: 1,
      unit_price: 0,
      discount: 0,
      tax: 11,
      tipe_pajak: 'eklusif',
      currency_id: dependencies.default_currency_id,
      uom: 'PCS',
      cabang_id: header.cabang_id,
      supplier_id: header.supplier_id,
    };
    setItems((prev) => [newItem, ...prev]);
  };

  // 5. Bulk Actions
  const handleBulkSetTaxType = (taxType: string) => {
    setItems((prev) =>
      prev.map((item) => {
        const isOrBacked = !!(item.refer_item_model_type || item.refer_item_model_id);
        if (isOrBacked) return item;
        return {
          ...item,
          tipe_pajak: taxType as TaxType,
          tax: taxType === 'none' ? 0 : 11,
        };
      })
    );
  };

  const handleBulkSetDiscount = (discount: number) => {
    setItems((prev) =>
      prev.map((item) => {
        const isOrBacked = !!(item.refer_item_model_type || item.refer_item_model_id);
        if (isOrBacked) return item;
        return { ...item, discount };
      })
    );
  };

  const handleToggleCollapseAll = () => {
    setIsAllCollapsed((prev) => !prev);
  };

  // 6. Submit Form
  const handleSubmit = async () => {
    setErrors({});
    setErrorMessage(null);

    // Frontend validation
    if (!header.po_number.trim()) {
      setErrors({ 'header.po_number': ['Nomor PO wajib diisi'] });
      return;
    }
    if (!header.supplier_id) {
      setErrors({ 'header.supplier_id': ['Supplier wajib dipilih'] });
      return;
    }
    if (items.length === 0) {
      setErrorMessage('Minimal harus menambahkan 1 item pembelian.');
      return;
    }
    for (let i = 0; i < items.length; i++) {
      if (!items[i].product_id) {
        setErrorMessage(`Baris item #${i + 1} belum memilih produk.`);
        return;
      }
      if (items[i].quantity <= 0) {
        setErrorMessage(`Kuantitas baris item #${i + 1} harus lebih besar dari 0.`);
        return;
      }
    }

    try {
      setIsSubmitting(true);
      const payload = {
        header,
        items,
      };

      let res;
      if (isEditMode && editId) {
        res = await axios.put(`/api/v1/purchase-orders/${editId}`, payload);
      } else {
        res = await axios.post('/api/v1/purchase-orders', payload);
      }

      if (res.data.success) {
        const redirectUrl = res.data.data?.redirect_url || '/admin/purchase-orders';
        window.location.href = redirectUrl;
      }
    } catch (err: any) {
      console.error('Failed to submit Purchase Order:', err);
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setErrors(err.response.data.errors);
        setErrorMessage(err.response.data.message || 'Validasi gagal. Periksa data yang dimasukkan.');
      } else {
        setErrorMessage(err.response?.data?.message || 'Gagal menyimpan Purchase Order.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancel = () => {
    window.location.href = isEditMode && editId ? `/admin/purchase-orders/${editId}` : '/admin/purchase-orders';
  };

  // 7. Filtered Items for Search
  const filteredItems = useMemo(() => {
    if (!searchQuery.trim()) return items;
    const q = searchQuery.toLowerCase();
    return items.filter(
      (item) =>
        item.product_name?.toLowerCase().includes(q) ||
        item.product_sku?.toLowerCase().includes(q) ||
        item.note?.toLowerCase().includes(q)
    );
  }, [items, searchQuery]);

  const summary = useMemo(() => {
    return calculatePurchaseOrderSummary(items, dependencies.currencies);
  }, [items, dependencies.currencies]);

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
        <Loader2 className="w-8 h-8 text-blue-600 animate-spin mb-3" />
        <p className="text-sm font-medium text-gray-600">Memuat formulir Purchase Order...</p>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto pb-12">
      {errorMessage && (
        <div className="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3 text-red-800 text-sm shadow-sm">
          <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
          <div className="flex-1 font-medium">{errorMessage}</div>
        </div>
      )}

      {/* Header Form */}
      <PurchaseOrderHeaderForm
        header={header}
        dependencies={dependencies}
        onChange={handleHeaderChange}
        onGenerateNumber={handleGenerateNumber}
        onSelectReference={handleSelectReference}
        isGeneratingNumber={isGeneratingNumber}
        errors={errors}
      />

      {/* Item Table Toolbar */}
      <PurchaseOrderToolbar
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        dependencies={dependencies}
        onAddItem={handleAddItem}
        onToggleCollapseAll={handleToggleCollapseAll}
        isAllCollapsed={isAllCollapsed}
        onBulkSetTaxType={handleBulkSetTaxType}
        onBulkSetDiscount={handleBulkSetDiscount}
        isOrderRequestReference={
          header.refer_model_type === 'OrderRequest' || header.refer_model_type === 'App\\Models\\OrderRequest'
        }
      />

      {/* Item Table */}
      <PurchaseOrderItemTable
        items={filteredItems}
        dependencies={dependencies}
        onChangeItems={setItems}
        onAddItem={handleAddItem}
        isOrderRequestReference={
          header.refer_model_type === 'OrderRequest' || header.refer_model_type === 'App\\Models\\OrderRequest'
        }
        errors={errors}
      />

      {/* Floating Summary Footer */}
      <PurchaseOrderFloatingSummary
        summary={summary}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
        isSubmitting={isSubmitting}
        isEditMode={isEditMode}
      />
    </div>
  );
};
