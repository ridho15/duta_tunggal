'use client';

import { useState, useEffect, useMemo, useCallback } from 'react';
import axios from 'axios';
import {
  FormDependencies,
  OrderRequestHeader,
  OrderRequestItemRow,
  OrderRequestSummary,
  TaxType,
} from '@/types/order-request';
import {
  calculateItemPreview,
  convertFromIdrAnchor,
  convertToIdrAnchor,
} from '@/lib/calculations';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8009/api/v1';

export function useOrderRequestForm() {
  const [dependencies, setDependencies] = useState<FormDependencies | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  // Header State
  const [header, setHeader] = useState<OrderRequestHeader>({
    request_number: '',
    request_date: new Date().toISOString().split('T')[0],
    currency_id: 1,
    note: '',
  });

  // Items State
  const [items, setItems] = useState<OrderRequestItemRow[]>([]);

  // Create a new empty item row
  const createEmptyRow = useCallback(
    (defaultCabangId?: number | null, defaultCurrencyId: number = 1): OrderRequestItemRow => {
      const initialPreview = calculateItemPreview(1, 0, 0, 11, 'eklusif');
      return {
        rowId: `row_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`,
        product_id: null,
        unit: '-',
        quantity: 1,
        cabang_id: defaultCabangId ?? null,
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
      };
    },
    []
  );

  // Fetch initial master data dependencies
  const fetchDependencies = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await axios.get(`${API_BASE_URL}/order-requests/dependencies`);

      if (res.data?.success && res.data?.data) {
        const data: FormDependencies = res.data.data;
        setDependencies(data);

        setHeader({
          request_number: data.next_request_number,
          request_date: data.default_request_date,
          currency_id: data.default_currency_id,
          note: '',
        });

        // Initialize with 1 default row
        setItems([createEmptyRow(data.default_cabang_id, data.default_currency_id)]);
      } else {
        throw new Error(res.data?.message || 'Gagal memuat master data.');
      }
    } catch (err: unknown) {
      console.error('Failed to load dependencies', err);
      const axiosErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(axiosErr.response?.data?.message || axiosErr.message || 'Gagal terhubung ke backend ERP.');
    } finally {
      setIsLoading(false);
    }
  }, [createEmptyRow]);

  useEffect(() => {
    fetchDependencies();
  }, [fetchDependencies]);

  // Generate new Request Number
  const generateNewRequestNumber = useCallback(async () => {
    try {
      const res = await axios.get(`${API_BASE_URL}/order-requests/generate-number`);
      if (res.data?.success && res.data?.request_number) {
        setHeader((prev) => ({ ...prev, request_number: res.data.request_number }));
      }
    } catch (err) {
      console.error('Failed to generate request number', err);
    }
  }, []);

  // Update Header field
  const updateHeader = useCallback(
    (field: keyof OrderRequestHeader, value: string | number) => {
      setHeader((prev) => {
        const updated = { ...prev, [field]: value };

        // If default currency changes, optionally propagate to rows with default currency
        if (field === 'currency_id' && dependencies?.currencies) {
          const numCurrencyId = Number(value);
          setItems((prevItems) =>
            prevItems.map((item) => {
              const convertedPrice = convertFromIdrAnchor(
                item.unit_price_idr,
                numCurrencyId,
                dependencies.currencies
              );
              const convertedOriginal = convertFromIdrAnchor(
                item.original_price_idr,
                numCurrencyId,
                dependencies.currencies
              );
              const preview = calculateItemPreview(
                item.quantity,
                convertedPrice,
                item.discount,
                item.tax,
                item.tipe_pajak
              );
              return {
                ...item,
                currency_id: numCurrencyId,
                unit_price: convertedPrice,
                original_price: convertedOriginal,
                ...preview,
              };
            })
          );
        }

        return updated;
      });
    },
    [dependencies]
  );

  // Add Row
  const addRow = useCallback(() => {
    if (!dependencies) return;
    setItems((prev) => [
      ...prev,
      createEmptyRow(dependencies.default_cabang_id, header.currency_id),
    ]);
  }, [dependencies, header.currency_id, createEmptyRow]);

  // Remove Row
  const removeRow = useCallback((rowId: string) => {
    setItems((prev) => {
      if (prev.length <= 1) {
        return prev; // Keep at least 1 row
      }
      return prev.filter((item) => item.rowId !== rowId);
    });
  }, []);

  // Duplicate Row
  const duplicateRow = useCallback((rowId: string) => {
    setItems((prev) => {
      const target = prev.find((item) => item.rowId === rowId);
      if (!target) return prev;
      const copy: OrderRequestItemRow = {
        ...target,
        rowId: `row_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`,
      };
      return [...prev, copy];
    });
  }, []);

  // Update Row item field with instant calculations
  const updateRow = useCallback(
    (
      rowId: string,
      field: keyof OrderRequestItemRow,
      value: string | number | null | TaxType
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
              const rawPriceIdr = matchedSupplier?.supplier_price !== null && matchedSupplier?.supplier_price !== undefined
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
                ...preview,
              };
            }
          }

          // 2. SUPPLIER CHANGED
          if (field === 'supplier_id') {
            const product = products.find((p) => p.id === row.product_id);
            if (product) {
              const matchedSupplier = product.suppliers.find((s) => s.id === Number(value));
              const rawPriceIdr = matchedSupplier?.supplier_price !== null && matchedSupplier?.supplier_price !== undefined
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

          // 4. UNIT PRICE (OVERRIDE) CHANGED
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
            const qty = field === 'quantity' ? Math.max(0, parseFloat(String(value ?? 0)) || 0) : row.quantity;
            const disc = field === 'discount' ? Math.min(100, Math.max(0, parseFloat(String(value ?? 0)) || 0)) : row.discount;

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
    [dependencies]
  );

  // Apply Recommended Supplier to a Row
  const applyRecommendedSupplier = useCallback(
    (rowId: string) => {
      const row = items.find((r) => r.rowId === rowId);
      if (!row || !row.recommended_supplier) return;
      updateRow(rowId, 'supplier_id', row.recommended_supplier.id);
    },
    [items, updateRow]
  );

  // Compute live summary across all rows
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

  // Submit Order Request
  const submitForm = useCallback(async () => {
    setValidationErrors({});
    setError(null);

    // Client-side quick checks
    const errors: Record<string, string[]> = {};
    if (!header.request_number.trim()) {
      errors.request_number = ['Nomor request wajib diisi.'];
    }
    if (!header.request_date) {
      errors.request_date = ['Tanggal request wajib diisi.'];
    }

    const validItems = items.filter((item) => item.product_id !== null);
    if (validItems.length === 0) {
      errors.items = ['Harap tambahkan setidaknya satu item produk.'];
    }

    items.forEach((item, index) => {
      if (!item.product_id) {
        errors[`items.${index}.product_id`] = ['Produk wajib dipilih.'];
      }
      if (!item.cabang_id) {
        errors[`items.${index}.cabang_id`] = ['Cabang wajib dipilih.'];
      }
      if ((item.quantity || 0) <= 0) {
        errors[`items.${index}.quantity`] = ['Quantity harus lebih besar dari 0.'];
      }
    });

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return { success: false, errors };
    }

    try {
      setIsSubmitting(true);

      const payload = {
        request_number: header.request_number,
        request_date: header.request_date,
        note: header.note,
        currency_id: header.currency_id,
        items: items.map((item) => ({
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
        })),
      };

      const res = await axios.post(`${API_BASE_URL}/order-requests`, payload);

      if (res.data?.success) {
        return {
          success: true,
          message: res.data.message,
          data: res.data.data,
        };
      } else {
        throw new Error(res.data?.message || 'Gagal menyimpan Order Request.');
      }
    } catch (err: unknown) {
      console.error('Submission failed', err);
      const axiosErr = err as {
        response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } };
        message?: string;
      };
      if (axiosErr.response?.status === 422 && axiosErr.response?.data?.errors) {
        setValidationErrors(axiosErr.response.data.errors);
      }
      const message =
        axiosErr.response?.data?.message || axiosErr.message || 'Terjadi kesalahan saat menyimpan.';
      setError(message);
      return { success: false, message, errors: axiosErr.response?.data?.errors };
    } finally {
      setIsSubmitting(false);
    }
  }, [header, items]);

  return {
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
  };
}
