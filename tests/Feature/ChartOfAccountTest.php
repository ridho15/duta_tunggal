<?php

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);
    // Setup test data if needed
});

test('can create chart of account', function () {
    $coa = ChartOfAccount::factory()->create([
        'code' => '1110.01',
        'name' => 'Kas Besar',
        'type' => 'Asset',
    ]);

    expect($coa)->toBeInstanceOf(ChartOfAccount::class)
        ->and($coa->code)->toBe('1110.01')
        ->and($coa->normal_balance)->toBe('debit');
});test('can update chart of account', function () {
    $coa = ChartOfAccount::factory()->create([
        'code' => '1110.01',
        'name' => 'Kas Besar',
        'type' => 'Asset',
    ]);

    $coa->update(['name' => 'Kas Kecil']);

    expect($coa->name)->toBe('Kas Kecil');
});

test('can soft delete chart of account', function () {
    $coa = ChartOfAccount::factory()->create([
        'code' => '1110.01',
        'name' => 'Kas Besar',
        'type' => 'Asset',
    ]);

    $coa->delete();

    expect(ChartOfAccount::find($coa->id))->toBeNull();
    expect(ChartOfAccount::withTrashed()->find($coa->id))->not->toBeNull();
});

test('validates coa code uniqueness', function () {
    ChartOfAccount::factory()->create([
        'code' => '1110.01',
        'name' => 'Kas Besar',
        'type' => 'Asset',
    ]);

    expect(function () {
        ChartOfAccount::factory()->create([
            'code' => '1110.01',
            'name' => 'Kas Kecil',
            'type' => 'Asset',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('validates account type hierarchy', function () {
    $parent = ChartOfAccount::factory()->create([
        'code' => '9999',
        'name' => 'Test Parent',
        'type' => 'Asset',
        'parent_id' => null,
    ]);

    $child = ChartOfAccount::factory()->create([
        'code' => '9999.01',
        'name' => 'Test Child',
        'type' => 'Asset',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent_id)->toBe($parent->id);
    expect($parent->fresh()->children->contains($child))->toBeTrue();
});