<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Filament\Resources\StockAdjustmentResource\RelationManagers;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'Stock Adjustment';

    protected static ?string $pluralLabel = 'Stock Adjustments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Informasi Adjustment')
                    ->schema([
                        TextInput::make('adjustment_number')
                            ->label('Nomor Adjustment')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => \App\Models\StockAdjustment::generateAdjustmentNumber())
                            ->readonly()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('regenerate')
                                    ->label('Generate Baru')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Forms\Set $set) {
                                        $set('adjustment_number', \App\Models\StockAdjustment::generateAdjustmentNumber());
                                    })
                            )
                            ->validationMessages([
                                'required' => 'Nomor adjustment harus diisi',
                                'unique' => 'Nomor adjustment sudah digunakan'
                            ]),

                        DatePicker::make('adjustment_date')
                            ->label('Tanggal Adjustment')
                            ->required()
                            ->default(now())
                            ->validationMessages([
                                'required' => 'Tanggal adjustment harus diisi'
                            ]),

                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1);
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->validationMessages([
                                'required' => 'Warehouse harus dipilih'
                            ]),

                        Select::make('adjustment_type')
                            ->label('Tipe Adjustment')
                            ->options([
                                'increase' => 'Penambahan Stock (+)',
                                'decrease' => 'Pengurangan Stock (-)',
                            ])
                            ->required()
                            ->validationMessages([
                                'required' => 'Tipe adjustment harus dipilih'
                            ]),

                        TextInput::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->validationMessages([
                                'required' => 'Alasan adjustment harus diisi'
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adjustment_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('adjustment_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('warehouse', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->sortable(),

                TextColumn::make('adjustment_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'increase' => 'success',
                        'decrease' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'increase' => 'Penambahan (+)',
                        'decrease' => 'Pengurangan (-)',
                    }),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->options(Warehouse::all()->mapWithKeys(function ($warehouse) {
                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                    })),

                SelectFilter::make('adjustment_type')
                    ->label('Tipe Adjustment')
                    ->options([
                        'increase' => 'Penambahan Stock (+)',
                        'decrease' => 'Pengurangan Stock (-)',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new HtmlString('
                <details style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #dee2e6;">
                    <summary style="cursor: pointer; font-weight: bold; color: #495057; font-size: 14px;">
                        📊 Panduan Manajemen Penyesuaian Stok
                    </summary>
                    <div style="margin-top: 12px; color: #495057; font-size: 13px; line-height: 1.5;">
                        <div style="margin-bottom: 12px;">
                            <strong style="color: #dc3545;">🎯 Tujuan & Fungsi:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li>Mengelola koreksi dan penyesuaian quantity stok inventory</li>
                                <li>Menangani perbedaan fisik vs sistem dalam stock taking</li>
                                <li>Memperbaiki kesalahan input atau kerusakan produk</li>
                                <li>Mempertahankan akurasi data inventory dengan approval workflow</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #28a745;">🔄 Tipe Penyesuaian:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Penambahan Stock (+):</strong> Menambah quantity untuk koreksi kekurangan</li>
                                <li><strong>Pengurangan Stock (-):</strong> Mengurangi quantity untuk koreksi kelebihan</li>
                                <li><strong>Nomor Adjustment:</strong> Auto-generated untuk tracking unik setiap adjustment</li>
                                <li><strong>Tanggal Adjustment:</strong> Tanggal efektif perubahan stok</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #007bff;">⚙️ Item Penyesuaian:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Produk:</strong> Pilih produk yang akan disesuaikan</li>
                                <li><strong>Rak & Lokasi:</strong> Tentukan lokasi penyimpanan spesifik</li>
                                <li><strong>Quantity:</strong> Jumlah yang akan ditambah/dikurangi</li>
                                <li><strong>Kondisi:</strong> Status produk (good/damage/repair)</li>
                                <li><strong>Alasan:</strong> Penjelasan detail mengapa adjustment dilakukan</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #ffc107;">📊 Alur Status:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Draft:</strong> Adjustment dibuat, dapat diedit/dihapus</li>
                                <li><strong>Approved:</strong> Adjustment disetujui, stok diperbarui otomatis</li>
                                <li><strong>Rejected:</strong> Adjustment ditolak, tidak ada perubahan stok</li>
                                <li><strong>Kode Warna:</strong> Abu-abu=Draft, Hijau=Approved, Merah=Rejected</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #17a2b8;">🔗 Integrasi & Dependensi:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Inventory Management:</strong> Langsung memperbarui stock levels</li>
                                <li><strong>Warehouse & Racks:</strong> Mengelola penempatan produk per lokasi</li>
                                <li><strong>Stock Movement:</strong> Mencatat pergerakan stok untuk audit trail</li>
                                <li><strong>Product Management:</strong> Terintegrasi dengan master data produk</li>
                                <li><strong>Reporting:</strong> Data adjustment tersedia untuk laporan inventory</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #6c757d;">🔐 Permission & Workflow:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Create:</strong> User dapat membuat adjustment draft</li>
                                <li><strong>Edit:</strong> Hanya adjustment draft yang dapat dimodifikasi</li>
                                <li><strong>Approve/Reject:</strong> Membutuhkan permission khusus untuk approval</li>
                                <li><strong>Delete:</strong> Hanya draft atau admin yang dapat menghapus</li>
                                <li><strong>View:</strong> Semua user dapat melihat detail adjustment</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #fd7e14;">📈 Reporting & Audit:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Audit Trail:</strong> Mencatat semua perubahan dan approval</li>
                                <li><strong>Stock History:</strong> Riwayat perubahan quantity per produk</li>
                                <li><strong>User Tracking:</strong> Mencatat siapa yang membuat/menyetujui</li>
                                <li><strong>Reason Logging:</strong> Alasan adjustment tersimpan untuk reference</li>
                            </ul>
                        </div>

                        <div style="background: #fff3cd; padding: 8px; border-radius: 4px; border-left: 4px solid #ffc107;">
                            <strong style="color: #856404;">⚠️ Catatan Penting:</strong>
                            <ul style="margin: 4px 0; padding-left: 20px; color: #856404;">
                                <li>Adjustment yang sudah approved akan langsung mempengaruhi quantity stok</li>
                                <li>Pastikan alasan adjustment jelas dan dapat dipertanggungjawabkan</li>
                                <li>Adjustment tidak dapat diubah setelah approved - buat adjustment baru jika perlu</li>
                                <li>Gunakan dengan hati-hati karena langsung mempengaruhi akurasi inventory</li>
                                <li>Selalu lakukan stock opname fisik sebelum melakukan adjustment besar</li>
                            </ul>
                        </div>
                    </div>
                </details>
            '));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockAdjustmentItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
