<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Client;
use App\Models\Process;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgreementFactory extends Factory
{
    protected $model = Agreement::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'worker_id' => Worker::factory(),
            'process_id' => Process::factory(),
            'total_amount' => fake()->randomFloat(2, 800, 9000),
            'status' => 'ativo',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'ativo',
        ]);
    }

    public function withDelay(): static
    {
        return $this->state(fn () => [
            'status' => 'com_atraso',
        ]);
    }

    public function breached(): static
    {
        return $this->state(fn () => [
            'status' => 'descumprido',
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => 'finalizado',
        ]);
    }

    public function closedWithoutSettlement(): static
    {
        return $this->state(fn () => [
            'status' => 'encerrado_sem_quitacao',
        ]);
    }
}
