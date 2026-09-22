import { CurrencyOption, TaxType } from '@/types/order-request';

export interface CalculatedItemPreview {
  total_cost: number;
  discount_nominal: number;
  after_discount: number;
  tax_nominal: number;
  subtotal: number;
}

/**
 * Pure client-side mathematical parity with OrderRequestResource::calculateApprovalItemPreview
 */
export function calculateItemPreview(
  quantity: number,
  unitPrice: number,
  discountPct: number,
  taxPct: number,
  taxType: TaxType
): CalculatedItemPreview {
  const qty = Math.max(0, Number(quantity) || 0);
  const price = Math.max(0, Number(unitPrice) || 0);
  const disc = Math.min(100, Math.max(0, Number(discountPct) || 0));
  const taxRate = Math.max(0, Number(taxPct) || 0);

  const base = qty * price;
  const discountNominal = Math.round((base * (disc / 100)) * 100) / 100;
  const afterDiscount = Math.max(0, base - discountNominal);

  // Nominal pajak is always calculated from the afterDiscount amount
  const taxNominal = Math.round((afterDiscount * (taxRate / 100)) * 100) / 100;

  // Subtotal calculation based on Tax Type
  let subtotal = 0;
  if (taxType === 'inklusif') {
    // PPN Included: Price already contains tax
    subtotal = afterDiscount;
  } else {
    // PPN Excluded / None: Add tax on top of afterDiscount
    subtotal = Math.round((afterDiscount + taxNominal) * 100) / 100;
  }

  return {
    total_cost: Math.round(base * 100) / 100,
    discount_nominal: discountNominal,
    after_discount: Math.round(afterDiscount * 100) / 100,
    tax_nominal: taxNominal,
    subtotal: subtotal,
  };
}

/**
 * Formats a number to Indonesian Currency String format (e.g. 1.250.000,00 or 1.250.000)
 */
export function formatRupiah(amount: number | null | undefined, withDecimals: boolean = true): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return 'Rp 0,00';
  }

  const num = Number(amount);
  const parts = num.toFixed(withDecimals ? 2 : 0).split('.');
  const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const decimalPart = parts[1] ? `,${parts[1]}` : '';

  return `Rp ${integerPart}${decimalPart}`;
}

/**
 * Formats a number with specific currency symbol and Indonesian notation (dot as thousands, comma as decimal)
 */
export function formatMoney(
  amount: number | null | undefined,
  currencySymbol: string = 'Rp',
  withDecimals: boolean = true
): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return `${currencySymbol} 0,00`;
  }

  const num = Number(amount);
  const isIdr = currencySymbol === 'Rp' || currencySymbol.toUpperCase() === 'IDR';
  // For IDR, if integer and withDecimals is false, show without decimals
  const decimals = withDecimals ? 2 : (isIdr ? 0 : 2);
  const parts = num.toFixed(decimals).split('.');
  const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const decimalPart = parts[1] ? `,${parts[1]}` : '';

  return `${currencySymbol} ${integerPart}${decimalPart}`;
}

/**
 * Parse string with Indonesian currency formats to numeric float
 */
export function parseCurrencyInput(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0;
  if (typeof value === 'number') return isNaN(value) ? 0 : value;

  // Remove currency prefixes (Rp, $, etc), replace thousands dots, replace comma with decimal dot
  const clean = value
    .toString()
    .replace(/[^\d,.-]/g, '')
    .replace(/\./g, '')
    .replace(',', '.');

  const parsed = parseFloat(clean);
  return isNaN(parsed) ? 0 : parsed;
}

/**
 * High-precision IDR Anchor Conversion helper
 */
export function convertFromIdrAnchor(
  idrAmount: number,
  targetCurrencyId: number,
  currencies: CurrencyOption[]
): number {
  const targetCurr = currencies.find((c) => c.id === targetCurrencyId);
  if (!targetCurr || targetCurr.code.toUpperCase() === 'IDR') {
    return idrAmount;
  }

  const rate = parseFloat(targetCurr.to_rupiah.toString()) || 1;
  if (rate <= 0) return idrAmount;

  return Math.round((idrAmount / rate) * 100) / 100;
}

export function convertToIdrAnchor(
  foreignAmount: number,
  fromCurrencyId: number,
  currencies: CurrencyOption[]
): number {
  const fromCurr = currencies.find((c) => c.id === fromCurrencyId);
  if (!fromCurr || fromCurr.code.toUpperCase() === 'IDR') {
    return foreignAmount;
  }

  const rate = parseFloat(fromCurr.to_rupiah.toString()) || 1;
  return Math.round(foreignAmount * rate * 100) / 100;
}
