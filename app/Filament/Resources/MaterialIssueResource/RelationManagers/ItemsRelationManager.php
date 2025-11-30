<?php

namespace App\Filament\Resources\MaterialIssueResource\RelationManagers;

use App\Models\MaterialIssueItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'product.name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('uom_id')
                    ->label('Satuan')
                    ->relationship('uom', 'name')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('cost_per_unit')
                    ->label('Harga per Unit')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->label('Status Approval')
                    ->options([
                        MaterialIssueItem::STATUS_DRAFT => 'Draft',
                        MaterialIssueItem::STATUS_PENDING_APPROVAL => 'Pending Approval',
                        MaterialIssueItem::STATUS_APPROVED => 'Approved',
                        MaterialIssueItem::STATUS_COMPLETED => 'Completed',
                    ])
                    ->default(MaterialIssueItem::STATUS_DRAFT)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status Approval')
                    ->colors([
                        'secondary' => MaterialIssueItem::STATUS_DRAFT,
                        'warning' => MaterialIssueItem::STATUS_PENDING_APPROVAL,
                        'success' => MaterialIssueItem::STATUS_APPROVED,
                        'success' => MaterialIssueItem::STATUS_COMPLETED,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        MaterialIssueItem::STATUS_DRAFT => 'Draft',
                        MaterialIssueItem::STATUS_PENDING_APPROVAL => 'Pending Approval',
                        MaterialIssueItem::STATUS_APPROVED => 'Approved',
                        MaterialIssueItem::STATUS_COMPLETED => 'Completed',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Waktu Approval')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Approval')
                    ->options([
                        MaterialIssueItem::STATUS_DRAFT => 'Draft',
                        MaterialIssueItem::STATUS_PENDING_APPROVAL => 'Pending Approval',
                        MaterialIssueItem::STATUS_APPROVED => 'Approved',
                        MaterialIssueItem::STATUS_COMPLETED => 'Completed',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('request_approval')
                        ->label('Request Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn(MaterialIssueItem $record) => $record->isDraft())
                        ->requiresConfirmation()
                        ->modalHeading('Request Approval Item')
                        ->modalDescription('Apakah Anda yakin ingin mengirim request approval untuk item ini?')
                        ->action(function (MaterialIssueItem $record) {
                            $record->update(['status' => MaterialIssueItem::STATUS_PENDING_APPROVAL]);

                            Notification::make()
                                ->title('Request Approval Terkirim')
                                ->body("Item {$record->product->name} telah dikirim untuk approval.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(MaterialIssueItem $record) => $record->isPendingApproval())
                        ->requiresConfirmation()
                        ->modalHeading('Approve Item')
                        ->modalDescription('Setelah di-approve, item dapat diproses menjadi Completed.')
                        ->action(function (MaterialIssueItem $record) {
                            $record->update([
                                'status' => MaterialIssueItem::STATUS_APPROVED,
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Item Di-approve')
                                ->body("Item {$record->product->name} telah di-approve.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(MaterialIssueItem $record) => $record->isPendingApproval())
                        ->requiresConfirmation()
                        ->modalHeading('Reject Item')
                        ->modalDescription('Berikan alasan penolakan:')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->action(function (MaterialIssueItem $record, array $data) {
                            $record->update([
                                'status' => MaterialIssueItem::STATUS_DRAFT,
                                'approved_by' => null,
                                'approved_at' => null,
                                'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                          "DITOLAK: {$data['rejection_reason']} - " . now()->format('Y-m-d H:i:s'),
                            ]);

                            Notification::make()
                                ->title('Item Ditolak')
                                ->body("Item {$record->product->name} telah ditolak.")
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('complete')
                        ->label('Selesai')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn(MaterialIssueItem $record) => $record->isApproved())
                        ->requiresConfirmation()
                        ->modalHeading('Selesaikan Item')
                        ->modalDescription('Apakah Anda yakin ingin menyelesaikan item ini? Stock akan dikurangi.')
                        ->action(function (MaterialIssueItem $record) {
                            $record->update(['status' => MaterialIssueItem::STATUS_COMPLETED]);

                            Notification::make()
                                ->title('Item Diselesaikan')
                                ->body("Item {$record->product->name} telah diselesaikan.")
                                ->success()
                                ->send();
                        }),
                ]),
            ],position:ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_request_approval')
                        ->label('Request Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Request Approval Items')
                        ->modalDescription('Apakah Anda yakin ingin mengirim request approval untuk semua item yang dipilih?')
                        ->action(function (array $records) {
                            foreach ($records as $record) {
                                if ($record->isDraft()) {
                                    $record->update(['status' => MaterialIssueItem::STATUS_PENDING_APPROVAL]);
                                }
                            }

                            Notification::make()
                                ->title('Bulk Request Approval Terkirim')
                                ->body(count($records) . ' item telah dikirim untuk approval.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Items')
                        ->modalDescription('Apakah Anda yakin ingin menyetujui semua item yang dipilih?')
                        ->action(function (array $records) {
                            foreach ($records as $record) {
                                if ($record->isPendingApproval()) {
                                    $record->update([
                                        'status' => MaterialIssueItem::STATUS_APPROVED,
                                        'approved_by' => Auth::id(),
                                        'approved_at' => now(),
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Bulk Approve Berhasil')
                                ->body(count($records) . ' item telah di-approve.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject Items')
                        ->modalDescription('Berikan alasan penolakan:')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->action(function (array $records, array $data) {
                            foreach ($records as $record) {
                                if ($record->isPendingApproval()) {
                                    $record->update([
                                        'status' => MaterialIssueItem::STATUS_DRAFT,
                                        'approved_by' => null,
                                        'approved_at' => null,
                                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                                  "DITOLAK: {$data['rejection_reason']} - " . now()->format('Y-m-d H:i:s'),
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Bulk Reject Berhasil')
                                ->body(count($records) . ' item telah ditolak.')
                                ->warning()
                                ->send();
                        }),
                ]),
            ]);
    }
}
