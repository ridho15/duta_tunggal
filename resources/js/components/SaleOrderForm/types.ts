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

export interface CustomerCreditSummary {
  credit_limit: number;
  current_usage: number;
  available_credit: number;
  usage_percentage: number;
  overdue_count: number;
  overdue_total: number;
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
  deposit_balance?: number;
  credit_summary?: CustomerCreditSummary;
}

export interface ProductOption {
  id: number;
  sku: string;
  name: string;
  sell_price: number;
  free_stock: number;
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

export interface ApprovedQuotationItem {
  product_id: number;
  product_sku?: string;
  product_name?: string;
  unit?: string;
  quantity: number;
  unit_price: number;
  discount: number;
  tax_type: string;
  tax: number;
  notes?: string;
}

export interface ApprovedQuotationOption {
  id: number;
  quotation_number: string;
  customer_id: number;
  customer_name?: string;
  customer_code?: string;
  cabang_id: number;
  currency_id: number;
  exchange_rate: number;
  tempo_pembayaran: number;
  shipped_to?: string;
  total_amount: number;
  notes?: string;
  items: ApprovedQuotationItem[];
}

export interface SaleOrderDependencies {
  next_so_number: string;
  default_order_date: string;
  default_delivery_date: string;
  default_currency_id: number;
  default_cabang_id: number | null;
  can_access_all_cabang: boolean;
  cabangs: CabangOption[];
  currencies: CurrencyOption[];
  customers: CustomerOption[];
  approved_quotations: ApprovedQuotationOption[];
  products: ProductOption[];
  tax_types: TaxTypeOption[];
  user: {
    id: number;
    name: string;
    cabang_id: number | null;
  } | null;
}

export interface SaleOrderHeader {
  id?: number;
  options_form: number; // 0 = None, 2 = Refer Quotation
  quotation_id: number | null;
  so_number: string;
  customer_id: number | null;
  cabang_id: number | null;
  order_date: string;
  delivery_date: string;
  tipe_pengiriman: 'Ambil Sendiri' | 'Kirim Langsung';
  shipped_to: string;
  currency_id: number;
  exchange_rate: number;
  tempo_pembayaran: number;
  status: string;
  total_amount?: number;
}

export interface SaleOrderItemRow {
  id?: number;
  row_id: string;
  product_id: number | null;
  product_sku?: string;
  product_name?: string;
  unit?: string;
  free_stock?: number;
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

export interface SaleOrderSummary {
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
