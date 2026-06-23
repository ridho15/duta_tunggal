<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ViewSaleOrder extends ViewRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function getActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->color('success')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('update sales order') &&
                            in_array($record->status, ['draft', 'request_approve', 'approved']);
                    }),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('delete sales order') &&
                            in_array($record->status, ['draft', 'request_approve']);
                    }),
                Action::make('request_approve')
                    ->label('Request Approve')
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-arrow-uturn-up')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('request sales order')
                            && $record->status == 'draft';
                    })
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestApprove($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Sales Order telah diajukan untuk persetujuan. Proses selanjutnya: Persetujuan oleh Manajer Sales.");
                        } catch (ValidationException $e) {
                            $messages = collect($e->errors())->flatten()->implode(' ');

                            HelperController::sendNotification(isSuccess: false, title: "Gagal Mengajukan Persetujuan", message: $messages ?: 'Validasi request approve gagal.');
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder request_approve failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Mengajukan Persetujuan", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('request_close')
                    ->label('Request Close')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('request sales order') &&
                            in_array($record->status, ['approved', 'confirmed', 'completed']);
                    })
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestClose($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Permintaan penutupan Sales Order telah diajukan. Proses selanjutnya: Konfirmasi penutupan oleh Manajer Sales.");
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder request_close failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Request Close", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('response sales order')
                            && ($record->status == 'request_approve');
                    })
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->approve($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Sales Order telah disetujui. Proses selanjutnya: Pembuatan Delivery Order oleh Tim Gudang/Logistik.");
                        } catch (ValidationException $e) {
                            $messages = collect($e->errors())->flatten()->implode(' ');

                            HelperController::sendNotification(isSuccess: false, title: "Gagal Menyetujui Sales Order", message: $messages ?: 'Validasi approval gagal.');
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder approve failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Menyetujui SO", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('closed')
                    ->label('Close')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->icon('heroicon-o-x-circle')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_close');
                    })
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->close($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Sales Order telah ditutup. Proses selanjutnya: Tim Finance perlu memastikan semua Invoice terkait telah diselesaikan dan tidak ada pembayaran yang tertunggak.");
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder closed failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Menutup SO", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                    })
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->reject($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Sales Order telah ditolak. Proses selanjutnya: Tim Sales perlu merevisi data pesanan sesuai feedback dan mengajukan kembali untuk disetujui.");
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder reject failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Menolak SO", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('pdf_sale_order')
                    ->label('Preview / Download PDF')
                    ->color('info')
                    ->visible(fn ($record) => in_array($record->status, ['approved', 'completed', 'confirmed', 'received']))
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn ($record) => route('pdf-stream', ['type' => 'sale-order', 'id' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('completed')
                    ->label('Complete')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('update sales order') &&
                            in_array($record->status, ['approved', 'confirmed']) &&
                            $record->deliveryOrder()->where('status', 'completed')->exists();
                    })
                    ->color('success')
                    ->action(function ($record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->completed($record);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Sales Order telah selesai. Proses selanjutnya: Penerbitan Invoice oleh Tim Finance.");
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder completed failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Menyelesaikan SO", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('btn_titip_saldo')
                    ->label('Saldo Titip Customer')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('update deposit') &&
                            in_array($record->status, ['approved', 'confirmed', 'completed']);
                    })
                    ->form(function () {
                        $record = $this->getRecord();
                        if ($record->customer->deposit->id == null) {
                            return [
                                TextInput::make('titip_saldo')
                                    ->indonesianMoney()
                                    ->required()
                                    ->default(0),
                                Select::make('coa_id')
                                    ->label('COA')
                                    ->preload()
                                    ->searchable()
                                    ->options(ChartOfAccount::orderBy('code')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    }))
                                    ->required(),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->nullable()
                            ];
                        } else {
                            return [
                                TextInput::make('titip_saldo')
                                    ->indonesianMoney()
                                    ->required()
                                    ->default(0),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->nullable()
                            ];
                        }
                    })
                    ->modalSubmitActionLabel("Simpan")
                    ->action(function (array $data, $record) {
                        try {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->titipSaldo($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Saldo Titip Customer berhasil disimpan. Proses selanjutnya: Tim Finance perlu memverifikasi saldo titip dan memastikan jurnal keuangan telah dicatat dengan benar.");
                        } catch (\Exception $e) {
                            Log::error('ViewSaleOrder titip_saldo failed', [
                                'sale_order_id' => $record->id,
                                'so_number' => $record->so_number,
                                'status' => $record->status,
                                'user_id' => Auth::id(),
                                'payload' => $data,
                                'error' => $e->getMessage(),
                            ]);
                            HelperController::sendNotification(isSuccess: false, title: "Gagal Simpan Saldo Titip", message: "Terjadi kesalahan: " . $e->getMessage());
                        }
                    }),
                Action::make('create_purchase_order')
                    ->label('Create Purchase Order')
                    ->color('success')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('create purchase order');
                    })
                    ->form([
                        Fieldset::make("Form")
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Supplier harus dipilih',
                                    ])
                                    ->afterStateUpdated(function ($state, $set) {
                                        $supplier = Supplier::find($state);
                                        if ($supplier) {
                                            $set('tempo_hutang', $supplier->tempo_hutang);
                                        }
                                    })
                                    ->options(function () {
                                        return Supplier::select(['id', 'perusahaan', 'code', DB::raw("CONCAT('(', code, ') ', perusahaan) as label")])->get()->pluck('label', 'id');
                                    })->required(),
                                TextInput::make('po_number')
                                    ->label('PO Number')
                                    ->string()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'PO Number tidak boleh kosong',
                                        'string' => 'PO Number tidak valid !',
                                        'unique' => 'PO Number sudah digunakan'
                                    ])
                                    ->suffixAction(ActionsAction::make('generatePoNumber')
                                        ->icon('heroicon-m-arrow-path') // ikon reload
                                        ->tooltip('Generate PO Number')
                                        ->action(function ($set, $get, $state) {
                                            $purchaseOrderService = app(PurchaseOrderService::class);
                                            $set('po_number', $purchaseOrderService->generatePoNumber());
                                        }))
                                    ->maxLength(255)
                                    ->rule(function ($state) {
                                        $purchaseOrder = PurchaseOrder::where('po_number', $state)->first();
                                        if ($purchaseOrder) {
                                            HelperController::sendNotification(isSuccess: false, title: 'Information', message: "PO number sudah digunakan");
                                            throw ValidationException::withMessages([
                                                "items" => 'PO Number sudah digunakan'
                                            ]);
                                        }
                                    })
                                    ->required(),
                                DatePicker::make('order_date')
                                    ->label('Tanggal Pembelian')
                                    ->validationMessages([
                                        'required' => 'Tanggal Pembelian tidak boleh kosong'
                                    ])
                                    ->required(),
                                DatePicker::make('delivery_date')
                                    ->label('Tanggal Pengiriman'),
                                DatePicker::make('expected_date')
                                    ->label('Tanggal Diharapkan'),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->preload()
                                    ->searchable(['name', 'kode'])
                                    ->required()
                                    ->options(function () {
                                        return Warehouse::select(['id', 'kode', 'name', DB::raw("CONCAT('(', kode, ') ', name) as label")])->get()->pluck('label', 'id');
                                    })
                                    ->validationMessages([
                                        'required' => 'Gudang belum dipilih',
                                    ]),
                                TextInput::make('tempo_hutang')
                                    ->label('Tempo Hutang (Hari)')
                                    ->numeric()
                                    ->reactive()
                                    ->default(0)
                                    ->validationMessages([
                                        'required' => 'Tempo Hutan tidak boleh kosong',
                                    ])
                                    ->required()
                                    ->suffix('Hari'),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->nullable()
                            ])
                    ])
                    ->action(function (array $data, $record) {
                        $salesOrderService = app(SalesOrderService::class);
                        $salesOrderService->createPurchaseOrder($record, $data);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Purchase Order berhasil dibuat dari Sales Order. Proses selanjutnya: Persetujuan Purchase Order oleh Manajer Purchasing.");
                    }),
                Action::make('sync_total_amount')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->label('Sync Total Amount')
                    ->color('primary')
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('update sales order');
                    })
                    ->action(function ($record) {
                        $salesOrderService = app(SalesOrderService::class);
                        $salesOrderService->updateTotalAmount($record);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                    })
            ])->label('Action')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('primary')
        ];
    }
}
