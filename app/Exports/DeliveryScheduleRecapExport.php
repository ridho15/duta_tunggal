<?php

namespace App\Exports;

use App\Models\DeliverySchedule;
use App\Models\Driver;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DeliveryScheduleRecapExport implements WithMultipleSheets
{
    protected array $driverIds;
    protected ?string $dateFrom;
    protected ?string $dateTo;

    public function __construct(array $driverIds, ?string $dateFrom, ?string $dateTo)
    {
        $this->driverIds = $driverIds;
        $this->dateFrom  = $dateFrom;
        $this->dateTo    = $dateTo;
    }

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new DeliveryScheduleRecapSummarySheet($this->driverIds, $this->dateFrom, $this->dateTo);

        $drivers = Driver::whereIn('id', $this->driverIds)->get();
        foreach ($drivers as $driver) {
            $sheets[] = new DeliveryScheduleRecapDriverSheet($driver, $this->dateFrom, $this->dateTo);
        }

        return $sheets;
    }
}

class DeliveryScheduleRecapSummarySheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $driverIds;
    protected ?string $dateFrom;
    protected ?string $dateTo;

    public function __construct(array $driverIds, ?string $dateFrom, ?string $dateTo)
    {
        $this->driverIds = $driverIds;
        $this->dateFrom  = $dateFrom;
        $this->dateTo    = $dateTo;
    }

    public function title(): string
    {
        return 'Rekap Semua Driver';
    }

    public function headings(): array
    {
        return [
            'No', 'No. Jadwal', 'Driver', 'Kendaraan',
            'Tanggal Keberangkatan', 'Jumlah Surat Jalan',
            'Status', 'Catatan',
        ];
    }

    public function collection(): Collection
    {
        $query = DeliverySchedule::withoutGlobalScopes()
            ->with(['driver', 'vehicle', 'suratJalans'])
            ->whereIn('driver_id', $this->driverIds);

        if ($this->dateFrom) {
            $query->whereDate('scheduled_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('scheduled_date', '<=', $this->dateTo);
        }

        $schedules = $query->orderBy('driver_id')->orderBy('scheduled_date')->get();

        $statusLabels = [
            'pending'           => 'Menunggu Keberangkatan',
            'on_the_way'        => 'Sedang Berjalan',
            'delivered'         => 'Selesai / Terkirim',
            'partial_delivered' => 'Sebagian Terkirim',
            'failed'            => 'Gagal',
            'cancelled'         => 'Dibatalkan',
        ];

        $data = collect();
        $no   = 1;

        foreach ($schedules as $schedule) {
            $data->push([
                $no++,
                $schedule->schedule_number,
                $schedule->driver->name ?? '-',
                $schedule->vehicle->plate ?? '-',
                $schedule->scheduled_date ? \Carbon\Carbon::parse($schedule->scheduled_date)->format('d/m/Y H:i') : '-',
                $schedule->suratJalans->count(),
                $statusLabels[$schedule->status] ?? strtoupper($schedule->status),
                $schedule->notes ?? '',
            ]);
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:H{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                    ]);
                }
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 22, 'C' => 20, 'D' => 16, 'E' => 22, 'F' => 18, 'G' => 22, 'H' => 30];
    }
}

class DeliveryScheduleRecapDriverSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Driver $driver;
    protected ?string $dateFrom;
    protected ?string $dateTo;

    public function __construct(Driver $driver, ?string $dateFrom, ?string $dateTo)
    {
        $this->driver   = $driver;
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function title(): string
    {
        return substr($this->driver->name, 0, 31);
    }

    public function headings(): array
    {
        return [
            'No', 'No. Jadwal', 'Tanggal Keberangkatan',
            'Kendaraan', 'No. Surat Jalan', 'Status', 'Catatan',
        ];
    }

    public function collection(): Collection
    {
        $query = DeliverySchedule::withoutGlobalScopes()
            ->with(['vehicle', 'suratJalans'])
            ->where('driver_id', $this->driver->id);

        if ($this->dateFrom) {
            $query->whereDate('scheduled_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('scheduled_date', '<=', $this->dateTo);
        }

        $schedules = $query->orderBy('scheduled_date')->get();

        $statusLabels = [
            'pending'           => 'Menunggu Keberangkatan',
            'on_the_way'        => 'Sedang Berjalan',
            'delivered'         => 'Selesai / Terkirim',
            'partial_delivered' => 'Sebagian Terkirim',
            'failed'            => 'Gagal',
            'cancelled'         => 'Dibatalkan',
        ];

        $data = collect();
        $no   = 1;

        foreach ($schedules as $schedule) {
            $sjNumbers = $schedule->suratJalans->pluck('sj_number')->implode(', ') ?: '-';

            $data->push([
                $no++,
                $schedule->schedule_number,
                $schedule->scheduled_date ? \Carbon\Carbon::parse($schedule->scheduled_date)->format('d/m/Y H:i') : '-',
                $schedule->vehicle->plate ?? '-',
                $sjNumbers,
                $statusLabels[$schedule->status] ?? strtoupper($schedule->status),
                $schedule->notes ?? '',
            ]);
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
                    ]);
                }
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 22, 'C' => 22, 'D' => 16, 'E' => 35, 'F' => 22, 'G' => 30];
    }
}
