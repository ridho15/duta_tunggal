# Income Statement Display Improvements

## Summary
Telah ditambahkan opsi pengaturan tampilan laporan untuk Income Statement (Laba Rugi) dengan fitur-fitur lengkap seperti Balance Sheet.

## Perubahan yang Dilakukan

### 1. IncomeStatementPage.php
**File**: `app/Filament/Pages/IncomeStatementPage.php`

Ditambahkan properties baru untuk display options:
```php
// Display options
public bool $show_only_totals = false;
public bool $show_parent_accounts = true;
public bool $show_child_accounts = true;
public bool $show_zero_balance = false;
```

### 2. income-statement-page.blade.php
**File**: `resources/views/filament/pages/income-statement-page.blade.php`

#### A. Helper Function untuk Filter
Ditambahkan helper function di bagian atas:
```php
// Helper function to filter accounts based on display options
$filterAccounts = function($accounts) {
    if ($this->show_only_totals) {
        return collect([]);
    }
    
    return $accounts->filter(function($account) {
        // Filter zero balance
        if (!$this->show_zero_balance && $account['balance'] == 0) {
            return false;
        }
        
        // Filter parent/child accounts
        $hasParent = isset($account['parent_id']) && $account['parent_id'] != null;
        
        if ($hasParent && !$this->show_child_accounts) {
            return false;
        }
        
        if (!$hasParent && !$this->show_parent_accounts) {
            return false;
        }
        
        return true;
    });
};
```

#### B. Display Options UI
Ditambahkan section opsi tampilan di form filter:
```blade
{{-- Display Options --}}
<div class="border-t pt-4 mt-4">
    <h4 class="text-sm font-semibold mb-3">📊 Opsi Tampilan Laporan</h4>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model="show_only_totals" id="show_only_totals" />
            <label for="show_only_totals" class="text-xs">Hanya Total</label>
        </div>
        
        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model="show_parent_accounts" id="show_parent_accounts" {{ $show_only_totals ? 'disabled' : '' }} />
            <label for="show_parent_accounts" class="text-xs">Akun Induk</label>
        </div>
        
        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model="show_child_accounts" id="show_child_accounts" {{ $show_only_totals ? 'disabled' : '' }} />
            <label for="show_child_accounts" class="text-xs">Akun Anak</label>
        </div>
        
        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model="show_zero_balance" id="show_zero_balance" />
            <label for="show_zero_balance" class="text-xs">Saldo Nol</label>
        </div>
    </div>
</div>
```

### 3. income-statement-table.blade.php (NEW)
**File**: `resources/views/filament/pages/partials/income-statement-table.blade.php`

File partial baru yang berisi struktur tabel lengkap dengan:

#### Fitur Utama:
1. **Visual Hierarchy yang Lebih Baik**
   - Gradient backgrounds untuk setiap section
   - Emoji icons untuk identifikasi cepat
   - Color coding berdasarkan kategori:
     - 💰 Hijau untuk Revenue
     - 📦 Merah untuk COGS
     - 💼 Orange untuk Operating Expenses
     - ✨ Purple untuk Other Income
     - ⚠️ Pink untuk Other Expenses
     - 🏛️ Gray untuk Tax

2. **Filtering Support**
   - Parent/child account indentation (└─)
   - Zero balance filtering
   - Only totals mode

3. **Improved Formatting**
   - Font mono untuk kode akun
   - Indentasi untuk akun anak
   - Clickable amounts untuk drill-down
   - Percentage dari pendapatan

4. **5-Level Income Statement Structure**
   ```
   1. Pendapatan Usaha (Sales Revenue)
   2. - Harga Pokok Penjualan (COGS)
   = LABA KOTOR (Gross Profit)
   
   3. - Beban Operasional (Operating Expenses)
   = LABA OPERASIONAL (Operating Profit)
   
   4. + Pendapatan Lain-lain (Other Income)
      - Beban Lain-lain (Other Expenses)
   = LABA SEBELUM PAJAK (Profit Before Tax)
   
   5. - Pajak Penghasilan (Tax Expense)
   = LABA BERSIH (Net Profit)
   ```

## Cara Penggunaan

### 1. Menampilkan Hanya Total
- Centang "Hanya Total"
- Semua detail akun akan disembunyikan
- Hanya menampilkan total per kategori dan hasil perhitungan

### 2. Filter Akun Induk/Anak
- Centang/uncentang "Akun Induk" untuk menampilkan/menyembunyikan akun parent
- Centang/uncentang "Akun Anak" untuk menampilkan/menyembunyikan akun child

### 3. Menampilkan/Menyembunyikan Akun dengan Saldo Nol
- Centang "Saldo Nol" untuk menampilkan akun dengan balance 0
- Uncentang untuk menyembunyikan akun dengan saldo nol

## Testing

Untuk menguji semua fitur:

1. **Test Display Options**
   ```
   - Buka halaman Laba Rugi
   - Coba centang/uncentang setiap opsi
   - Verifikasi filter bekerja dengan benar
   ```

2. **Test dengan Data**
   ```
   - Pastikan ada data dengan saldo 0
   - Pastikan ada akun induk dan anak
   - Pastikan semua sections memiliki data
   ```

3. **Test Kombinasi**
   ```
   - Hanya Total + Saldo Nol
   - Hanya Induk
   - Hanya Anak
   - Semua opsi aktif
   ```

## Notes

- File backup dibuat di: `income-statement-page.blade.php.backup-YYYYMMDD`
- Kode lebih modular dengan penggunaan partial
- Ukuran file berkurang dari 673 baris menjadi 425 baris
- Maintenance lebih mudah karena pemisahan concerns

## Next Steps

1. ✅ Test semua opsi display
2. ⏳ Tambahkan export PDF dengan opsi display yang sama
3. ⏳ Tambahkan export Excel dengan opsi display yang sama
4. ⏳ Tambahkan multi-period comparison view (jika diperlukan)
