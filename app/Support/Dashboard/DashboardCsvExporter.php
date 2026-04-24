<?php

namespace App\Support\Dashboard;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardCsvExporter
{
    public const DELIMITER = ';';

    public function __construct(private readonly DashboardQueries $dashboardQueries) {}

    public function download(array $filters, User $user): Response
    {
        $filters = $this->normalizeFilters($filters);
        $export = $this->build($filters);
        $exportedAt = now();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'dashboard_csv_exported',
            'entity_type' => 'dashboard',
            'entity_id' => null,
            'context' => [
                'source' => 'dashboard_export',
                'export_type' => $filters['export_type'],
                'export_label' => DashboardStatusCatalog::exportTypeLabel($filters['export_type']),
                'selected_month' => $filters['selected_month'],
                'period_start' => $export['period_start'],
                'period_end' => $export['period_end'],
                'search' => $filters['search'],
                'quick_filter' => $filters['quick_filter'],
                'sort_by' => $filters['sort_by'],
                'row_count' => $export['row_count'],
                'filename' => $export['filename'],
                'exported_at' => $exportedAt->toISOString(),
                'uses_due_date_period' => $export['uses_due_date_period'],
                'includes_derived_status' => $export['includes_derived_status'],
                'delimiter' => self::DELIMITER,
                'money_format' => 'decimal_dot',
            ],
            'created_at' => $exportedAt,
        ]);

        return response($export['csv'])
            ->withHeaders([
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
    }

    /**
     * @return array{csv: string, filename: string, row_count: int, period_start: string, period_end: string, uses_due_date_period: bool, includes_derived_status: bool}
     */
    public function build(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        [$periodStart, $periodEnd] = $this->monthBounds($filters['selected_month']);
        [$headers, $rows, $usesDueDatePeriod, $includesDerivedStatus] = $this->rowsFor($filters);

        return [
            'csv' => $this->toCsv($headers, $rows),
            'filename' => $this->filename($filters['export_type']),
            'row_count' => $rows->count(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'uses_due_date_period' => $usesDueDatePeriod,
            'includes_derived_status' => $includesDerivedStatus,
        ];
    }

    /**
     * @return array{export_type: string, selected_month: string, search: ?string, quick_filter: string, sort_by: string}
     */
    public function normalizeFilters(array $filters): array
    {
        return [
            'export_type' => (string) ($filters['export_type'] ?? DashboardStatusCatalog::EXPORT_INSTALLMENTS_OVERDUE),
            'selected_month' => (string) ($filters['selected_month'] ?? now()->format('Y-m')),
            'search' => filled($filters['search'] ?? null) ? (string) $filters['search'] : null,
            'quick_filter' => (string) ($filters['quick_filter'] ?? DashboardStatusCatalog::FILTER_OVERDUE),
            'sort_by' => (string) ($filters['sort_by'] ?? 'due_date_asc'),
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: Collection<int, array<int, string>>, 2: bool, 3: bool}
     */
    private function rowsFor(array $filters): array
    {
        return match ($filters['export_type']) {
            DashboardStatusCatalog::EXPORT_AGREEMENTS_WITH_DELAY => $this->agreementRows(
                $this->dashboardQueries->exportDelayAgreements($filters['search']),
            ),
            DashboardStatusCatalog::EXPORT_BREACHED_AGREEMENTS => $this->breachedAgreementRows(
                $this->dashboardQueries->exportBreachedAgreements($filters['search'], $filters['quick_filter']),
            ),
            default => $this->installmentRows(
                $this->dashboardQueries->exportInstallments(
                    exportType: $filters['export_type'],
                    selectedMonth: $filters['selected_month'],
                    search: $filters['search'],
                    sortBy: $filters['sort_by'],
                ),
                $filters['export_type'],
            ),
        };
    }

    /**
     * @param  Collection<int, Installment>  $installments
     * @return array{0: array<int, string>, 1: Collection<int, array<int, string>>, 2: bool, 3: bool}
     */
    private function installmentRows(Collection $installments, string $exportType): array
    {
        $asOf = now()->startOfDay();

        return [
            ['cliente', 'trabalhador', 'processo', 'agreement_id', 'parcela', 'vencimento', 'valor', 'status_persistido', 'estado_operacional', 'data_pagamento', 'is_active'],
            $installments->map(fn (Installment $installment): array => [
                $installment->agreement->client->name,
                $installment->agreement->worker->name,
                $installment->agreement->process->process_number,
                (string) $installment->agreement_id,
                (string) $installment->installment_number,
                $this->formatDate($installment->due_date),
                $this->formatMoney($installment->amount),
                $installment->persistedStatusLabel(),
                $installment->derivedStatusLabel($asOf),
                $this->formatDate($installment->paid_date),
                $installment->is_active ? '1' : '0',
            ]),
            $exportType === DashboardStatusCatalog::EXPORT_INSTALLMENTS_PAID_MONTH,
            true,
        ];
    }

    /**
     * @param  Collection<int, Agreement>  $agreements
     * @return array{0: array<int, string>, 1: Collection<int, array<int, string>>, 2: bool, 3: bool}
     */
    private function agreementRows(Collection $agreements): array
    {
        return [
            ['cliente', 'trabalhador', 'processo', 'agreement_id', 'valor_total', 'status_persistido', 'status_sugerido', 'requer_atencao_status', 'data_referencia', 'valor_relacionado'],
            $agreements->map(fn (Agreement $agreement): array => [
                $agreement->client->name,
                $agreement->worker->name,
                $agreement->process->process_number,
                (string) $agreement->id,
                $this->formatMoney($agreement->total_amount),
                $agreement->persistedStatusLabel(),
                (string) ($agreement->suggested_status_label ?? ''),
                $agreement->requires_status_attention ? 'sim' : 'nao',
                $this->formatDate($agreement->reference_due_date),
                $this->formatMoney($agreement->matched_amount ?? 0),
            ]),
            false,
            true,
        ];
    }

    /**
     * @param  Collection<int, Agreement>  $agreements
     * @return array{0: array<int, string>, 1: Collection<int, array<int, string>>, 2: bool, 3: bool}
     */
    private function breachedAgreementRows(Collection $agreements): array
    {
        return [
            ['cliente', 'trabalhador', 'processo', 'agreement_id', 'valor_total', 'status_persistido', 'updated_at'],
            $agreements->map(fn (Agreement $agreement): array => [
                $agreement->client->name,
                $agreement->worker->name,
                $agreement->process->process_number,
                (string) $agreement->id,
                $this->formatMoney($agreement->total_amount),
                $agreement->persistedStatusLabel(),
                $this->formatDate($agreement->updated_at),
            ]),
            false,
            false,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  Collection<int, array<int, string>>  $rows
     */
    private function toCsv(array $headers, Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $headers, self::DELIMITER);

        foreach ($rows as $row) {
            fputcsv($handle, $row, self::DELIMITER);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function filename(string $exportType): string
    {
        return 'dashboard-'.Str::slug($exportType).'-'.now()->format('Y-m-d-Hi').'.csv';
    }

    private function formatDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBounds(string $selectedMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
