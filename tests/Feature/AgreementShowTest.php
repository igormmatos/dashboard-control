<?php

use App\Livewire\Agreements\Show;
use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Installment;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can view agreement detail', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->create([
        'status' => 'ativo',
    ]);

    Installment::factory()->for($agreement)->create([
        'installment_number' => 1,
        'due_date' => now()->subDays(2)->toDateString(),
        'status' => 'em_dia',
    ]);

    $this->actingAs($user);

    $this->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertSee($agreement->client->name)
        ->assertSee($agreement->process->process_number)
        ->assertSee('Parcelas do acordo')
        ->assertSee('Status persistido')
        ->assertSee('Estado operacional derivado')
        ->assertSee('ativo')
        ->assertSee('Sugestão operacional')
        ->assertSee('com atraso')
        ->assertSee('Alteração manual de status')
        ->assertSee('em dia')
        ->assertSee('vencido');

    expect($agreement->fresh()->status)->toBe('ativo');
});

test('authenticated users can manually update agreement status and audit the change', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->active()->create();

    Installment::factory()->for($agreement)->overdue()->create([
        'status' => 'em_dia',
    ]);

    Livewire::actingAs($user)
        ->test(Show::class, ['agreement' => $agreement])
        ->assertSet('selectedStatus', 'ativo')
        ->set('selectedStatus', 'com_atraso')
        ->call('updateStatus')
        ->assertHasNoErrors()
        ->assertSee('Status do acordo atualizado manualmente.');

    $agreement->refresh();

    expect($agreement->status)->toBe('com_atraso')
        ->and($agreement->installments()->first()->status)->toBe('em_dia');

    $auditLog = AuditLog::query()
        ->where('action', 'agreement_status_updated')
        ->where('entity_type', Agreement::class)
        ->where('entity_id', $agreement->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->user_id)->toBe($user->id)
        ->and($auditLog->context)->toMatchArray([
            'old_status' => 'ativo',
            'new_status' => 'com_atraso',
            'source' => 'agreement_detail',
        ]);
});

test('manual agreement status update rejects invalid statuses without audit', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->active()->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['agreement' => $agreement])
        ->set('selectedStatus', 'cancelado')
        ->call('updateStatus')
        ->assertHasErrors(['selectedStatus' => ['in']]);

    expect($agreement->fresh()->status)->toBe('ativo')
        ->and(AuditLog::query()->where('entity_type', Agreement::class)->where('entity_id', $agreement->id)->exists())->toBeFalse();
});
