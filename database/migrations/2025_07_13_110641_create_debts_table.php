<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // credit_card, personal_loan, mortgage, auto_loan, student_loan
            $table->decimal('original_balance', 15, 2);
            $table->decimal('current_balance', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('minimum_payment', 15, 2);
            $table->date('due_date');
            $table->string('payment_frequency'); // monthly, weekly, bi-weekly
            $table->string('status')->default('active'); // active, paid_off, closed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
