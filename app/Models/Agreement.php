<?php

namespace App\Models;

use App\Support\Dashboard\DashboardStatusCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'worker_id',
        'process_id',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function persistedStatusLabel(): string
    {
        return DashboardStatusCatalog::agreementStatusLabel($this->status);
    }

    public function suggestedStatus(?CarbonInterface $asOf = null): ?string
    {
        $asOf ??= now()->startOfDay();

        if (! DashboardStatusCatalog::shouldSuggestAgreementDelay($this->status)) {
            return null;
        }

        $hasOverdueInstallments = $this->relationLoaded('installments')
            ? $this->installments->contains(fn (Installment $installment) => $installment->is_active && $installment->derivedStatus($asOf) === 'vencido')
            : $this->installments()->overdue($asOf)->exists();

        return $hasOverdueInstallments ? 'com_atraso' : null;
    }

    public function suggestedStatusLabel(?CarbonInterface $asOf = null): ?string
    {
        $status = $this->suggestedStatus($asOf);

        return $status === null ? null : DashboardStatusCatalog::agreementStatusLabel($status);
    }

    public function operationalStatus(?CarbonInterface $asOf = null): string
    {
        return $this->suggestedStatus($asOf) ?? $this->status;
    }

    public function operationalStatusLabel(?CarbonInterface $asOf = null): string
    {
        return DashboardStatusCatalog::agreementStatusLabel($this->operationalStatus($asOf));
    }

    public function requiresStatusAttention(?CarbonInterface $asOf = null): bool
    {
        return $this->suggestedStatus($asOf) !== null;
    }

    public function scopeSearchDashboard(Builder $query, ?string $search): Builder
    {
        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term) {
            $like = '%'.$term.'%';

            $builder
                ->whereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))
                ->orWhereHas('worker', fn (Builder $worker) => $worker->where('name', 'like', $like))
                ->orWhereHas('process', fn (Builder $process) => $process->where('process_number', 'like', $like));
        });
    }

    public function scopeWithDelaySignals(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        $asOf ??= now()->startOfDay();

        return $query->where(function (Builder $builder) use ($asOf) {
            $builder
                ->whereIn('status', DashboardStatusCatalog::agreementDelayStatuses())
                ->orWhereHas('installments', fn (Builder $installment) => $installment
                    ->operational()
                    ->overdue($asOf)
                );
        });
    }

    public function scopeBreached(Builder $query): Builder
    {
        return $query->whereIn('status', DashboardStatusCatalog::agreementBreachedStatuses());
    }
}
