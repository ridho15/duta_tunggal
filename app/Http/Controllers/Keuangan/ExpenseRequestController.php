<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Services\DocumentPrintBuilder;
use App\Services\ExpenseRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Permintaan Bayar non-barang (Keuangan). Akses halaman lewat checklist; setuju/bayar lewat ExpenseRequestService. */
class ExpenseRequestController extends Controller
{
    public function __construct(private ExpenseRequestService $service) {}

    /** Bukan peninjau: hanya boleh melihat permintaannya sendiri. */
    private function authorizeView(ExpenseRequest $request): void
    {
        $user = Auth::user();
        abort_unless($this->service->isReviewer($user) || (int) $request->requested_by === (int) $user->getKey(), 403,
            'Anda tidak memiliki akses: permintaan bayar milik orang lain. Solusi: minta finance / atasan membukanya.');
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = in_array($request->query('tab'), ['semua', 'menunggu', 'disetujui', 'dibayar', 'draf', 'ditolak'], true) ? $request->query('tab') : null;
        $reviewer = $this->service->isReviewer($user);
        $tab ??= $reviewer ? 'menunggu' : 'semua';
        $search = trim((string) $request->query('q', ''));

        $base = ExpenseRequest::query()->with(['requester:id,name', 'cabang:id,nama'])
            ->when(! $reviewer, fn ($q) => $q->where('requested_by', $user->getKey()))
            ->when(! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id && $reviewer && ! \App\Support\Access::isSuper($user),
                fn ($q) => $q->where('cabang_id', $user->cabang_id));
        $statusOf = [
            'menunggu' => [ExpenseRequest::STATUS_SUBMITTED], 'disetujui' => [ExpenseRequest::STATUS_APPROVED],
            'dibayar' => [ExpenseRequest::STATUS_PAID], 'draf' => [ExpenseRequest::STATUS_DRAFT],
            'ditolak' => [ExpenseRequest::STATUS_REJECTED, ExpenseRequest::STATUS_CANCELLED],
        ];
        $counts = ['semua' => (clone $base)->count()];
        foreach ($statusOf as $k => $st) {
            $counts[$k] = (clone $base)->whereIn('status', $st)->count();
        }

        $list = (clone $base)
            ->when(isset($statusOf[$tab]), fn ($q) => $q->whereIn('status', $statusOf[$tab]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$search}%")->orWhere('payee', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('keuangan.payment-requests.index', compact('list', 'counts', 'tab', 'search', 'reviewer'));
    }

    public function create(): View
    {
        return view('keuangan.payment-requests.form', [
            'req' => null, 'accounts' => $this->expenseAccounts(), 'branches' => Cabang::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $req = $this->service->save(null, $data, Auth::user(), $this->attachment($request));
            if ($request->boolean('submit_now')) {
                $this->service->submit($req, Auth::user());

                return redirect()->route('keuangan.payment-requests.show', $req)->with('success', "Permintaan {$req->number} diajukan dan menunggu persetujuan.");
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Simpan permintaan bayar gagal', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Permintaan gagal disimpan: ' . $e->getMessage() . '. Solusi: periksa isian lalu coba lagi.');
        }

        return redirect()->route('keuangan.payment-requests.show', $req)->with('success', "Draf {$req->number} tersimpan. Klik Ajukan bila sudah lengkap.");
    }

    public function show(ExpenseRequest $paymentRequest): View
    {
        $this->authorizeView($paymentRequest);
        $paymentRequest->load(['lines.coa', 'requester', 'approver', 'payer', 'cabang', 'paymentCoa', 'cashBankTransaction', 'signatures']);
        [, $approverLabel] = $this->service->approverRoles((float) $paymentRequest->amount);

        return view('keuangan.payment-requests.show', [
            'req' => $paymentRequest,
            'approval' => $this->service->canApprove($paymentRequest, Auth::user()),
            'canPay' => $this->service->canPay($paymentRequest, Auth::user()),
            'approverLabel' => $approverLabel,
            'cashAccounts' => ChartOfAccount::query()->where('is_active', true)->whereDoesntHave('children')
                ->where(fn ($q) => $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%'))->orderBy('code')->get(['id', 'code', 'name']),
            'mine' => (int) $paymentRequest->requested_by === (int) Auth::id(),
        ]);
    }

    public function edit(ExpenseRequest $paymentRequest): View|RedirectResponse
    {
        $this->authorizeOwner($paymentRequest);
        if (! $paymentRequest->isEditable()) {
            return redirect()->route('keuangan.payment-requests.show', $paymentRequest)->with('error', 'Hanya draf atau permintaan yang ditolak yang bisa diubah.');
        }

        return view('keuangan.payment-requests.form', [
            'req' => $paymentRequest->load('lines'), 'accounts' => $this->expenseAccounts(), 'branches' => Cabang::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function update(Request $request, ExpenseRequest $paymentRequest): RedirectResponse
    {
        $this->authorizeOwner($paymentRequest);
        if (! $paymentRequest->isEditable()) {
            return redirect()->route('keuangan.payment-requests.show', $paymentRequest)->with('error', 'Hanya draf atau permintaan yang ditolak yang bisa diubah.');
        }
        $req = $this->service->save($paymentRequest, $this->validated($request), Auth::user(), $this->attachment($request));
        if ($request->boolean('submit_now')) {
            $this->service->submit($req, Auth::user());

            return redirect()->route('keuangan.payment-requests.show', $req)->with('success', "Permintaan {$req->number} diajukan dan menunggu persetujuan.");
        }

        return redirect()->route('keuangan.payment-requests.show', $req)->with('success', "Perubahan {$req->number} tersimpan.");
    }

    public function submit(ExpenseRequest $paymentRequest): RedirectResponse
    {
        return $this->act(fn () => $this->service->submit($paymentRequest, Auth::user()), $paymentRequest, 'Permintaan diajukan dan menunggu persetujuan.');
    }

    public function approve(Request $request, ExpenseRequest $paymentRequest): RedirectResponse
    {
        return $this->act(fn () => $this->service->approve($paymentRequest, Auth::user(), $request->input('approval_notes')), $paymentRequest, 'Permintaan disetujui. Kasir / finance bisa membayarnya.');
    }

    public function reject(Request $request, ExpenseRequest $paymentRequest): RedirectResponse
    {
        return $this->act(fn () => $this->service->reject($paymentRequest, Auth::user(), (string) $request->input('approval_notes')), $paymentRequest, 'Permintaan ditolak; pembuat bisa memperbaiki lalu mengajukan ulang.');
    }

    public function pay(Request $request, ExpenseRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validate([
            'payment_coa_id' => ['required', 'integer'],
            'paid_date' => ['required', 'date', 'before_or_equal:today'],
        ], ['payment_coa_id.required' => 'Pilih akun Kas / Bank yang dipakai membayar.', 'paid_date.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.']);

        return $this->act(fn () => $this->service->pay($paymentRequest, Auth::user(), (int) $data['payment_coa_id'], $data['paid_date']), $paymentRequest, 'Pembayaran tercatat di Kas & Bank dan jurnal sudah dibuat. Voucher siap dicetak.');
    }

    public function cancel(Request $request, ExpenseRequest $paymentRequest): RedirectResponse
    {
        return $this->act(fn () => $this->service->cancel($paymentRequest, Auth::user(), (string) $request->input('cancel_reason', 'Dibatalkan')), $paymentRequest, 'Permintaan dibatalkan.');
    }

    public function pdf(ExpenseRequest $paymentRequest): Response|RedirectResponse
    {
        $this->authorizeView($paymentRequest);
        if ($paymentRequest->status !== ExpenseRequest::STATUS_PAID) {
            return back()->with('error', 'Voucher baru bisa dicetak setelah permintaan dibayar.');
        }
        $paymentRequest->load(['lines.coa', 'requester', 'approver', 'payer', 'cabang', 'paymentCoa', 'cashBankTransaction', 'signatures']);

        return Pdf::loadView('keuangan.payment-requests.pdf', [
            'req' => $paymentRequest,
            'company' => app(DocumentPrintBuilder::class)->company($paymentRequest->cabang, false),
        ])->setPaper('a4', 'portrait')->stream('Voucher_' . $paymentRequest->number . '.pdf');
    }

    // ------------------------------------------------------------------ bantu

    private function act(callable $fn, ExpenseRequest $req, string $ok): RedirectResponse
    {
        $this->authorizeView($req);
        try {
            $fn();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (Throwable $e) {
            Log::error('Aksi permintaan bayar gagal', ['id' => $req->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Gagal: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('keuangan.payment-requests.show', $req)->with('success', $ok);
    }

    private function authorizeOwner(ExpenseRequest $req): void
    {
        abort_unless((int) $req->requested_by === (int) Auth::id() || \App\Support\Access::isSuper(Auth::user()), 403,
            'Anda tidak memiliki akses: hanya pembuat yang bisa mengubah permintaan ini.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'request_date' => ['required', 'date'],
            'payee' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'cabang_id' => ['nullable', 'integer', 'exists:cabangs,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.coa_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'payee.required' => 'Isi dibayar kepada siapa (nama toko / vendor / orang).',
            'description.required' => 'Isi keperluan pembayaran.',
            'attachment.mimes' => 'Lampiran harus PDF / JPG / PNG.',
        ]);
    }

    private function attachment(Request $request): ?string
    {
        return $request->hasFile('attachment') ? $request->file('attachment')->store('payment-requests', 'public') : null;
    }

    private function expenseAccounts()
    {
        return ExpenseRequestService::expenseAccountQuery()->get(['id', 'code', 'name', 'type']);
    }
}
