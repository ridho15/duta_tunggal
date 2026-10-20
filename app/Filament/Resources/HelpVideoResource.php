<?php

namespace App\Filament\Resources;

use App\Models\HelpVideo;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/** PENGATURAN → Video Tutorial (Tahap 3): daftar video per peran untuk halaman Bantuan. */
class HelpVideoResource extends Resource
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $model = HelpVideo::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'PENGATURAN';

    protected static ?string $navigationLabel = 'Video Tutorial';

    protected static ?string $modelLabel = 'Video Tutorial';

    protected static ?string $pluralModelLabel = 'Video Tutorial';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'video-tutorial';

    private static function allowed(): bool
    {
        return (bool) Auth::user()?->hasRole(['Owner', 'Super Admin', 'Admin']);
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit($record): bool { return static::allowed(); }
    public static function canDelete($record): bool { return static::allowed(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Select::make('role_code')->label('Untuk peran')->options(HelpVideo::ROLE_LABELS)->placeholder('Semua peran'),
            TextInput::make('url')->label('Link video')->required()->url()->maxLength(500)
                ->helperText('Link YouTube (boleh unlisted) atau file .mp4.'),
            TextInput::make('duration_minutes')->label('Durasi (menit)')->numeric()->minValue(0),
            TextInput::make('sort')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Tampilkan')->default(true),
            Textarea::make('description')->label('Keterangan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('role_code')->label('Peran')
                    ->formatStateUsing(fn (?string $state) => $state ? (HelpVideo::ROLE_LABELS[$state] ?? $state) : 'Semua peran')
                    ->placeholder('Semua peran'),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' menit')->placeholder('-'),
                TextColumn::make('sort')->label('Urutan'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => HelpVideoResource\Pages\ListHelpVideos::route('/'),
            'create' => HelpVideoResource\Pages\CreateHelpVideo::route('/create'),
            'edit' => HelpVideoResource\Pages\EditHelpVideo::route('/{record}/edit'),
        ];
    }
}
