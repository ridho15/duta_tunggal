import React, { useState, useMemo, useCallback, useEffect } from 'react';
import {
  FormDependencies,
  OrderRequestHeader,
  OrderRequestItemRow,
  OrderRequestSummary,
  OrderRequestRecord,
  TaxType,
} from './types';
import {
  calculateItemPreview,
  convertFromIdrAnchor,
  convertToIdrAnchor,
} from './calculations';
import { OrderRequestHeaderForm } from './OrderRequestHeaderForm';
import { OrderRequestToolbar } from './OrderRequestToolbar';
import { OrderRequestItemTable } from './OrderRequestItemTable';
import {
  OrderRequestBottomSection,
  OrderRequestItemSummaryBox,
} from './OrderRequestFloatingSummary';
import { AlertCircle, X, ChevronLeft, ChevronRight, Plus } from 'lucide-react';

interface Props {
  initialData?: FormDependencies;
  initialRecord?: OrderRequestRecord;
}

export const OrderRequestApp: React.FC<Props> = ({ initialData, initialRecord }) => {
  const [dependencies, setDependencies] = useState<FormDependencies | null>(initialData || null);
  const [isDepsLoading, setIsDepsLoading] = useState<boolean>(!initialData);
  const isEditMode = Boolean(initialRecord && initialRecord.id);

  // Header State
  const [header, setHeader] = useState<OrderRequestHeader>(() => {
    if (initialRecord) {
      return {
        request_number: initialRecord.request_number || '',
        request_date: initialRecord.request_date ? String(initialRecord.request_date).split('T')[0] : '',
        currency_id: Number(initialRecord.currency_id) || initialData?.default_currency_id || 1,
        note: initialRecord.note || '',
      };
    }
    return {
      request_number: initialData?.next_request_number || '',
      request_date: initialData?.default_request_date || new Date().toISOString().split('T')[0],
      currency_id: initialData?.default_currency_id || 1,
      note: '',
    };
  });

  // Create empty row helper
  const createEmptyRow = useCallback(
    (defaultCabangId?: number | null, defaultCurrencyId: number = 1, expand: boolean = true): OrderRequestItemRow => {
      const initialPreview = calculateItemPreview(1, 0, 0, 11, 'eklusif');
      return {
        rowId: `row_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`,
        product_id: null,
        unit: '-',
        quantity: 1,
        cabang_id: defaultCabangId ?? dependencies?.default_cabang_id ?? null,
        supplier_id: null,
        currency_id: defaultCurrencyId,
        original_price: 0,
        unit_price: 0,
        discount: 0,
        tipe_pajak: 'eklusif',
        tax: 11,
        note: '',
        unit_price_idr: 0,
        original_price_idr: 0,
        total_cost: initialPreview.total_cost,
        discount_nominal: initialPreview.discount_nominal,
        after_discount: initialPreview.after_discount,
        tax_nominal: initialPreview.tax_nominal,
        subtotal: initialPreview.subtotal,
        recommended_supplier: null,
        product_suppliers: [],
        status: 'draft',
        available_stock: 0,
        fulfilled_quantity: 0,
        remaining_quantity: 1,
        isExpanded: expand,
        isSelected: false,
      };
    },
    [dependencies?.default_cabang_id]
  );

  // Items State
  const [items, setItems] = useState<OrderRequestItemRow[]>(() => {
    if (initialRecord && Array.isArray(initialRecord.order_request_item) && initialRecord.order_request_item.length > 0) {
      return initialRecord.order_request_item.map((item, idx) => {
        const qty = parseFloat(String(item.quantity || 1));
        const price = parseFloat(String(item.unit_price || 0));
        const originalPrice = parseFloat(String(item.original_price || price));
        const disc = parseFloat(String(item.discount || 0));
        const taxRate = parseFloat(String(item.tax || 11));
        const taxType: TaxType = (item.tipe_pajak as TaxType) || 'eklusif';

        const preview = calculateItemPreview(qty, price, disc, taxRate, taxType);
        const product = initialData?.products.find((p) => p.id === item.product_id);

        return {
          rowId: `existing_${item.id || Math.random().toString(36).substring(2, 9)}`,
          id: item.id,
          product_id: item.product_id,
          unit: item.product?.uom?.abbreviation || product?.uom || '-',
          quantity: qty,
          cabang_id: item.cabang_id,
          supplier_id: item.supplier_id || null,
          currency_id: Number(item.currency_id) || Number(initialRecord.currency_id) || 1,
          original_price: originalPrice,
          unit_price: price,
          discount: disc,
          tipe_pajak: taxType,
          tax: taxRate,
          note: item.note || '',
          unit_price_idr: parseFloat(String(item.unit_price_idr || price)),
          original_price_idr: parseFloat(String(item.original_price_idr || originalPrice)),
          total_cost: preview.total_cost,
          discount_nominal: preview.discount_nominal,
          after_discount: preview.after_discount,
          tax_nominal: preview.tax_nominal,
          subtotal: preview.subtotal,
          recommended_supplier: product?.recommended_supplier || null,
          product_suppliers: product?.suppliers || [],
          status: (item as any).status || 'draft',
          available_stock: 0,
          fulfilled_quantity: 0,
          remaining_quantity: qty,
          isExpanded: idx === 0, // expand first row by default
          isSelected: false,
        };
      });
    }

    return [createEmptyRow(dependencies?.default_cabang_id, dependencies?.default_currency_id || 1, true)];
  });

  // Asynchronously fetch dependencies if not passed via initialData
  useEffect(() => {
    if (initialData) return;
    let isMounted = true;
    setIsDepsLoading(true);

    fetch('/api/v1/order-requests/dependencies', {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((r) => r.json())
      .then((res) => {
        if (!isMounted) return;
        if (res && res.success && res.data) {
          const deps = res.data as FormDependencies;
          setDependencies(deps);

          if (!isEditMode) {
            setHeader((prev) => ({
              ...prev,
              request_number: prev.request_number || deps.next_request_number || '',
              request_date: prev.request_date || deps.default_request_date || new Date().toISOString().split('T')[0],
              currency_id: prev.currency_id || deps.default_currency_id || 1,
            }));
            setItems([createEmptyRow(deps.default_cabang_id, deps.default_currency_id || 1, true)]);
          } else if (initialRecord && Array.isArray(initialRecord.order_request_item)) {
            setItems((prevItems) =>
              prevItems.map((row) => {
                const prod = deps.products.find((p) => p.id === row.product_id);
                if (prod) {
                  return {
                    ...row,
                    unit: prod.uom || row.unit,
                    recommended_supplier: prod.recommended_supplier || null,
                    product_suppliers: prod.suppliers || [],
                  };
                }
                return row;
              })
            );
          }
        }
      })
      .catch((err) => {
        console.error('Failed to load order request dependencies', err);
      })
      .finally(() => {
        if (isMounted) setIsDepsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [initialData, isEditMode, initialRecord, createEmptyRow]);

  // Table Filters & Pagination State
  const [searchQuery, setSearchQuery] = useState('');
  const [supplierFilter, setSupplierFilter] = useState<number | null>(null);
  const [cabangFilter, setCabangFilter] = useState<number | null>(null);
  const [taxFilter, setTaxFilter] = useState<TaxType | 'all'>('all');
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isGeneratingNumber, setIsGeneratingNumber] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  // Generate new Request Number
  const handleGenerateNumber = useCallback(async () => {
    try {
      setIsGeneratingNumber(true);
      const res = await fetch('/api/v1/order-requests/generate-number');
      const data = await res.json();
      if (data.success && data.request_number) {
        setHeader((prev) => ({ ...prev, request_number: data.request_number }));
      }
    } catch (e) {
      console.error('Failed to generate number', e);
    } finally {
      setIsGeneratingNumber(false);
    }
  }, []);

  // Header change
  const handleHeaderChange = useCallback(
    (field: keyof OrderRequestHeader, value: string | number) => {
      setHeader((prev) => ({ ...prev, [field]: value }));
    },
    []
  );

  // Add Item Row
  const handleAddItem = useCallback(() => {
    setItems((prev) => {
      // Collapse all existing rows and add new expanded row at the end
      const collapsedExisting = prev.map((r) => ({ ...r, isExpanded: false }));
      return [
        ...collapsedExisting,
        createEmptyRow(dependencies?.default_cabang_id, header.currency_id, true),
      ];
    });
  }, [createEmptyRow, dependencies?.default_cabang_id, header.currency_id]);

  // Remove Row
  const handleRemoveRow = useCallback((rowId: string) => {
    setItems((prev) => {
      if (prev.length <= 1) return prev;
      return prev.filter((item) => item.rowId !== rowId);
    });
  }, []);

  // Toggle Row Expand (Single-open accordion behavior: auto-collapse other rows)
  const handleToggleExpandRow = useCallback((rowId: string) => {
    setItems((prev) =>
      prev.map((r) => {
        if (r.rowId === rowId) {
          return { ...r, isExpanded: !r.isExpanded };
        }
        return { ...r, isExpanded: false };
      })
    );
  }, []);

  // Toggle Collapse All / Expand All
  const allCollapsed = useMemo(() => items.every((i) => !i.isExpanded), [items]);

  const handleToggleCollapseAll = useCallback(() => {
    setItems((prev) => {
      const targetState = allCollapsed; // if all are collapsed, expand all; otherwise collapse all
      return prev.map((r) => ({ ...r, isExpanded: targetState }));
    });
  }, [allCollapsed]);

  // Row Selection
  const handleToggleSelectRow = useCallback((rowId: string) => {
    setItems((prev) =>
      prev.map((r) => (r.rowId === rowId ? { ...r, isSelected: !r.isSelected } : r))
    );
  }, []);

  const handleSelectAllRows = useCallback((selected: boolean) => {
    setItems((prev) => prev.map((r) => ({ ...r, isSelected: selected })));
  }, []);

  const handleClearSelection = useCallback(() => {
    setItems((prev) => prev.map((r) => ({ ...r, isSelected: false })));
  }, []);

  const selectedCount = useMemo(() => items.filter((i) => i.isSelected).length, [items]);

  // Bulk Actions
  const handleBulkSetSupplier = useCallback((supplierId: number) => {
    setItems((prev) =>
      prev.map((r) => (r.isSelected ? { ...r, supplier_id: supplierId } : r))
    );
  }, []);

  const handleBulkSetCabang = useCallback((cabangId: number) => {
    setItems((prev) =>
      prev.map((r) => (r.isSelected ? { ...r, cabang_id: cabangId } : r))
    );
  }, []);

  const handleBulkApprove = useCallback(() => {
    setItems((prev) =>
      prev.map((r) => (r.isSelected ? { ...r, status: 'approved' } : r))
    );
  }, []);

  const handleBulkReject = useCallback(() => {
    setItems((prev) =>
      prev.map((r) => (r.isSelected ? { ...r, status: 'rejected' } : r))
    );
  }, []);

  const handleBulkSetDraft = useCallback(() => {
    setItems((prev) =>
      prev.map((r) => (r.isSelected ? { ...r, status: 'draft' } : r))
    );
  }, []);

  // Single Row Approve / Reject
  const handleApproveRow = useCallback((rowId: string) => {
    setItems((prev) =>
      prev.map((r) => (r.rowId === rowId ? { ...r, status: 'approved' } : r))
    );
  }, []);

  const handleRejectRow = useCallback((rowId: string) => {
    setItems((prev) =>
      prev.map((r) => (r.rowId === rowId ? { ...r, status: 'rejected' } : r))
    );
  }, []);

  // Update Row item field with instant calculations
  const handleUpdateRow = useCallback(
    (
      rowId: string,
      field: keyof OrderRequestItemRow,
      value: any
    ) => {
      setItems((prevItems) => {
        return prevItems.map((row) => {
          if (row.rowId !== rowId) return row;

          let updatedRow = { ...row, [field]: value };
          const currencies = dependencies?.currencies || [];
          const products = dependencies?.products || [];

          // 1. PRODUCT CHANGED
          if (field === 'product_id') {
            const product = products.find((p) => p.id === Number(value));
            if (product) {
              const defaultSupplier = product.recommended_supplier
                ? product.recommended_supplier.id
                : product.suppliers[0]?.id || null;

              const matchedSupplier = product.suppliers.find((s) => s.id === defaultSupplier);
              const rawPriceIdr =
                matchedSupplier?.supplier_price !== null && matchedSupplier?.supplier_price !== undefined
                  ? matchedSupplier.supplier_price
                  : product.cost_price;

              const convertedPrice = convertFromIdrAnchor(rawPriceIdr, row.currency_id, currencies);
              const defaultTaxRate = row.tipe_pajak === 'none' ? 0 : product.default_tax_rate;

              const preview = calculateItemPreview(
                row.quantity,
                convertedPrice,
                row.discount,
                defaultTaxRate,
                row.tipe_pajak
              );

              updatedRow = {
                ...updatedRow,
                product_id: product.id,
                unit: product.uom,
                supplier_id: defaultSupplier,
                cabang_id: row.cabang_id || product.cabang_id || dependencies?.default_cabang_id || null,
                unit_price_idr: rawPriceIdr,
                original_price_idr: rawPriceIdr,
                unit_price: convertedPrice,
                original_price: convertedPrice,
                tax: defaultTaxRate,
                recommended_supplier: product.recommended_supplier,
                product_suppliers: product.suppliers,
                ...preview,
              };
            } else {
              updatedRow = {
                ...updatedRow,
                product_id: null,
                unit: '-',
                recommended_supplier: null,
                product_suppliers: [],
              };
            }
          }

          // 2. SUPPLIER CHANGED
          if (field === 'supplier_id') {
            const product = products.find((p) => p.id === row.product_id);
            if (product) {
              const matchedSupplier = product.suppliers.find((s) => s.id === Number(value));
              const rawPriceIdr =
                matchedSupplier?.supplier_price !== null && matchedSupplier?.supplier_price !== undefined
                  ? matchedSupplier.supplier_price
                  : product.cost_price;

              const convertedPrice = convertFromIdrAnchor(rawPriceIdr, row.currency_id, currencies);
              const preview = calculateItemPreview(
                row.quantity,
                convertedPrice,
                row.discount,
                row.tax,
                row.tipe_pajak
              );

              updatedRow = {
                ...updatedRow,
                supplier_id: value ? Number(value) : null,
                unit_price_idr: rawPriceIdr,
                original_price_idr: rawPriceIdr,
                unit_price: convertedPrice,
                original_price: convertedPrice,
                ...preview,
              };
            }
          }

          // 3. CURRENCY CHANGED
          if (field === 'currency_id') {
            const newCurrencyId = Number(value);
            const convertedPrice = convertFromIdrAnchor(row.unit_price_idr, newCurrencyId, currencies);
            const convertedOriginal = convertFromIdrAnchor(row.original_price_idr, newCurrencyId, currencies);

            const preview = calculateItemPreview(
              row.quantity,
              convertedPrice,
              row.discount,
              row.tax,
              row.tipe_pajak
            );

            updatedRow = {
              ...updatedRow,
              currency_id: newCurrencyId,
              unit_price: convertedPrice,
              original_price: convertedOriginal,
              ...preview,
            };
          }

          // 4. UNIT PRICE CHANGED
          if (field === 'unit_price') {
            const numPrice = Math.max(0, parseFloat(String(value ?? 0)) || 0);
            const rawIdr = convertToIdrAnchor(numPrice, row.currency_id, currencies);
            const preview = calculateItemPreview(
              row.quantity,
              numPrice,
              row.discount,
              row.tax,
              row.tipe_pajak
            );

            updatedRow = {
              ...updatedRow,
              unit_price: numPrice,
              unit_price_idr: rawIdr,
              ...preview,
            };
          }

          // 5. QUANTITY OR DISCOUNT CHANGED
          if (field === 'quantity' || field === 'discount') {
            const qty =
              field === 'quantity' ? Math.max(0, parseFloat(String(value ?? 0)) || 0) : row.quantity;
            const disc =
              field === 'discount'
                ? Math.min(100, Math.max(0, parseFloat(String(value ?? 0)) || 0))
                : row.discount;

            const preview = calculateItemPreview(
              qty,
              row.unit_price,
              disc,
              row.tax,
              row.tipe_pajak
            );

            updatedRow = {
              ...updatedRow,
              quantity: qty,
              discount: disc,
              remaining_quantity: Math.max(0, qty - (row.fulfilled_quantity || 0)),
              ...preview,
            };
          }

          // 6. TIPE PAJAK CHANGED
          if (field === 'tipe_pajak') {
            const newTaxType = (value as TaxType) || 'none';
            const product = products.find((p) => p.id === row.product_id);
            const defaultProductTax = product ? product.default_tax_rate : 11;
            const newTaxRate = newTaxType === 'none' ? 0 : defaultProductTax;

            const preview = calculateItemPreview(
              row.quantity,
              row.unit_price,
              row.discount,
              newTaxRate,
              newTaxType
            );

            updatedRow = {
              ...updatedRow,
              tipe_pajak: newTaxType,
              tax: newTaxRate,
              ...preview,
            };
          }

          return updatedRow;
        });
      });
    },
    [dependencies, initialData]
  );

  // Filtered Items for Display
  const filteredItems = useMemo(() => {
    return items.filter((item) => {
      if (supplierFilter && item.supplier_id !== supplierFilter) return false;
      if (cabangFilter && item.cabang_id !== cabangFilter) return false;
      if (taxFilter !== 'all' && item.tipe_pajak !== taxFilter) return false;

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const product = dependencies?.products.find((p) => p.id === item.product_id);
        const supplier = dependencies?.suppliers.find((s) => s.id === item.supplier_id);
        const matchProduct = product && (product.name.toLowerCase().includes(q) || product.sku.toLowerCase().includes(q));
        const matchSupplier = supplier && (supplier.perusahaan.toLowerCase().includes(q) || supplier.code.toLowerCase().includes(q));
        if (!matchProduct && !matchSupplier) return false;
      }

      return true;
    });
  }, [items, supplierFilter, cabangFilter, taxFilter, searchQuery, dependencies]);

  // Paginated Items
  const paginatedItems = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filteredItems.slice(start, start + pageSize);
  }, [filteredItems, currentPage, pageSize]);

  const totalPages = Math.ceil(filteredItems.length / pageSize) || 1;

  // Live Grand Summary
  const summary = useMemo<OrderRequestSummary>(() => {
    let totalItems = 0;
    let totalQuantity = 0;
    let totalRaw = 0;
    let totalDiscount = 0;
    let totalTax = 0;
    let grandSubtotal = 0;

    items.forEach((item) => {
      if (item.product_id) {
        totalItems += 1;
        totalQuantity += Number(item.quantity) || 0;
        totalRaw += Number(item.total_cost) || 0;
        totalDiscount += Number(item.discount_nominal) || 0;
        totalTax += Number(item.tax_nominal) || 0;
        grandSubtotal += Number(item.subtotal) || 0;
      }
    });

    return {
      total_items: totalItems,
      total_quantity: Math.round(totalQuantity * 100) / 100,
      total_raw_amount: Math.round(totalRaw * 100) / 100,
      total_discount: Math.round(totalDiscount * 100) / 100,
      total_tax: Math.round(totalTax * 100) / 100,
      grand_subtotal: Math.round(grandSubtotal * 100) / 100,
    };
  }, [items]);

  // Submit Form
  const handleSubmit = async (stayOnPage: boolean = false) => {
    setValidationErrors({});
    setErrorMessage(null);

    const errors: Record<string, string[]> = {};
    if (!header.request_number.trim()) {
      errors.request_number = ['Nomor request wajib diisi.'];
    }
    if (!header.request_date) {
      errors.request_date = ['Tanggal request wajib diisi.'];
    }

    const validItems = items.filter((item) => item.product_id !== null);
    if (validItems.length === 0) {
      setErrorMessage('Harap pilih setidaknya satu item produk.');
      return;
    }

    validItems.forEach((item, index) => {
      if (!item.cabang_id) errors[`items.${index}.cabang_id`] = ['Cabang tujuan wajib dipilih.'];
      if ((Number(item.quantity) || 0) <= 0) errors[`items.${index}.quantity`] = ['Quantity harus lebih besar dari 0.'];
    });

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      setErrorMessage('Terdapat field yang belum lengkap. Mohon periksa baris yang ditandai merah.');
      return;
    }

    try {
      setIsSubmitting(true);
      const url = isEditMode
        ? `/api/v1/order-requests/${initialRecord?.id}`
        : '/api/v1/order-requests';
      const method = isEditMode ? 'PUT' : 'POST';

      const payload = {
        request_number: header.request_number,
        request_date: header.request_date,
        currency_id: header.currency_id,
        note: header.note,
        items: validItems.map((item) => {
          const itemData: any = {
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            original_price: item.original_price,
            unit_price_idr: item.unit_price_idr,
            original_price_idr: item.original_price_idr,
            cabang_id: item.cabang_id,
            supplier_id: item.supplier_id,
            currency_id: item.currency_id,
            discount: item.discount,
            tipe_pajak: item.tipe_pajak,
            tax: item.tax,
            note: item.note,
            status: item.status || 'draft',
          };

          if (item.rowId.startsWith('existing_')) {
            const rawId = item.rowId.replace('existing_', '');
            if (/^\d+$/.test(rawId)) {
              itemData.id = parseInt(rawId, 10);
            }
          }

          return itemData;
        }),
      };

      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN':
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        if (stayOnPage) {
          // Reset for creating another document
          handleGenerateNumber();
          setItems([createEmptyRow(dependencies?.default_cabang_id, header.currency_id, true)]);
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          window.location.href = result.data?.redirect_url || '/admin/order-requests';
        }
      } else {
        if (result.errors) {
          setValidationErrors(result.errors);
        }
        setErrorMessage(result.message || 'Gagal menyimpan data Order Request.');
      }
    } catch (e) {
      console.error('Submit error', e);
      setErrorMessage('Terjadi kesalahan pada server saat menyimpan data.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancel = () => {
    window.location.href = '/admin/order-requests';
  };

  if (isDepsLoading && !dependencies) {
    return (
      <div className="space-y-6 animate-pulse p-4">
        <div className="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
          <div className="h-5 bg-gray-200 dark:bg-gray-800 rounded w-1/4"></div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
            <div className="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
            <div className="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
            <div className="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
          </div>
        </div>
        <div className="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
          <div className="h-5 bg-gray-200 dark:bg-gray-800 rounded w-1/3"></div>
          <div className="h-40 bg-gray-100 dark:bg-gray-800/60 rounded-xl"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 pb-20">
      {/* Error Alert Box */}
      {errorMessage && (
        <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs flex items-start gap-3 shadow-2xs">
          <AlertCircle className="w-4 h-4 shrink-0 text-rose-500 mt-0.5" />
          <div className="flex-1">
            <strong className="font-bold block text-rose-900">Gagal Menyimpan Data</strong>
            <span>{errorMessage}</span>
          </div>
          <button
            type="button"
            onClick={() => setErrorMessage(null)}
            className="text-rose-400 hover:text-rose-600 p-0.5"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* 1. Header Card: Form Order Request */}
      <OrderRequestHeaderForm
        header={header}
        dependencies={dependencies}
        errors={validationErrors}
        isGeneratingNumber={isGeneratingNumber}
        disabled={isSubmitting}
        onChange={handleHeaderChange}
        onGenerateNumber={handleGenerateNumber}
      />

      {/* 2. Main Panel: Ringkasan Item OR (Blue Bordered Box matching reference) */}
      <div className="mb-6">
        <h2 className="text-base font-semibold text-gray-950 mb-3">
          Ringkasan Item OR
        </h2>

        <div className="bg-white rounded-xl border border-gray-200 shadow-xs p-5 space-y-4">
          {/* Toolbar */}
          <OrderRequestToolbar
            dependencies={dependencies}
            searchQuery={searchQuery}
            onSearchChange={setSearchQuery}
            supplierFilter={supplierFilter}
            onSupplierFilterChange={setSupplierFilter}
            cabangFilter={cabangFilter}
            onCabangFilterChange={setCabangFilter}
            taxFilter={taxFilter}
            onTaxFilterChange={setTaxFilter}
            allCollapsed={allCollapsed}
            onToggleCollapseAll={handleToggleCollapseAll}
            onAddItem={handleAddItem}
            selectedCount={selectedCount}
            onBulkSetSupplier={handleBulkSetSupplier}
            onBulkSetCabang={handleBulkSetCabang}
            onBulkApprove={handleBulkApprove}
            onBulkReject={handleBulkReject}
            onBulkSetDraft={handleBulkSetDraft}
            onClearSelection={handleClearSelection}
            disabled={isSubmitting}
          />

          {/* Showing Items Notice */}
          <div className="text-[11px] text-gray-500">
            Showing {filteredItems.length > 0 ? (currentPage - 1) * pageSize + 1 : 0} to{' '}
            {Math.min(currentPage * pageSize, filteredItems.length)} of {filteredItems.length} items
          </div>

          {/* Table with Accordion Inline Editor */}
          <OrderRequestItemTable
            items={paginatedItems}
            dependencies={dependencies}
            errors={validationErrors}
            onUpdateRow={handleUpdateRow}
            onToggleExpandRow={handleToggleExpandRow}
            onToggleSelectRow={handleToggleSelectRow}
            onSelectAllRows={handleSelectAllRows}
            onRemoveRow={handleRemoveRow}
            onApproveRow={handleApproveRow}
            onRejectRow={handleRejectRow}
            canRemove={items.length > 1}
            disabled={isSubmitting}
          />

          {/* Tombol Tambah Item di Bawah Tabel */}
          <div className="pt-2">
            <button
              type="button"
              onClick={handleAddItem}
              disabled={isSubmitting}
              className="w-full py-2.5 px-4 bg-white hover:bg-blue-50/50 active:bg-blue-100/60 border border-dashed border-gray-300 hover:border-blue-400 text-gray-700 hover:text-blue-700 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs group"
            >
              <div className="w-5 h-5 rounded-full bg-blue-50 group-hover:bg-blue-600 text-blue-600 group-hover:text-white flex items-center justify-center transition-colors">
                <Plus className="w-3.5 h-3.5" />
              </div>
              <span>Tambah Item Permintaan Baru</span>
            </button>
          </div>

          {/* Pagination Controls */}
          {totalPages > 1 && (
            <div className="flex items-center justify-end gap-1 pt-2">
              <button
                type="button"
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                disabled={currentPage === 1 || isSubmitting}
                className="p-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 disabled:opacity-40"
              >
                <ChevronLeft className="w-3.5 h-3.5" />
              </button>
              {Array.from({ length: totalPages }, (_, i) => i + 1).map((pageNum) => (
                <button
                  key={pageNum}
                  type="button"
                  onClick={() => setCurrentPage(pageNum)}
                  className={`min-w-[28px] h-7 px-2 text-xs font-bold rounded border ${
                    currentPage === pageNum
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                  }`}
                >
                  {pageNum}
                </button>
              ))}
              <button
                type="button"
                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages || isSubmitting}
                className="p-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 disabled:opacity-40"
              >
                <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>
          )}

          {/* Summary Box */}
          <OrderRequestItemSummaryBox
            totalItems={summary.total_items}
            totalSubtotalIdr={summary.grand_subtotal}
          />
        </div>
      </div>

      {/* 3. Bottom Form Actions */}
      <OrderRequestBottomSection
        summary={summary}
        isEditMode={isEditMode}
        isSubmitting={isSubmitting}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  );
};
