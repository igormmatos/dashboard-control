<?php

namespace App\Support\Dashboard;

class DashboardStatusCatalog
{
    public const FILTER_DUE_SOON = 'a_vencer';

    public const FILTER_OVERDUE = 'em_atraso';

    public const FILTER_PAID_THIS_MONTH = 'pagas_mes';

    public const EXPORT_INSTALLMENTS_DUE_SOON = 'installments_due_soon';

    public const EXPORT_INSTALLMENTS_OVERDUE = 'installments_overdue';

    public const EXPORT_INSTALLMENTS_PAID_MONTH = 'installments_paid_month';

    public const EXPORT_AGREEMENTS_WITH_DELAY = 'agreements_with_delay';

    public const EXPORT_BREACHED_AGREEMENTS = 'breached_agreements';

    public static function agreementStatuses(): array
    {
        return ['com_atraso', 'ativo', 'descumprido', 'finalizado', 'encerrado_sem_quitacao'];
    }

    public static function installmentStatuses(): array
    {
        return ['vencido', 'pago_com_atraso', 'pago_em_dia', 'acordo_feito', 'em_dia'];
    }

    public static function installmentPaidStatuses(): array
    {
        return ['pago_com_atraso', 'pago_em_dia'];
    }

    public static function installmentDeferredStatuses(): array
    {
        return ['acordo_feito'];
    }

    public static function agreementDelayStatuses(): array
    {
        return ['com_atraso'];
    }

    public static function agreementBreachedStatuses(): array
    {
        return ['descumprido', 'encerrado_sem_quitacao'];
    }

    public static function shouldSuggestAgreementDelay(string $agreementStatus): bool
    {
        return $agreementStatus === 'ativo';
    }

    public static function quickFilterLabels(): array
    {
        return [
            self::FILTER_DUE_SOON => 'A vencer em 3 dias',
            self::FILTER_OVERDUE => 'Em atraso',
            self::FILTER_PAID_THIS_MONTH => 'Pagas no mês',
        ];
    }

    public static function exportTypes(): array
    {
        return [
            self::EXPORT_INSTALLMENTS_DUE_SOON,
            self::EXPORT_INSTALLMENTS_OVERDUE,
            self::EXPORT_INSTALLMENTS_PAID_MONTH,
            self::EXPORT_AGREEMENTS_WITH_DELAY,
            self::EXPORT_BREACHED_AGREEMENTS,
        ];
    }

    public static function installmentExportTypes(): array
    {
        return [
            self::EXPORT_INSTALLMENTS_DUE_SOON,
            self::EXPORT_INSTALLMENTS_OVERDUE,
            self::EXPORT_INSTALLMENTS_PAID_MONTH,
        ];
    }

    public static function exportTypeForQuickFilter(string $quickFilter): string
    {
        return match ($quickFilter) {
            self::FILTER_DUE_SOON => self::EXPORT_INSTALLMENTS_DUE_SOON,
            self::FILTER_PAID_THIS_MONTH => self::EXPORT_INSTALLMENTS_PAID_MONTH,
            default => self::EXPORT_INSTALLMENTS_OVERDUE,
        };
    }

    public static function exportTypeLabel(string $exportType): string
    {
        return match ($exportType) {
            self::EXPORT_INSTALLMENTS_DUE_SOON => 'Parcelas vencendo em ate 3 dias',
            self::EXPORT_INSTALLMENTS_OVERDUE => 'Parcelas em atraso',
            self::EXPORT_INSTALLMENTS_PAID_MONTH => 'Parcelas pagas no mes',
            self::EXPORT_AGREEMENTS_WITH_DELAY => 'Acordos com atraso',
            self::EXPORT_BREACHED_AGREEMENTS => 'Acordos descumpridos',
            default => self::label($exportType),
        };
    }

    public static function agreementStatusLabel(string $status): string
    {
        return self::label($status);
    }

    public static function installmentStatusLabel(string $status): string
    {
        return self::label($status);
    }

    public static function operationalRules(): array
    {
        return [
            'agreements.status persistido: ativo, com_atraso, descumprido, finalizado, encerrado_sem_quitacao.',
            'installments.status oficial: em_dia, vencido, pago_em_dia, pago_com_atraso, acordo_feito.',
            'RF007 usa estratégia híbrida: parcelas vencidas podem sugerir com_atraso, mas agreements.status só muda por ação manual.',
            'RF012 mantém as duas ordenações mínimas: atraso mais antigo e maior valor; RF013 aplica o filtro rápido a todas as listas do dashboard.',
        ];
    }

    private static function label(string $status): string
    {
        return str_replace('_', ' ', $status);
    }
}
