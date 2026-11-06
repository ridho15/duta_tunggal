<?php

use App\Models\ApprovalRule;
use App\Models\User;
use Spatie\Permission\Models\Role;

function approvalUser(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    \App\Support\MenuAccess::flush();
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function approvalPayload(array $override = []): array
{
    return array_merge([
        'document_type' => 'sale_order',
        'label' => 'SO di atas 50 juta',
        'above_amount' => '50.000.000',
        'up_to_amount' => '',
        'roles' => ['Owner', 'Finance Manager'],
        'approver_label' => 'Direktur',
        'is_active' => '1',
    ], $override);
}

it('Owner melihat daftar aturan; Kasir ditolak 403 di semua halaman', function () {
    ApprovalRule::create(approvalPayload(['above_amount' => 50000000, 'up_to_amount' => null, 'is_active' => true]));

    $this->actingAs(approvalUser('Owner'))->get(route('settings.approval-rules.index'))
        ->assertOk()->assertSee('SO di atas 50 juta')->assertSee('Rp 50.000.000')->assertSee('Tanpa batas');

    $kasir = approvalUser('Kasir');
    $this->actingAs($kasir)->get(route('settings.approval-rules.index'))->assertForbidden();
    $this->actingAs($kasir)->get(route('settings.approval-rules.create'))->assertForbidden();
    $this->actingAs($kasir)->post(route('settings.approval-rules.store'), approvalPayload())->assertForbidden();
    expect(ApprovalRule::where('label', 'SO di atas 50 juta')->count())->toBe(1);
});

it('menyimpan aturan dengan rupiah bertopeng dan langsung dipakai oleh ApprovalRule::forDocument', function () {
    $this->actingAs(approvalUser('Super Admin'))
        ->post(route('settings.approval-rules.store'), approvalPayload())
        ->assertRedirect(route('settings.approval-rules.index'));

    $rule = ApprovalRule::where('label', 'SO di atas 50 juta')->firstOrFail();
    expect((float) $rule->above_amount)->toBe(50000000.0)
        ->and($rule->up_to_amount)->toBeNull()
        ->and($rule->roles)->toBe(['Owner', 'Finance Manager'])
        ->and(ApprovalRule::forDocument('sale_order', 60000000)?->is($rule))->toBeTrue()
        ->and(ApprovalRule::forDocument('sale_order', 40000000)?->is($rule))->toBeFalse();
});

it('menolak rentang terbalik, peran kosong, peran yang tidak ada, dan jenis dokumen asing', function () {
    $owner = approvalUser('Owner');

    $this->actingAs($owner)->post(route('settings.approval-rules.store'), approvalPayload(['above_amount' => '100.000', 'up_to_amount' => '50.000']))
        ->assertSessionHasErrors('up_to_amount');
    $this->actingAs($owner)->post(route('settings.approval-rules.store'), approvalPayload(['up_to_amount' => '50.000.000']))
        ->assertSessionHasErrors('up_to_amount');   // sama dengan batas bawah: tidak ada nilai yang cocok
    $this->actingAs($owner)->post(route('settings.approval-rules.store'), approvalPayload(['roles' => []]))
        ->assertSessionHasErrors('roles');
    $this->actingAs($owner)->post(route('settings.approval-rules.store'), approvalPayload(['roles' => ['Peran Palsu']]))
        ->assertSessionHasErrors('roles.0');
    $this->actingAs($owner)->post(route('settings.approval-rules.store'), approvalPayload(['document_type' => 'invoice']))
        ->assertSessionHasErrors('document_type');

    expect(ApprovalRule::where('label', 'SO di atas 50 juta')->exists())->toBeFalse();
});

it('halaman ubah menampilkan nilai apa adanya dan perubahan serta penonaktifan tersimpan', function () {
    $rule = ApprovalRule::create(approvalPayload(['above_amount' => 10000000, 'up_to_amount' => 50000000]));
    $owner = approvalUser('Owner');

    $this->actingAs($owner)->get(route('settings.approval-rules.edit', $rule))
        ->assertOk()->assertSee('value="10000000"', false)->assertSee('value="50000000"', false);

    $this->actingAs($owner)->put(route('settings.approval-rules.update', $rule), approvalPayload([
        'label' => 'Diubah', 'above_amount' => '20.000.000', 'up_to_amount' => '', 'roles' => ['Admin'], 'is_active' => '0',
    ]))->assertRedirect(route('settings.approval-rules.index'));

    $rule->refresh();
    expect($rule->label)->toBe('Diubah')
        ->and((float) $rule->above_amount)->toBe(20000000.0)
        ->and($rule->up_to_amount)->toBeNull()
        ->and($rule->roles)->toBe(['Admin'])
        ->and($rule->is_active)->toBeFalse()
        ->and(ApprovalRule::forDocument('sale_order', 30000000)?->is($rule))->toBeFalse();   // aturan nonaktif tidak berlaku
});
