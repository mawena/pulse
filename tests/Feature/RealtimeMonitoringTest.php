<?php

use App\Events\MetricsUpdated;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function loginAs(string $role): User
{
    $user = User::factory()->create(['activated' => true]);
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

describe('system services', function () {
    it('lists all systemd services', function () {
        loginAs('observer');
        Process::fake([
            '*' => Process::result(json_encode([
                ['unit' => 'nginx.service', 'description' => 'nginx', 'load' => 'loaded', 'active' => 'active', 'sub' => 'running'],
                ['unit' => 'foo.service', 'description' => 'Foo', 'load' => 'loaded', 'active' => 'failed', 'sub' => 'failed'],
            ])),
        ]);

        $response = $this->getJson('/api/system-services')->assertOk();

        expect($response->json('data'))->toHaveCount(2)
            ->and($response->json('data.0.running'))->toBeTrue()
            ->and($response->json('data.1.failed'))->toBeTrue();
    });

    it('requires the read service permission', function () {
        Sanctum::actingAs(User::factory()->create(['activated' => true])); // aucun rôle

        $this->getJson('/api/system-services')->assertForbidden();
    });
});

describe('jobs monitoring', function () {
    it('returns pending and failed jobs with counts', function () {
        loginAs('admin');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\BrokenJob']),
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);

        $response = $this->getJson('/api/jobs')->assertOk();

        expect($response->json('data.counts.pending'))->toBe(1)
            ->and($response->json('data.counts.failed'))->toBe(1)
            ->and($response->json('data.pending.0.name'))->toBe('App\\Jobs\\TestJob')
            ->and($response->json('data.failed.0.name'))->toBe('App\\Jobs\\BrokenJob');
    });

    it('denies retry to an observer', function () {
        loginAs('observer');

        $this->postJson('/api/jobs/11111111-2222-3333-4444-555555555555/retry')
            ->assertForbidden();
    });

    it('rejects malformed uuids on retry', function () {
        loginAs('admin');

        $this->postJson('/api/jobs/not-a-uuid;rm/retry')->assertUnprocessable();
    });
});

describe('realtime broadcasting', function () {
    it('streams a metrics snapshot on the private metrics channel', function () {
        Event::fake([MetricsUpdated::class]);

        $this->artisan('pulse:stream', ['--once' => true, '--interval' => 1])
            ->assertSuccessful();

        Event::assertDispatched(MetricsUpdated::class, function (MetricsUpdated $event) {
            return $event->broadcastOn()->name === 'private-metrics'
                && array_key_exists('cpu', $event->snapshot);
        });
    });

    it('authorizes the metrics channel for observers only via permission', function () {
        $observer = User::factory()->create(['activated' => true]);
        $observer->assignRole('observer');
        $stranger = User::factory()->create(['activated' => true]); // aucun rôle

        expect($observer->hasPermissionTo('read', 'system'))->toBeTrue()
            ->and($stranger->hasPermissionTo('read', 'system'))->toBeFalse();
    });
});
