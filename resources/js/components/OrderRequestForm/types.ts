export type TaxType = 'none' | 'eklusif' | 'inklusif';

export interface ProductSupplierPivot {
  id: number;
  code: string;
  perusahaan: string;
  supplier_price: number | null;
}

export interface RecommendedSupplier {
  id: number;
  code: string;
  perusahaan: string;
  price: number;
}

export interface ProductOption {
  id: number;
  name: string;
  sku: string;
  cost_price: number;
  uom_id?: number | null;
  uom: string;
  cabang_id?: number | null;
  default_tax_rate: number;
  suppliers: ProductSupplierPivot[];
  recommended_supplier: RecommendedSupplier | null;
}

export interface SupplierOption {
  id: number;
  code: string;
  perusahaan: string;
  kontak_person?: string | null;
  phone?: string | null;
  cabang_id?: number | null;
}

export interface CabangOption {
  id: number;
  kode: string;
  nama: string;
  alamat?: string | null;
}

export interface CurrencyOption {
  id: number;
  name: string;
  code: string;
  symbol: string;
  to_rupiah: number | string;
}

export interface TaxTypeOption {
  value: TaxType;
  label: string;
}

export interface FormDependencies {
  next_request_number: string;
  default_request_date: string;
  default_currency_id: number;
  default_cabang_id: number | null;
  cabangs: CabangOption[];
  currencies: CurrencyOption[];
  suppliers: SupplierOption[];
  products: ProductOption[];
  tax_types: TaxTypeOption[];
}

export interface ExistingItemData {
  id?: number;
  order_request_id?: number;
  product_id: number;
  product?: {
    id: number;
    name: string;
    sku: string;
    uom?: { abbreviation?: string; name?: string };
  };
  supplier_id?: number | null;
  cabang_id: number;
  currency_id?: number;
  quantity: number | string;
  unit_price: number | string;
  original_price?: number | string;
  unit_price_idr?: number | string;
  original_price_idr?: number | string;
  discount?: number | string;
  tax?: number | string;
  tipe_pajak?: TaxType;
  subtotal?: number | string;
  note?: string | null;
}

export interface OrderRequestRecord {
  id: number;
  request_number: string;
  request_date: string;
  currency_id: number;
  note?: string | null;
  status: string;
  order_request_item?: ExistingItemData[];
}

export interface OrderRequestItemRow {
  rowId: string;
  id?: number;
  product_id: number | null;
  unit: string;
  quantity: number;
  cabang_id: number | null;
  supplier_id: number | null;
  currency_id: number;
  original_price: number;
  unit_price: number;
  discount: number;
  tipe_pajak: TaxType;
  tax: number;
  note: string;
  unit_price_idr: number;
  original_price_idr: number;

  // Live calculated metrics (zero-latency instant update)
  total_cost: number;
  discount_nominal: number;
  after_discount: number;
  tax_nominal: number;
  subtotal: number;
  recommended_supplier?: RecommendedSupplier | null;
  product_suppliers?: ProductSupplierPivot[];  // Suppliers linked to the selected product (with prices)

  // Table UI and Approval state
  status: 'draft' | 'approved' | 'rejected' | string;
  available_stock?: number;
  fulfilled_quantity?: number;
  remaining_quantity?: number;
  isExpanded?: boolean;
  isSelected?: boolean;
}

export interface OrderRequestHeader {
  request_number: string;
  request_date: string;
  currency_id: number;
  note: string;
}

export interface OrderRequestSummary {
  total_items: number;
  total_quantity: number;
  total_raw_amount: number;
  total_discount: number;
  total_tax: number;
  grand_subtotal: number;
}
