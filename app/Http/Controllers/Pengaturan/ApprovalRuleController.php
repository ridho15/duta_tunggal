<?php

namespace App\Http\Controllers\Pengaturan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Aturan Persetujuan (T3.1, D6): ambang nominal dan peran penyetuju Quotation / Sales Order / Nota Kredit.
 * Hanya Owner / Super Admin / Admin (Spesifikasi UI/UX 5.2); perubahan tercatat lewat activity log model.
 */
class ApprovalRuleController extends Controller
{
    private function authorizeAccess(): void
    {
        abort_unless(MenuAccess::isSuper(Auth::user()), 403, 'Aturan Persetujuan hanya untuk Owner, Super Admin, dan Admin.');
    }

    public function index(): View
    {
        $this->authorizeAccess();

        $rules = ApprovalRule::query()->orderBy('document_type')->orderBy('above_amount')->get();

        return view('pengaturan.approval-rules.index', compact('rules'));
    }

    public function create(): View
    {
        $this->authorizeAccess();

        return view('pengaturan.approval-rules.create', ['rule' => new ApprovalRule(['is_active' => true]), 'roles' => $this->roleNames()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $rule = ApprovalRule::create($this->validated($request));

        return redirect()->route('settings.approval-rules.index')->with('success', "Aturan '{$rule->label}' berhasil ditambahkan.");
    }

    public function edit(ApprovalRule $approvalRule): View
    {
        $this->authorizeAccess();

        return view('pengaturan.approval-rules.edit', ['rule' => $approvalRule, 'roles' => $this->roleNames()]);
    }

    public function update(Request $request, ApprovalRule $approvalRule): RedirectResponse
    {
        $this->authorizeAccess();

        $approvalRule->update($this->validated($request));

        return redirect()->route('settings.approval-rules.index')->with('success', "Aturan '{$approvalRule->label}' berhasil diperbarui.");
    }

    private function roleNames(): array
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }

    private function validated(Request $request): array
    {
        // Input rupiah bertopeng ("1.000.000") diubah ke angka; kosong tetap null (= tanpa batas).
        foreach (['above_amount', 'up_to_amount'] as $field) {
            $request->merge([$field => filled($request->input($field)) ? MoneyHelper::safeParse($request->input($field)) : null]);
        }

        $data = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', array_keys(ApprovalRule::TYPES))],
            'label' => ['required', 'string', 'max:120'],
            'above_amount' => ['nullable', 'numeric', 'min:0'],
            // Rentang terbalik membuat aturan tidak pernah cocok tanpa pesan apa pun.
            'up_to_amount' => ['nullable', 'numeric', 'min:0', 'gt:above_amount'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'approver_label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], [
            'up_to_amount.gt' => 'Batas atas harus lebih besar dari batas bawah.',
            'roles.required' => 'Pilih minimal satu peran penyetuju.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
