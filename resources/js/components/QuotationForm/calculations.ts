import { CalculationResult, QuotationItemRow, QuotationSummary } from './types';

/**
 * Calculates financial breakdown for an individual quotation item.
 */
export function calculateItemPreview(
  quantity: number,
  unitPrice: number,
  discount: number,
  tax: number,
  taxType: string = 'None'
): CalculationResult {
  const qty = Number(quantity) || 0;
  const price = Number(unitPrice) || 0;
  const disc = Math.min(100, Math.max(0, Number(discount) || 0));
  const normalizedTaxType = (taxType || 'None').trim().toLowerCase();

  const total = qty * price;
  const discountNominal = total * (disc / 100);
  const afterDiscount = total - discountNominal;

  let taxRate = 0;
  if (normalizedTaxType === 'inklusif' || normalizedTaxType === 'eksklusif' || normalizedTaxType === 'ppn included' || normalizedTaxType === 'ppn excluded') {
    taxRate = Number(tax) || 0;
  }

  const taxNominal = Math.round(afterDiscount * (taxRate / 100) * 100) / 100;

  let subtotal = 0;
  if (normalizedTaxType === 'inklusif' || normalizedTaxType === 'ppn included' || normalizedTaxType === 'none') {
    subtotal = Math.round(afterDiscount * 100) / 100;
  } else {
    // Eksklusif / PPN Excluded
    subtotal = Math.round((afterDiscount + taxNominal) * 100) / 100;
  }

  return {
    total: Math.round(total * 100) / 100,
    discount_nominal: Math.round(discountNominal * 100) / 100,
    tax_nominal: taxNominal,
    subtotal,
  };
}

/**
 * Calculates document-wide quotation summary.
 */
export function calculateQuotationSummary(
  items: QuotationItemRow[],
  exchangeRate: number = 1.0,
  currencySymbol: string = 'Rp'
): QuotationSummary {
  let totalQty = 0;
  let totalGross = 0;
  let totalDiscount = 0;
  let dpp = 0;
  let ppn = 0;
  let grandTotal = 0;

  for (const item of items) {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const disc = Number(item.discount) || 0;
    const tax = Number(item.tax) || 0;
    const taxType = item.tax_type || 'None';

    const preview = calculateItemPreview(qty, price, disc, tax, taxType);

    totalQty += qty;
    totalGross += preview.total;
    totalDiscount += preview.discount_nominal;
    ppn += preview.tax_nominal;
    grandTotal += preview.subtotal;
  }

  dpp = totalGross - totalDiscount;
  const grandTotalIdr = grandTotal * (exchangeRate || 1.0);

  return {
    total_items: items.length,
    total_qty: Math.round(totalQty * 1000) / 1000,
    total_gross: Math.round(totalGross * 100) / 100,
    total_discount: Math.round(totalDiscount * 100) / 100,
    dpp: Math.round(dpp * 100) / 100,
    ppn: Math.round(ppn * 100) / 100,
    grand_total: Math.round(grandTotal * 100) / 100,
    grand_total_idr: Math.round(grandTotalIdr * 100) / 100,
    currency_symbol: currencySymbol,
  };
}

/**
 * Formats a numeric amount to Indonesian Rupiah currency format (e.g., Rp 15.000.000,00)
 */
export function formatCurrency(
  amount: number | null | undefined,
  currencySymbol: string = 'Rp',
  decimals: number = 2
): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return `${currencySymbol} 0,00`;
  }

  const parts = Number(amount).toFixed(decimals).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  if (decimals === 0) {
    return `${currencySymbol} ${parts[0]}`;
  }

  return `${currencySymbol} ${parts.join(',')}`;
}

/**
 * Format raw number with thousand separators
 */
export function formatNumber(value: number | null | undefined, decimals: number = 0): string {
  if (value === null || value === undefined || isNaN(value)) {
    return '0';
  }

  const parts = Number(value).toFixed(decimals).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  return decimals > 0 ? parts.join(',') : parts[0];
}
