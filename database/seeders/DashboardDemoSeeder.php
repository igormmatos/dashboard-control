<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\Installment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        $overdueAgreement = Agreement::factory()->create([
            'status' => 'com_atraso',
            'total_amount' => 4200,
        ]);

        Installment::factory()->for($overdueAgreement)->overdue()->create([
            'installment_number' => 1,
            'amount' => 1200,
            'due_date' => $today->copy()->subDays(12)->toDateString(),
        ]);

        Installment::factory()->for($overdueAgreement)->dueSoon()->create([
            'installment_number' => 2,
            'amount' => 900,
            'due_date' => $today->copy()->addDays(2)->toDateString(),
        ]);

        $activeWithOverdueSignal = Agreement::factory()->active()->create([
            'total_amount' => 5400,
        ]);

        Installment::factory()->for($activeWithOverdueSignal)->overdue()->create([
            'installment_number' => 1,
            'amount' => 1350,
            'due_date' => $today->copy()->subDays(7)->toDateString(),
            'status' => 'em_dia',
        ]);

        $breachedAgreement = Agreement::factory()->create([
            'status' => 'descumprido',
            'total_amount' => 7600,
        ]);

        Installment::factory()->for($breachedAgreement)->overdue()->create([
            'installment_number' => 1,
            'amount' => 1700,
            'due_date' => $today->copy()->subDays(25)->toDateString(),
        ]);

        $paidAgreement = Agreement::factory()->create([
            'status' => 'finalizado',
            'total_amount' => 3200,
        ]);

        Installment::factory()->for($paidAgreement)->paidThisMonth()->create([
            'installment_number' => 1,
            'amount' => 800,
            'due_date' => $today->copy()->startOfMonth()->addDays(3)->toDateString(),
            'paid_date' => $today->copy()->startOfMonth()->addDays(4)->toDateString(),
        ]);

        $closedWithoutSettlement = Agreement::factory()->create([
            'status' => 'encerrado_sem_quitacao',
            'total_amount' => 2500,
        ]);

        Installment::factory()->for($closedWithoutSettlement)->agreementMade()->create([
            'installment_number' => 1,
            'amount' => 500,
            'due_date' => $today->copy()->subDays(3)->toDateString(),
        ]);
    }
}
