<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin application settings page.
 *
 * Only accessible by the "Super Admin" role.
 */
class AppSettingsPage extends Page
{
    protected static ?string $navigationIcon   = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel  = 'Pengaturan Aplikasi';
    protected static ?string $navigationGroup  = 'Pengaturan';
    protected static ?int    $navigationSort   = 99;
    protected static string  $view             = 'filament.pages.app-settings';

    public bool $do_approval_required = true;

    /** Kop dokumen GLOBAL (T6, D41): dipakai bila Cabang tidak mengisi datanya sendiri. */
    public ?string $company_legal_name = null;
    public ?string $company_npwp = null;
    public ?string $company_address = null;
    public ?string $company_phone = null;
    public ?string $company_email = null;

    /** @var array<int, array<string, string|null>> */
    public array $company_bank_accounts = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Super Admin') === true;
    }

    public function mount(): void
    {
        $this->do_approval_required = AppSetting::doApprovalRequired();

        foreach (['company_legal_name', 'company_npwp', 'company_address', 'company_phone', 'company_email'] as $key) {
            $this->{$key} = (string) AppSetting::get($key, '') ?: null;
        }
        $banks = json_decode((string) AppSetting::get('company_bank_accounts', '[]'), true);
        $this->company_bank_accounts = is_array($banks) ? array_values($banks) : [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Delivery Order')
                    ->description('Pengaturan alur approval Delivery Order')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Toggle::make('do_approval_required')
                            ->label('Wajib Approval sebelum DO dapat dikirim')
                            ->helperText(
                                'Jika aktif: DO harus melalui tahap Request Approve → Approve sebelum bisa ditandai Terkirim. '
                              . 'Jika nonaktif: DO dapat langsung ditandai Terkirim dari status Draft.'
                            )
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->onColor('success')
                            ->offColor('danger'),
                    ]),
                Section::make('Kop Dokumen (Global)')
                    ->description('Dipakai pada Invoice, Delivery Order, Kwitansi, Nota Kredit, dan Retur bila Cabang tidak mengisi data kop sendiri.')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        TextInput::make('company_legal_name')->label('Nama Legal Perusahaan')->maxLength(150),
                        TextInput::make('company_npwp')->label('NPWP')->maxLength(40),
                        Textarea::make('company_address')->label('Alamat')->columnSpanFull(),
                        TextInput::make('company_phone')->label('Telepon')->maxLength(50),
                        TextInput::make('company_email')->label('Email')->email()->maxLength(100),
                        Repeater::make('company_bank_accounts')
                            ->label('Rekening Bank untuk Pembayaran')
                            ->schema([
                                TextInput::make('bank')->label('Bank')->required()->maxLength(60),
                                TextInput::make('number')->label('No. Rekening')->required()->maxLength(40),
                                TextInput::make('holder')->label('Atas Nama')->maxLength(100),
                                TextInput::make('branch')->label('Kantor Cabang Bank')->maxLength(100),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('+ Tambah Rekening')
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('');
    }

    public function save(): void
    {
        AppSetting::set(
            'do_approval_required',
            $this->do_approval_required ? '1' : '0',
            'Wajib approval sebelum Delivery Order dapat ditandai Terkirim'
        );

        foreach (['company_legal_name', 'company_npwp', 'company_address', 'company_phone', 'company_email'] as $key) {
            AppSetting::set($key, trim((string) $this->{$key}), 'Kop dokumen global');
        }
        AppSetting::set(
            'company_bank_accounts',
            json_encode(array_values(array_filter($this->company_bank_accounts, fn ($row) => filled($row['bank'] ?? null) && filled($row['number'] ?? null)))),
            'Rekening bank pada kop dokumen global'
        );

        Notification::make()
            ->title('Pengaturan disimpan')
            ->success()
            ->send();
    }
}
