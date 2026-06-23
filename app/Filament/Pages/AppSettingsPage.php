<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Section;
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

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Super Admin') === true;
    }

    public function mount(): void
    {
        $this->do_approval_required = AppSetting::doApprovalRequired();
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

        Notification::make()
            ->title('Pengaturan disimpan')
            ->success()
            ->send();
    }
}
