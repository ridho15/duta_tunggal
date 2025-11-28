<?php

namespace App\Filament\Resources\PurchaseReceiptResource\RelationManagers;

use App\Models\Currency;
use App\Models\QualityControl;
use App\Services\QualityControlService;
use App\Services\PurchaseReceiptService;
use App\Http\Controllers\HelperController;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use App\Notifications\FilamentDatabaseNotification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Facades\Filament;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class PurchaseReceiptItemRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseReceiptItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->relationship('product', 'name', function ($get, Builder $query) {
                        $purchaseOrderId = $get('../../purchase_order_id');
                        return $query->whereHas('purchaseOrderItem', function (Builder $query) use ($purchaseOrderId) {
                            $query->where('purchase_order_id', $purchaseOrderId);
                        });
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->code})"),
                TextInput::make('qty_received')
                    ->label('Quantity Received')
                    ->numeric()
                    ->required(),
                TextInput::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->numeric()
                    ->required(),
                TextInput::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->numeric(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->required(),
                Select::make('rak_id')
                    ->label('Rak')
                    ->relationship('rak', 'name'),
                TextInput::make('reason_rejected')
                    ->label('Reason Rejected'),
                FileUpload::make('photos')
                    ->label('Photos')
                    ->multiple()
                    ->directory('purchase-receipt-items')
                    ->visibility('public'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(function ($record) {
                        return '(' . $record->product->sku . ') ' . $record->product->name;
                    })
                    ->sortable(),
                TextColumn::make('qty_received')
                    ->label('Quantity Received')
                    ->sortable(),
                TextColumn::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->sortable(),
                TextColumn::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->formatStateUsing(function ($record) {
                        if ($record->warehouse) {
                            return '(' . $record->warehouse->kode . ') ' . $record->warehouse->name;
                        }
                        return '';
                    })
                    ->sortable(),
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->sortable(),
                IconColumn::make('is_sent')
                    ->label('Sent to QC')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        // dd($record->qualityControl()->exists());
                        return $record->qualityControl()->exists() && $record->is_sent == 1;
                    }),
                ImageColumn::make('purchaseReceiptItemPhoto.photo_url')
                    ->label('Photos')
                    ->circular(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('send_to_qc')
                        ->label('Kirim ke Quality Control')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim ke Quality Control')
                        ->modalDescription('Apakah Anda yakin ingin mengirim item ini ke Quality Control? Tindakan ini tidak dapat dibatalkan.')
                        ->modalSubmitActionLabel('Ya, Kirim ke QC')
                        ->action(function ($record) {
                            // Check if already sent to QC
                            if ($record->qualityControl()->exists()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Sudah dikirim ke QC')
                                    ->body('Item ini sudah dikirim ke Quality Control.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Create Quality Control record
                            $qcService = app(QualityControlService::class);
                            $qcData = [
                                'passed_quantity' => 0, // Start with 0, will be determined by QC inspection
                                'rejected_quantity' => $record->qty_rejected ?? 0,
                                'warehouse_id' => $record->warehouse_id,
                                'rak_id' => $record->rak_id,
                                'inspected_by' => Auth::id(),
                            ];

                            $qc = $qcService->createQCFromPurchaseReceiptItem($record, $qcData);

                            // Update is_sent status
                            $record->update(['is_sent' => 1]);

                            \Filament\Notifications\Notification::make()
                                ->title('Berhasil dikirim ke QC')
                                ->body('Item berhasil dikirim ke Quality Control.')
                                ->success()
                                ->send();
                        })
                        ->visible(function ($record) {
                            return !$record->qualityControl()->exists() && $record->qty_received > 0;
                        }),
                    EditAction::make()
                        ->color('success')
                        ->hidden(function ($record) {
                            return $record->is_sent == 1;
                        }),
                    DeleteAction::make()
                        ->hidden(function ($record) {
                            return $record->is_sent == 1;
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
