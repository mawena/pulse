<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function actingAsAdmin(): User
{
    $user = User::factory()->create(['activated' => true]);
    $user->assignRole('admin');
    Sanctum::actingAs($user);

    return $user;
}

function actingAsObserver(): User
{
    $user = User::factory()->create(['activated' => true]);
    $user->assignRole('observer');
    Sanctum::actingAs($user);

    return $user;
}

describe('metrics', function () {
    it('returns a full metrics snapshot', function () {
        actingAsObserver();

        $this->getJson('/api/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cpu' => ['usage_percent', 'load_1', 'load_5', 'load_15', 'cores'],
                    'memory' => ['total', 'used', 'usage_percent', 'swap_total'],
                    'disks',
                    'network',
                    'system' => ['hostname', 'os', 'kernel', 'uptime_seconds'],
                    'timestamp',
                ],
            ]);
    });

    it('denies unauthenticated access', function () {
        $this->getJson('/api/metrics')->assertUnauthorized();
    });
});

describe('processes', function () {
    it('lists processes for an observer', function () {
        actingAsObserver();

        $response = $this->getJson('/api/processes')->assertOk();

        expect($response->json('data'))->not->toBeEmpty()
            ->and($response->json('data.0'))->toHaveKeys(['pid', 'user', 'cpu_percent', 'memory_percent', 'command']);
    });

    it('lets an admin kill a process and records an audit log', function () {
        actingAsAdmin();
        Process::fake();

        $this->postJson('/api/processes/kill', ['pid' => 99999, 'signal' => 'TERM'])
            ->assertOk();

        Process::assertRan(fn ($process) => $process->command === ['kill', '-s', 'TERM', '99999']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'process.kill',
            'target' => 'PID 99999',
            'status' => 'success',
        ]);
    });

    it('denies kill to an observer and audits the attempt', function () {
        actingAsObserver();
        Process::fake();

        $this->postJson('/api/processes/kill', ['pid' => 99999])->assertForbidden();

        Process::assertNothingRan();
        $this->assertDatabaseHas('audit_logs', ['action' => 'process.kill', 'status' => 'denied']);
    });

    it('rejects invalid PIDs', function () {
        actingAsAdmin();
        Process::fake();

        $this->postJson('/api/processes/kill', ['pid' => 1])->assertUnprocessable();
        $this->postJson('/api/processes/kill', ['pid' => '12; rm -rf /'])->assertUnprocessable();
        $this->postJson('/api/processes/kill', ['pid' => 1234, 'signal' => 'HUP'])->assertUnprocessable();

        Process::assertNothingRan();
    });
});

describe('lnmp services', function () {
    it('returns service statuses', function () {
        actingAsObserver();

        $response = $this->getJson('/api/lnmp')->assertOk();

        expect($response->json('data'))->toHaveCount(count(config('pulse.services')))
            ->and($response->json('data.0'))->toHaveKeys(['key', 'unit', 'active_state', 'running']);
    });

    it('lets an admin restart a whitelisted service', function () {
        actingAsAdmin();
        Process::fake();

        $this->postJson('/api/lnmp/action', ['service' => 'nginx', 'action' => 'restart'])
            ->assertOk();

        Process::assertRan(fn ($process) => $process->command === ['systemctl', 'restart', 'nginx']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service.restart',
            'target' => 'nginx',
            'status' => 'success',
        ]);
    });

    it('rejects non-whitelisted services and actions', function () {
        actingAsAdmin();
        Process::fake();

        $this->postJson('/api/lnmp/action', ['service' => 'sshd', 'action' => 'restart'])
            ->assertUnprocessable();
        $this->postJson('/api/lnmp/action', ['service' => 'nginx', 'action' => 'stop'])
            ->assertUnprocessable();
        $this->postJson('/api/lnmp/action', ['service' => 'nginx; reboot', 'action' => 'restart'])
            ->assertUnprocessable();

        Process::assertNothingRan();
    });

    it('denies service actions to an observer', function () {
        actingAsObserver();
        Process::fake();

        $this->postJson('/api/lnmp/action', ['service' => 'nginx', 'action' => 'restart'])
            ->assertForbidden();

        Process::assertNothingRan();
    });
});

describe('audit logs', function () {
    it('is readable by an admin', function () {
        actingAsAdmin();
        AuditLog::record('process.kill', 'PID 42');

        $this->getJson('/api/audit-logs')->assertOk();
    });

    it('is not writable via the API even for an admin', function () {
        actingAsAdmin();

        // Aucune route de mutation n'existe pour l'audit trail.
        $this->postJson('/api/audit-logs', ['action' => 'fake'])->assertStatus(405);
    });
});
