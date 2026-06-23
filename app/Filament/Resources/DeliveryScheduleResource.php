<?php

namespace App\Filament\Resources;

use App\Exports\DeliveryScheduleRecapExport;
use App\Filament\Resources\DeliveryScheduleResource\Pages;
use App\Models\Cabang;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\Vehicle;
use App\Services\DeliveryScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DeliveryScheduleResource extends Resource
{
    protected static ?string $model = DeliverySchedule::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?string $navigationLabel = 'Penjadwalan Pengiriman';

    protected static ?string $modelLabel = 'Jadwal Pengiriman';

    protected static ?string $pluralModelLabel = 'Jadwal Pengiriman';

    protected static ?int $navigationSort = 3;

    // ── Permission checks ──────────────────────────────────────────────

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('view any delivery schedule') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('create delivery schedule') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('update delivery schedule') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('delete delivery schedule') ?? false;
    }

    public static function canView($record): bool
    {
        return Auth::user()?->can('view delivery schedule') ?? false;
    }

    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Jadwal Pengiriman')
                    ->schema([
                        TextInput::make('schedule_number')
                            ->label('Nomor Jadwal')
                            ->required()
                            ->reactive()
                            ->suffixAction(ActionsAction::make('generateScheduleNumber')
                                ->icon('heroicon-m-arrow-path')
                                ->tooltip('Generate Nomor Jadwal')
                                ->action(function ($set) {
                                    $service = app(DeliveryScheduleService::class);
                                    $set('schedule_number', $service->generateScheduleNumber());
                                }))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Nomor jadwal wajib diisi',
                                'unique'   => 'Nomor jadwal sudah digunakan',
                            ]),

                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->searchable()
                            ->preload()
                            ->options(Cabang::all()->mapWithKeys(fn($c) => [$c->id => "({$c->kode}) {$c->nama}"]))
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->live()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $set('suratJalan', []);
                            })
                            ->required()
                            ->validationMessages(['required' => 'Cabang wajib dipilih']),

                        Select::make('suratJalan')
                            ->label('Surat Jalan')
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->required()
                            ->disabled(fn($get) => empty($get('cabang_id')))
                            ->live()
                            ->relationship('suratJalan', 'sj_number', function (Builder $query, Get $get) {
                                $cabangId = $get('cabang_id');

                                if (! $cabangId) {
                                    $query->whereRaw('1 = 0');

                                    return;
                                }

                                $selectedIds = collect($get('suratJalan') ?? [])
                                    ->filter()
                                    ->map(fn($id) => (int) $id)
                                    ->values()
                                    ->all();

                                $assignedIds = DB::table('delivery_schedule_surat_jalans')
                                    ->distinct()
                                    ->pluck('surat_jalan_id')
                                    ->map(fn($id) => (int) $id)
                                    ->values()
                                    ->all();

                                $excludedIds = array_values(array_diff($assignedIds, $selectedIds));

                                $query->withoutGlobalScopes()
                                    ->where('cabang_id', $cabangId)
                                    ->where('status', 1)
                                    ->when(! empty($excludedIds), fn(Builder $query) => $query->whereNotIn('surat_jalans.id', $excludedIds))
                                    ->orderBy('sj_number');
                            })
                            ->helperText(fn($get) => empty($get('cabang_id')) ? 'Pilih Cabang terlebih dahulu untuk menampilkan Surat Jalan.' : 'Pilih satu atau lebih Surat Jalan untuk jadwal ini')
                            ->validationMessages(['required' => 'Minimal satu Surat Jalan wajib dipilih']),

                        Fieldset::make('Preview Surat Jalan dan Delivery Order')
                            ->schema([
                                Placeholder::make('selected_surat_jalan_preview')
                                    ->label('Surat Jalan Terpilih')
                                    ->content(fn(Get $get) => new HtmlString(static::getSelectedSuratJalanPreviewContent($get('suratJalan') ?? [], $get('cabang_id'))))
                                    ->columnSpanFull()
                                    ->visible(fn(Get $get) => filled($get('suratJalan'))),
                                Placeholder::make('selected_delivery_order_preview')
                                    ->label('Delivery Order Terkait')
                                    ->content(fn(Get $get) => new HtmlString(static::getSelectedDeliveryOrderPreviewContent($get('suratJalan') ?? [], $get('cabang_id'))))
                                    ->columnSpanFull()
                                    ->visible(fn(Get $get) => filled($get('suratJalan'))),
                            ])
                            ->columnSpanFull(),

                        DateTimePicker::make('scheduled_date')
                            ->label('Tanggal Keberangkatan')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->helperText('Tentukan tanggal dan waktu keberangkatan pengiriman')
                            ->validationMessages(['required' => 'Tanggal keberangkatan wajib diisi']),

                        Select::make('delivery_method')
                            ->label('Metode Pengiriman')
                            ->options([
                                'internal'       => 'Internal (Driver Perusahaan)',
                                'kurir_internal' => 'Kurir Internal',
                                'ekspedisi'      => 'Ekspedisi / Pihak Ketiga',
                            ])
                            ->default('internal')
                            ->required()
                            ->reactive()
                            ->helperText('Pilih metode pengiriman untuk jadwal ini')
                            ->validationMessages(['required' => 'Metode pengiriman wajib dipilih']),

                        Select::make('driver_id')
                            ->label('Driver')
                            ->searchable()
                            ->preload()
                            ->relationship('driver', 'name')
                            ->visible(fn($get) => in_array($get('delivery_method'), ['internal', 'kurir_internal', null, '']))
                            ->required(fn($get) => in_array($get('delivery_method'), ['internal', 'kurir_internal', null, '']))
                            ->validationMessages(['required' => 'Driver wajib dipilih']),

                        Select::make('vehicle_id')
                            ->label('Kendaraan')
                            ->searchable()
                            ->preload()
                            ->relationship('vehicle', 'plate')
                            ->visible(fn($get) => in_array($get('delivery_method'), ['internal', 'kurir_internal', null, '']))
                            ->required(fn($get) => in_array($get('delivery_method'), ['internal', 'kurir_internal', null, '']))
                            ->validationMessages(['required' => 'Kendaraan wajib dipilih']),

                        TextInput::make('driver_name')
                            ->label('Nama Driver / Ekspedisi')
                            ->maxLength(255)
                            ->visible(fn($get) => $get('delivery_method') === 'ekspedisi')
                            ->required(fn($get) => $get('delivery_method') === 'ekspedisi')
                            ->helperText('Nama driver atau nama ekspedisi')
                            ->validationMessages(['required' => 'Nama driver/ekspedisi wajib diisi']),

                        TextInput::make('vehicle_info')
                            ->label('Info Kendaraan / Resi')
                            ->maxLength(255)
                            ->visible(fn($get) => $get('delivery_method') === 'ekspedisi')
                            ->helperText('Plat kendaraan, nama ekspedisi, atau nomor resi'),



                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'           => 'Menunggu Keberangkatan',
                                'on_the_way'        => 'Sedang Berjalan',
                                'delivered'         => 'Selesai / Terkirim',
                                'failed'            => 'Gagal',
                                'cancelled'         => 'Dibatalkan',
                            ])
                            ->default('pending')
                            ->required()
                            ->validationMessages(['required' => 'Status wajib dipilih']),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),

                        Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getSuratJalanOptions(?int $cabangId, bool $excludeAlreadyAssigned = false): array
    {
        if (! $cabangId) {
            return [];
        }

        $assignedIds = $excludeAlreadyAssigned
            ? DB::table('delivery_schedule_surat_jalans')
            ->distinct()
            ->pluck('surat_jalan_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all()
            : [];

        return SuratJalan::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->where('status', 1)
            ->when(
                ! empty($assignedIds),
                fn(Builder $query) => $query->whereNotIn('surat_jalans.id', $assignedIds)
            )
            ->orderBy('sj_number')
            ->pluck('sj_number', 'id')
            ->toArray();
    }

    public static function shouldExcludeAssignedSuratJalan(): bool
    {
        return request()->routeIs('filament.admin.resources.delivery-schedules.create')
            || str_ends_with((string) request()->path(), 'delivery-schedules/create');
    }

    public static function getDeliveryOrderOptions(?int $cabangId): array
    {
        if (! $cabangId) {
            return [];
        }

        return SuratJalan::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->where('status', 1)
            ->with('deliveryOrder')
            ->orderBy('sj_number')
            ->get()
            ->flatMap(function (SuratJalan $suratJalan) {
                $deliveryOrders = $suratJalan->getRelationValue('deliveryOrder');

                return $deliveryOrders instanceof \Illuminate\Support\Collection
                    ? $deliveryOrders
                    : $suratJalan->deliveryOrder()->get();
            })
            ->unique('id')
            ->pluck('do_number', 'id')
            ->toArray();
    }

    public static function getSelectedSuratJalanPreviewContent(array $suratJalanIds, ?int $cabangId): string
    {
        $suratJalanIds = collect($suratJalanIds)->filter()->map(fn($id) => (int) $id)->values()->all();

        if (empty($suratJalanIds) || ! $cabangId) {
            return 'Pilih Surat Jalan untuk melihat detail.';
        }

        $suratJalans = SuratJalan::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->whereIn('id', $suratJalanIds)
            ->with('deliveryOrder')
            ->orderBy('sj_number')
            ->get();

        if ($suratJalans->isEmpty()) {
            return 'Surat Jalan yang dipilih belum tersedia atau tidak sesuai cabang.';
        }

        $items = $suratJalans->map(function (SuratJalan $suratJalan) {
            return sprintf(
                '<li><strong>%s</strong> - %s</li>',
                e($suratJalan->sj_number),
                e($suratJalan->shipping_method_label)
            );
        })->implode('');

        return '<div class="space-y-2"><p class="text-sm text-gray-600">' .
            'Jumlah Surat Jalan: <strong>' . $suratJalans->count() . '</strong>' .
            '</p><ul class="list-disc pl-5 space-y-1">' . $items . '</ul></div>';
    }

    public static function getSelectedDeliveryOrderPreviewContent(array $suratJalanIds, ?int $cabangId): string
    {
        $suratJalanIds = collect($suratJalanIds)->filter()->map(fn($id) => (int) $id)->values()->all();

        if (empty($suratJalanIds) || ! $cabangId) {
            return 'Pilih Surat Jalan terlebih dahulu untuk melihat Delivery Order terkait.';
        }

        $deliveryOrders = SuratJalan::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->whereIn('id', $suratJalanIds)
            ->with('deliveryOrder')
            ->get()
            ->flatMap(fn(SuratJalan $suratJalan) => $suratJalan->deliveryOrder)
            ->unique('id')
            ->values();

        if ($deliveryOrders->isEmpty()) {
            return 'Belum ada Delivery Order yang terhubung dengan Surat Jalan terpilih.';
        }

        $items = $deliveryOrders->map(function ($deliveryOrder) {
            return sprintf(
                '<li><strong>%s</strong> - %s</li>',
                e($deliveryOrder->do_number),
                e($deliveryOrder->status_label ?? ucfirst($deliveryOrder->status))
            );
        })->implode('');

        return '<div class="space-y-2"><p class="text-sm text-gray-600">' .
            'Jumlah Delivery Order: <strong>' . $deliveryOrders->count() . '</strong>' .
            '</p><ul class="list-disc pl-5 space-y-1">' . $items . '</ul></div>';
    }

    protected static function deliveryScheduleStatusLegend(): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<style>
                .fi-ta-header:has(.ds-legend){display:block!important;width:100%}
                .fi-ta-description:has(.ds-legend){display:block!important;width:100%;margin-bottom:16px}
                .ds-legend{width:100%;min-width:100%;max-width:none;box-sizing:border-box;display:block}
                .ds-legend+.fi-ta-header,.fi-ta-description+.fi-ta-header{margin-top:16px!important}
                .fi-ta-description .ds-legend{margin-bottom:0}
            </style>' .
            '<div class="ds-legend mb-4" style="width:100%;min-width:100%;max-width:none;box-sizing:border-box;margin-bottom:16px;">' .
            // Panduan Penjadwalan
            '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;margin-bottom:16px;">' .
                '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;color:#374151;">' .
                    '<span class="flex items-center gap-2" style="display:flex;align-items:center;gap:8px;">' .
                    '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#3b82f6;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                    '</svg>' .
                    'Panduan Penjadwalan Pengiriman' .
                    '</span>' .
                    '<span class="transition group-open:rotate-180">' .
                    '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                    '</span>' .
                '</summary>' .
                '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top:12px;font-size:14px;color:#4b5563;padding-left:28px;border-left:2px solid rgba(59,130,246,0.3);">' .
                '<ul class="list-disc pl-0" style="list-style:none;padding-left:0;">' .
                '<li><strong>Apa ini:</strong> Penjadwalan Pengiriman adalah dokumen untuk mengatur jadwal pengiriman barang.</li>' .
                '<li><strong>Flow:</strong> Pending → On The Way → Delivered/Failed. Status otomatis berubah saat DO terkait di-update.</li>' .
                '<li><strong>Driver:</strong> Untuk metode internal/kurir, wajib assign driver. Untuk ekspedisi, opsional.</li>' .
                '</ul>' .
                '</div>' .
            '</details>' .
            // Legenda Warna Status
            '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:8px;">' .
                '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">' .
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                '</svg>' .
                'Legenda Warna Status Baris Data' .
                '</h4>' .
                '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">' .
                // Kuning (Pending)
                '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 243, 199, 0.4); border: 1px solid rgba(253, 230, 138, 0.8);">' .
                '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #eab308; box-shadow: 0 1px 3px rgba(234, 179, 8, 0.4); flex-shrink: 0;"></div>' .
                '<div class="leading-tight">' .
                '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #854d0e;">Kuning (Pending)</span>' .
                '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Menunggu keberangkatan</span>' .
                '</div>' .
                '</div>' .
                // Biru (On The Way)
                '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
                '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
                '<div class="leading-tight">' .
                '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (On The Way)</span>' .
                '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Sedang dalam perjalanan</span>' .
                '</div>' .
                '</div>' .
                // Hijau (Delivered)
                '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(220, 252, 231, 0.4); border: 1px solid rgba(187, 247, 208, 0.8);">' .
                '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #22c55e; box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4); flex-shrink: 0;"></div>' .
                '<div class="leading-tight">' .
                '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #166534;">Hijau (Delivered)</span>' .
                '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Selesai / terkirim</span>' .
                '</div>' .
                '</div>' .
                // Merah (Failed)
                '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">' .
                '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>' .
                '<div class="leading-tight">' .
                '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Failed)</span>' .
                '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Pengiriman gagal</span>' .
                '</div>' .
                '</div>' .
                // Abu (Cancelled)
                '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(243, 244, 246, 0.6); border: 1px solid rgba(229, 231, 235, 0.9);">' .
                '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #6b7280; box-shadow: 0 1px 3px rgba(107, 114, 128, 0.4); flex-shrink: 0;"></div>' .
                '<div class="leading-tight">' .
                '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #374151;">Abu (Cancelled)</span>' .
                '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Jadwal dibatalkan</span>' .
                '</div>' .
                '</div>' .
                '</div>' .
            '</div>' .
            '</div>'
        );
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Detail Jadwal Pengiriman')
                    ->schema([
                        TextEntry::make('schedule_number')->label('Nomor Jadwal'),
                        TextEntry::make('scheduled_date')->label('Tanggal Keberangkatan')->dateTime('d/m/Y H:i'),
                        TextEntry::make('delivery_method')
                            ->label('Metode Pengiriman')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'internal'       => 'Internal (Driver Perusahaan)',
                                'kurir_internal' => 'Kurir Internal',
                                'ekspedisi'      => 'Ekspedisi / Pihak Ketiga',
                                default          => $state ?? '-',
                            }),
                        TextEntry::make('driver.name')
                            ->label('Driver')
                            ->placeholder('-')
                            ->visible(fn($record) => in_array($record?->delivery_method, ['internal', 'kurir_internal', null, ''])),

                        TextEntry::make('driver.license')
                            ->label('Kode Driver')
                            ->placeholder('-')
                            ->visible(fn($record) => in_array($record?->delivery_method, ['internal', 'kurir_internal', null, ''])),
                        TextEntry::make('vehicle.plate')
                            ->label('Kendaraan (Plat)')
                            ->placeholder('-')
                            ->visible(fn($record) => in_array($record?->delivery_method, ['internal', 'kurir_internal', null, ''])),

                        TextEntry::make('vehicle.type')
                            ->label('Tipe Kendaraan')
                            ->placeholder('-')
                            ->visible(fn($record) => in_array($record?->delivery_method, ['internal', 'kurir_internal', null, ''])),
                        TextEntry::make('driver_name')
                            ->label('Nama Driver / Ekspedisi')
                            ->placeholder('-')
                            ->visible(fn($record) => $record?->delivery_method === 'ekspedisi'),
                        TextEntry::make('vehicle_info')
                            ->label('Info Kendaraan / Resi')
                            ->placeholder('-')
                            ->visible(fn($record) => $record?->delivery_method === 'ekspedisi'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending'           => 'warning',
                                'on_the_way'        => 'info',
                                'delivered'         => 'success',
                                'failed'            => 'danger',
                                'cancelled'         => 'gray',
                                default             => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'pending'           => 'Menunggu Keberangkatan',
                                'on_the_way'        => 'Sedang Berjalan',
                                'delivered'         => 'Selesai / Terkirim',
                                'failed'            => 'Gagal',
                                'cancelled'         => 'Dibatalkan',
                                default             => ucfirst($state),
                            }),
                        TextEntry::make('cabang.kode')->label('Kode Cabang'),
                        TextEntry::make('cabang.nama')->label('Cabang'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                        TextEntry::make('surat_jalan_count')
                            ->label('Jumlah Surat Jalan')
                            ->getStateUsing(fn(DeliverySchedule $record): int => $record->relatedSuratJalanCount())
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('surat_jalan_summary')
                            ->label('Surat Jalan Dipilih')
                            ->getStateUsing(fn(DeliverySchedule $record): string => $record->relatedSuratJalanSummary())
                            ->columnSpanFull()
                            ->placeholder('-'),
                        TextEntry::make('delivery_order_count')
                            ->label('Jumlah Delivery Order')
                            ->getStateUsing(fn(DeliverySchedule $record): int => $record->relatedDeliveryOrderCount())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('delivery_order_summary')
                            ->label('Delivery Order dari Surat Jalan')
                            ->getStateUsing(fn(DeliverySchedule $record): string => $record->relatedDeliveryOrderSummary())
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('schedule_number')
                    ->label('Nomor Jadwal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scheduled_date')
                    ->label('Tanggal Keberangkatan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('vehicle.plate')
                    ->label('Kendaraan')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('delivery_method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'ekspedisi' => 'warning',
                        'kurir_internal' => 'info',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'internal'       => 'Internal',
                        'kurir_internal' => 'Kurir Internal',
                        'ekspedisi'      => 'Ekspedisi',
                        default          => '-',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('surat_jalan_summary')
                    ->label('Surat Jalan Dipilih')
                    ->getStateUsing(fn(DeliverySchedule $record): string => $record->relatedSuratJalanSummary())
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('suratJalan', function (Builder $query) use ($search) {
                            $query->where('sj_number', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('delivery_order_summary')
                    ->label('Delivery Order dari Surat Jalan')
                    ->getStateUsing(fn(DeliverySchedule $record): string => $record->relatedDeliveryOrderSummary())
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('suratJalan.deliveryOrder', function (Builder $query) use ($search) {
                            $query->where('do_number', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending'           => 'warning',
                        'on_the_way'        => 'info',
                        'delivered'         => 'success',
                        'failed'            => 'danger',
                        'cancelled'         => 'gray',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending'           => 'Menunggu Keberangkatan',
                        'on_the_way'        => 'Sedang Berjalan',
                        'delivered'         => 'Selesai / Terkirim',
                        'failed'            => 'Gagal',
                        'cancelled'         => 'Dibatalkan',
                        default             => ucfirst($state),
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->description(static::deliveryScheduleStatusLegend())
            ->recordClasses(fn($record) => match ($record->status) {
                'pending' => 'bg-yellow-100',
                'on_the_way' => 'bg-blue-100',
                'delivered' => 'bg-green-100',
                'failed' => 'bg-red-100',
                'cancelled' => 'bg-gray-100',
                default => '',
            })
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'           => 'Menunggu Keberangkatan',
                        'on_the_way'        => 'Sedang Berjalan',
                        'delivered'         => 'Selesai / Terkirim',
                        'failed'            => 'Gagal',
                        'cancelled'         => 'Dibatalkan',
                    ]),
                SelectFilter::make('driver_id')
                    ->relationship('driver', 'name')
                    ->label('Driver')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('vehicle_id')
                    ->relationship('vehicle', 'plate')
                    ->label('Kendaraan')
                    ->preload()
                    ->searchable(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('print_surat_kerja')
                        ->label('Print Surat Kerja')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->visible(fn(DeliverySchedule $record) => in_array($record->delivery_method, ['internal', 'kurir_internal']))
                        ->action(fn(DeliverySchedule $record) => static::streamWorkOrderPdf($record)),
                    Action::make('preview_pdf')
                        ->label('Preview / Download PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->visible(fn(DeliverySchedule $record) => in_array($record->delivery_method, ['internal', 'kurir_internal', 'ekspedisi']))
                        ->url(fn($record) => route('pdf-stream', ['type' => 'delivery-schedule', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                    // Quick status change actions
                    Action::make('set_on_the_way')
                        ->label('Mulai Pengiriman')
                        ->icon('heroicon-o-truck')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Mulai Pengiriman')
                        ->modalDescription('Ubah status jadwal ini menjadi "Sedang Berjalan"?')
                        ->visible(fn(DeliverySchedule $record) => in_array($record->status, ['pending']))
                        ->action(fn(DeliverySchedule $record) => $record->update(['status' => 'on_the_way'])),
                    Action::make('set_delivered')
                        ->label('Tandai Selesai')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Selesai')
                        ->modalDescription('Tandai jadwal pengiriman ini sebagai selesai/terkirim?')
                        ->visible(fn(DeliverySchedule $record) => in_array($record->status, ['on_the_way', 'pending']))
                        ->action(fn(DeliverySchedule $record) => $record->update(['status' => 'delivered'])),
                    Action::make('set_failed')
                        ->label('Tandai Gagal')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Pengiriman Gagal')
                        ->modalDescription('Tandai jadwal pengiriman ini sebagai gagal?')
                        ->visible(fn(DeliverySchedule $record) => in_array($record->status, ['on_the_way', 'pending']))
                        ->action(fn(DeliverySchedule $record) => $record->update(['status' => 'failed'])),
                    Action::make('set_cancelled')
                        ->label('Batalkan')
                        ->icon('heroicon-o-no-symbol')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn(DeliverySchedule $record) => in_array($record->status, ['pending']))
                        ->action(fn(DeliverySchedule $record) => $record->update(['status' => 'cancelled'])),
                    DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['driver', 'vehicle', 'suratJalan.deliveryOrder', 'cabang']);
            })
            ->headerActions([
                Action::make('rekap_driver')
                    ->label('Rekap per Driver')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->visible(fn() => Auth::user()?->can('rekap delivery schedule') ?? false)
                    ->form([
                        Select::make('driver_ids')
                            ->label('Driver')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(Driver::all()->pluck('name', 'id'))
                            ->placeholder('Pilih satu atau lebih driver...'),
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_to')
                            ->label('Sampai Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Select::make('export_format')
                            ->label('Format Export')
                            ->options([
                                'excel' => 'Excel (.xlsx)',
                                'pdf'   => 'PDF',
                            ])
                            ->default('excel')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $driverIds = $data['driver_ids'];
                        $dateFrom  = $data['date_from'] ?? null;
                        $dateTo    = $data['date_to'] ?? null;
                        $format    = $data['export_format'] ?? 'excel';

                        if ($format === 'pdf') {
                            $schedules = DeliverySchedule::withoutGlobalScopes()
                                ->with(['driver', 'vehicle', 'suratJalan.deliveryOrder'])
                                ->whereIn('driver_id', $driverIds)
                                ->when($dateFrom, fn($q) => $q->whereDate('scheduled_date', '>=', $dateFrom))
                                ->when($dateTo,   fn($q) => $q->whereDate('scheduled_date', '<=', $dateTo))
                                ->orderBy('driver_id')
                                ->orderBy('scheduled_date')
                                ->get();

                            $driverNames = Driver::whereIn('id', $driverIds)->pluck('name')->toArray();

                            $pdf = Pdf::loadView('pdf.delivery-schedule-recap', [
                                'schedules'   => $schedules,
                                'driverNames' => $driverNames,
                                'dateFrom'    => $dateFrom,
                                'dateTo'      => $dateTo,
                            ])->setPaper('a4', 'landscape');

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                'rekap-jadwal-pengiriman-' . now()->format('Ymd-His') . '.pdf'
                            );
                        }

                        return Excel::download(
                            new DeliveryScheduleRecapExport($driverIds, $dateFrom, $dateTo),
                            'rekap-jadwal-pengiriman-' . now()->format('Ymd-His') . '.xlsx'
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeliverySchedules::route('/'),
            'create' => Pages\CreateDeliverySchedule::route('/create'),
            'view'   => Pages\ViewDeliverySchedule::route('/{record}'),
            'edit'   => Pages\EditDeliverySchedule::route('/{record}/edit'),
        ];
    }

    public static function streamWorkOrderPdf(DeliverySchedule $record)
    {
        $record->loadMissing([
            'driver',
            'vehicle',
            'suratJalan.deliveryOrder.deliveryOrderItem.product.uom',
            'suratJalan.deliveryOrder.salesOrders.customer',
            'cabang',
        ]);

        $deliveryOrders = $record->relatedDeliveryOrders();

        $pdf = Pdf::loadView('pdf.delivery-schedule-work-order', [
            'schedule' => $record,
            'deliveryOrders' => $deliveryOrders,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'surat-kerja-' . ($record->schedule_number ?? 'delivery-schedule') . '.pdf'
        );
    }
}
