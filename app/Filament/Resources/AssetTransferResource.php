<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetTransferResource\Pages;
use App\Filament\Resources\AssetTransferResource\RelationManagers;
use App\Models\AssetTransfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class AssetTransferResource extends Resource
{
    protected static ?string $model = AssetTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Transfer Aset';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('asset', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transfer Information')
                    ->schema([
                        Forms\Components\Select::make('asset_id')
                            ->label('Asset')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                'required',
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $existingTransfer = \App\Models\AssetTransfer::where('asset_id', $value)
                                            ->whereIn('status', ['pending', 'approved'])
                                            ->exists();
                                        
                                        if ($existingTransfer) {
                                            $fail('Aset ini sedang dalam proses transfer dan belum selesai.');
                                        }
                                    };
                                },
                            ])
                            ->validationMessages([
                                'required' => 'Asset wajib dipilih.',
                            ])
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->name} - {$record->cabang->nama}";
                            })
                            ->options(function () {
                                $user = Auth::user();
                                $query = \App\Models\Asset::where('status', 'active')
                                    ->with('cabang');
                                
                                if ($user && !in_array('all', $user->manage_type ?? [])) {
                                    $query->where('cabang_id', $user->cabang_id);
                                }
                                
                                return $query->get()
                                    ->mapWithKeys(function ($asset) {
                                        return [$asset->id => "{$asset->name} - {$asset->cabang->nama}"];
                                    });
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $asset = \App\Models\Asset::find($state);
                                    if ($asset) {
                                        $set('from_cabang_id', $asset->cabang_id);
                                    }
                                }
                            }),
                        Forms\Components\Hidden::make('from_cabang_id'),
                        Forms\Components\Hidden::make('requested_by')
                            ->default(fn () => Auth::id()),
                        Forms\Components\Select::make('to_cabang_id')
                            ->label('Transfer To Cabang')
                            ->relationship('toCabang', 'nama')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                'required',
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $fromCabangId = request()->input('from_cabang_id');
                                        if ($fromCabangId && $value == $fromCabangId) {
                                            $fail('Cabang tujuan harus berbeda dengan cabang asal.');
                                        }
                                    };
                                },
                            ])
                            ->validationMessages([
                                'required' => 'Cabang tujuan wajib dipilih.',
                            ])
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "({$record->kode}) {$record->nama}";
                            }),
                        Forms\Components\DatePicker::make('transfer_date')
                            ->label('Transfer Date')
                            ->required()
                            ->rules([
                                'required',
                                'date',
                                'after_or_equal:today',
                            ])
                            ->validationMessages([
                                'required' => 'Tanggal transfer wajib diisi.',
                                'date' => 'Format tanggal tidak valid.',
                                'after_or_equal' => 'Tanggal transfer tidak boleh di masa lalu.',
                            ])
                            ->default(now()),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->required()
                            ->rules([
                                'required',
                                'string',
                                'min:10',
                                'max:1000',
                            ])
                            ->validationMessages([
                                'required' => 'Alasan transfer wajib diisi.',
                                'min' => 'Alasan transfer minimal 10 karakter.',
                                'max' => 'Alasan transfer maksimal 1000 karakter.',
                            ]),
                        Forms\Components\FileUpload::make('transfer_document')
                            ->label('Supporting Document')
                            ->directory('asset-transfers')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/gif'])
                            ->maxSize(5120) // 5MB
                            ->rules([
                                'nullable',
                                'file',
                                'mimes:pdf,jpeg,png,gif',
                                'max:5120', // 5MB in KB
                            ])
                            ->validationMessages([
                                'mimes' => 'Dokumen pendukung harus berupa file PDF atau gambar (JPEG, PNG, GIF).',
                                'max' => 'Ukuran file maksimal 5MB.',
                            ]),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Asset')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromCabang.nama')
                    ->label('From Cabang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toCabang.nama')
                    ->label('To Cabang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transfer_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('from_cabang_id')
                    ->label('From Cabang')
                    ->relationship('fromCabang', 'nama'),
                Tables\Filters\SelectFilter::make('to_cabang_id')
                    ->label('To Cabang')
                    ->relationship('toCabang', 'nama'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => $record->status === 'pending'),
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn($record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $transferService = app(\App\Services\AssetTransferService::class);
                            $transferService->approveTransfer($record);
                        }),
                    Tables\Actions\Action::make('complete')
                        ->label('Complete Transfer')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->status === 'approved')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $transferService = app(\App\Services\AssetTransferService::class);
                            $transferService->completeTransfer($record);
                        }),
                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn($record) => in_array($record->status, ['pending', 'approved']))
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('cancel_reason')
                                ->label('Cancel Reason')
                                ->required()
                                ->rules([
                                    'required',
                                    'string',
                                    'min:10',
                                    'max:500',
                                ])
                                ->validationMessages([
                                    'required' => 'Alasan pembatalan wajib diisi.',
                                    'min' => 'Alasan pembatalan minimal 10 karakter.',
                                    'max' => 'Alasan pembatalan maksimal 500 karakter.',
                                ]),
                        ])
                        ->action(function ($record, array $data) {
                            $transferService = app(\App\Services\AssetTransferService::class);
                            $transferService->cancelTransfer($record, $data['cancel_reason']);
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->description(new HtmlString('
                <details class="space-y-2">
                    <summary class="cursor-pointer font-semibold text-gray-700 dark:text-gray-300">
                        📋 Panduan Penggunaan Asset Transfer Management
                    </summary>
                    <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🎯 Fungsi Utama</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Mengelola perpindahan aset antar cabang perusahaan</li>
                                <li>Melacak status transfer dari request hingga penyelesaian</li>
                                <li>Membuat jurnal akuntansi untuk perpindahan aset</li>
                                <li>Menjaga integritas data lokasi aset</li>
                                <li>Menyediakan approval workflow untuk transfer aset</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📊 Status Flow</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Pending:</strong> Transfer request telah dibuat, menunggu approval</li>
                                <li><strong>Approved:</strong> Transfer telah disetujui, siap untuk dieksekusi</li>
                                <li><strong>Completed:</strong> Transfer telah selesai, aset sudah berpindah lokasi</li>
                                <li><strong>Cancelled:</strong> Transfer dibatalkan dengan alasan tertentu</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">✅ Validasi & Aturan</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Aset harus dalam status aktif untuk dapat ditransfer</li>
                                <li>Cabang tujuan harus berbeda dengan cabang asal</li>
                                <li>Hanya user dengan permission yang sesuai yang dapat approve</li>
                                <li>Transfer yang sudah completed tidak dapat diubah</li>
                                <li>Setiap transfer akan membuat jurnal akuntansi otomatis</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">⚡ Aksi Tersedia</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>View:</strong> Melihat detail transfer dan riwayat</li>
                                <li><strong>Edit:</strong> Mengubah detail transfer (hanya untuk status pending)</li>
                                <li><strong>Approve:</strong> Menyetujui transfer request</li>
                                <li><strong>Complete Transfer:</strong> Menyelesaikan transfer dan update lokasi aset</li>
                                <li><strong>Cancel:</strong> Membatalkan transfer dengan alasan</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔐 Permission & Akses</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>User Biasa:</strong> Dapat membuat request transfer untuk aset di cabang mereka</li>
                                <li><strong>Manager:</strong> Dapat approve transfer request antar cabang</li>
                                <li><strong>Admin:</strong> Dapat mengelola semua transfer di semua cabang</li>
                                <li><strong>Accounting:</strong> Dapat melihat jurnal yang dihasilkan dari transfer</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔗 Integrasi Sistem</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Asset Management:</strong> Update lokasi aset secara otomatis</li>
                                <li><strong>Chart of Account:</strong> Posting jurnal transfer aset</li>
                                <li><strong>General Ledger:</strong> Pencatatan perpindahan aset antar cabang</li>
                                <li><strong>User Management:</strong> Tracking user yang melakukan request dan approval</li>
                                <li><strong>Cabang Management:</strong> Validasi cabang asal dan tujuan</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📋 Proses Transfer</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>1. Create Request:</strong> User membuat request transfer dengan memilih aset dan cabang tujuan</li>
                                <li><strong>2. Approval:</strong> Manager menyetujui atau menolak request</li>
                                <li><strong>3. Execution:</strong> Admin menyelesaikan transfer dan update lokasi aset</li>
                                <li><strong>4. Journal Posting:</strong> Sistem otomatis membuat jurnal akuntansi</li>
                                <li><strong>5. Status Update:</strong> Aset tercatat di cabang baru</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">⚠️ Catatan Penting</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Transfer hanya dapat dilakukan untuk aset yang masih aktif</li>
                                <li>Pastikan cabang tujuan memiliki kapasitas untuk menerima aset</li>
                                <li>Approval diperlukan untuk mencegah transfer yang tidak sah</li>
                                <li>Semua transfer tercatat dalam audit trail untuk compliance</li>
                                <li>Jurnal akuntansi akan terbentuk otomatis setelah transfer completed</li>
                            </ul>
                        </div>
                    </div>
                </details>
            '));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetTransfers::route('/'),
            'create' => Pages\CreateAssetTransfer::route('/create'),
            'edit' => Pages\EditAssetTransfer::route('/{record}/edit'),
        ];
    }
}
