<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Installment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentFactory extends Factory
{
    protected $model = Installment::class;

    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'installment_number' => fake()->numberBetween(1, 12),
            'amount' => fake()->randomFloat(2, 150, 4000),
            'due_date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'paid_date' => null,
            'status' => 'em_dia',
            'is_active' => true,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(5)->toDateString(),
            'paid_date' => null,
            'status' => 'vencido',
            'is_active' => true,
        ]);
    }

    public function dueSoon(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->addDays(2)->toDateString(),
            'paid_date' => null,
            'status' => 'em_dia',
            'is_active' => true,
        ]);
    }

    public function paidThisMonth(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'paid_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'status' => 'pago_com_atraso',
            'is_active' => true,
        ]);
    }

    public function agreementMade(): static
    {
        return $this->state(fn () => [
            'status' => 'acordo_feito',
            'is_active' => true,
        ]);
    }
}
