<?php

use App\Models\Supplier;
use App\Models\User;

test('home redirects guests to login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('home redirects authenticated admin users to admin dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('home redirects authenticated supplier users to supplier dashboard', function (string $role) {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => $role,
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('supplier.dashboard'));

    $this->assertAuthenticatedAs($user);
})->with(['supplier_admin', 'supplier_reservations', 'supplier_pricing', 'supplier_user']);
