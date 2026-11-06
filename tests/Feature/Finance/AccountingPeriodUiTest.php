<?php

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\MenuAccess;
use Spatie\Permission\Models\Role;

function periodUser(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    MenuAccess::flush();
    $user = User::factory()->create(['manage_type' => ['all']]);
    $user->assignRole($role);

    return $user;
}

function periodPayload(Cabang $cabang, array $override = []): array
{
    return array_merge(['cabang_id' => $cabang->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31'], $override);
}

function periodJournal(ChartOfAccount $coa, Cabang $cabang, string $date, float $debit, float $credit): JournalEntry
{
    return JournalEntry::create(['coa_id' => $coa->id, 'date' => $date, 'reference' => 'T-'.uniqid(), 'description' => 'Uji', 'debit' => $debit, 'credit' => $credit,
        'journal_type' => 'manual', 'cabang_id' => $cabang->id, 'transaction_id' => 'T-'.uniqid()]);
}

it('Auditor (tanpa izin) ditolak; Accounting boleh melihat tetapi tidak boleh membuat atau menutup', function () {
    $cabang = Cabang::factory()->create();
    $period = AccountingPeriod::create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'open', 'cabang_id' => $cabang->id]);

    $this->actingAs(periodUser('Auditor'))->get(route('keuangan.period.index'))->assertForbidden();

    $accounting = periodUser('Accounting');
    $this->actingAs($accounting)->get(route('keuangan.period.index'))->assertOk()->assertSee('01/10/2026')->assertDontSee('Tutup buku<');
    $this->actingAs($accounting)->get(route('keuangan.period.create'))->assertForbidden();
    $this->actingAs($accounting)->post(route('keuangan.period.store'), periodPayload($cabang))->assertForbidden();
    $this->actingAs($accounting)->get(route('keuangan.period.close.form', $period))->assertForbidden();
    $this->actingAs($accounting)->post(route('keuangan.period.close', $period))->assertForbidden();
    expect($period->fresh()->status)->toBe('open');
});

it('Finance Manager membuat periode; tumpang tindih dan rentang terbalik ditolak', function () {
    $cabang = Cabang::factory()->create();
    $finance = periodUser('Finance Manager');

    $this->actingAs($finance)->post(route('keuangan.period.store'), periodPayload($cabang))->assertRedirect(route('keuangan.period.index'));
    expect(AccountingPeriod::where('cabang_id', $cabang->id)->count())->toBe(1);

    $this->actingAs($finance)->post(route('keuangan.period.store'), periodPayload($cabang, ['period_start' => '2026-10-15', 'period_end' => '2026-11-15']))
        ->assertSessionHasErrors('period_start');
    $this->actingAs($finance)->post(route('keuangan.period.store'), periodPayload($cabang, ['period_start' => '2026-12-31', 'period_end' => '2026-12-01']))
        ->assertSessionHasErrors('period_end');
    expect(AccountingPeriod::count())->toBe(1);
});

it('menutup buku: jurnal penutup dibuat, status terkunci, dan jurnal bertanggal di periode itu ditolak', function () {
    $cabang = Cabang::factory()->create();
    $revenue = ChartOfAccount::factory()->create(['code' => '4000', 'type' => 'Revenue']);
    $equity = ChartOfAccount::factory()->create(['code' => '3400', 'name' => 'LABA DITAHAN', 'type' => 'Equity']);
    $bank = ChartOfAccount::factory()->create(['code' => '1100', 'type' => 'Asset']);
    periodJournal($revenue, $cabang, '2026-10-10', 0, 500000);
    periodJournal($bank, $cabang, '2026-10-10', 500000, 0);
    $period = AccountingPeriod::create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'open', 'cabang_id' => $cabang->id]);
    $finance = periodUser('Finance Manager');

    $this->actingAs($finance)->get(route('keuangan.period.close.form', $period))->assertOk()->assertSee('Yang akan terjadi');
    $this->actingAs($finance)->post(route('keuangan.period.close', $period), ['generate_closing_entries' => '1'])->assertRedirect(route('keuangan.period.index'));

    $period->refresh();
    expect($period->status)->toBe('closed')->and($period->closed_by)->toBe($finance->id)->and($period->closed_at)->not->toBeNull()
        ->and(JournalEntry::withoutGlobalScopes()->where('journal_type', 'closing')->where('source_id', $period->id)->count())->toBe(2);

    expect(fn () => periodJournal($bank, $cabang, '2026-10-20', 1000, 0))->toThrow(ClosedPeriodException::class);
    periodJournal($bank, $cabang, '2026-11-02', 1000, 0);   // di luar periode tetap boleh
    expect(JournalEntry::withoutGlobalScopes()->where('date', '2026-11-02')->exists())->toBeTrue();
});

it('menutup tanpa jurnal penutup hanya mengunci; akun Laba Ditahan yang hilang menggagalkan penutupan penuh', function () {
    $cabang = Cabang::factory()->create();
    $revenue = ChartOfAccount::factory()->create(['code' => '4000', 'type' => 'Revenue']);
    periodJournal($revenue, $cabang, '2026-10-10', 0, 500000);
    $finance = periodUser('Finance Manager');

    $gagal = AccountingPeriod::create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'open', 'cabang_id' => $cabang->id]);
    $this->actingAs($finance)->post(route('keuangan.period.close', $gagal), ['generate_closing_entries' => '1'])->assertSessionHas('error');
    expect($gagal->fresh()->status)->toBe('open')   // transaksi di-rollback, tidak setengah tertutup
        ->and(JournalEntry::withoutGlobalScopes()->where('journal_type', 'closing')->count())->toBe(0);

    $this->actingAs($finance)->post(route('keuangan.period.close', $gagal))->assertRedirect(route('keuangan.period.index'));   // kotak jurnal penutup tidak dicentang
    expect($gagal->fresh()->status)->toBe('closed')->and(JournalEntry::withoutGlobalScopes()->where('journal_type', 'closing')->count())->toBe(0);
});

it('buka kembali hanya oleh peran yang diizinkan Policy; periode tertutup tidak bisa dihapus, yang terbuka bisa', function () {
    $cabang = Cabang::factory()->create();
    $closed = AccountingPeriod::create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'status' => 'closed', 'cabang_id' => $cabang->id, 'closed_at' => now()]);
    $open = AccountingPeriod::create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'open', 'cabang_id' => $cabang->id]);

    $this->actingAs(periodUser('Admin'))->post(route('keuangan.period.reopen', $closed))->assertForbidden();   // Policy: hanya Super Admin/Director/Finance Manager

    $finance = periodUser('Finance Manager');
    $this->actingAs($finance)->delete(route('keuangan.period.destroy', $closed))->assertForbidden();
    $this->actingAs($finance)->delete(route('keuangan.period.destroy', $open))->assertSessionHas('success');
    $this->actingAs($finance)->post(route('keuangan.period.reopen', $closed))->assertSessionHas('success');

    expect($closed->fresh()->status)->toBe('open')->and($closed->fresh()->closed_by)->toBeNull()->and(AccountingPeriod::whereKey($open->id)->exists())->toBeFalse();
});
