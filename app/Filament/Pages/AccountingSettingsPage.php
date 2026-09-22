<?php

namespace App\Filament\Pages;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Services\AccountingSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan Akuntansi (T3.4, D9): akun jurnal alur penjualan. Hanya Super Admin dan Finance Manager; setiap perubahan tercatat.
 * Kunci kosong = memakai akun bawaan (config/coa.php + kode bawaan).
 */
class AccountingSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Pengaturan Akuntansi';

    protected static ?string $title = 'Pengaturan Akuntansi';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.pages.accounting-settings';

    /** @var array<string, int|null> */
    public array $accounts = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasRole(['Super Admin', 'Finance Manager']);
    }

    public function getFlagEnabledProperty(): bool
    {
        return AccountingSettings::enabled();
    }

    public function mount(): void
    {
        $saved = AccountingSetting::query()->pluck('coa_id', 'key');
        foreach (AccountingSettings::editableKeys() as $key => $definition) {
            $this->accounts[$key] = $saved[$key] ?? null;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function statusRows(): array
    {
        return app(AccountingSettings::class)->status();
    }

    public function form(Form $form): Form
    {
        $service = app(AccountingSettings::class);
        $fields = [];

        foreach (AccountingSettings::editableKeys() as $key => $definition) {
            $fields[] = Select::make("accounts.{$key}")
                ->label($definition['label'])
                ->helperText($definition['hint'].' · tipe: '.implode('/', $definition['types']))
                ->searchable()
                ->placeholder('Pakai akun bawaan')
                ->options(fn () => ChartOfAccount::query()
                    ->where('is_active', true)->whereIn('type', $definition['types'])->whereDoesntHave('children')
                    ->orderBy('code')->get(['id', 'code', 'name'])
                    ->mapWithKeys(fn ($coa) => [$coa->id => "{$coa->code} — {$coa->name}"])->all())
                ->rule(function () use ($service, $key) {
                    return function (string $attribute, $value, \Closure $fail) use ($service, $key) {
                        if (blank($value)) {
                            return;
                        }
                        $coa = ChartOfAccount::find($value);
                        if ($coa && ($error = $service->problem($key, $coa))) {
                            $fail($error);
                        }
                    };
                });
        }

        return $form->schema([
            Section::make('Akun jurnal penjualan')
                ->description('Mengganti akun di sini mengubah akun pada jurnal transaksi BERIKUTNYA (jurnal lama tidak berubah). Hanya akun detail yang aktif dan bertipe sesuai.')
                ->schema($fields)->columns(2),
        ]);
    }

    public function save(): void
    {
        $this->form->validate();
        $service = app(AccountingSettings::class);

        try {
            foreach ($this->accounts as $key => $coaId) {
                $service->set($key, filled($coaId) ? (int) $coaId : null, Auth::user());
            }
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Pengaturan tidak valid')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        }

        Notification::make()->success()->title('Pengaturan Akuntansi disimpan')->send();
    }
}
