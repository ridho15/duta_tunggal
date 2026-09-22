import { TaxType, PurchaseOrderItemRow, CurrencyOption } from './types';

export interface ItemCalculationResult {
  grossTotal: number;
  discountNominal: number;
  afterDiscount: number;
  dpp: number;
  taxNominal: number;
  subtotal: number;
}

export function hitungItemCalculations(
  quantity: number,
  unitPrice: number,
  discountPercent: number = 0,
  taxRate: number = 11,
  taxType: TaxType = 'eklusif'
): ItemCalculationResult {
  const qty = Number(quantity) || 0;
  const price = Number(unitPrice) || 0;
  const discPct = Number(discountPercent) || 0;
  const taxPct = Number(taxRate) || 0;

  const grossTotal = qty * price;
  const discountNominal = grossTotal * (discPct / 100);
  const afterDiscount = Math.max(0, grossTotal - discountNominal);

  let dpp = afterDiscount;
  let taxNominal = 0;
  let subtotal = afterDiscount;

  if (taxType === 'none' || taxPct === 0) {
    dpp = afterDiscount;
    taxNominal = 0;
    subtotal = afterDiscount;
  } else if (taxType === 'inklusif') {
    dpp = afterDiscount / (1 + taxPct / 100);
    taxNominal = afterDiscount - dpp;
    subtotal = afterDiscount;
  } else {
    // eklusif
    dpp = afterDiscount;
    taxNominal = afterDiscount * (taxPct / 100);
    subtotal = afterDiscount + taxNominal;
  }

  return {
    grossTotal,
    discountNominal,
    afterDiscount,
    dpp,
    taxNominal,
    subtotal,
  };
}

export function formatMoney(val: number | null | undefined, currencySymbol: string = 'Rp'): string {
  if (val === null || val === undefined || isNaN(val)) {
    return `${currencySymbol} 0,00`;
  }
  return (
    `${currencySymbol} ` +
    val.toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

export function parseMoneyInput(raw: string): number {
  if (!raw) return 0;
  const cleaned = raw.replace(/[^0-9,-]/g, '').replace(',', '.');
  const num = parseFloat(cleaned);
  return isNaN(num) ? 0 : num;
}

export function calculatePurchaseOrderSummary(
  items: PurchaseOrderItemRow[],
  currencies: CurrencyOption[] = []
) {
  let totalItems = items.length;
  let totalQuantity = 0;
  let totalGross = 0;
  let totalDiscount = 0;
  let totalDpp = 0;
  let totalTax = 0;
  let grandTotalIdr = 0;

  const currencyMap = new Map<number, CurrencyOption>();
  currencies.forEach((c) => currencyMap.set(c.id, c));

  items.forEach((item) => {
    const calc = hitungItemCalculations(
      item.quantity,
      item.unit_price,
      item.discount,
      item.tax,
      item.tipe_pajak
    );

    totalQuantity += Number(item.quantity) || 0;
    totalGross += calc.grossTotal;
    totalDiscount += calc.discountNominal;
    totalDpp += calc.dpp;
    totalTax += calc.taxNominal;

    const curr = currencyMap.get(item.currency_id);
    const rate = curr ? Number(curr.to_rupiah) || 1 : 1;
    grandTotalIdr += calc.subtotal * rate;
  });

  return {
    totalItems,
    totalQuantity,
    totalGross,
    totalDiscount,
    totalDpp,
    totalTax,
    grandTotalIdr,
  };
}
