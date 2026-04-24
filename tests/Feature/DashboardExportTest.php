<?php

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Process;
use App\Models\User;
use App\Models\Worker;
use App\Support\Dashboard\DashboardStatusCatalog;
use Illuminate\Support\Carbon;

function agreementFixture(string $clientName, string $processNumber, string $status = 'ativo', float $totalAmount = 1000): Agreement
{
    return Agreement::factory()
        ->for(Client::factory()->state(['name' => $clientName]))
        ->for(Worker::factory()->state(['name' => $clientName.' Trabalhadora']))
        ->for(Process::factory()->state(['process_number' => $processNumber]))
        ->create([
            'status' => $status,
            'total_amount' => $totalAmount,
        ]);
}

function exportQuery(array $overrides = []): array
{
    return array_merge([
        'export_type' => DashboardStatusCatalog::EXPORT_INSTALLMENTS_OVERDUE,
        'selected_month' => '2026-05',
        'search' => '',
        'quick_filter' => DashboardStatusCatalog::FILTER_OVERDUE,
        'sort_by' => 'due_date_asc',
    ], $overrides);
}

test('guests cannot export dashboard csv', function () {
    $this->get(route('dashboard.export', exportQuery()))
        ->assertRedirect(route('login'));
});

test('exports the complete filtered due soon installments csv and audits the action', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    $agreement = agreementFixture('Cliente Exportavel', 'PROC-EXPORT-001');
    $excludedAgreement = agreementFixture('Cliente Fora', 'PROC-FORA-001');

    foreach (range(1, 16) as $number) {
        Installment::factory()->for($agreement)->create([
            'installment_number' => $number,
            'amount' => 100 + $number,
            'due_date' => now()->addDays(2)->toDateString(),
            'paid_date' => null,
            'status' => 'em_dia',
            'is_active' => true,
        ]);
    }

    Installment::factory()->for($excludedAgreement)->create([
        'installment_number' => 1,
        'amount' => 999,
        'due_date' => now()->addDays(2)->toDateString(),
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.export', exportQuery([
        'export_type' => DashboardStatusCatalog::EXPORT_INSTALLMENTS_DUE_SOON,
        'quick_filter' => DashboardStatusCatalog::FILTER_DUE_SOON,
        'search' => 'PROC-EXPORT-001',
        'sort_by' => 'amount_desc',
    ])));

    $response
        ->assertOk()
        ->assertDownload('dashboard-installments-due-soon-2026-05-10-1000.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->getContent();

    expect($csv)->toContain('cliente;trabalhador;processo;agreement_id;parcela;vencimento;valor;status_persistido;estado_operacional;data_pagamento;is_active')
        ->and(substr_count($csv, 'PROC-EXPORT-001'))->toBe(16)
        ->and($csv)->not->toContain('Cliente Fora');

    $auditLog = AuditLog::query()->where('action', 'dashboard_csv_exported')->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->user_id)->toBe($user->id)
        ->and($auditLog->context)->toMatchArray([
            'source' => 'dashboard_export',
            'export_type' => DashboardStatusCatalog::EXPORT_INSTALLMENTS_DUE_SOON,
            'selected_month' => '2026-05',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'search' => 'PROC-EXPORT-001',
            'quick_filter' => DashboardStatusCatalog::FILTER_DUE_SOON,
            'sort_by' => 'amount_desc',
            'row_count' => 16,
            'filename' => 'dashboard-installments-due-soon-2026-05-10-1000.csv',
            'uses_due_date_period' => false,
            'includes_derived_status' => true,
            'delimiter' => ';',
            'money_format' => 'decimal_dot',
        ]);

    Carbon::setTestNow();
});

test('exports paid installments only from the selected due date month', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    $mayAgreement = agreementFixture('Cliente Maio', 'PROC-MAY-001', 'finalizado');
    $juneAgreement = agreementFixture('Cliente Junho', 'PROC-JUNE-001', 'finalizado');

    Installment::factory()->for($mayAgreement)->create([
        'installment_number' => 1,
        'amount' => 800,
        'due_date' => '2026-05-06',
        'paid_date' => '2026-05-07',
        'status' => 'pago_com_atraso',
        'is_active' => true,
    ]);

    Installment::factory()->for($juneAgreement)->create([
        'installment_number' => 1,
        'amount' => 900,
        'due_date' => '2026-06-06',
        'paid_date' => '2026-06-07',
        'status' => 'pago_com_atraso',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.export', exportQuery([
        'export_type' => DashboardStatusCatalog::EXPORT_INSTALLMENTS_PAID_MONTH,
        'quick_filter' => DashboardStatusCatalog::FILTER_PAID_THIS_MONTH,
        'selected_month' => '2026-05',
    ])));

    $csv = $response->assertOk()->getContent();

    expect($csv)->toContain('Cliente Maio')
        ->and($csv)->toContain('pago com atraso')
        ->and($csv)->not->toContain('Cliente Junho')
        ->and(AuditLog::query()->first()->context['uses_due_date_period'])->toBeTrue();

    Carbon::setTestNow();
});

test('exports delay agreements without mutating agreement status', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    $activeAgreement = agreementFixture('Cliente Ativo Atrasado', 'PROC-ACTIVE-001', 'ativo', 3000);
    $explicitDelayAgreement = agreementFixture('Cliente Status Atraso', 'PROC-DELAY-001', 'com_atraso', 4000);

    Installment::factory()->for($activeAgreement)->create([
        'installment_number' => 1,
        'amount' => 1200,
        'due_date' => '2026-05-01',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Installment::factory()->for($explicitDelayAgreement)->create([
        'installment_number' => 1,
        'amount' => 1500,
        'due_date' => '2026-05-02',
        'paid_date' => null,
        'status' => 'vencido',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.export', exportQuery([
        'export_type' => DashboardStatusCatalog::EXPORT_AGREEMENTS_WITH_DELAY,
    ])));

    $csv = $response->assertOk()->getContent();

    expect($csv)->toContain('cliente;trabalhador;processo;agreement_id;valor_total;status_persistido;status_sugerido;requer_atencao_status;data_referencia;valor_relacionado')
        ->and($csv)->toContain('Cliente Ativo Atrasado')
        ->and($csv)->toContain('Cliente Status Atraso')
        ->and($csv)->toContain('com atraso')
        ->and($activeAgreement->fresh()->status)->toBe('ativo')
        ->and($explicitDelayAgreement->fresh()->status)->toBe('com_atraso');

    expect(AuditLog::query()->first()->context['row_count'])->toBe(2);

    Carbon::setTestNow();
});

test('exports breached agreements only when the global overdue filter is active', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    agreementFixture('Cliente Descumprido', 'PROC-BREACHED-001', 'descumprido');
    agreementFixture('Cliente Encerrado', 'PROC-CLOSED-001', 'encerrado_sem_quitacao');
    agreementFixture('Cliente Ativo', 'PROC-ACTIVE-002', 'ativo');

    $response = $this->actingAs($user)->get(route('dashboard.export', exportQuery([
        'export_type' => DashboardStatusCatalog::EXPORT_BREACHED_AGREEMENTS,
    ])));

    $csv = $response->assertOk()->getContent();

    expect($csv)->toContain('Cliente Descumprido')
        ->and($csv)->toContain('Cliente Encerrado')
        ->and($csv)->not->toContain('Cliente Ativo')
        ->and(AuditLog::query()->first()->context['row_count'])->toBe(2);

    $emptyResponse = $this->actingAs($user)->get(route('dashboard.export', exportQuery([
        'export_type' => DashboardStatusCatalog::EXPORT_BREACHED_AGREEMENTS,
        'quick_filter' => DashboardStatusCatalog::FILTER_DUE_SOON,
    ])));

    $emptyCsv = $emptyResponse->assertOk()->getContent();

    expect($emptyCsv)->toContain('cliente;trabalhador;processo;agreement_id;valor_total;status_persistido;updated_at')
        ->and(substr_count(trim($emptyCsv), PHP_EOL))->toBe(0);

    Carbon::setTestNow();
});

test('rejects unknown dashboard export types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.export', exportQuery(['export_type' => 'ranking'])))
        ->assertSessionHasErrors('export_type');
});
