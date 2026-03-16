# Audit Notifikasi: Informasi Proses Selanjutnya

**Tanggal Audit:** 15 Maret 2026  
**Status:** ✅ SELESAI — Semua resource telah diperbarui

---

## Ringkasan

Semua notifikasi pada action/workflow utama telah diperkaya dengan informasi:
- **Proses selanjutnya**: apa yang harus dilakukan berikutnya
- **Siapa yang bertanggung jawab**: peran/tim yang harus bertindak

---

## Status Per Resource

### ✅ OrderRequestResource
| File | Action | Status |
|------|--------|--------|
| `OrderRequestResource.php` | Tolak Order Request | ✅ Pemohon dapat merevisi dan mengajukan kembali |
| `OrderRequestResource.php` | Setujui Order Request | ✅ Tim Purchasing perlu membuat Purchase Order |
| `OrderRequestResource.php` | Tutup Order Request | ✅ Proses pembelian tidak akan dilanjutkan |
| `OrderRequestResource.php` | Buat Purchase Order (bulk) | ✅ Persetujuan PO oleh Manajer Purchasing |
| `OrderRequestResource.php` | Buat Purchase Order (single) | ✅ Persetujuan PO oleh Manajer Purchasing |
| `ViewOrderRequest.php` | Tolak | ✅ Pemohon dapat merevisi dan mengajukan kembali |
| `ViewOrderRequest.php` | Setujui | ✅ Tim Purchasing perlu membuat Purchase Order |
| `ViewOrderRequest.php` | Buat Purchase Order | ✅ Persetujuan PO oleh Manajer Purchasing |
| `CreateOrderRequest.php` | Create | ✅ (sudah ada dari sebelumnya) |

---

### ✅ SuratJalanResource
| File | Action | Status |
|------|--------|--------|
| `SuratJalanResource.php` | Terbitkan Surat Jalan | ✅ Driver mengambil dokumen dan melakukan pengiriman |
| `SuratJalanResource.php` | Mark as Sent | ✅ Tim Finance menerbitkan Invoice |

---

### ✅ DeliveryOrderResource
| File | Action | Status |
|------|--------|--------|
| `DeliveryOrderResource.php` | Ajukan Persetujuan | ✅ Persetujuan oleh Manajer Logistik/Finance |
| `DeliveryOrderResource.php` | Ajukan Penutupan | ✅ Konfirmasi penutupan oleh Manajer Logistik |
| `DeliveryOrderResource.php` | Konfirmasi Dana Diterima | ✅ Pengiriman oleh Driver melalui Surat Jalan |
| `DeliveryOrderResource.php` | Tutup DO | ✅ Tim Finance memastikan Invoice diselesaikan |
| `DeliveryOrderResource.php` | Tolak DO | ✅ Periksa alasan dan perbaiki data |
| `DeliveryOrderResource.php` | DO Selesai (dengan GL posting) | ✅ Penerbitan Invoice oleh Tim Finance |
| `DeliveryOrderResource.php` | DO Selesai (tanpa GL) | ✅ Penerbitan Invoice oleh Tim Finance |
| `DeliveryOrderResource.php` | Tandai Gagal Kirim | ✅ Koordinasi Tim Sales, jadwal ulang |
| `ViewDeliveryOrder.php` | Semua actions | ✅ Konsisten dengan resource utama |

---

### ✅ SaleOrderResource
| File | Action | Status |
|------|--------|--------|
| `SaleOrderResource.php` | Ajukan Persetujuan | ✅ Persetujuan oleh Manajer Sales |
| `SaleOrderResource.php` | Ajukan Penutupan | ✅ Konfirmasi penutupan oleh Manajer Sales |
| `SaleOrderResource.php` | Setujui SO | ✅ Pembuatan Delivery Order oleh Tim Gudang/Logistik |
| `SaleOrderResource.php` | Tutup SO | ✅ Tim Finance memastikan Invoice diselesaikan |
| `SaleOrderResource.php` | Tolak SO | ✅ Sales dapat merevisi dan mengajukan kembali |
| `SaleOrderResource.php` | SO Selesai | ✅ Penerbitan Invoice oleh Tim Finance |
| `SaleOrderResource.php` | Buat PO dari SO | ✅ Persetujuan PO oleh Manajer Purchasing |
| `ViewSaleOrder.php` | Semua actions | ✅ Konsisten dengan resource utama |

---

### ✅ QuotationResource
| File | Action | Status |
|------|--------|--------|
| `QuotationResource.php` | Ajukan Approve | ✅ Manajer Sales mereview dan memberikan persetujuan |
| `QuotationResource.php` | Approve Quotation | ✅ Tim Sales membuat Sale Order |
| `QuotationResource.php` | Reject Quotation | ✅ Tim Sales merevisi dan mengajukan kembali |
| `QuotationResource.php` | Buat Sale Order | ✅ Manajer Sales menyetujui Sales Order |
| `ViewQuotation.php` | Semua actions | ✅ Konsisten dengan resource utama |

---

### ✅ ManufacturingOrderResource
| File | Action | Status |
|------|--------|--------|
| `ManufacturingOrderResource.php` | Start Production | ✅ Supervisor Produksi memantau jalannya produksi |
| `ViewManufacturingOrder.php` | Start Production | ✅ Konsisten dengan resource utama |

---

### ✅ ProductionResource
| File | Action | Status |
|------|--------|--------|
| `ProductionResource.php` | Production Finished | ✅ Tim QC melakukan pemeriksaan kualitas |

---

### ✅ ProductionPlanResource
| File | Action | Status |
|------|--------|--------|
| `ProductionPlanResource.php` | Jadwalkan (MaterialIssue berhasil) | ✅ Kepala Produksi memulai MO |
| `ProductionPlanResource.php` | Jadwalkan (fallback) | ✅ Kepala Produksi memulai MO |
| `ProductionPlanResource.php` | Buat Manufacturing Order | ✅ Supervisor Produksi memulai MO |
| `CreateProductionPlan.php` | Create (langsung jadwal) | ✅ Kepala Produksi memulai MO |
| `ViewProductionPlan.php` | Jadwalkan | ✅ Kepala Produksi memulai MO |

---

### ✅ QualityControlManufactureResource
| File | Action | Status |
|------|--------|--------|
| `QualityControlManufactureResource.php` | Complete QC | ✅ Tim Gudang memindahkan barang ke penyimpanan |
| `ViewQualityControlManufacture.php` | Complete QC | ✅ Konsisten dengan resource utama |

---

### ✅ QualityControlPurchaseResource
| File | Action | Status |
|------|--------|--------|
| `QualityControlPurchaseResource.php` | Complete QC | ✅ Tim Gudang memperbarui stok penerimaan |
| `ViewQualityControlPurchase.php` | Complete QC | ✅ Konsisten dengan resource utama |

---

### ✅ PurchaseOrderResource
| File | Action | Status |
|------|--------|--------|
| `PurchaseOrderResource.php` | Sinkronkan Total | ✅ Verifikasi data sebelum mengajukan |
| `PurchaseOrderResource.php` | Generate Invoice | ✅ Tim Finance memproses pembayaran |

---

### ✅ StockTransferResource
| File | Action | Status |
|------|--------|--------|
| `StockTransferResource.php` | Kirim Request | ✅ Manajer Gudang/Logistik mereview dan menyetujui |
| `StockTransferResource.php` | Approve Transfer | ✅ Tim Gudang memproses dan mengirimkan barang |
| `StockTransferResource.php` | Reject Transfer | ✅ Pemohon merevisi dan mengajukan kembali |

---

### ✅ ReturnProductResource
| File | Action | Status |
|------|--------|--------|
| `ReturnProductResource.php` | Approve Return | ✅ Tim Gudang memproses pengembalian fisik barang |

---

### ✅ PurchaseReturnResource
| File | Action | Status |
|------|--------|--------|
| `ListPurchaseReturns.php` | Run Automation | ✅ Tim Purchasing menghubungi supplier untuk credit note |

---

### ✅ DepositResource
| File | Action | Status |
|------|--------|--------|
| `DepositResource.php` | Tambah Saldo | ✅ Tim Finance memverifikasi jurnal keuangan |
| `DepositResource.php` | Kurangi Saldo | ✅ Tim Finance memverifikasi jurnal keuangan |
| `CreateDeposit.php` | Create Deposit | ✅ Tim Finance memverifikasi saldo awal |
| `EditDeposit.php` | Edit Deposit | ✅ Supervisor Finance memverifikasi perubahan |

---

### ✅ DepositAdjustmentResource
| File | Action | Status |
|------|--------|--------|
| `CreateDepositAdjustment.php` | Create | ✅ Supervisor Finance memverifikasi dan menyetujui |
| `EditDepositAdjustment.php` | Edit | ✅ Supervisor Finance memverifikasi perubahan |

---

## Notifikasi yang TIDAK diubah (error/validasi)

Notifikasi berikut adalah **error messages** atau **validasi** — tidak memerlukan "next step" karena sudah jelas tindakannya:

- Semua `isSuccess: false` notifikasi (error messages)
- `ReturnProductResource/Pages/CreateReturnProduct.php` — semua validasi format/warehouse/quantity
- `ReturnProductResource/Pages/EditReturnProduct.php` — semua validasi
- `OrderRequestResource.php` — "PO Number sudah digunakan" (error)
- `DepositResource.php` — "Jumlah pengurangan tidak boleh melebihi sisa saldo" (error)
- `ProductResource/Pages/ListProducts.php` — bulk price update (utility action, bukan workflow)

---

## Alur Workflow Bisnis (Referensi)

```
Quotation → Sale Order → Delivery Order → Surat Jalan → [Terkirim] → Invoice
                  ↓
         Order Request → Purchase Order → QC Purchase → Stok Update
                  ↓
         Production Plan → Manufacturing Order → Production → QC Manufacture → Stok Update
```