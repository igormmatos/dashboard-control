<?php

use App\Livewire\Dashboard\Foundation;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Process;
use App\Models\User;
use App\Models\Worker;
use App\Support\Dashboard\DashboardStatusCatalog;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('Dashboard Operacional')
        ->assertSee('Busca rápida')
        ->assertSee('Lista operacional');
});

test('dashboard livewire shell renders expected sections', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Foundation::class)
        ->assertSee('Parcelas do mês')
        ->assertSee('Valor total em atraso')
        ->assertSee('Acordos descumpridos');
});

test('dashboard month cards, filters and lists use due_date and operational statuses', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    $this->actingAs($user);

    $overdueAgreement = Agreement::factory()->create([
        'status' => 'ativo',
        'total_amount' => 5000,
    ]);

    $dueSoonAgreement = Agreement::factory()->create([
        'status' => 'ativo',
        'total_amount' => 2800,
    ]);

    $breachedAgreement = Agreement::factory()->create([
        'status' => 'descumprido',
        'total_amount' => 8200,
    ]);

    $paidAgreement = Agreement::factory()->create([
        'status' => 'finalizado',
        'total_amount' => 1600,
    ]);

    $agreementMadeAgreement = Agreement::factory()->create([
        'status' => 'ativo',
        'total_amount' => 700,
    ]);

    Installment::factory()->for($overdueAgreement)->create([
        'installment_number' => 1,
        'amount' => 1100,
        'due_date' => '2026-05-04',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Installment::factory()->for($dueSoonAgreement)->create([
        'installment_number' => 1,
        'amount' => 900,
        'due_date' => '2026-05-12',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Installment::factory()->for($paidAgreement)->create([
        'installment_number' => 1,
        'amount' => 800,
        'due_date' => '2026-05-06',
        'paid_date' => '2026-05-07',
        'status' => 'pago_com_atraso',
        'is_active' => true,
    ]);

    Installment::factory()->for($breachedAgreement)->create([
        'installment_number' => 1,
        'amount' => 1700,
        'due_date' => '2026-04-20',
        'paid_date' => null,
        'status' => 'vencido',
        'is_active' => true,
    ]);

    Installment::factory()->for($agreementMadeAgreement)->agreementMade()->create([
        'installment_number' => 1,
        'amount' => 700,
        'due_date' => '2026-05-09',
    ]);

    Livewire::test(Foundation::class)
        ->set('selectedMonth', '2026-05')
        ->assertSee('Parcelas do mês')
        ->assertSee('R$ 1.700,00')
        ->assertSee('Pagas no mês')
        ->assertSee('R$ 800,00')
        ->assertSee('Valor total em atraso')
        ->assertSee('R$ 2.800,00')
        ->assertSee($overdueAgreement->client->name)
        ->assertSee($breachedAgreement->client->name)
        ->assertSee('Sugestão: com atraso')
        ->assertSee('vencido')
        ->assertDontSee($agreementMadeAgreement->process->process_number)
        ->call('setQuickFilter', 'a_vencer')
        ->assertSee($dueSoonAgreement->client->name)
        ->assertDontSee($breachedAgreement->client->name)
        ->call('setQuickFilter', 'pagas_mes')
        ->assertSee($paidAgreement->client->name)
        ->assertSee('pago com atraso')
        ->set('search', $dueSoonAgreement->process->process_number)
        ->call('setQuickFilter', 'a_vencer')
        ->assertSee($dueSoonAgreement->process->process_number)
        ->assertDontSee($overdueAgreement->process->process_number);

    Carbon::setTestNow();
});

test('dashboard list can be ordered by highest amount', function () {
    Carbon::setTestNow('2026-05-10 10:00:00');

    $user = User::factory()->create();
    $this->actingAs($user);

    $agreementA = Agreement::factory()->create(['status' => 'com_atraso']);
    $agreementB = Agreement::factory()->create(['status' => 'com_atraso']);

    $agreementA->client->update(['name' => 'Cliente A']);
    $agreementB->client->update(['name' => 'Cliente B']);

    Installment::factory()->for($agreementA)->create([
        'installment_number' => 1,
        'amount' => 500,
        'due_date' => '2026-05-01',
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Installment::factory()->for($agreementB)->create([
        'installment_number' => 1,
        'amount' => 1500,
        'due_date' => '2026-05-01',
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Livewire::test(Foundation::class)
        ->set('sortBy', 'amount_desc')
        ->assertSeeInOrder([
            'Cliente B',
            'Cliente A',
        ]);

    Carbon::setTestNow();
});

test('dashboard operational lists use real pagination with filters search and ordering', function () {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (range(1, 12) as $number) {
        $agreement = Agreement::factory()
            ->for(Client::factory()->state(['name' => sprintf('Cliente Pagina %02d', $number)]))
            ->for(Worker::factory()->state(['name' => sprintf('Trabalhadora Pagina %02d', $number)]))
            ->for(Process::factory()->state(['process_number' => sprintf('PROC-PAGE-%02d', $number)]))
            ->create(['status' => 'ativo']);

        Installment::factory()->for($agreement)->create([
            'installment_number' => 1,
            'amount' => 100 + $number,
            'due_date' => sprintf('2026-05-%02d', $number),
            'paid_date' => null,
            'status' => 'em_dia',
            'is_active' => true,
        ]);
    }

    $component = Livewire::test(Foundation::class)
        ->set('selectedMonth', '2026-05')
        ->set('sortBy', 'due_date_asc')
        ->assertSee('Cliente Pagina 01')
        ->assertSee('Cliente Pagina 10')
        ->assertDontSee('Cliente Pagina 11')
        ->call('setPage', 2, 'installments-page')
        ->assertSee('Cliente Pagina 11')
        ->assertSee('Cliente Pagina 12');

    $component
        ->set('search', 'PROC-PAGE-12')
        ->assertSee('Cliente Pagina 12')
        ->assertDontSee('Cliente Pagina 11');

    $component
        ->set('search', '')
        ->set('sortBy', 'amount_desc')
        ->assertSeeInOrder([
            'Cliente Pagina 12',
            'Cliente Pagina 11',
        ]);

    Carbon::setTestNow();
});

test('dashboard resets paginated lists when quick filter changes', function () {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (range(1, 12) as $number) {
        $agreement = Agreement::factory()
            ->for(Client::factory()->state(['name' => sprintf('Cliente Atraso %02d', $number)]))
            ->for(Process::factory()->state(['process_number' => sprintf('PROC-OVERDUE-%02d', $number)]))
            ->create(['status' => 'ativo']);

        Installment::factory()->for($agreement)->create([
            'installment_number' => 1,
            'amount' => 100 + $number,
            'due_date' => sprintf('2026-05-%02d', $number),
            'paid_date' => null,
            'status' => 'em_dia',
            'is_active' => true,
        ]);
    }

    $dueSoonAgreement = Agreement::factory()
        ->for(Client::factory()->state(['name' => 'Cliente A Vencer Reset']))
        ->for(Process::factory()->state(['process_number' => 'PROC-DUE-SOON-RESET']))
        ->create(['status' => 'ativo']);

    Installment::factory()->for($dueSoonAgreement)->create([
        'installment_number' => 1,
        'amount' => 900,
        'due_date' => '2026-05-22',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Livewire::test(Foundation::class)
        ->call('setPage', 2, 'installments-page')
        ->assertSee('Cliente Atraso 11')
        ->call('setQuickFilter', DashboardStatusCatalog::FILTER_DUE_SOON)
        ->assertSee('Cliente A Vencer Reset')
        ->assertDontSee('Cliente Atraso 11');

    Carbon::setTestNow();
});

test('rf015 reflects overdue transition by derived query state without mutating agreement status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $agreement = Agreement::factory()
        ->for(Client::factory()->state(['name' => 'Cliente Virada RF015']))
        ->for(Process::factory()->state(['process_number' => 'PROC-RF015-001']))
        ->create(['status' => 'ativo']);

    Installment::factory()->for($agreement)->create([
        'installment_number' => 1,
        'amount' => 700,
        'due_date' => '2026-05-11',
        'paid_date' => null,
        'status' => 'em_dia',
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-05-10 10:00:00');

    Livewire::test(Foundation::class)
        ->call('setQuickFilter', DashboardStatusCatalog::FILTER_DUE_SOON)
        ->assertSee('Cliente Virada RF015')
        ->assertSee('em dia')
        ->assertDontSee('Sugestão: com atraso');

    Carbon::setTestNow('2026-05-12 10:00:00');

    Livewire::test(Foundation::class)
        ->call('setQuickFilter', DashboardStatusCatalog::FILTER_OVERDUE)
        ->assertSee('Cliente Virada RF015')
        ->assertSee('vencido')
        ->assertSee('Sugestão: com atraso');

    expect($agreement->fresh()->status)->toBe('ativo');

    Carbon::setTestNow();
});
