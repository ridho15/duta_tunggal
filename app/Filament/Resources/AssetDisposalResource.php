<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetDisposalResource\Pages;
use App\Filament\Resources\AssetDisposalResource\RelationManagers;
use App\Models\AssetDisposal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class AssetDisposalResource extends Resource
{
    protected static ?string $model = AssetDisposal::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Disposal Aset';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asset Information')
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
                                        $asset = \App\Models\Asset::find($value);
                                        if (!$asset) {
                                            $fail('Asset tidak ditemukan.');
                                            return;
                                        }
                                        
                                        if ($asset->status !== 'active') {
                                            $fail('Hanya asset dengan status aktif yang dapat didisposal.');
                                        }
                                        
                                        $existingDisposal = \App\Models\AssetDisposal::where('asset_id', $value)
                                            ->whereIn('status', ['pending', 'approved', 'completed'])
                                            ->exists();
                                        
                                        if ($existingDisposal) {
                                            $fail('Asset ini sudah memiliki disposal yang sedang diproses.');
                                        }
                                    };
                                },
                            ])
                            ->validationMessages([
                                'required' => 'Asset wajib dipilih.',
                            ])
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->name} (Book Value: Rp " . number_format($record->book_value, 0, ',', '.') . ")";
                            })
                            ->options(function () {
                                return \App\Models\Asset::where('status', 'active')
                                    ->with('cabang')
                                    ->get()
                                    ->mapWithKeys(function ($asset) {
                                        return [$asset->id => "{$asset->name} - {$asset->cabang->nama} (Rp " . number_format($asset->book_value, 0, ',', '.') . ")"];
                                    });
                            }),
                    ]),

                Forms\Components\Section::make('Disposal Details')
                    ->schema([
                        Forms\Components\DatePicker::make('disposal_date')
                            ->label('Disposal Date')
                            ->required()
                            ->rules([
                                'required',
                                'date',
                                'after_or_equal:today',
                            ])
                            ->validationMessages([
                                'required' => 'Tanggal disposal wajib diisi.',
                                'date' => 'Format tanggal tidak valid.',
                                'after_or_equal' => 'Tanggal disposal tidak boleh di masa lalu.',
                            ])
                            ->default(now()),
                        Forms\Components\Select::make('disposal_type')
                            ->label('Disposal Type')
                            ->options([
                                'sale' => 'Sale',
                                'scrap' => 'Scrap',
                                'donation' => 'Donation',
                                'theft' => 'Theft/Loss',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->rules([
                                'required',
                                'in:sale,scrap,donation,theft,other',
                            ])
                            ->validationMessages([
                                'required' => 'Tipe disposal wajib dipilih.',
                                'in' => 'Tipe disposal tidak valid.',
                            ]),
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Sale Price')
                            ->numeric()
                            ->prefix('Rp')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $disposalType = request()->input('disposal_type');
                                        if ($disposalType === 'sale') {
                                            if (empty($value)) {
                                                $fail('Harga jual wajib diisi untuk disposal tipe Sale.');
                                                return;
                                            }
                                            if (!is_numeric($value)) {
                                                $fail('Harga jual harus berupa angka.');
                                                return;
                                            }
                                            if ($value <= 0) {
                                                $fail('Harga jual harus lebih besar dari 0.');
                                            }
                                        }
                                    };
                                },
                            ])
                            ->validationMessages([
                                'numeric' => 'Harga jual harus berupa angka.',
                            ])
                            ->visible(fn ($get) => $get('disposal_type') === 'sale')
                            ->required(fn ($get) => $get('disposal_type') === 'sale'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->rules([
                                'nullable',
                                'string',
                                'min:10',
                                'max:1000',
                            ])
                            ->validationMessages([
                                'min' => 'Catatan minimal 10 karakter.',
                                'max' => 'Catatan maksimal 1000 karakter.',
                            ]),
                        Forms\Components\FileUpload::make('disposal_document')
                            ->label('Supporting Document')
                            ->directory('asset-disposals')
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
                Tables\Columns\TextColumn::make('asset.cabang.nama')
                    ->label('Cabang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('disposal_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('disposal_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sale' => 'success',
                        'scrap' => 'danger',
                        'donation' => 'info',
                        'theft' => 'warning',
                        'other' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sale_price')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('book_value_at_disposal')
                    ->label('Book Value')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('formatted_gain_loss')
                    ->label('Gain/Loss'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('disposal_type')
                    ->options([
                        'sale' => 'Sale',
                        'scrap' => 'Scrap',
                        'donation' => 'Donation',
                        'theft' => 'Theft/Loss',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
                            'approved_at' => now(),
                        ]);

                        // Update asset status
                        $record->asset->update(['status' => 'disposed']);

                        // Post journal entries
                        $disposalService = app(\App\Services\AssetDisposalService::class);
                        $disposalService->postDisposalJournalEntries($record->asset, $record);

                        \Filament\Notifications\Notification::make()
                            ->title('Asset disposal berhasil diproses')
                            ->body("Asset {$record->asset->name} telah didisposal dan jurnal telah dipost")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->description(new HtmlString('
                <details class="space-y-2">
                    <summary class="cursor-pointer font-semibold text-gray-700 dark:text-gray-300">
                        📋 Panduan Penggunaan Asset Disposal Management
                    </summary>
                    <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🎯 Fungsi Utama</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Mengelola penghapusan aset tetap perusahaan (penjualan, scrap, donasi, dll)</li>
                                <li>Menghitung gain/loss dari penjualan aset</li>
                                <li>Membuat jurnal akuntansi untuk penghapusan aset</li>
                                <li>Melacak status disposal dari request hingga penyelesaian</li>
                                <li>Menyediakan approval workflow untuk disposal aset</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📊 Status Flow</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Pending:</strong> Disposal request telah dibuat, menunggu approval</li>
                                <li><strong>Completed:</strong> Disposal telah disetujui dan diproses</li>
                                <li><strong>Cancelled:</strong> Disposal dibatalkan dengan alasan tertentu</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">✅ Validasi & Aturan</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Hanya aset dengan status aktif yang dapat didisposal</li>
                                <li>Harga jual wajib diisi untuk disposal type "sale"</li>
                                <li>Book value akan otomatis dihitung dari data aset</li>
                                <li>Gain/Loss dihitung otomatis (harga jual - book value)</li>
                                <li>Setiap disposal akan membuat jurnal akuntansi otomatis</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">⚡ Aksi Tersedia</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>View:</strong> Melihat detail disposal dan riwayat</li>
                                <li><strong>Edit:</strong> Mengubah detail disposal (hanya untuk status pending)</li>
                                <li><strong>Approve:</strong> Menyetujui disposal dan memproses jurnal</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔐 Permission & Akses</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>User Biasa:</strong> Dapat membuat request disposal untuk aset di cabang mereka</li>
                                <li><strong>Manager:</strong> Dapat approve disposal request</li>
                                <li><strong>Admin:</strong> Dapat mengelola semua disposal di semua cabang</li>
                                <li><strong>Accounting:</strong> Dapat melihat jurnal yang dihasilkan dari disposal</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔗 Integrasi Sistem</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Asset Management:</strong> Update status aset menjadi "disposed"</li>
                                <li><strong>Chart of Account:</strong> Posting jurnal disposal aset</li>
                                <li><strong>General Ledger:</strong> Pencatatan penghapusan aset</li>
                                <li><strong>User Management:</strong> Tracking user yang melakukan approval</li>
                                <li><strong>File Storage:</strong> Upload dokumen disposal</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📋 Jenis Disposal</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Sale:</strong> Penjualan aset dengan harga tertentu</li>
                                <li><strong>Scrap:</strong> Penghancuran aset yang tidak layak pakai</li>
                                <li><strong>Donation:</strong> Penyerahan aset sebagai donasi</li>
                                <li><strong>Theft/Loss:</strong> Kehilangan atau pencurian aset</li>
                                <li><strong>Other:</strong> Disposal dengan alasan lain</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📈 Perhitungan Gain/Loss</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Gain:</strong> Jika harga jual > book value (keuntungan)</li>
                                <li><strong>Loss:</strong> Jika harga jual < book value (kerugian)</li>
                                <li><strong>Break Even:</strong> Jika harga jual = book value</li>
                                <li>Gain/Loss akan dicatat dalam jurnal akuntansi</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">⚠️ Catatan Penting</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Disposal bersifat permanen dan tidak dapat dibatalkan</li>
                                <li>Pastikan semua dokumen disposal tersedia sebelum approval</li>
                                <li>Approval diperlukan untuk mencegah disposal yang tidak sah</li>
                                <li>Semua disposal tercatat dalam audit trail untuk compliance</li>
                                <li>Jurnal akuntansi akan terbentuk otomatis setelah approval</li>
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
            'index' => Pages\ListAssetDisposals::route('/'),
            'create' => Pages\CreateAssetDisposal::route('/create'),
            'edit' => Pages\EditAssetDisposal::route('/{record}/edit'),
        ];
    }
}
