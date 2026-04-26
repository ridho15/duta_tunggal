<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('master data hub page renders without component errors', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/master-data-hub');

    $response->assertOk();
    $response->assertSeeText('Data Master');
    $response->assertSeeText('Satuan');
});