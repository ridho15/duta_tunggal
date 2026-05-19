# Legacy Product Approval Summary

- Main cabang: 2
- Staging cabang: 3
- Staging warehouse: 3
- Prefix: CAB-

| Reason | Groups | Rows | Approval File |
| --- | ---: | ---: | --- |
| name,category,uom | 1 | 2 | legacy-product-approval-name-category-uom-20260411-postbatch.csv |

## Approval Workflow

1. Review file approval per reason.
2. Isi `approval_status` dengan `APPROVE` untuk grup yang disetujui.
3. Jika menerima rekomendasi, cukup set `approval_status=APPROVE` dan biarkan kolom approved kosong.
4. Jika override diperlukan, isi `approved_canonical_sku`, `approved_category_id`, dan/atau `approved_biaya`.
5. Jalankan command konsolidasi approval khusus reason yang sesuai.
