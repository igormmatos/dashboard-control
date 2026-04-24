<?php

namespace App\Livewire\Dashboard;

use App\Support\Dashboard\DashboardQueries;
use App\Support\Dashboard\DashboardStatusCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Foundation extends Component
{
    use WithPagination;

    private const INSTALLMENTS_PAGE = 'installments-page';

    private const AGREEMENTS_PAGE = 'agreements-page';

    private const BREACHED_PAGE = 'breached-page';

    private const INSTALLMENTS_PER_PAGE = 10;

    private const AGREEMENTS_PER_PAGE = 5;

    private const BREACHED_PER_PAGE = 5;

    public string $search = '';

    public string $selectedMonth;

    public string $quickFilter = DashboardStatusCatalog::FILTER_OVERDUE;

    public string $sortBy = 'due_date_asc';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
    }

    public function setQuickFilter(string $filter): void
    {
        if (! array_key_exists($filter, DashboardStatusCatalog::quickFilterLabels())) {
            return;
        }

        $this->quickFilter = $filter;
        $this->resetDashboardPages();
    }

    public function updatedSearch(): void
    {
        $this->resetDashboardPages();
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetDashboardPages();
    }

    public function updatedSortBy(): void
    {
        $this->resetDashboardPages();
    }

    public function render(): View
    {
        $dashboard = app(DashboardQueries::class);
        $agreementPanel = $dashboard->agreementPanel(
            selectedMonth: $this->selectedMonth,
            search: $this->search,
            quickFilter: $this->quickFilter,
            perPage: self::AGREEMENTS_PER_PAGE,
            pageName: self::AGREEMENTS_PAGE,
        );
        $exportFilters = [
            'selected_month' => $this->selectedMonth,
            'search' => $this->search,
            'quick_filter' => $this->quickFilter,
            'sort_by' => $this->sortBy,
        ];

        return view('livewire.dashboard.foundation', [
            'asOf' => now()->startOfDay(),
            'operationalRules' => DashboardStatusCatalog::operationalRules(),
            'quickFilterLabels' => DashboardStatusCatalog::quickFilterLabels(),
            'cards' => $dashboard->cards($this->selectedMonth, $this->search),
            'quickFilterCounts' => $dashboard->quickFilterCounts($this->selectedMonth, $this->search),
            'installments' => $dashboard->filteredInstallments(
                selectedMonth: $this->selectedMonth,
                search: $this->search,
                quickFilter: $this->quickFilter,
                sortBy: $this->sortBy,
                perPage: self::INSTALLMENTS_PER_PAGE,
                pageName: self::INSTALLMENTS_PAGE,
            ),
            'agreementPanel' => $agreementPanel,
            'breachedAgreements' => $dashboard->breachedAgreements(
                selectedMonth: $this->selectedMonth,
                search: $this->search,
                quickFilter: $this->quickFilter,
                perPage: self::BREACHED_PER_PAGE,
                pageName: self::BREACHED_PAGE,
            ),
            'installmentsExportUrl' => route('dashboard.export', [
                ...$exportFilters,
                'export_type' => DashboardStatusCatalog::exportTypeForQuickFilter($this->quickFilter),
            ]),
            'delayAgreementsExportUrl' => route('dashboard.export', [
                ...$exportFilters,
                'export_type' => DashboardStatusCatalog::EXPORT_AGREEMENTS_WITH_DELAY,
            ]),
            'breachedAgreementsExportUrl' => route('dashboard.export', [
                ...$exportFilters,
                'export_type' => DashboardStatusCatalog::EXPORT_BREACHED_AGREEMENTS,
            ]),
        ])
            ->layout('layouts.app', ['title' => __('Dashboard')]);
    }

    private function resetDashboardPages(): void
    {
        $this->resetPage(pageName: self::INSTALLMENTS_PAGE);
        $this->resetPage(pageName: self::AGREEMENTS_PAGE);
        $this->resetPage(pageName: self::BREACHED_PAGE);
    }
}
