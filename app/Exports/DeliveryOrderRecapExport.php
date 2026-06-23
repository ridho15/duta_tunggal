<?php

namespace App\Exports;

use App\Models\DeliveryOrder;
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

class DeliveryOrderRecapExport implements WithMultipleSheets
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

        // Summary sheet (all drivers)
        $sheets[] = new DeliveryOrderRecapSummarySheet($this->driverIds, $this->dateFrom, $this->dateTo);

        // Per-driver detail sheet
        $drivers = Driver::whereIn('id', $this->driverIds)->get();
        foreach ($drivers as $driver) {
            $sheets[] = new DeliveryOrderRecapDriverSheet($driver, $this->dateFrom, $this->dateTo);
        }

        return $sheets;
    }
}

class DeliveryOrderRecapSummarySheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
        return ['No', 'Driver', 'No. DO', 'Tanggal Pengiriman', 'Customer', 'Status', 'Jumlah Item', 'Keterangan'];
    }

    public function collection(): Collection
    {
        $query = DeliveryOrder::with(['driver', 'salesOrders.customer', 'deliveryOrderItem'])
            ->whereIn('driver_id', $this->driverIds);

        if ($this->dateFrom) {
            $query->whereDate('delivery_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('delivery_date', '<=', $this->dateTo);
        }

        $orders = $query->orderBy('driver_id')->orderBy('delivery_date')->get();

        $data   = collect();
        $no     = 1;

        foreach ($orders as $do) {
            $customers = $do->salesOrders->map(fn($so) => $so->customer?->perusahaan ?? $so->customer?->name ?? '-')
                ->filter()->unique()->implode(', ') ?: '-';

            $data->push([
                $no++,
                $do->driver->name ?? '-',
                $do->do_number,
                $do->delivery_date ? \Carbon\Carbon::parse($do->delivery_date)->format('d/m/Y') : '-',
                $customers,
                strtoupper($do->status),
                $do->deliveryOrderItem->count(),
                $do->notes ?? '',
            ]);
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Header row styling
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

            // Alternate row colors
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
        return ['A' => 5, 'B' => 20, 'C' => 22, 'D' => 18, 'E' => 30, 'F' => 15, 'G' => 12, 'H' => 30];
    }
}

class DeliveryOrderRecapDriverSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
        return substr($this->driver->name, 0, 31); // max sheet name 31 chars
    }

    public function headings(): array
    {
        return ['No', 'No. DO', 'Tanggal Pengiriman', 'Customer', 'Produk', 'Qty', 'Status', 'Keterangan'];
    }

    public function collection(): Collection
    {
        $query = DeliveryOrder::with(['salesOrders.customer', 'deliveryOrderItem.product'])
            ->where('driver_id', $this->driver->id);

        if ($this->dateFrom) {
            $query->whereDate('delivery_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('delivery_date', '<=', $this->dateTo);
        }

        $orders = $query->orderBy('delivery_date')->get();

        $data = collect();
        $no   = 1;

        foreach ($orders as $do) {
            $customers = $do->salesOrders->map(fn($so) => $so->customer?->perusahaan ?? $so->customer?->name ?? '-')
                ->filter()->unique()->implode(', ') ?: '-';

            foreach ($do->deliveryOrderItem as $item) {
                $data->push([
                    $no++,
                    $do->do_number,
                    $do->delivery_date ? \Carbon\Carbon::parse($do->delivery_date)->format('d/m/Y') : '-',
                    $customers,
                    $item->product?->name ?? '-',
                    $item->quantity,
                    strtoupper($do->status),
                    $do->notes ?? '',
                ]);
            }
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
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
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
                    ]);
                }
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 22, 'C' => 18, 'D' => 30, 'E' => 30, 'F' => 8, 'G' => 15, 'H' => 30];
    }
}
