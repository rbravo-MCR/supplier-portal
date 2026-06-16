<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('users table removes obsolete role column and keeps nullable email', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'role_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email'))->toBeTrue();

    User::factory()->create([
        'username' => 'no-email',
        'email' => null,
    ]);

    $this->assertDatabaseHas('users', [
        'username' => 'no-email',
        'email' => null,
    ]);
});

test('user role remains readable from role_id for application compatibility', function () {
    $role = Role::where('code', 'supplier_pricing')->firstOrFail();

    $user = User::query()->create([
        'name' => 'Pricing User',
        'username' => 'pricing-user',
        'email' => null,
        'password' => 'password',
        'role_id' => $role->id,
        'supplier_id' => null,
        'status' => 'active',
    ]);

    expect($user->fresh()->role)->toBe('supplier_pricing');
});

test('legacy role assignment maps to role_id without writing a role column', function () {
    $user = User::factory()->create([
        'role' => 'supplier_reservations',
    ]);

    expect($user->fresh()->portalRole->code)->toBe('supplier_reservations')
        ->and($user->fresh()->role)->toBe('supplier_reservations');
});
