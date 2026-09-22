<?php

namespace App\Filament\Pages;

use App\Exports\SalesReportExport;
use App\Helpers\MoneyHelper;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Services\Reports\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Penjualan (Fase 6): tiga mode — Penjualan (Invoice, default), Pengiriman, Pesanan (SO).
 * Filter status berasal dari konstanta model; HPP/margin/status pembayaran pada mode Invoice.
 */
class SalesReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.sales-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?string $mode = SalesReportService::DEFAULT_MODE;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $customer_id = null;
    public ?string $so_number = null;
    public ?string $sort_by_total = null;
    public ?string $status = null;

    public function mount(): void
    {
        $this->mode = SalesReportService::DEFAULT_MODE;
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->customer_id = null;
        $this->so_number = null;
        $this->sort_by_total = null;
        $this->status = null;

        $this->form->fill([
            'mode' => $this->mode,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'customer_id' => $this->customer_id,
            'so_number' => $this->so_number,
            'sort_by_total' => $this->sort_by_total,
            'status' => $this->status,
        ]);
    }

    public function table(Table $table): Table
    {
        $service = $this->salesReportService();
        $mode = $this->currentMode();

        $table = $table
            ->query($service->query($this->reportFilters(), Auth::user()))
            ->columns(match ($mode) {
                SalesReportService::MODE_ORDER => $this->orderColumns(),
                SalesReportService::MODE_DELIVERY => $this->deliveryColumns(),
                default => $this->invoiceColumns(),
            })
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        return Excel::download(
                            new SalesReportExport($this->getFilteredQuery(), $this->currentMode()),
                            'laporan_penjualan_' . $this->currentMode() . '_' . now()->format('Ymd_His') . '.xlsx'
                        );
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(fn () => $this->streamPdf()),
            ]);

        return match ($mode) {
            SalesReportService::MODE_ORDER => $table->defaultSort('order_date', 'desc')->actions([
                Action::make('view')
                    ->label('Lihat Detail')
                    ->url(fn ($record) => route('filament.admin.resources.sale-orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ]),
            SalesReportService::MODE_DELIVERY => $table->defaultSort('delivery_date', 'desc'),
            default => $table->defaultSort('invoice_date', 'desc')->actions([
                Action::make('view')
                    ->label('Lihat Invoice')
                    ->url(fn ($record) => \App\Filament\Resources\SalesInvoiceResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ]),
        };
    }

    /** @return array<int, TextColumn> */
    private function invoiceColumns(): array
    {
        $row = fn (Invoice $record) => $this->salesReportService()->invoiceRow($record);
        $money = fn ($state) => MoneyHelper::rupiah($state);

        return [
            TextColumn::make('invoice_number')->label('No. Invoice')->sortable(),
            TextColumn::make('invoice_date')->label('Tanggal')->date('d/m/Y')->sortable(),
            TextColumn::make('customer_display')->label('Customer')
                ->getStateUsing(fn (Invoice $record) => '(' . $row($record)['customer_code'] . ') ' . $row($record)['customer_name']),
            TextColumn::make('so_display')->label('No. SO')->getStateUsing(fn (Invoice $record) => $row($record)['so_number']),
            TextColumn::make('dpp_display')->label('DPP')->getStateUsing(fn (Invoice $record) => $money($row($record)['dpp'])),
            TextColumn::make('ppn_display')->label('PPN')->getStateUsing(fn (Invoice $record) => $money($row($record)['ppn'])),
            TextColumn::make('total')->label('Total')->rupiah()->sortable(),
            TextColumn::make('hpp_display')->label('HPP')
                ->getStateUsing(fn (Invoice $record) => $money($row($record)['hpp']) . ($row($record)['hpp_estimated'] ? ' *' : ''))
                ->tooltip(fn (Invoice $record) => $row($record)['hpp_estimated'] ? 'Estimasi: invoice belum punya snapshot HPP dari jurnal' : 'Snapshot HPP dari jurnal'),
            TextColumn::make('margin_display')->label('Margin')
                ->getStateUsing(fn (Invoice $record) => $money($row($record)['margin']))
                ->color(fn (Invoice $record) => $row($record)['margin'] < 0 ? 'danger' : 'success'),
            TextColumn::make('margin_pct_display')->label('Margin %')
                ->getStateUsing(fn (Invoice $record) => number_format($row($record)['margin_pct'], 2, ',', '.') . '%'),
            TextColumn::make('payment_display')->label('Pembayaran')->badge()
                ->getStateUsing(fn (Invoice $record) => SalesReportService::PAYMENT_STATUS_LABELS[$row($record)['payment_status']])
                ->color(fn (Invoice $record) => match ($row($record)['payment_status']) {
                    'lunas' => 'success',
                    'sebagian' => 'info',
                    'jatuh_tempo' => 'danger',
                    default => 'warning',
                }),
            TextColumn::make('branch_display')->label('Cabang')->getStateUsing(fn (Invoice $record) => $row($record)['branch'])
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /** @return array<int, TextColumn> */
    private function deliveryColumns(): array
    {
        $row = fn (DeliveryOrder $record) => $this->salesReportService()->deliveryRow($record);
        $money = fn ($state) => MoneyHelper::rupiah($state);

        return [
            TextColumn::make('do_number')->label('No. DO')->sortable(),
            TextColumn::make('delivery_date')->label('Tanggal Kirim')->date('d/m/Y')->sortable(),
            TextColumn::make('customer_display')->label('Customer')->getStateUsing(fn (DeliveryOrder $record) => $row($record)['customer_name']),
            TextColumn::make('so_display')->label('No. SO')->getStateUsing(fn (DeliveryOrder $record) => $row($record)['so_numbers']),
            TextColumn::make('invoice_display')->label('No. Invoice')
                ->getStateUsing(fn (DeliveryOrder $record) => implode(', ', $row($record)['invoice_numbers']) ?: '-'),
            TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn ($state) => DeliveryOrder::statusLabel($state))
                ->color(fn ($state) => DeliveryOrder::statusColor($state)),
            TextColumn::make('qty_display')->label('Qty')->getStateUsing(fn (DeliveryOrder $record) => rtrim(rtrim(number_format($row($record)['quantity'], 2, ',', '.'), '0'), ',')),
            TextColumn::make('dpp_display')->label('DPP')->getStateUsing(fn (DeliveryOrder $record) => $money($row($record)['dpp'])),
            TextColumn::make('hpp_display')->label('HPP (Stok)')->getStateUsing(fn (DeliveryOrder $record) => $money($row($record)['hpp'])),
            TextColumn::make('margin_display')->label('Margin')->getStateUsing(fn (DeliveryOrder $record) => $money($row($record)['margin'])),
            TextColumn::make('margin_pct_display')->label('Margin %')
                ->getStateUsing(fn (DeliveryOrder $record) => number_format($row($record)['margin_pct'], 2, ',', '.') . '%'),
        ];
    }

    /** @return array<int, TextColumn> */
    private function orderColumns(): array
    {
        return [
            TextColumn::make('so_number')->label('No. SO')->sortable(),
            TextColumn::make('order_date')->label('Tanggal')->date()->sortable(),
            TextColumn::make('customer.code')->label('Kode Customer')->sortable(),
            TextColumn::make('customer.name')->label('Nama Customer')->sortable(),
            TextColumn::make('total_amount')->label('Total')->rupiah()->sortable(),
            TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn ($state) => SaleOrder::statusLabel($state))
                ->color(fn ($state) => SaleOrder::statusColor($state)),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('mode')
                    ->label('Jenis Laporan')
                    ->options(SalesReportService::modeOptions())
                    ->default(SalesReportService::DEFAULT_MODE)
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText('Penjualan (Invoice) = dasar akrual dengan HPP & margin; Pengiriman = barang keluar gudang; Pesanan = SO.')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        // Status berbeda antar mode: reset agar tidak menyaring dengan nilai yang tidak berlaku
                        $this->mode = $state;
                        $this->status = null;
                        $this->form->fill(array_merge($this->form->getRawState(), ['mode' => $state, 'status' => null]));
                        $this->updateFilters();
                    }),

                DatePicker::make('start_date')
                    ->label(fn () => 'Tanggal Mulai (' . $this->dateBasisLabel() . ')')
                    ->default(now()->startOfMonth())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                DatePicker::make('end_date')
                    ->label(fn () => 'Tanggal Akhir (' . $this->dateBasisLabel() . ')')
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('customer_id')
                    ->label('Customer')
                    ->options(function () {
                        return Customer::all()->mapWithKeys(function ($customer) {
                            return [$customer->id => $customer->code . ' - ' . $customer->name];
                        });
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Customer::where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($customer) {
                                return [$customer->id => $customer->code . ' - ' . $customer->name];
                            })
                            ->toArray();
                    })
                    ->placeholder('Semua Customer')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                TextInput::make('so_number')
                    ->label(fn () => match ($this->currentMode()) {
                        SalesReportService::MODE_INVOICE => 'No. Invoice / No. SO',
                        SalesReportService::MODE_DELIVERY => 'No. DO / No. SO',
                        default => 'No. SO',
                    })
                    ->placeholder('Cari berdasarkan nomor dokumen')
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('sort_by_total')
                    ->label('Urutkan Total')
                    ->options([
                        'asc' => 'Terendah ke Tertinggi',
                        'desc' => 'Tertinggi ke Terendah',
                    ])
                    ->placeholder('Tidak diurutkan')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                // Opsi status dari konstanta model (tidak ada daftar tulis-tangan) dan mengikuti mode
                Select::make('status')
                    ->label(fn () => SalesReportService::statusFilterLabel($this->currentMode()))
                    ->options(fn () => SalesReportService::statusOptions($this->currentMode()))
                    ->placeholder(fn () => $this->currentMode() === SalesReportService::MODE_DELIVERY ? 'Sudah dikirim (Dikirim/Diterima/Selesai)' : 'Semua Status')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),
            ])
            ->columns(3);
    }

    public function updatedMode(): void
    {
        $this->status = null;
        $this->updateFilters();
    }

    public function updatedStartDate(): void
    {
        $this->updateFilters();
    }

    public function updatedEndDate(): void
    {
        $this->updateFilters();
    }

    public function updatedCustomerId(): void
    {
        $this->updateFilters();
    }

    public function updatedSoNumber(): void
    {
        $this->updateFilters();
    }

    public function updatedSortByTotal(): void
    {
        $this->updateFilters();
    }

    public function updatedStatus(): void
    {
        $this->updateFilters();
    }

    public function updateFilters(): void
    {
        $this->resetTable();
    }

    /**
     * Ringkasan periode di atas tabel: dari service yang sama dengan PDF (angka layar = PDF = ekspor).
     *
     * @return array<string, string>
     */
    public function getSummaryLines(): array
    {
        $payload = $this->salesReportService()->pdfPayload($this->reportFilters(), Auth::user());

        if (isset($payload['summary_lines'])) {
            return $payload['summary_lines'];
        }

        $summary = $payload['summary'];

        return [
            'Jumlah SO' => (string) $summary['total_orders'],
            'Total Qty' => (string) $summary['total_quantity'],
            'Total DPP' => MoneyHelper::rupiah($summary['total_dpp']),
            'Total Nilai SO' => MoneyHelper::rupiah($summary['total_amount']),
            'Rata-rata per SO' => MoneyHelper::rupiah($summary['average_amount']),
        ];
    }

    public function getFootnote(): ?string
    {
        return $this->currentMode() === SalesReportService::MODE_INVOICE
            ? '* HPP ditandai estimasi (cost_price master saat ini) bila invoice belum memiliki snapshot HPP dari jurnal.'
            : null;
    }

    private function streamPdf()
    {
        $filters = $this->reportFilters();
        $payload = $this->salesReportService()->pdfPayload($filters, Auth::user());

        return response()->streamDownload(function () use ($payload) {
            $view = $payload['mode'] === SalesReportService::MODE_ORDER ? 'reports.sales_report' : 'reports.sales_report_modes';

            $pdf = Pdf::loadView($view, array_merge($payload, [
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ]));

            $pdf->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'orientation' => 'landscape',
                'defaultPaperSize' => 'a4',
            ]);

            echo $pdf->output();
        }, 'laporan_penjualan_' . $payload['mode'] . '_' . now()->format('Ymd_His') . '.pdf');
    }

    private function dateBasisLabel(): string
    {
        return match ($this->currentMode()) {
            SalesReportService::MODE_ORDER => 'tanggal SO',
            SalesReportService::MODE_DELIVERY => 'tanggal kirim',
            default => 'tanggal invoice',
        };
    }

    private function currentMode(): string
    {
        return SalesReportService::resolveMode(['mode' => $this->mode]);
    }

    private function getFilteredQuery()
    {
        return $this->salesReportService()->query($this->reportFilters(), Auth::user());
    }

    private function reportFilters(): array
    {
        return [
            'mode' => $this->currentMode(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'customer_id' => $this->customer_id,
            'so_number' => $this->so_number,
            'sort_by_total' => $this->sort_by_total,
            'status' => $this->status,
        ];
    }

    private ?SalesReportService $salesReportServiceInstance = null;

    private function salesReportService(): SalesReportService
    {
        // Satu instance per request agar cache baris (invoiceRowCache, dst.) benar-benar terpakai.
        return $this->salesReportServiceInstance ??= app(SalesReportService::class);
    }
}
