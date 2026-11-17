<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('logs in with valid credentials and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'admin@test.local',
        'password' => 'secret-password',
        'activated' => true,
    ]);
    $user->assignRole('admin');

    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@test.local',
        'password' => 'secret-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['userToken', 'user']]);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'admin@test.local']);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@test.local',
        'password' => 'wrong-password',
    ])->assertStatus(401);
});

it('exposes CASL ability rules for a super admin', function () {
    $user = User::factory()->create(['activated' => true]);
    $user->assignRole('admin');

    expect($user->fresh()->ability_rules)
        ->toContain(['subject' => ['all'], 'action' => ['manage']]);
});

it('gives the observer role read-only monitoring permissions', function () {
    $user = User::factory()->create(['activated' => true]);
    $user->assignRole('observer');

    expect($user->hasPermissionTo('read', 'system'))->toBeTrue()
        ->and($user->hasPermissionTo('manage', 'system'))->toBeFalse();
});

it('blocks protected routes until the required password change is done', function () {
    $user = User::factory()->create([
        'password' => 'initial-password',
        'activated' => true,
        'password_change_required' => true,
    ]);
    $user->assignRole('admin');
    \Laravel\Sanctum\Sanctum::actingAs($user);

    // Bloqué tant que le mot de passe n'est pas changé (sub_code 002).
    $this->getJson('/api/metrics')
        ->assertForbidden()
        ->assertJsonPath('errors.sub_code', '002');

    // La route update-password reste accessible et débloque le compte.
    $this->putJson('/api/users/update-password', [
        'current_password' => 'initial-password',
        'new_password' => 'new-secure-password',
        'new_password_confirmation' => 'new-secure-password',
    ])->assertOk();

    expect($user->fresh()->password_change_required)->toBeFalse();
    $this->getJson('/api/metrics')->assertOk();
});

it('rejects a password change with a wrong current password', function () {
    $user = User::factory()->create([
        'password' => 'initial-password',
        'activated' => true,
        'password_change_required' => true,
    ]);
    $user->assignRole('admin');
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->putJson('/api/users/update-password', [
        'current_password' => 'wrong-password',
        'new_password' => 'new-secure-password',
        'new_password_confirmation' => 'new-secure-password',
    ])->assertUnprocessable();

    expect($user->fresh()->password_change_required)->toBeTrue();
});

it('records audit log entries with the record helper', function () {
    $user = User::factory()->create();

    $log = AuditLog::record(
        action: 'process.kill',
        target: 'PID 1234',
        details: ['signal' => 'SIGKILL'],
        user: $user,
    );

    expect($log->status)->toBe('success')
        ->and($log->user_id)->toBe($user->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'process.kill', 'target' => 'PID 1234']);
});
