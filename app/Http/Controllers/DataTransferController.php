<?php

namespace App\Http\Controllers;

use App\Exports\PageDataExport;
use App\Support\Access;
use App\Support\DataTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Export / Import Excel-CSV semua halaman data, khusus Owner / Super Admin (keputusan Ko Handy 27 Nov 2026).
 * Import hanya data master; dua langkah: periksa dulu, lalu proses.
 */
class DataTransferController extends Controller
{
    private function authorizeSuper(): void
    {
        abort_unless(Access::canExportImport(), 403,
            'Anda tidak memiliki akses: Export / Import Excel-CSV. Solusi: fitur ini khusus Owner / Super Admin.');
    }

    public function export(Request $request, string $page): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeSuper();
        DataTransfer::dataset($page);
        $data = $request->validate([
            'format' => ['nullable', 'in:xlsx,csv'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'Tanggal "sampai" harus sama atau setelah tanggal "dari".']);

        $format = $data['format'] ?? 'xlsx';
        $name = Str::slug(DataTransfer::label($page)) . '-' . now()->format('Ymd-His') . '.' . $format;

        try {
            return Excel::download(new PageDataExport($page, $data['from'] ?? null, $data['to'] ?? null), $name,
                $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX);
        } catch (Throwable $e) {
            Log::error('Export gagal', ['page' => $page, 'error' => $e->getMessage()]);

            return back()->with('error', 'Export gagal: ' . Str::limit($e->getMessage(), 200) . '. Solusi: persempit rentang tanggal lalu coba lagi.');
        }
    }

    public function importForm(string $page): View
    {
        $this->authorizeSuper();
        abort_unless(DataTransfer::importable($page), 404, 'Data halaman ini tidak bisa diimpor. Solusi: import hanya untuk data master.');

        return view('data-transfer.import', [
            'page' => $page,
            'label' => DataTransfer::label($page),
            'key' => DataTransfer::dataset($page)['import']['key'],
            'columns' => DataTransfer::importColumns($page),
            'required' => DataTransfer::requiredColumns($page),
            'relations' => DataTransfer::relations($page),
            'result' => null,
            'token' => null,
            'listRoute' => Access::pages()[$page]['prefix'] . '.index',
        ]);
    }

    public function importTemplate(string $page): BinaryFileResponse
    {
        $this->authorizeSuper();
        abort_unless(DataTransfer::importable($page), 404);

        // Template = judul kolom + 3 contoh data yang sudah ada (bisa ditimpa / dihapus sebelum diisi)
        $rels = DataTransfer::relations($page);
        $sample = DataTransfer::query($page)->limit(3)->get()->map(fn ($r) => DataTransfer::row($page, $r, $rels))->all();
        $export = new class(DataTransfer::headings($page), $sample) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private array $head, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->head;
            }
        };

        return Excel::download($export,
            'template-import-' . Str::slug(DataTransfer::label($page)) . '.xlsx', ExcelFormat::XLSX);
    }

    /** Langkah 1: unggah & periksa (tidak ada data yang tersimpan). */
    public function importCheck(Request $request, string $page): View|RedirectResponse
    {
        $this->authorizeSuper();
        abort_unless(DataTransfer::importable($page), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']], [
            'file.required' => 'Pilih file Excel / CSV dulu.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 10 MB. Solusi: pecah file menjadi beberapa bagian.',
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension()) ?: 'xlsx';
        $token = Str::uuid()->toString();
        $path = $request->file('file')->storeAs('imports', "{$token}.{$ext}", 'local');

        $result = $this->runImport($page, $path, false);
        if (is_string($result)) {
            Storage::disk('local')->delete($path);

            return back()->with('error', $result);
        }

        return view('data-transfer.import', [
            'page' => $page,
            'label' => DataTransfer::label($page),
            'key' => DataTransfer::dataset($page)['import']['key'],
            'columns' => DataTransfer::importColumns($page),
            'required' => DataTransfer::requiredColumns($page),
            'relations' => DataTransfer::relations($page),
            'result' => $result,
            'token' => $result['errors'] === [] ? "{$token}.{$ext}" : null,
            'listRoute' => Access::pages()[$page]['prefix'] . '.index',
        ]);
    }

    /** Langkah 2: proses file yang sudah lolos pemeriksaan. */
    public function importStore(Request $request, string $page): RedirectResponse
    {
        $this->authorizeSuper();
        abort_unless(DataTransfer::importable($page), 404);
        $token = (string) $request->input('token');
        if (! preg_match('/^[0-9a-f-]{36}\.(xlsx|xls|csv|txt)$/', $token) || ! Storage::disk('local')->exists("imports/{$token}")) {
            return redirect()->route('data-transfer.import', $page)
                ->with('error', 'File pemeriksaan sudah tidak ada. Solusi: unggah ulang file lalu periksa lagi.');
        }

        $result = $this->runImport($page, "imports/{$token}", true);
        Storage::disk('local')->delete("imports/{$token}");
        if (is_string($result)) {
            return redirect()->route('data-transfer.import', $page)->with('error', $result);
        }
        if ($result['errors'] !== []) {
            return redirect()->route('data-transfer.import', $page)
                ->with('error', 'Import dibatalkan, tidak ada data yang berubah: ' . $result['errors'][0] . ' Solusi: perbaiki file lalu periksa ulang.');
        }

        Log::info('Import data', ['page' => $page, 'user' => $request->user()->id, 'created' => $result['created'], 'updated' => $result['updated']]);
        $listRoute = Access::pages()[$page]['prefix'] . '.index';

        return redirect()->route($listRoute)
            ->with('success', "Import {$this->label($page)} selesai: {$result['created']} data baru, {$result['updated']} data diperbarui.");
    }

    private function label(string $page): string
    {
        return DataTransfer::label($page);
    }

    /** @return array|string hasil import, atau pesan error bila file tidak terbaca */
    private function runImport(string $page, string $path, bool $commit): array|string
    {
        try {
            $sheets = Excel::toArray(new class {}, Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Log::warning('File import tidak terbaca', ['page' => $page, 'error' => $e->getMessage()]);

            return 'File tidak bisa dibaca: ' . Str::limit($e->getMessage(), 150) . '. Solusi: simpan ulang sebagai .xlsx atau .csv (UTF-8).';
        }
        $rows = $sheets[0] ?? [];
        if (count($rows) < 2) {
            return 'File kosong atau hanya berisi baris judul. Solusi: isi data mulai baris ke-2.';
        }

        try {
            return DataTransfer::import($page, $rows, $commit);
        } catch (Throwable $e) {
            Log::error('Import gagal', ['page' => $page, 'error' => $e->getMessage()]);

            return 'Import gagal: ' . Str::limit($e->getMessage(), 200) . '. Solusi: periksa isi file; bila berulang hubungi tim IT.';
        }
    }
}
