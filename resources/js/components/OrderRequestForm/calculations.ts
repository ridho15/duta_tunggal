import { CurrencyOption, TaxType } from './types';

export interface CalculatedItemPreview {
  total_cost: number;
  discount_nominal: number;
  after_discount: number;
  tax_nominal: number;
  subtotal: number;
}

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
  const taxNominal = Math.round((afterDiscount * (taxRate / 100)) * 100) / 100;

  let subtotal = 0;
  if (taxType === 'inklusif') {
    subtotal = afterDiscount;
  } else {
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

export function formatMoney(amount: number | null | undefined): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return '0,00';
  }

  const num = Number(amount);
  const parts = num.toFixed(2).split('.');
  const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const decimalPart = parts[1] ? `,${parts[1]}` : ',00';

  return `${integerPart}${decimalPart}`;
}

export function formatCurrency(
  amount: number | null | undefined,
  currencySymbol: string = 'Rp'
): string {
  return `${currencySymbol} ${formatMoney(amount)}`;
}

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
