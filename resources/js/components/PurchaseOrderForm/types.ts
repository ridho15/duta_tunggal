export type TaxType = 'none' | 'eklusif' | 'inklusif';
export type TopType = 'cod' | 'advance_before_delivery' | 'deposit_balance' | 'credit_days';

export interface ProductSupplierPivot {
  id: number;
  code: string;
  perusahaan: string;
  supplier_price: number | null;
}

export interface ProductOption {
  id: number;
  name: string;
  sku: string;
  cost_price: number;
  uom_id: number | null;
  uom: string;
  cabang_id: number | null;
  default_tax_rate: number;
  suppliers?: ProductSupplierPivot[];
  recommended_supplier?: {
    id: number;
    code: string;
    perusahaan: string;
    price: number;
  } | null;
}

export interface CabangOption {
  id: number;
  kode: string;
  nama: string;
  alamat?: string;
}

export interface SupplierOption {
  id: number;
  code: string;
  perusahaan: string;
  kontak_person?: string;
  phone?: string;
  cabang_id?: number | null;
  tempo_hutang?: number;
}

export interface CurrencyOption {
  id: number;
  name: string;
  code: string;
  symbol: string;
  to_rupiah: number;
}

export interface OrderRequestRefOption {
  id: number;
  request_number: string;
  request_date: string;
  currency_id: number;
  total_items: number;
  remaining_items: number;
  cabang_id: number | null;
  supplier_ids: number[];
  suppliers?: Array<{
    id: number;
    code: string;
    perusahaan: string;
    tempo_hutang?: number;
  }>;
}

export interface SalesOrderRefOption {
  id: number;
  so_number: string;
  order_date: string;
  customer_name: string;
  customer_code: string;
  cabang_id: number;
  currency_id: number;
  total_items: number;
}

export interface PurchaseOrderHeader {
  po_number: string;
  supplier_id: number | null;
  cabang_id: number | null;
  order_date: string;
  expected_date: string | null;
  status?: string;
  top_type: TopType;
  tempo_hutang: number;
  is_asset: boolean;
  is_import: boolean;
  refer_model_type: string | null;
  refer_model_id: number | null;
  note: string;
}

export interface PurchaseOrderItemRow {
  row_id: string; // unique client key
  id?: number; // DB ID if editing
  product_id: number | null;
  product_name?: string;
  product_sku?: string;
  uom?: string;
  quantity: number;
  max_quantity?: number;
  unit_price: number;
  discount: number;
  tax: number;
  tipe_pajak: TaxType;
  currency_id: number;
  cabang_id?: number | null;
  supplier_id?: number | null;
  note?: string;
  refer_item_model_type?: string | null;
  refer_item_model_id?: number | null;
  product_suppliers?: ProductSupplierPivot[];
  is_collapsed?: boolean;
  selected?: boolean;
}

export interface PurchaseOrderDependencies {
  next_po_number: string;
  default_order_date: string;
  default_expected_date: string;
  default_currency_id: number;
  default_cabang_id: number | null;
  cabangs: CabangOption[];
  currencies: CurrencyOption[];
  suppliers: SupplierOption[];
  products: ProductOption[];
  tax_types: Array<{ value: string; label: string }>;
  top_types: Array<{ value: TopType; label: string }>;
  available_order_requests: OrderRequestRefOption[];
  available_sales_orders: SalesOrderRefOption[];
}
