<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('status');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
