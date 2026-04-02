<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\SuratJalan;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;

class ListSuratJalans extends ListRecords
{
    protected static string $resource = SuratJalanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cetak_rekap_fleksibel')
                ->label('Cetak Rekap Surat Jalan')
                ->icon('heroicon-o-printer')
                ->modalHeading('Cetak Rekap Surat Jalan')
                ->form([
                    DatePicker::make('tanggal_mulai')
                        ->label('Tanggal Mulai')
                        ->required(),
                    DatePicker::make('tanggal_selesai')
                        ->label('Tanggal Selesai')
                        ->required(),
                    Select::make('status_pengiriman')
                        ->label('Status Pengiriman')
                        ->options([
                            'all' => 'Semua',
                            '1' => 'Terbit',
                        ])
                        ->default('1')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return static::streamRekapSuratJalanPdf(
                        $data['tanggal_mulai'] ?? null,
                        $data['tanggal_selesai'] ?? null,
                        $data['status_pengiriman'] ?? '1',
                    );
                }),
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public static function getRekapSuratJalanQuery(?string $tanggalMulai, ?string $tanggalSelesai, string|int $statusPengiriman = '1'): Builder
    {
        return SuratJalan::withoutGlobalScopes()
            ->with(['deliveryOrder.salesOrders.customer', 'deliveryOrder.cabang'])
            ->when(
                $statusPengiriman !== 'all' && $statusPengiriman !== null && $statusPengiriman !== '',
                fn (Builder $query) => $query->where('status', (int) $statusPengiriman),
            )
            ->when(
                $tanggalMulai,
                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '>=', $date),
            )
            ->when(
                $tanggalSelesai,
                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '<=', $date),
            )
            ->orderBy('issued_at')
            ->orderBy('sj_number');
    }

    public static function streamRekapSuratJalanPdf(?string $tanggalMulai, ?string $tanggalSelesai, string|int $statusPengiriman = '1')
    {
        $suratJalans = static::getRekapSuratJalanQuery($tanggalMulai, $tanggalSelesai, $statusPengiriman)->get();

        $pdf = Pdf::loadView('pdf.surat-jalan-recap', [
            'suratJalans' => $suratJalans,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'statusPengiriman' => $statusPengiriman,
        ])->setPaper('a4', 'landscape');

        return Response::streamDownload(
            fn () => print($pdf->output()),
            'rekap-surat-jalan-' . Carbon::now()->format('Ymd-His') . '.pdf'
        );
    }
}
