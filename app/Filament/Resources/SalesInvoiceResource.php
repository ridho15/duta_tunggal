<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Helpers\MoneyHelper;
use App\Http\Controllers\HelperController;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\DeliveryOrder;
use App\Models\Customer;
use App\Models\Cabang;
use App\Services\InvoiceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Penjualan';
    protected static ?string $modelLabel = 'Invoice Penjualan';
    protected static ?string $pluralModelLabel = 'Invoice Penjualan';
    protected static ?string $navigationGroup = 'Keuangan Penjualan';
    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected static function readonlyInputAttributes(): array
    {
        return [
            'class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400',
            'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;',
        ];
    }

    protected static function resolveCoaIdByCodes(array $codes): ?int
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if (empty($codes)) {
            return null;
        }

        $accounts = \App\Models\ChartOfAccount::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        foreach ($codes as $code) {
            if ($accounts->has($code)) {
                return $accounts->get($code)?->id;
            }
        }

        return null;
    }

    /** Isian modal aksi "Isi No. Faktur Pajak". */
    public static function taxNumberFormSchema(): array
    {
        return [
            TextInput::make('tax_invoice_number')
                ->label('No. Faktur Pajak')
                ->placeholder('010.000-26.12345678')
                ->maxLength(50)
                ->helperText('16 digit. Kosongkan untuk menghapus nomor. Perubahan tercatat di riwayat aktivitas.')
                ->rules(fn (?\Illuminate\Database\Eloquent\Model $record) => [new \App\Rules\TaxInvoiceNumber($record?->getKey())]),
        ];
    }

    /** Simpan lewat layanan (validasi ulang di server + otorisasi); dipakai aksi tabel dan aksi halaman View. */
    public static function saveTaxNumber(Invoice $record, array $data): void
    {
        \Illuminate\Support\Facades\Gate::authorize('updateTaxNumber', $record);

        app(\App\Services\SalesInvoiceTaxNumber::class)->set($record, $data['tax_invoice_number'] ?? null);

        \Filament\Notifications\Notification::make()
            ->title(filled($data['tax_invoice_number'] ?? null) ? 'No. Faktur Pajak disimpan' : 'No. Faktur Pajak dihapus')
            ->body('Jurnal dan piutang tidak berubah.')
            ->success()
            ->send();
    }

    public static function taxNumberTableAction(): \Filament\Tables\Actions\Action
    {
        return \Filament\Tables\Actions\Action::make('set_tax_invoice_number')
            ->label(fn (Invoice $record) => filled($record->tax_invoice_number) ? 'Ubah No. Faktur Pajak' : 'Isi No. Faktur Pajak')
            ->icon('heroicon-o-document-text')
            ->color('warning')
            ->visible(fn (Invoice $record) => Auth::user()?->can('updateTaxNumber', $record) ?? false)
            ->modalHeading('No. Faktur Pajak')
            ->modalSubmitActionLabel('Simpan')
            ->form(static::taxNumberFormSchema())
            ->fillForm(fn (Invoice $record) => ['tax_invoice_number' => $record->tax_invoice_number])
            ->action(fn (Invoice $record, array $data) => static::saveTaxNumber($record, $data));
    }

    public static function taxNumberPageAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('set_tax_invoice_number')
            ->label(fn (Invoice $record) => filled($record->tax_invoice_number) ? 'Ubah No. Faktur Pajak' : 'Isi No. Faktur Pajak')
            ->icon('heroicon-o-document-text')
            ->color('warning')
            ->visible(fn (Invoice $record) => Auth::user()?->can('updateTaxNumber', $record) ?? false)
            ->modalHeading('No. Faktur Pajak')
            ->modalSubmitActionLabel('Simpan')
            ->form(static::taxNumberFormSchema())
            ->fillForm(fn (Invoice $record) => ['tax_invoice_number' => $record->tax_invoice_number])
            ->action(fn (Invoice $record, array $data) => static::saveTaxNumber($record, $data));
    }

    public static function normalizeInvoiceTaxTypeValue(?string $taxType): string
    {
        return match (\App\Services\TaxService::normalizeType($taxType)) {
            'Inklusif' => 'Inklusif',
            'Eksklusif' => 'Eksklusif',
            default => 'None',
        };
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Form Invoice')
                    ->schema([
                        // Header Section - Sumber Invoice
                        Section::make('Sumber Invoice')
                            ->description('Silahkan Pilih Customer')
                            ->columns(2)
                            ->schema([
                                Select::make('selected_customer')
                                    ->label('Customer')
                                    ->remoteSearch('customers')   // T7.2: pencarian sisi-server (tanpa memuat seluruh customer)
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Customer harus dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_sale_order', null);
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        $set('other_fees', []);
                                        $set('dpp', 0);
                                    }),

                                Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                                    ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Cabang harus dipilih'
                                    ]),

                                Select::make('selected_sale_order')
                                    ->label('SO (Sales Order)')
                                    ->options(function ($get, $livewire) {
                                        $customerId = $get('selected_customer');
                                        if (!$customerId) return [];

                                        $currentInvoiceId = $get('id') ?? ($livewire instanceof \Filament\Resources\Pages\EditRecord ? $livewire->record?->id : null);
                                        $currentInvoice = $currentInvoiceId ? \App\Models\Invoice::find($currentInvoiceId) : null;
                                        $currentInvoiceSoId = ($currentInvoice && $currentInvoice->from_model_type === 'App\Models\SaleOrder')
                                            ? (int) $currentInvoice->from_model_id
                                            : null;

                                        // Ambil semua ID DO yang sudah ditagih pada invoice aktif
                                        $invoicedDOIds = \App\Models\Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                            ->whereNotNull('delivery_orders')
                                            ->whereNotIn('status', ['canceled', 'cancelled'])
                                            ->when($currentInvoiceId, fn ($q) => $q->where('id', '!=', $currentInvoiceId))
                                            ->get()
                                            ->pluck('delivery_orders')
                                            ->flatten()
                                            ->filter()
                                            ->unique()
                                            ->toArray();

                                        return SaleOrder::with(['customer:id,name', 'deliverySalesOrder.deliveryOrder'])
                                            ->where('customer_id', $customerId)
                                            ->where(function ($q) {
                                                $q->whereIn('status', ['completed', 'partially_delivered', 'confirmed', 'approved'])
                                                  ->orWhereHas('deliverySalesOrder.deliveryOrder', function ($doQuery) {
                                                      $doQuery->whereIn('status', \App\Models\DeliveryOrder::DELIVERED_STATUSES);
                                                  });
                                            })
                                            ->get()
                                            ->filter(function ($so) use ($invoicedDOIds, $currentInvoiceId, $currentInvoiceSoId) {
                                                // Jika sedang edit invoice dan SO ini adalah asal invoice saat ini, selalu izinkan
                                                if ($currentInvoiceId && (int) $so->id === (int) $currentInvoiceSoId) {
                                                    return true;
                                                }

                                                if ($so->tipe_pengiriman === 'Ambil Sendiri') {
                                                    // Ambil Sendiri: hanya tampil jika belum pernah terbit invoice aktif
                                                    $alreadyInvoiced = \App\Models\Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                                        ->where('from_model_id', $so->id)
                                                        ->whereNotIn('status', ['canceled', 'cancelled'])
                                                        ->when($currentInvoiceId, fn ($q) => $q->where('id', '!=', $currentInvoiceId))
                                                        ->exists();
                                                    return ! $alreadyInvoiced;
                                                }

                                                // Pengiriman lewat DO: hanya tampil jika masih ada DO terkirim yang BELUM ditagih
                                                $deliveredDos = $so->deliverySalesOrder
                                                    ? $so->deliverySalesOrder
                                                        ->map(fn ($dso) => $dso->deliveryOrder)
                                                        ->filter(fn ($do) => $do && in_array($do->status, \App\Models\DeliveryOrder::DELIVERED_STATUSES, true))
                                                    : collect();

                                                if ($deliveredDos->isEmpty()) {
                                                    return false;
                                                }

                                                return $deliveredDos->contains(fn ($do) => ! in_array($do->id, $invoicedDOIds));
                                            })
                                            ->mapWithKeys(function ($so) {
                                                $label = \App\Support\DocumentLabels::saleOrder($so);
                                                if ($so->tipe_pengiriman === 'Ambil Sendiri') {
                                                    $label .= ' [Ambil Sendiri]';
                                                } elseif ($so->status === 'partially_delivered') {
                                                    $label .= ' [Pengiriman Sebagian]';
                                                } elseif ($so->status === 'completed') {
                                                    $label .= ' [Selesai]';
                                                } else {
                                                    $label .= ' [Ada DO Terkirim]';
                                                }
                                                return [$so->id => $label];
                                            });
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->helperText(function ($get) {
                                        $customerId = $get('selected_customer');
                                        if (!$customerId) {
                                            return 'Pilih customer terlebih dahulu untuk memuat daftar Sales Order yang siap ditagih.';
                                        }

                                        return 'Hanya menampilkan SO yang memiliki pengiriman barang belum ditagih (atau SO Ambil Sendiri yang belum terbit invoice).';
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        $set('other_fees', []);
                                        $set('dpp', 0);
                                    }),
                            ]),

                        // Invoice Info Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Nomor Invoice')
                                    ->required()
                                    ->unique(table: 'invoices', column: 'invoice_number', ignoreRecord: true)
                                    ->validationMessages([
                                        'required' => 'Nomor invoice tidak boleh kosong',
                                        'max' => 'Nomor invoice terlalu panjang',
                                        'unique' => 'Nomor invoice sudah digunakan'
                                    ])
                                    ->suffixAction(
                                        Action::make('generate')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Generate Invoice Number')
                                            ->action(function ($set, $get) {
                                                $invoiceService = app(InvoiceService::class);
                                                $set('invoice_number', $invoiceService->generateSalesInvoiceNumber());
                                            })
                                    )
                                    ->maxLength(255),

                                DatePicker::make('invoice_date')
                                    ->label('Tanggal Invoice')
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Tanggal invoice harus diisi'
                                    ])
                                    ->default(now()),

                                TextInput::make('tax_invoice_number')
                                    ->label('No. Faktur Pajak')
                                    ->placeholder('010.000-26.12345678')
                                    ->maxLength(50)
                                    ->rules(fn (?\Illuminate\Database\Eloquent\Model $record) => [new \App\Rules\TaxInvoiceNumber($record?->getKey())])
                                    ->dehydrateStateUsing(fn ($state) => \App\Rules\TaxInvoiceNumber::normalize($state))
                                    ->helperText('16 digit untuk rekonsiliasi PPN Keluaran. Boleh dikosongkan dan diisi setelah invoice terbit (lewat aksi "Isi No. Faktur Pajak").'),

                                DatePicker::make('due_date')
                                    ->label('Tanggal Jatuh Tempo')
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Tanggal jatuh tempo harus diisi'
                                    ])
                                    ->helperText(function ($get) {
                                        $due = $get('due_date');
                                        if (!$due) return null;
                                        $invoice = $get('invoice_date') ?? now();
                                        $days = (int) \Illuminate\Support\Carbon::parse($invoice)
                                            ->diffInDays(\Illuminate\Support\Carbon::parse($due), false);
                                        return $days >= 0 ? "Tenor: {$days} hari" : "Jatuh tempo terlewat " . abs($days) . " hari";
                                    }),
                            ]),

                        // Delivery Orders Selection / Direct SO Items
                        Section::make('Silahkan Pilih DO atau Items')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_delivery_orders')
                                    ->label('')
                                    ->options(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return [];

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        if (!$saleOrder) return [];

                                        // Jika SO tipe "Ambil Sendiri", return empty array (akan handle di bawah)
                                        if ($saleOrder->tipe_pengiriman === 'Ambil Sendiri') {
                                            return [];
                                        }

                                        // Get all DOs from this SO — tampilkan semua DO yang sudah terkirim (DELIVERED_STATUSES)
                                        // FIX #1b: DO berstatus sent/received/completed semuanya sudah kirim barang, bisa ditagih.
                                        $deliveryOrders = $saleOrder->deliverySalesOrder()
                                            ->with(['deliveryOrder' => function ($query) {
                                                $query->with('deliveryOrderItem.saleOrderItem');
                                            }])
                                            ->get()
                                            ->pluck('deliveryOrder')
                                            ->filter(function ($do) {
                                                return $do && in_array($do->status, \App\Models\DeliveryOrder::DELIVERED_STATUSES);
                                            });
                                        // Get current invoice record if editing (for allowing already selected DOs)
                                        $currentInvoiceId = $get('id') ?? null;

                                        // Check which DOs are already invoiced (exclude canceled invoices and current invoice if editing)
                                        $invoicedDOIds = Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                            ->whereNotNull('delivery_orders')
                                            ->whereNotIn('status', ['canceled', 'cancelled'])
                                            ->when($currentInvoiceId, function ($query) use ($currentInvoiceId) {
                                                return $query->where('id', '!=', $currentInvoiceId);
                                            })
                                            ->get()
                                            ->pluck('delivery_orders')
                                            ->flatten()
                                            ->unique()
                                            ->toArray();

                                        // Nilai DO dari satu sumber (DeliveryOrderValuation) — sama dengan invoice yang akan terbit.
                                        $selectable = $deliveryOrders->reject(fn ($do) => in_array($do->id, $invoicedDOIds))->values();
                                        $values = app(\App\Services\DeliveryOrderValuation::class)->forDeliveryOrders($selectable);

                                        $options = [];
                                        foreach ($selectable as $do) {
                                            $options[$do->id] = \App\Support\DocumentLabels::deliveryOrder($do, $values[$do->id]['total'] ?? 0.0, ! empty($values[$do->id]['unlinked_items']));
                                        }

                                        return $options;
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->visible(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return false;

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        return $saleOrder && $saleOrder->tipe_pengiriman !== 'Ambil Sendiri';
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            $set('invoiceItem', []);
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            $set('other_fees', []);
                                            $set('dpp', 0);
                                            $set('delivery_order_items', []);
                                            return;
                                        }

                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');

                                        if (!$saleOrderId || !$customerId) return;

                                        $saleOrder = SaleOrder::with('customer')->find($saleOrderId);
                                        $deliveryOrders = DeliveryOrder::with('deliveryOrderItem.saleOrderItem', 'deliveryOrderItem.product')
                                            ->whereIn('id', $state)
                                            ->get();

                                        // Set customer info
                                        $set('customer_name', $saleOrder->customer->name);
                                        $set('customer_phone', $saleOrder->customer->phone ?? '');
                                        $set('from_model_type', 'App\Models\SaleOrder');
                                        $set('from_model_id', $saleOrderId);

                                        // Calculate items from delivery orders
                                        $items = [];
                                        $subtotal = 0;

                                        foreach ($deliveryOrders as $do) {
                                            foreach ($do->deliveryOrderItem as $item) {
                                                if ($item->saleOrderItem) {
                                                    // FIX #3: discount and tax are percentages (0-100), not IDR amounts.
                                                    // Correct net-DPP price = unit_price * (1 - discount/100)
                                                    $discountPct = max(0.0, min(100.0, (float) $item->saleOrderItem->discount));
                                                    $price = (float) $item->saleOrderItem->unit_price * (1 - $discountPct / 100);
                                                    $total = (float) $price * (float) $item->quantity;

                                                    $items[] = [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->quantity,
                                                        'price' => $price,
                                                        'total' => $total
                                                    ];

                                                    $subtotal += $total;
                                                }
                                            }
                                        }

                                        $set('invoiceItem', $items);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        $set('delivery_orders', $state);
                                        $set('other_fees', []);

                                        // For create and edit, set delivery_order_items
                                        $deliveryOrderItems = [];
                                        $currentInvoiceId = $get('id');

                                        foreach ($deliveryOrders as $do) {
                                            foreach ($do->deliveryOrderItem as $item) {
                                                $product = $item->product ?: $item->saleOrderItem?->product;
                                                $saleOrderItem = $item->saleOrderItem;
                                                if ($product) {
                                                    // Net DPP price = unit_price * (1 - discount/100)
                                                    $rawUnitPrice = $saleOrderItem && (float) $saleOrderItem->unit_price > 0
                                                        ? (float) $saleOrderItem->unit_price
                                                        : (float) ($product->sell_price ?? 0);
                                                    $discountPct = $saleOrderItem ? max(0.0, min(100.0, (float) $saleOrderItem->discount)) : 0.0;
                                                    $originalPrice = round($rawUnitPrice * (1 - $discountPct / 100), 2);

                                                    // For edit, try to find existing invoice item data
                                                    $invoiceQuantity = (float) $item->quantity;
                                                    $invoicePrice = $originalPrice;
                                                    $invoiceCoaId = $product->sales_coa_id;

                                                    if ($currentInvoiceId) {
                                                        // Find matching invoice item for this product
                                                        $existingInvoiceItems = $get('invoiceItem') ?? [];
                                                        $matchingItem = collect($existingInvoiceItems)->first(function ($invItem) use ($item) {
                                                            return $invItem['product_id'] == $item->product_id;
                                                        });

                                                        if ($matchingItem) {
                                                            $invoiceQuantity = $matchingItem['quantity'] ?? $item->quantity;
                                                            $invoicePrice = $matchingItem['price'] ?? $originalPrice;
                                                            $invoiceCoaId = $matchingItem['coa_id'] ?? $product->sales_coa_id;
                                                        }
                                                    }

                                                    $deliveryOrderItems[] = [
                                                        'do_number' => $do->do_number,
                                                        'product_id' => $item->product_id ?: $product->id,
                                                        'product_name' => $product->name . ($product->sku ? ' (' . $product->sku . ')' : ''),
                                                        'original_quantity' => $item->quantity,
                                                        'invoice_quantity' => $invoiceQuantity,
                                                        'original_price' => $originalPrice,
                                                        'unit_price' => $invoicePrice,
                                                        'total_price' => (float) $invoiceQuantity * (float) $invoicePrice,
                                                        'coa_id' => $invoiceCoaId,
                                                    ];
                                                }
                                            }
                                        }
                                        $set('delivery_order_items', $deliveryOrderItems);

                                        // L1: Auto-fill tipe_pajak from SO items
                                        $soForTax = SaleOrder::with('saleOrderItem')->find($saleOrderId);
                                        if ($soForTax && $soForTax->saleOrderItem->isNotEmpty()) {
                                            $tipePajak = static::normalizeInvoiceTaxTypeValue($soForTax->saleOrderItem->first()->tipe_pajak ?? 'None');
                                            $set('tipe_pajak', $tipePajak);
                                            if ($tipePajak === 'None') {
                                                $set('ppn_rate', 0);
                                            }
                                        }

                                        // Calculate tax and total
                                        $tax = 0;
                                        $otherFee = 0; // Initialize as 0
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),

                                // Checkbox untuk konfirmasi pemilihan SO Ambil Sendiri
                                Forms\Components\Checkbox::make('confirm_self_pickup_invoice')
                                    ->label('Buat invoice dari Sales Order "Ambil Sendiri" ini')
                                    ->visible(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return false;

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        return $saleOrder && $saleOrder->tipe_pengiriman === 'Ambil Sendiri';
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state) {
                                            $set('invoiceItem', []);
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            $set('other_fees', []);
                                            $set('dpp', 0);
                                            $set('delivery_order_items', []);
                                            return;
                                        }

                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');

                                        if (!$saleOrderId || !$customerId) return;

                                        $saleOrder = SaleOrder::with('customer', 'saleOrderItem.product')->find($saleOrderId);

                                        // Set customer info
                                        $set('customer_name', $saleOrder->customer->name);
                                        $set('customer_phone', $saleOrder->customer->phone ?? '');
                                        $set('from_model_type', 'App\Models\SaleOrder');
                                        $set('from_model_id', $saleOrderId);

                                        // Calculate items directly from SO items
                                        $items = [];
                                        $subtotal = 0;

                                        foreach ($saleOrder->saleOrderItem as $item) {
                                            // FIX #3: discount is a percentage, not an IDR amount
                                            $discountPct = max(0.0, min(100.0, (float) $item->discount));
                                            $price = (float) $item->unit_price * (1 - $discountPct / 100);
                                            $total = (float) $price * (float) $item->quantity;

                                            $items[] = [
                                                'product_id' => $item->product_id,
                                                'quantity' => $item->quantity,
                                                'price' => $price,
                                                'total' => $total
                                            ];

                                            $subtotal += $total;
                                        }

                                        $set('invoiceItem', $items);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        $set('delivery_orders', []); // Empty for self-pickup
                                        $set('other_fees', []);

                                        // For create, set delivery_order_items from SO items
                                        if (!$get('id')) {
                                            $deliveryOrderItems = [];
                                            foreach ($saleOrder->saleOrderItem as $item) {
                                                if ($item->product) {
                                                    // FIX #3: discount is a percentage, not an IDR amount
                                                    $discountPct = max(0.0, min(100.0, (float) $item->discount));
                                                    $originalPrice = (float) $item->unit_price * (1 - $discountPct / 100);

                                                    $deliveryOrderItems[] = [
                                                        'do_number' => 'SO-' . $saleOrder->so_number, // Use SO number as reference
                                                        'product_id' => $item->product_id,
                                                        'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                                                        'original_quantity' => $item->quantity,
                                                        'invoice_quantity' => $item->quantity,
                                                        'original_price' => $originalPrice,
                                                        'unit_price' => $originalPrice,
                                                        'total_price' => (float) $originalPrice * (float) $item->quantity,
                                                        'coa_id' => $item->product->sales_coa_id,
                                                    ];
                                                }
                                            }
                                            $set('delivery_order_items', $deliveryOrderItems);
                                        }

                                        // L1: Auto-fill tipe_pajak from SO items
                                        if ($saleOrder && $saleOrder->saleOrderItem->isNotEmpty()) {
                                            $tipePajak = static::normalizeInvoiceTaxTypeValue($saleOrder->saleOrderItem->first()->tipe_pajak ?? 'None');
                                            $set('tipe_pajak', $tipePajak);
                                            if ($tipePajak === 'None') {
                                                $set('ppn_rate', 0);
                                            }
                                        }

                                        // Calculate tax and total
                                        $tax = 0;
                                        $otherFee = 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),
                            ]),

                        // Delivery Order Items Section
                        Section::make('Rincian Item Delivery Order')
                            ->description('Kuantitas untuk invoice dari delivery order yang dipilih (harga satuan terkunci sesuai Sales Order yang disepakati)')
                            ->schema([
                                Repeater::make('delivery_order_items')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('do_number')
                                            ->label('No. DO')
                                            ->disabled()
                                            ->extraInputAttributes(static::readonlyInputAttributes())
                                            ->columnSpan(1),
                                        TextInput::make('product_name')
                                            ->label('Product')
                                            ->disabled()
                                            ->extraInputAttributes(static::readonlyInputAttributes())
                                            ->columnSpan(2),
                                        TextInput::make('original_quantity')
                                            ->label('Qty DO Asli')
                                            ->disabled()
                                            ->numeric()
                                            ->extraInputAttributes(static::readonlyInputAttributes())
                                            ->columnSpan(1),
                                        TextInput::make('invoice_quantity')
                                            ->label('Qty untuk Invoice')
                                            ->numeric()
                                            ->required()
                                            ->default(function ($get) {
                                                return $get('original_quantity') ?? 0;
                                            })
                                            ->minValue(0)
                                            ->maxValue(function ($get) {
                                                return $get('original_quantity') ?? 0;
                                            })
                                            ->validationMessages([
                                                'required' => 'Qty invoice tidak boleh kosong',
                                                'numeric' => 'Qty invoice harus berupa angka',
                                                'min' => 'Qty invoice tidak boleh negatif',
                                                'max' => 'Qty invoice tidak boleh lebih dari qty DO asli'
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $get) {
                                                $quantity = (float) ($get('invoice_quantity') ?? 0);
                                                $price = (float) \App\Helpers\MoneyHelper::safeParse($get('unit_price') ?? 0);
                                                $set('total_price', $quantity * $price);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label('Harga Satuan')
                                            ->indonesianMoney()
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->extraInputAttributes(static::readonlyInputAttributes())
                                            ->helperText('Terkunci sesuai Sales Order yang disepakati')
                                            ->default(function ($get) {
                                                return $get('original_price') ?? 0;
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('total_price')
                                            ->label('Total')
                                            ->indonesianMoney()
                                            ->disabled()
                                            ->extraInputAttributes(static::readonlyInputAttributes())
                                            ->columnSpan(1),
                                        Hidden::make('coa_id')
                                            ->default(function ($get) {
                                                $productId = $get('product_id');
                                                if ($productId) {
                                                    $product = \App\Models\Product::find($productId);
                                                    return $product?->sales_coa_id;
                                                }
                                                return null;
                                            }),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->itemLabel(function ($state) {
                                        return $state['product_name'] ?? 'Item';
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Recalculate invoice items when delivery order items change
                                        $deliveryOrderItems = $state ?? [];

                                        $invoiceItems = [];
                                        $subtotal = 0;

                                        foreach ($deliveryOrderItems as $item) {
                                            $quantity = (float) ($item['invoice_quantity'] ?? 0);
                                            $price = (float) \App\Helpers\MoneyHelper::safeParse($item['unit_price'] ?? 0);
                                            $total = $quantity * $price;

                                            $invoiceItems[] = [
                                                'product_id' => $item['product_id'] ?? null,
                                                'quantity' => $quantity,
                                                'price' => $price,
                                                'total' => $total,
                                                'coa_id' => $item['coa_id'] ?? null,
                                            ];

                                            $subtotal += $total;
                                        }

                                        $set('invoiceItem', $invoiceItems);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);

                                        // Recalculate total
                                        $tax = 0;
                                        $ppnRate = (float) ($get('ppn_rate') ?? 0);
                                        $otherFees = $get('other_fees') ?? [];
                                        $otherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0));
                                        $finalTotal = $subtotal + $otherFeeTotal + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $otherFeeTotal);
                                    }),
                            ]),

                        // Biaya Lain Section
                        Section::make('Biaya Lain - lain')
                            ->schema([
                                Repeater::make('other_fees')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Biaya')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Nama biaya tidak boleh kosong'
                                            ])
                                            ->default('Biaya Lain'),
                                        TextInput::make('amount')
                                            ->label('Jumlah')
                                            ->indonesianMoney()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jumlah tidak boleh kosong',
                                                'numeric' => 'Jumlah harus berupa angka'
                                            ])
                                            ->default(0)
                                            ->live(debounce: 500),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $totalOtherFee = (float) collect($state ?? [])->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0));
                                        $set('other_fee', $totalOtherFee);

                                        $subtotal = (float) \App\Helpers\MoneyHelper::safeParse($get('subtotal') ?? 0);
                                        $tax = 0;
                                        $ppnRate = (float) ($get('ppn_rate') ?? 0);
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    })
                                    ->collapsible(),
                            ]),

                        // Tax and Total Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('dpp')
                                    ->label('DPP (Dasar Pengenaan Pajak)')
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'numeric' => 'DPP harus berupa angka'
                                    ])
                                    ->default(0)
                                    ->readonly()
                                    ->extraInputAttributes(static::readonlyInputAttributes()),

                                \Filament\Forms\Components\Hidden::make('tax')
                                    ->default(0),

                                Select::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->options([
                                        'None'     => 'Tidak Kena Pajak (None)',
                                        'Inklusif' => 'PPN Inklusif (sudah termasuk harga)',
                                        'Eksklusif'  => 'PPN Eksklusif (ditambah ke harga)',
                                    ])
                                    ->default('None')
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) {
                                        $component->state(static::normalizeInvoiceTaxTypeValue($state));
                                    })
                                    ->helperText('Diisi otomatis dari Sales Order. Dapat diubah bila perlu.')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $activePpnRate = \App\Models\TaxSetting::activeRate('PPN');

                                        // If None, set ppn_rate to 0
                                        if ($state === 'None') {
                                            $set('ppn_rate', 0);
                                            $subtotal = (float) \App\Helpers\MoneyHelper::safeParse($get('subtotal') ?? 0);
                                            $otherFees = $get('other_fees') ?? [];
                                            $otherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0));
                                            $set('total', $subtotal + $otherFeeTotal);
                                        } else {
                                            // Reapply active rate when PPN is enabled, but keep the field editable
                                            $set('ppn_rate', $activePpnRate);
                                        }
                                    }),

                                TextInput::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'PPN rate harus berupa angka'
                                    ])
                                    ->suffix('%')
                                    ->default(fn () => \App\Models\TaxSetting::activeRate('PPN'))
                                    ->reactive()
                                    ->visible(fn ($get) => $get('tipe_pajak') !== 'None')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $state = $state ?? 11; // Ensure it's not null, default to 11
                                        $subtotal = (float) \App\Helpers\MoneyHelper::safeParse($get('subtotal') ?? 0);
                                        $otherFees = $get('other_fees') ?? [];
                                        $otherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0));
                                        $tax = 0;
                                        $finalTotal = $subtotal + $otherFeeTotal + ($subtotal * $state / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $otherFeeTotal);
                                    }),
                            ]),

                        // Grand Total
                        Section::make('Grand Total Invoice')
                            ->schema([
                                TextInput::make('total')
                                    ->label('')
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'numeric' => 'Total harus berupa angka'
                                    ])
                                    ->readonly()
                                    ->extraInputAttributes(static::readonlyInputAttributes())
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        // COA fields — hidden from UI, auto-populated from defaults
                        Hidden::make('ar_coa_id')
                            ->default(fn () => static::resolveCoaIdByCodes(app(\App\Services\AccountingSettings::class)->codes('accounts_receivable'))),
                        Hidden::make('revenue_coa_id')
                            ->default(fn () => static::resolveCoaIdByCodes(app(\App\Services\AccountingSettings::class)->codes('sales_revenue'))),
                        Hidden::make('ppn_keluaran_coa_id')
                            ->default(fn () => static::resolveCoaIdByCodes(app(\App\Services\AccountingSettings::class)->codes('sales_output_vat'))),

                        // Hidden fields
                        Hidden::make('id'),
                        Hidden::make('from_model_type')->default('App\Models\SaleOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('customer_name'),
                        Hidden::make('customer_phone'),
                        Hidden::make('subtotal')->default(0),
                        Hidden::make('status')->default('draft'),
                        Hidden::make('delivery_orders'),
                        Hidden::make('dpp')->default(0),
                        Hidden::make('total')->default(0),

                        Repeater::make('invoiceItem')
                            ->label('Item Invoice')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->remoteSearch('products')   // T7.2: label produk terpilih tidak lagi bergantung pada 50 produk pertama
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Produk harus dipilih'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Qty tidak boleh kosong',
                                        'numeric' => 'Qty harus berupa angka'
                                    ]),
                                TextInput::make('price')
                                    ->label('Harga Satuan')
                                    ->indonesianMoney()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Harga tidak boleh kosong',
                                        'numeric' => 'Harga harus berupa angka'
                                    ]),
                                TextInput::make('total')
                                    ->label('Total Baris')
                                    ->indonesianMoney()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Total tidak boleh kosong',
                                        'numeric' => 'Total harus berupa angka'
                                    ]),
                                // Rincian baris (Fase 5B) — hanya tampilan, diisi dari InvoiceItem::breakdown() saat invoice diubah.
                                // Baris disimpan ulang dengan rincian baku (gross, diskon, DPP, PPN, total) oleh SalesInvoiceLineBuilder.
                                TextInput::make('bd_gross')
                                    ->label('Jumlah (Harga × Qty)')
                                    ->disabled()->dehydrated(false)
                                    ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice),
                                TextInput::make('bd_discount')
                                    ->label('Diskon (% = Rp)')
                                    ->disabled()->dehydrated(false)
                                    ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice),
                                TextInput::make('bd_dpp')
                                    ->label('DPP')
                                    ->disabled()->dehydrated(false)
                                    ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice),
                                TextInput::make('bd_ppn')
                                    ->label('PPN (% = Rp)')
                                    ->disabled()->dehydrated(false)
                                    ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsed()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->cloneable(false)
                            ->helperText('Kuantitas dan harga dikunci otomatis sesuai Delivery Order & Sales Order yang telah disepakati.'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('from_model_type', 'App\Models\SaleOrder');
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tax_invoice_number')
                    ->label('No. Faktur Pajak')
                    ->searchable()
                    ->placeholder('–')
                    ->formatStateUsing(fn ($state, Invoice $record) => filled($state) ? $state : (\App\Services\SalesInvoiceTaxNumber::isMissing($record) ? 'Belum diisi' : '–'))
                    ->color(fn ($state, Invoice $record) => filled($state) ? null : (\App\Services\SalesInvoiceTaxNumber::isMissing($record) ? 'danger' : 'gray'))
                    ->toggleable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_phone')
                    ->label('No. Telepon')
                    ->searchable(),

                TextColumn::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->rupiah()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(\App\Support\StatusLabels::formatter('invoice'))
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'sent',
                        'success' => 'paid',
                        'primary' => 'partially_paid',
                        'danger' => 'overdue',
                        'gray' => 'cancelled',
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Terkirim',
                        'paid' => 'Lunas',
                        'partially_paid' => 'Dibayar Sebagian',
                        'overdue' => 'Terlambat',
                        'cancelled' => 'Dibatalkan',
                    ]),
                SelectFilter::make('tax_invoice_state')
                    ->label('Faktur Pajak')
                    ->options([
                        'ada' => 'Sudah ada',
                        'belum' => 'Belum ada (invoice ber-PPN yang sudah terbit)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'ada' => $query->whereNotNull('tax_invoice_number')->where('tax_invoice_number', '!=', ''),
                            'belum' => $query
                                ->where(fn (Builder $q) => $q->whereNull('tax_invoice_number')->orWhere('tax_invoice_number', ''))
                                ->whereNotIn('status', [Invoice::STATUS_DRAFT, 'canceled', 'cancelled'])
                                ->where(fn (Builder $q) => $q->where('ppn_rate', '>', 0)->orWhere('tax', '>', 0)),
                            default => $query,
                        };
                    }),
                SelectFilter::make('customer_name')
                    ->label('Customer')
                    ->options(function () {
                        return Invoice::whereNotNull('customer_name')
                            ->distinct()
                            ->pluck('customer_name', 'customer_name')
                            ->toArray();
                    })
                    ->searchable(),
                Filter::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->form([
                        DatePicker::make('invoice_date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('invoice_date_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['invoice_date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['invoice_date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                            );
                    }),
                Filter::make('due_date')
                    ->label('Jatuh Tempo')
                    ->form([
                        DatePicker::make('due_date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('due_date_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    // FIX #2: Edit hanya muncul untuk invoice yang belum final
                    EditAction::make()
                        ->visible(fn ($record) => !in_array($record->status, [
                            \App\Models\Invoice::STATUS_PAID,
                            \App\Models\Invoice::STATUS_PARTIALLY_PAID,
                            \App\Models\Invoice::STATUS_OVERDUE,
                            \App\Models\Invoice::STATUS_CANCELLED,
                        ])),
                    Tables\Actions\Action::make('print_invoice')
                        ->label('Preview Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(fn($record) => route('pdf-stream', ['type' => 'sales-invoice', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                    static::taxNumberTableAction(),
                    \App\Filament\Support\CreditNoteActions::create(Tables\Actions\Action::make('create_credit_note')),
                    \App\Filament\Support\CreditNoteActions::cancelInvoice(Tables\Actions\Action::make('cancel_invoice')),
                    Tables\Actions\Action::make('view_journal_entries')
                        ->label('Lihat Journal Entries')
                        ->icon('heroicon-o-book-open')
                        ->color('success')
                        ->action(function ($record) {
                            $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
                                ->where('source_id', $record->id)
                                ->get();

                            if ($journalEntries->count() === 1) {
                                // Jika hanya 1 journal entry, langsung ke halaman detail
                                $entry = $journalEntries->first();
                                return redirect()->to("/admin/journal-entries/{$entry->id}");
                            } else {
                                // Jika multiple entries, gunakan filter
                                $sourceType = urlencode(\App\Models\Invoice::class);
                                $sourceId = $record->id;
                                return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][source_id]={$sourceId}");
                            }
                        }),
                    // FIX #2: Hapus hanya muncul untuk invoice yang belum final
                    DeleteAction::make()
                        ->visible(fn ($record) => !in_array($record->status, [
                            \App\Models\Invoice::STATUS_PAID,
                            \App\Models\Invoice::STATUS_PARTIALLY_PAID,
                            \App\Models\Invoice::STATUS_OVERDUE,
                            \App\Models\Invoice::STATUS_CANCELLED,
                        ])),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Sales Invoice</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Sales Invoice adalah faktur penjualan kepada customer berdasarkan Sale Order yang telah disetujui, digunakan untuk mencatat pendapatan dan memproses penerimaan pembayaran.</li>' .
                    '<li><strong>Status Flow:</strong> Dibuat dari Sale Order yang confirmed, dapat diedit sebelum dikirim.</li>' .
                    '<li><strong>Validasi:</strong> Subtotal, Tax, PPN dihitung otomatis berdasarkan item. Total invoice digunakan untuk Account Receivable.</li>' .
                    '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah invoice), <em>Delete</em> (hapus).</li>' .
                    '<li><strong>Filters:</strong> Customer, Status, Date Range, Amount Range, Due Date Range, dll.</li>' .
                    '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan invoice dari cabang tersebut jika tidak memiliki akses all.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan Sale Order dan menghasilkan Account Receivable.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('from_model_type', 'App\Models\SaleOrder')
            ->orderByDesc('created_at')
            ->with([
                'invoiceItem.product',
                'fromModel',
                'accountReceivable',
                'accountPayable'
            ]);
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
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
