# Legacy Product Approval Summary

- Main cabang: 2
- Staging cabang: 3
- Staging warehouse: 3
- Prefix: CAB-

| Reason | Groups | Rows | Approval File |
| --- | ---: | ---: | --- |
| category | 11 | 22 | legacy-product-approval-category-20260410.csv |
| category,biaya | 153 | 306 | legacy-product-approval-category-biaya-20260410.csv |
| category,biaya,qty_min | 5 | 10 | legacy-product-approval-category-biaya-qty-min-20260410.csv |
| category,bulk_capacity | 10 | 20 | legacy-product-approval-category-bulk-capacity-20260410.csv |
| category,bulk_sell_qty | 32 | 64 | legacy-product-approval-category-bulk-sell-qty-20260410.csv |
| category,bulk_sell_qty,biaya | 25 | 50 | legacy-product-approval-category-bulk-sell-qty-biaya-20260410.csv |
| category,bulk_sell_qty,biaya,qty_min | 6 | 12 | legacy-product-approval-category-bulk-sell-qty-biaya-qty-min-20260410.csv |
| category,bulk_sell_qty,qty_min | 2 | 4 | legacy-product-approval-category-bulk-sell-qty-qty-min-20260410.csv |
| category,cost | 2 | 4 | legacy-product-approval-category-cost-20260410.csv |
| category,cost,biaya | 31 | 62 | legacy-product-approval-category-cost-biaya-20260410.csv |
| category,cost,biaya,qty_min | 4 | 8 | legacy-product-approval-category-cost-biaya-qty-min-20260410.csv |
| category,cost,bulk_capacity,biaya | 1 | 2 | legacy-product-approval-category-cost-bulk-capacity-biaya-20260410.csv |
| category,cost,bulk_sell_qty | 2 | 4 | legacy-product-approval-category-cost-bulk-sell-qty-20260410.csv |
| category,cost,bulk_sell_qty,biaya | 21 | 42 | legacy-product-approval-category-cost-bulk-sell-qty-biaya-20260410.csv |
| category,cost,bulk_sell_qty,biaya,qty_min | 4 | 8 | legacy-product-approval-category-cost-bulk-sell-qty-biaya-qty-min-20260410.csv |
| category,cost,qty_min | 1 | 2 | legacy-product-approval-category-cost-qty-min-20260410.csv |
| category,qty_min | 29 | 58 | legacy-product-approval-category-qty-min-20260410.csv |
| name,category,uom | 1 | 2 | legacy-product-approval-name-category-uom-20260410.csv |

## Approval Workflow

1. Review file approval per reason.
2. Isi `approval_status` dengan `APPROVE` untuk grup yang disetujui.
3. Jika menerima rekomendasi, cukup set `approval_status=APPROVE` dan biarkan kolom approved kosong.
4. Jika override diperlukan, isi `approved_canonical_sku`, `approved_category_id`, dan/atau `approved_biaya`.
5. Jalankan command konsolidasi approval khusus reason yang sesuai.
