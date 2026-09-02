export interface CabangOption {
  id: number;
  kode: string;
  nama: string;
  alamat?: string;
}

export interface CurrencyOption {
  id: number;
  name: string;
  code: string;
  symbol: string;
  to_rupiah: number;
}

export interface CustomerOption {
  id: number;
  code: string;
  name: string;
  perusahaan: string;
  nik_npwp?: string;
  address?: string;
  telephone?: string;
  phone?: string;
  email?: string;
  tempo_kredit?: number;
  kredit_limit?: number;
  tipe_pembayaran?: string;
  tipe?: string;
}

export interface ProductOption {
  id: number;
  sku: string;
  name: string;
  sell_price: number;
  uom?: {
    id: number;
    name: string;
    abbreviation: string;
  } | null;
}

export interface TaxTypeOption {
  value: string;
  label: string;
  rate: number;
}

export interface QuotationDependencies {
  next_quotation_number: string;
  default_date: string;
  default_valid_until: string;
  default_currency_id: number;
  default_cabang_id: number | null;
  can_access_all_cabang: boolean;
  cabangs: CabangOption[];
  currencies: CurrencyOption[];
  customers: CustomerOption[];
  products: ProductOption[];
  tax_types: TaxTypeOption[];
  user: {
    id: number;
    name: string;
    cabang_id: number | null;
  } | null;
}

export interface QuotationHeader {
  id?: number;
  quotation_number: string;
  customer_id: number | null;
  cabang_id: number | null;
  date: string;
  valid_until: string;
  currency_id: number;
  exchange_rate: number;
  tempo_pembayaran: number;
  notes: string;
  status: string;
  total_amount?: number;
}

export interface QuotationItemRow {
  id?: number;
  row_id: string;
  product_id: number | null;
  product_sku?: string;
  product_name?: string;
  unit?: string;
  quantity: number;
  unit_price: number;
  unit_price_idr?: number;
  discount: number;
  tax_type: 'None' | 'Inklusif' | 'Eksklusif' | string;
  tax: number;
  notes: string;
}

export interface CalculationResult {
  total: number;
  discount_nominal: number;
  tax_nominal: number;
  subtotal: number;
}

export interface QuotationSummary {
  total_items: number;
  total_qty: number;
  total_gross: number;
  total_discount: number;
  dpp: number;
  ppn: number;
  grand_total: number;
  grand_total_idr: number;
  currency_symbol: string;
}
