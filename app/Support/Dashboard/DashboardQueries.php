<?php

namespace App\Support\Dashboard;

use App\Models\Agreement;
use App\Models\Installment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardQueries
{
    public function agreementPanel(
        string $selectedMonth,
        ?string $search = null,
        string $quickFilter = DashboardStatusCatalog::FILTER_OVERDUE,
        int $perPage = 5,
        string $pageName = 'agreements-page',
    ): array {
        return match ($quickFilter) {
            DashboardStatusCatalog::FILTER_DUE_SOON => $this->dueSoonAgreementPanel($search, $perPage, $pageName),
            DashboardStatusCatalog::FILTER_PAID_THIS_MONTH => $this->paidThisMonthAgreementPanel($selectedMonth, $search, $perPage, $pageName),
            default => $this->overdueAgreementPanel($search, $perPage, $pageName),
        };
    }

    public function cards(string $selectedMonth, ?string $search = null): array
    {
        [$start, $end] = $this->monthBounds($selectedMonth);

        $dueThisMonth = Installment::query()
            ->searchDashboard($search)
            ->unpaidOperational()
            ->forMonthDueDate($start, $end);

        $paidThisMonth = Installment::query()
            ->searchDashboard($search)
            ->operational()
            ->paid()
            ->forMonthDueDate($start, $end);

        $overdue = Installment::query()
            ->searchDashboard($search)
            ->overdue($this->today());

        return [
            'due_this_month' => [
                'label' => 'Parcelas do mês',
                'count' => (clone $dueThisMonth)->count(),
                'amount' => (float) (clone $dueThisMonth)->sum('amount'),
            ],
            'paid_this_month' => [
                'label' => 'Pagas no mês',
                'count' => (clone $paidThisMonth)->count(),
                'amount' => (float) (clone $paidThisMonth)->sum('amount'),
            ],
            'overdue_total' => [
                'label' => 'Valor total em atraso',
                'count' => (clone $overdue)->count(),
                'amount' => (float) (clone $overdue)->sum('amount'),
            ],
        ];
    }

    public function quickFilterCounts(string $selectedMonth, ?string $search = null): array
    {
        [$start, $end] = $this->monthBounds($selectedMonth);
        $today = $this->today();

        return [
            DashboardStatusCatalog::FILTER_DUE_SOON => Installment::query()
                ->searchDashboard($search)
                ->dueSoon($today)
                ->count(),
            DashboardStatusCatalog::FILTER_OVERDUE => Installment::query()
                ->searchDashboard($search)
                ->overdue($today)
                ->count(),
            DashboardStatusCatalog::FILTER_PAID_THIS_MONTH => Installment::query()
                ->searchDashboard($search)
                ->operational()
                ->paid()
                ->forMonthDueDate($start, $end)
                ->count(),
        ];
    }

    public function filteredInstallments(
        string $selectedMonth,
        ?string $search = null,
        string $quickFilter = DashboardStatusCatalog::FILTER_OVERDUE,
        string $sortBy = 'due_date_asc',
        int $perPage = 10,
        string $pageName = 'installments-page',
    ): LengthAwarePaginator {
        return $this->installmentsExportQuery(
            exportType: DashboardStatusCatalog::exportTypeForQuickFilter($quickFilter),
            selectedMonth: $selectedMonth,
            search: $search,
            sortBy: $sortBy,
        )->paginate($perPage, ['*'], $pageName);
    }

    public function exportInstallments(
        string $exportType,
        string $selectedMonth,
        ?string $search = null,
        string $sortBy = 'due_date_asc',
    ): Collection {
        return $this->installmentsExportQuery($exportType, $selectedMonth, $search, $sortBy)->get();
    }

    public function breachedAgreements(
        string $selectedMonth,
        ?string $search = null,
        string $quickFilter = DashboardStatusCatalog::FILTER_OVERDUE,
        int $perPage = 5,
        string $pageName = 'breached-page',
    ): LengthAwarePaginator {
        if ($quickFilter !== DashboardStatusCatalog::FILTER_OVERDUE) {
            return $this->emptyPaginator($perPage, $pageName);
        }

        return $this->breachedAgreementsQuery($search)->paginate($perPage, ['*'], $pageName);
    }

    public function exportDelayAgreements(?string $search = null): Collection
    {
        return $this->decorateAgreements($this->delayAgreementsExportQuery($search)->get());
    }

    public function exportBreachedAgreements(
        ?string $search = null,
        string $quickFilter = DashboardStatusCatalog::FILTER_OVERDUE,
    ): Collection {
        if ($quickFilter !== DashboardStatusCatalog::FILTER_OVERDUE) {
            return collect();
        }

        return $this->breachedAgreementsQuery($search)->get();
    }

    private function overdueAgreementPanel(?string $search = null, int $perPage = 5, string $pageName = 'agreements-page'): array
    {
        $agreements = $this->delayAgreementsExportQuery($search)
            ->paginate($perPage, ['*'], $pageName)
            ->through(fn (Agreement $agreement): Agreement => $this->decorateAgreement($agreement));

        return [
            'title' => 'Acordos com atraso',
            'description' => 'RF007 usa estratégia híbrida: o acordo pode já estar com_atraso ou receber sugestão operacional de mudança quando existir parcela vencida.',
            'agreements' => $agreements,
        ];
    }

    private function dueSoonAgreementPanel(?string $search = null, int $perPage = 5, string $pageName = 'agreements-page'): array
    {
        $agreements = Agreement::query()
            ->with(['client', 'worker', 'process'])
            ->withMin([
                'installments as reference_due_date' => fn ($query) => $query->dueSoon($this->today()),
            ], 'due_date')
            ->withSum([
                'installments as matched_amount' => fn ($query) => $query->dueSoon($this->today()),
            ], 'amount')
            ->searchDashboard($search)
            ->whereHas('installments', fn ($query) => $query->dueSoon($this->today()))
            ->orderBy('reference_due_date')
            ->orderByDesc('matched_amount')
            ->orderBy('id')
            ->paginate($perPage, ['*'], $pageName)
            ->through(fn (Agreement $agreement): Agreement => $this->decorateAgreement($agreement));

        return [
            'title' => 'Acordos com parcelas a vencer',
            'description' => 'RF013 aplica o filtro rápido globalmente; aqui entram acordos com parcelas vencendo em até 3 dias.',
            'agreements' => $agreements,
        ];
    }

    private function paidThisMonthAgreementPanel(
        string $selectedMonth,
        ?string $search = null,
        int $perPage = 5,
        string $pageName = 'agreements-page',
    ): array {
        [$start, $end] = $this->monthBounds($selectedMonth);

        $paidInstallments = fn ($query) => $query
            ->operational()
            ->paid()
            ->forMonthDueDate($start, $end);

        $agreements = Agreement::query()
            ->with(['client', 'worker', 'process'])
            ->withMax([
                'installments as reference_due_date' => $paidInstallments,
            ], 'due_date')
            ->withSum([
                'installments as matched_amount' => $paidInstallments,
            ], 'amount')
            ->searchDashboard($search)
            ->whereHas('installments', $paidInstallments)
            ->orderByDesc('matched_amount')
            ->orderByDesc('reference_due_date')
            ->orderBy('id')
            ->paginate($perPage, ['*'], $pageName)
            ->through(fn (Agreement $agreement): Agreement => $this->decorateAgreement($agreement));

        return [
            'title' => 'Acordos com parcelas pagas no mês',
            'description' => 'RF013 aplica o filtro rápido globalmente; aqui entram acordos com parcelas pagas dentro do mês filtrado por due_date.',
            'agreements' => $agreements,
        ];
    }

    private function decorateAgreements(Collection $agreements): Collection
    {
        return $agreements->each(fn (Agreement $agreement) => $this->decorateAgreement($agreement));
    }

    private function decorateAgreement(Agreement $agreement): Agreement
    {
        $asOf = $this->today();

        $agreement->setAttribute('status_label', $agreement->persistedStatusLabel());
        $agreement->setAttribute('suggested_status', $agreement->suggestedStatus($asOf));
        $agreement->setAttribute('suggested_status_label', $agreement->suggestedStatusLabel($asOf));
        $agreement->setAttribute('requires_status_attention', $agreement->requiresStatusAttention($asOf));
        $agreement->setAttribute('matched_amount', (float) ($agreement->matched_amount ?? $agreement->overdue_amount ?? 0));

        return $agreement;
    }

    private function installmentsExportQuery(
        string $exportType,
        string $selectedMonth,
        ?string $search = null,
        string $sortBy = 'due_date_asc',
    ): Builder {
        [$start, $end] = $this->monthBounds($selectedMonth);
        $today = $this->today();

        $query = Installment::query()
            ->with(['agreement.client', 'agreement.worker', 'agreement.process'])
            ->searchDashboard($search);

        match ($exportType) {
            DashboardStatusCatalog::EXPORT_INSTALLMENTS_DUE_SOON => $query->dueSoon($today),
            DashboardStatusCatalog::EXPORT_INSTALLMENTS_PAID_MONTH => $query->operational()->paid()->forMonthDueDate($start, $end),
            default => $query->overdue($today),
        };

        if ($sortBy === 'amount_desc') {
            $query->orderByDesc('amount')->orderBy('due_date')->orderBy('id');
        } else {
            $query->orderBy('due_date')->orderByDesc('amount')->orderBy('id');
        }

        return $query;
    }

    private function delayAgreementsExportQuery(?string $search = null): Builder
    {
        return Agreement::query()
            ->with(['client', 'worker', 'process'])
            ->withExists([
                'installments as has_overdue_installments' => fn ($query) => $query->overdue($this->today()),
            ])
            ->withSum([
                'installments as overdue_amount' => fn ($query) => $query->overdue($this->today()),
            ], 'amount')
            ->withMin([
                'installments as reference_due_date' => fn ($query) => $query->overdue($this->today()),
            ], 'due_date')
            ->searchDashboard($search)
            ->withDelaySignals($this->today())
            ->orderByRaw('reference_due_date is null')
            ->orderBy('reference_due_date')
            ->orderByDesc('overdue_amount')
            ->orderBy('id');
    }

    private function breachedAgreementsQuery(?string $search = null): Builder
    {
        return Agreement::query()
            ->with(['client', 'worker', 'process'])
            ->searchDashboard($search)
            ->breached()
            ->orderByDesc('updated_at')
            ->orderByDesc('total_amount')
            ->orderBy('id');
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => $pageName,
            ],
        );
    }

    private function monthBounds(string $selectedMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    private function today(): CarbonInterface
    {
        return now()->startOfDay();
    }
}
