<?php

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Process;
use App\Models\User;
use App\Models\Worker;

test('agreement relates to client worker process and installments', function () {
    $client = Client::create(['name' => 'Cliente Base']);
    $worker = Worker::create(['name' => 'Trabalhadora Base']);
    $process = Process::create(['process_number' => '0001234-56.2026.5.00.0001']);

    $agreement = Agreement::create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'process_id' => $process->id,
        'total_amount' => 1500.00,
        'status' => 'ativo',
    ]);

    $installment = Installment::create([
        'agreement_id' => $agreement->id,
        'installment_number' => 1,
        'amount' => 500.00,
        'due_date' => '2026-05-10',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    expect($agreement->client->is($client))->toBeTrue()
        ->and($agreement->worker->is($worker))->toBeTrue()
        ->and($agreement->process->is($process))->toBeTrue()
        ->and($agreement->installments)->toHaveCount(1)
        ->and($agreement->installments->first()->is($installment))->toBeTrue();
});

test('audit log belongs to user', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $auditLog = AuditLog::create([
        'user_id' => $user->id,
        'action' => 'export_csv',
        'entity_type' => 'dashboard',
        'entity_id' => null,
        'created_at' => now(),
    ]);

    expect($auditLog->user->is($user))->toBeTrue()
        ->and($user->auditLogs)->toHaveCount(1);
});

test('agreement can be flagged by explicit status or overdue installment signal', function () {
    $explicitDelay = Agreement::factory()->create(['status' => 'com_atraso']);
    $derivedDelay = Agreement::factory()->create(['status' => 'ativo']);
    $breached = Agreement::factory()->create(['status' => 'descumprido']);

    Installment::factory()->for($derivedDelay)->create([
        'status' => 'em_dia',
        'due_date' => now()->subDays(10)->toDateString(),
        'paid_date' => null,
        'is_active' => true,
    ]);

    expect(Agreement::withDelaySignals()->pluck('id'))
        ->toContain($explicitDelay->id)
        ->toContain($derivedDelay->id)
        ->not->toContain($breached->id)
        ->and($derivedDelay->fresh()->status)->toBe('ativo');
});
