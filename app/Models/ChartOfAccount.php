<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class ChartOfAccount extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'chart_of_accounts';
    protected $fillable = [
        'code',
        'name',
        'type', //'Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset',
        'parent_id',
        'is_active',
        'is_cash_bank',
        'is_current',
        'description',
        'opening_balance',
        'debit',
        'credit',
        'ending_balance'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_cash_bank' => 'boolean',
    ];

    /** Awalan kode akun kas (1111*) dan bank (1112*) — dasar kandidat akun penerima uang. */
    public const CASH_PREFIX = '1111';

    public const BANK_PREFIX = '1112';

    /**
     * Kandidat akun kas/bank yang boleh menerima uang, dipakai untuk (a) usulan `coa:flag-cash-bank`
     * dan (b) jembatan selama belum ada akun yang ditandai:
     *  - aktif, kode 1111* / 1112*,
     *  - akun DETAIL saja: tanpa anak menurut `parent_id` DAN tanpa akun lain yang kodenya diawali
     *    "<kode>." (data nyata: 1112.01 "Bank BCA - Operasional" punya 1112.01.01 tetapi parent_id-nya
     *    sama-sama 1112, sehingga parent_id saja tidak cukup),
     *  - bukan akun DEPOSITO / INVESTASI.
     * Hasilnya WAJIB ditinjau akuntansi sebelum ditandai permanen.
     */
    public function scopeCashBankCandidates($query)
    {
        return $query
            ->where('chart_of_accounts.is_active', true)
            ->where(function ($q) {
                $q->where('chart_of_accounts.code', 'LIKE', self::CASH_PREFIX . '%')
                    ->orWhere('chart_of_accounts.code', 'LIKE', self::BANK_PREFIX . '%');
            })
            ->where('chart_of_accounts.name', 'NOT LIKE', '%deposito%')
            ->where('chart_of_accounts.name', 'NOT LIKE', '%investasi%')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('chart_of_accounts as child')
                    ->whereNull('child.deleted_at')
                    ->where(function ($c) {
                        $c->whereColumn('child.parent_id', 'chart_of_accounts.id')
                            ->orWhereRaw("child.code LIKE CONCAT(chart_of_accounts.code, '.%')");
                    });
            });
    }

    public static function hasCashBankFlags(): bool
    {
        // Aman dipanggil sebelum migrasi is_cash_bank dijalankan (dianggap belum ada penanda).
        return Schema::hasColumn('chart_of_accounts', 'is_cash_bank')
            && static::query()->where('is_cash_bank', true)->exists();
    }

    /**
     * Akun yang boleh dipilih sebagai penerima uang.
     *
     * Sudah ada akun bertanda `is_cash_bank` → HANYA yang bertanda (hasil tinjauan akuntansi).
     * Belum ada satu pun → kandidat (jembatan) agar penerimaan tidak terblokir sebelum backfill dijalankan.
     *
     * @param  string|null  $kind  'cash' (kas/tunai) | 'bank' (bank/rekening/giro/cek) | null (keduanya)
     */
    public function scopeCashBank($query, ?string $kind = null)
    {
        if (static::hasCashBankFlags()) {
            $query->where('chart_of_accounts.is_active', true)->where('chart_of_accounts.is_cash_bank', true);
        } else {
            $query->cashBankCandidates();
        }

        return match ($kind) {
            'cash' => $query->where(function ($q) {
                $q->where('chart_of_accounts.code', 'LIKE', self::CASH_PREFIX . '%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%kas%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%tunai%');
            }),
            'bank' => $query->where(function ($q) {
                $q->where('chart_of_accounts.code', 'LIKE', self::BANK_PREFIX . '%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%bank%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%rekening%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%giro%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%cek%')
                    ->orWhere('chart_of_accounts.name', 'LIKE', '%cheque%');
            }),
            default => $query,
        };
    }

    public function coaParent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id')->withDefault();
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'coa_id');
    }

    /**
     * Get the normal balance type for this account type
     * 
     * @return string 'debit' or 'credit'
     */
    public function getNormalBalanceAttribute()
    {
        return match ($this->type) {
            'Asset', 'Expense' => 'debit',
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => 'credit',
            default => 'debit',
        };
    }

    /**
     * Calculate ending balance based on account type formula
     * 
     * @return float
     */
    public function calculateEndingBalance()
    {
        // Get all journal entries for this account
        $entries = $this->journalEntries;

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        // Calculate balance based on account type (normal balance)
        // Asset: Debit increases, Credit decreases
        // Contra Asset: Credit increases, Debit decreases (contra to asset)
        // Liability & Equity: Credit increases, Debit decreases
        $balance = match ($this->type) {
            'Asset', 'Expense' => $this->opening_balance + $totalDebit - $totalCredit,
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => $this->opening_balance - $totalDebit + $totalCredit,
            default => $this->opening_balance + $totalDebit - $totalCredit,
        };

        return $balance;
    }

    /**
     * Update ending balance automatically
     */
    public function updateEndingBalance()
    {
        $this->ending_balance = $this->calculateEndingBalance();
        $this->save();
    }

    /**
     * Get balance calculation formula description
     * 
     * @return string
     */
    public function getBalanceFormulaAttribute()
    {
        return match ($this->type) {
            'Asset', 'Expense' => 'Saldo Awal + Debit - Kredit',
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => 'Saldo Awal - Debit + Kredit',
            default => 'Saldo Awal + Debit - Kredit',
        };
    }

    /**
     * Get formatted name with code
     * 
     * @return string
     */
    public function getFormattedNameAttribute()
    {
        return "{$this->code} - {$this->name}";
    }
}
