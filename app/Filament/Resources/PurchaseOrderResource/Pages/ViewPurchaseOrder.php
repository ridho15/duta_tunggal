<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('response purchase order') && ($record->status == 'request_approval' || $record->status == 'request_close');
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form(function ($record) {
                    if ($record->status == 'request_close') {
                        return [
                            Textarea::make('close_reason')
                                ->label('Close Reason')
                                ->required()
                                ->string()
                        ];
                    }
                    // request_approval case
                    if ($record->status == 'request_approval' && $record->is_asset) {
                        return [
                            Fieldset::make('Asset Parameters')->schema([
                                DatePicker::make('usage_date')->label('Tanggal Pakai')->required()->default($record->order_date),
                                TextInput::make('useful_life_years')->label('Umur Manfaat (Tahun)')->numeric()->required()->default(5),
                                TextInput::make('salvage_value')->label('Nilai Sisa')->numeric()->default(0),
                                Select::make('asset_coa_id')->label('COA Aset')->options(function () {
                                    return ChartOfAccount::where('type', 'Asset')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                })->searchable()->required(),
                                Select::make('accumulated_depreciation_coa_id')->label('COA Akumulasi Penyusutan')->options(function () {
                                    return ChartOfAccount::where('type', 'Contra Asset')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                })->searchable()->required(),
                                Select::make('depreciation_expense_coa_id')->label('COA Beban Penyusutan')->options(function () {
                                    return ChartOfAccount::where('type', 'Expense')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                })->searchable()->required(),
                            ])->columns(1),
                        ];
                    }

                    return null;
                })
                ->action(function (array $data, $record) {
                    if ($record->status == 'request_approval') {
                        // Check if user has signature set
                        $user = Auth::user();
                        if (!$user->signature) {
                            throw new \Exception('Tanda tangan belum diatur di profil user. Silakan atur tanda tangan terlebih dahulu.');
                        }

                        DB::transaction(function () use ($record, $data, $user) {
                            // Use user's pre-set signature
                            $signaturePath = $user->signature;

                            $status = $record->is_asset ? 'completed' : 'approved';
                            $record->update([
                                'status' => $status,
                                'date_approved' => Carbon::now(),
                                'approved_by' => $user->id,
                                'approval_signature' => $signaturePath,
                                'approval_signed_at' => Carbon::now(),
                            ]);

                            if ($record->is_asset) {
                                $record->update([
                                    'completed_at' => Carbon::now(),
                                    'completed_by' => $user->id,
                                ]);
                                foreach ($record->purchaseOrderItem as $item) {
                                    $total = \App\Http\Controllers\HelperController::hitungSubtotal((int)$item->quantity, (int)$item->unit_price, (int)$item->discount, (int)$item->tax, $item->tipe_pajak);

                                    $asset = Asset::create([
                                        'name' => $item->product->name,
                                        'product_id' => $item->product_id,
                                        'purchase_order_id' => $record->id,
                                        'purchase_order_item_id' => $item->id,
                                        'purchase_date' => $record->order_date,
                                        'usage_date' => $data['usage_date'] ?? $record->order_date,
                                        'purchase_cost' => $total,
                                        'salvage_value' => $data['salvage_value'] ?? 0,
                                        'useful_life_years' => (int)($data['useful_life_years'] ?? 5),
                                        'asset_coa_id' => $data['asset_coa_id'],
                                        'accumulated_depreciation_coa_id' => $data['accumulated_depreciation_coa_id'],
                                        'depreciation_expense_coa_id' => $data['depreciation_expense_coa_id'],
                                        'status' => 'active',
                                        'notes' => 'Generated from PO ' . $record->po_number,
                                    ]);

                                    $asset->calculateDepreciation();
                                }
                            }
                        });
                    } elseif ($record->status == 'request_close') {
                        $record->update([
                            'close_reason' => $data['close_reason'],
                            'status' => 'closed',
                            'closed_at' => Carbon::now(),
                            'closed_by' => Auth::user()->id,
                        ]);
                    }
                }),
            Action::make('tolak')
                ->label('Tolak')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'draft'
                    ]);
                }),
            Action::make('request_approval')
                ->label('Request Approval')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_approval', 'closed', 'completed', 'approved']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'request_approval'
                    ]);
                }),
            Action::make('request_close')
                ->label('Request Close')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_close', 'closed', 'completed', 'approved']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'request_close'
                    ]);
                }),
            Action::make('cetak_pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-document-check')
                ->color('danger')
                ->visible(function ($record) {
                    return $record->status != 'draft' && $record->status != 'closed';
                })
                ->action(function ($record) {
                    $record->load(['assets.assetCoa', 'assets.accumulatedDepreciationCoa', 'assets.depreciationExpenseCoa']);
                    $pdf = Pdf::loadView('pdf.purchase-order', [
                        'purchaseOrder' => $record
                    ])->setPaper('A4', 'potrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Pembelian_' . $record->po_number . '.pdf');
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $total = 0;

        if ($record) {
            foreach ($record->purchaseOrderItem as $item) {
                $total += HelperController::hitungSubtotal((int)$item->quantity, (int)$item->unit_price, (int)$item->discount, (int)$item->tax, $item->tipe_pajak);
            }

            foreach ($record->purchaseOrderBiaya as $biaya) {
                $biayaAmount = $biaya->total * ($biaya->currency->to_rupiah ?? 1);
                $total += $biayaAmount;
            }
        }

        $data['total_amount'] = $total;
        return $data;
    }
}
