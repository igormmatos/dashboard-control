<?php

namespace App\Livewire\Agreements;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Support\Dashboard\DashboardStatusCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public Agreement $agreement;

    public string $selectedStatus = '';

    public function mount(Agreement $agreement): void
    {
        $this->agreement = $agreement->load([
            'client',
            'worker',
            'process',
            'installments' => fn ($query) => $query->orderBy('installment_number'),
        ]);

        $this->selectedStatus = $this->agreement->status;
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'selectedStatus' => ['required', 'string', Rule::in(DashboardStatusCatalog::agreementStatuses())],
        ]);

        $newStatus = $validated['selectedStatus'];
        $oldStatus = $this->agreement->status;

        if ($newStatus === $oldStatus) {
            session()->flash('status', __('Status do acordo mantido sem alterações.'));

            return;
        }

        DB::transaction(function () use ($oldStatus, $newStatus): void {
            $this->agreement->forceFill([
                'status' => $newStatus,
            ])->save();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'agreement_status_updated',
                'entity_type' => Agreement::class,
                'entity_id' => $this->agreement->id,
                'context' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'source' => 'agreement_detail',
                ],
                'created_at' => now(),
            ]);
        });

        $this->agreement = $this->agreement->fresh([
            'client',
            'worker',
            'process',
            'installments' => fn ($query) => $query->orderBy('installment_number'),
        ]);
        $this->selectedStatus = $this->agreement->status;

        session()->flash('status', __('Status do acordo atualizado manualmente.'));
    }

    public function render(): View
    {
        $asOf = now()->startOfDay();

        return view('livewire.agreements.show', [
            'asOf' => $asOf,
            'operationalRules' => DashboardStatusCatalog::operationalRules(),
            'agreementStatuses' => DashboardStatusCatalog::agreementStatuses(),
            'operationalStatusLabel' => $this->agreement->operationalStatusLabel($asOf),
            'suggestedStatus' => $this->agreement->suggestedStatus($asOf),
            'suggestedStatusLabel' => $this->agreement->suggestedStatusLabel($asOf),
        ])
            ->layout('layouts.app', ['title' => __('Detalhe do Acordo')]);
    }
}
