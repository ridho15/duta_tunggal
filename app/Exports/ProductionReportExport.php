<?php

namespace App\Exports;

use App\Models\ManufacturingOrder;
use App\Models\Production;
use App\Models\MaterialIssue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class ProductionReportExport implements FromCollection, WithHeadings
{
    protected $manufacturing_order_id;
    protected $start_date;
    protected $end_date;
    protected $type;

    public function __construct($manufacturing_order_id = null, $start_date = null, $end_date = null, $type = 'summary')
    {
        $this->manufacturing_order_id = $manufacturing_order_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->type = $type;
    }

    public function collection()
    {
        if ($this->type === 'material_usage') {
            return $this->getMaterialUsageData();
        } elseif ($this->type === 'efficiency') {
            return $this->getEfficiencyData();
        } else {
            return $this->getSummaryData();
        }
    }

    private function getSummaryData()
    {
        return ManufacturingOrder::query()
            ->when($this->manufacturing_order_id, fn($q) => $q->where('id', $this->manufacturing_order_id))
            ->when($this->start_date, fn($q) => $q->whereDate('start_date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('end_date', '<=', $this->end_date))
            ->with(['productionPlan', 'productions', 'materialIssues'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($mo) {
                $totalProduced = $mo->productions()->where('status', 'finished')->count();
                $totalMaterialCost = $mo->materialIssues()->where('material_issues.status', 'completed')->sum('total_cost');
                $productionDays = $mo->start_date && $mo->end_date ?
                    Carbon::parse($mo->start_date)->diffInDays(Carbon::parse($mo->end_date)) + 1 : 0;

                return [
                    'No. MO' => $mo->mo_number,
                    'Plan Produksi' => $mo->productionPlan->name ?? '-',
                    'Status' => $this->formatStatus($mo->status),
                    'Tanggal Mulai' => $mo->start_date ? $mo->start_date->format('d/m/Y') : '-',
                    'Tanggal Selesai' => $mo->end_date ? $mo->end_date->format('d/m/Y') : '-',
                    'Total Produksi' => $totalProduced,
                    'Total Biaya Material' => 'Rp ' . number_format($totalMaterialCost, 0, ',', '.'),
                    'Durasi (Hari)' => $productionDays,
                    'Efisiensi (%)' => $productionDays > 0 ? round(($totalProduced / $productionDays) * 100, 2) : 0,
                ];
            });
    }

    private function getMaterialUsageData()
    {
        return MaterialIssue::query()
            ->when($this->manufacturing_order_id, function($q) {
                $q->where('manufacturing_order_id', $this->manufacturing_order_id);
            })
            ->when($this->start_date, fn($q) => $q->whereDate('issue_date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('issue_date', '<=', $this->end_date))
            ->with(['manufacturingOrder', 'productionPlan', 'warehouse', 'items.product'])
            ->where('status', 'completed')
            ->orderBy('issue_date', 'desc')
            ->get()
            ->flatMap(function ($issue) {
                return $issue->items->map(function ($item) use ($issue) {
                    return [
                        'No. Issue' => $issue->issue_number,
                        'No. MO' => $issue->manufacturingOrder->mo_number ?? '-',
                        'Plan Produksi' => $issue->productionPlan->name ?? '-',
                        'Tanggal Issue' => $issue->issue_date->format('d/m/Y'),
                        'Gudang' => $issue->warehouse->name ?? '-',
                        'Kode Material' => $item->product->code ?? '-',
                        'Nama Material' => $item->product->name ?? '-',
                        'Qty Diminta' => $item->quantity,
                        'Qty Dikeluarkan' => $item->issued_quantity ?? $item->quantity,
                        'Harga Satuan' => 'Rp ' . number_format($item->unit_cost, 0, ',', '.'),
                        'Total Biaya' => 'Rp ' . number_format($item->total_cost, 0, ',', '.'),
                        'Status' => $this->formatStatus($issue->status),
                    ];
                });
            });
    }

    private function getEfficiencyData()
    {
        return ManufacturingOrder::query()
            ->when($this->manufacturing_order_id, fn($q) => $q->where('id', $this->manufacturing_order_id))
            ->when($this->start_date, fn($q) => $q->whereDate('start_date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('end_date', '<=', $this->end_date))
            ->with(['productionPlan', 'productions', 'materialIssues'])
            ->where('status', 'completed')
            ->get()
            ->map(function ($mo) {
                $plannedQuantity = $mo->productionPlan->quantity ?? 0;
                $actualProduced = $mo->productions()->where('status', 'finished')->count();
                $materialCost = $mo->materialIssues()->where('material_issues.status', 'completed')->sum('total_cost');

                $plannedDays = $mo->start_date && $mo->end_date ?
                    Carbon::parse($mo->start_date)->diffInDays(Carbon::parse($mo->end_date)) + 1 : 0;

                $efficiency = $plannedQuantity > 0 ? ($actualProduced / $plannedQuantity) * 100 : 0;
                $costPerUnit = $actualProduced > 0 ? $materialCost / $actualProduced : 0;
                $productivityRate = $plannedDays > 0 ? $actualProduced / $plannedDays : 0;

                return [
                    'No. MO' => $mo->mo_number,
                    'Plan Produksi' => $mo->productionPlan->name ?? '-',
                    'Qty Direncanakan' => $plannedQuantity,
                    'Qty Diproduksi' => $actualProduced,
                    'Pencapaian (%)' => round($efficiency, 2),
                    'Total Biaya Material' => 'Rp ' . number_format($materialCost, 0, ',', '.'),
                    'Biaya per Unit' => 'Rp ' . number_format($costPerUnit, 0, ',', '.'),
                    'Durasi (Hari)' => $plannedDays,
                    'Produktivitas (Unit/Hari)' => round($productivityRate, 2),
                    'Status Efisiensi' => $this->getEfficiencyStatus($efficiency),
                ];
            });
    }

    private function formatStatus($status)
    {
        return match($status) {
            'draft' => 'Draft',
            'in_progress' => 'Dalam Proses',
            'completed' => 'Selesai',
            'pending_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            default => ucfirst($status)
        };
    }

    private function getEfficiencyStatus($efficiency)
    {
        if ($efficiency >= 95) return 'Sangat Baik';
        if ($efficiency >= 85) return 'Baik';
        if ($efficiency >= 75) return 'Cukup';
        if ($efficiency >= 60) return 'Kurang';
        return 'Buruk';
    }

    public function headings(): array
    {
        if ($this->type === 'material_usage') {
            return [
                'No. Issue', 'No. MO', 'Plan Produksi', 'Tanggal Issue', 'Gudang',
                'Kode Material', 'Nama Material', 'Qty Diminta', 'Qty Dikeluarkan',
                'Harga Satuan', 'Total Biaya', 'Status'
            ];
        } elseif ($this->type === 'efficiency') {
            return [
                'No. MO', 'Plan Produksi', 'Qty Direncanakan', 'Qty Diproduksi',
                'Pencapaian (%)', 'Total Biaya Material', 'Biaya per Unit',
                'Durasi (Hari)', 'Produktivitas (Unit/Hari)', 'Status Efisiensi'
            ];
        } else {
            return [
                'No. MO', 'Plan Produksi', 'Status', 'Tanggal Mulai', 'Tanggal Selesai',
                'Total Produksi', 'Total Biaya Material', 'Durasi (Hari)', 'Efisiensi (%)'
            ];
        }
    }
}