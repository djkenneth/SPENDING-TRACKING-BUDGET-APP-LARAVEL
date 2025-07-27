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
        Schema::create('account_balance_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 2);
            $table->date('date');
            $table->string('change_type')->nullable(); // transaction, adjustment, sync, initial
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure unique date per account
            $table->unique(['account_id', 'date']);

            // Indexes for better performance
            $table->index(['account_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balance_histories');
    }
};
