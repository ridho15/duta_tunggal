<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalRuleResource\Pages;
use App\Models\ApprovalRule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * Aturan Persetujuan (T3.1, D6): ambang nominal & peran penyetuju Quotation / Sales Order yang dapat diubah tanpa deploy.
 * Hanya Super Admin dan Finance Manager; setiap perubahan tercatat (activity log).
 */
class ApprovalRuleResource extends Resource
{
    protected static ?string $model = ApprovalRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Aturan Persetujuan';

    protected static ?string $modelLabel = 'Aturan Persetujuan';

    protected static ?int $navigationSort = 90;

    private static function allowed(): bool
    {
        return (bool) Auth::user()?->hasRole(['Super Admin', 'Finance Manager']);
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('document_type')->label('Dokumen')->options(ApprovalRule::TYPES)->required(),
            TextInput::make('label')->label('Nama aturan')->required()->maxLength(120),
            TextInput::make('above_amount')->label('Berlaku bila nilai di atas (Rp)')->numeric()->minValue(0)
                ->helperText('Kosong = dari 0.'),
            TextInput::make('up_to_amount')->label('… dan sampai (Rp)')->numeric()->minValue(0)
                ->helperText('Kosong = tanpa batas atas.'),
            Select::make('roles')->label('Peran yang boleh menyetujui')->multiple()->required()
                ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name')->all()),
            TextInput::make('approver_label')->label('Teks penyetuju (pesan penolakan)')->required()->maxLength(255)
                ->placeholder('mis. Sales Manager / Direktur'),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_type')->label('Dokumen')->formatStateUsing(fn ($state) => ApprovalRule::TYPES[$state] ?? $state)->badge(),
                TextColumn::make('label')->label('Aturan')->searchable(),
                TextColumn::make('above_amount')->label('Di atas')->money('IDR')->placeholder('—'),
                TextColumn::make('up_to_amount')->label('Sampai')->money('IDR')->placeholder('tanpa batas'),
                TextColumn::make('roles')->label('Peran')->badge()->separator(','),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('document_type');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalRules::route('/'),
            'create' => Pages\CreateApprovalRule::route('/create'),
            'edit' => Pages\EditApprovalRule::route('/{record}/edit'),
        ];
    }
}
