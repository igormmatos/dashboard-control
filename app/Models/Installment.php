<?php

namespace App\Models;

use App\Support\Dashboard\DashboardStatusCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function persistedStatusLabel(): string
    {
        return DashboardStatusCatalog::installmentStatusLabel($this->status);
    }

    public function derivedStatus(?CarbonInterface $asOf = null): string
    {
        $asOf ??= now()->startOfDay();

        if ($this->status === 'acordo_feito') {
            return 'acordo_feito';
        }

        if ($this->paid_date !== null) {
            if ($this->due_date !== null && $this->paid_date->gt($this->due_date)) {
                return 'pago_com_atraso';
            }

            return 'pago_em_dia';
        }

        if ($this->due_date !== null && $this->due_date->lt($asOf)) {
            return 'vencido';
        }

        return 'em_dia';
    }

    public function derivedStatusLabel(?CarbonInterface $asOf = null): string
    {
        return DashboardStatusCatalog::installmentStatusLabel($this->derivedStatus($asOf));
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
                ->whereHas('agreement.client', fn (Builder $client) => $client->where('name', 'like', $like))
                ->orWhereHas('agreement.worker', fn (Builder $worker) => $worker->where('name', 'like', $like))
                ->orWhereHas('agreement.process', fn (Builder $process) => $process->where('process_number', 'like', $like));
        });
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotIn('status', DashboardStatusCatalog::installmentDeferredStatuses());
    }

    public function scopeForMonthDueDate(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->whereIn('status', DashboardStatusCatalog::installmentPaidStatuses())
                ->orWhereNotNull('paid_date');
        });
    }

    public function scopeUnpaidOperational(Builder $query): Builder
    {
        return $query
            ->operational()
            ->whereNull('paid_date')
            ->whereNotIn('status', DashboardStatusCatalog::installmentPaidStatuses());
    }

    public function scopeOverdue(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        $asOf ??= now()->startOfDay();

        return $query
            ->unpaidOperational()
            ->whereDate('due_date', '<', $asOf->toDateString());
    }

    public function scopeDueSoon(Builder $query, ?CarbonInterface $asOf = null, int $days = 3): Builder
    {
        $asOf ??= now()->startOfDay();
        $limit = $asOf->copy()->addDays($days);

        return $query
            ->unpaidOperational()
            ->whereBetween('due_date', [$asOf->toDateString(), $limit->toDateString()]);
    }
}
