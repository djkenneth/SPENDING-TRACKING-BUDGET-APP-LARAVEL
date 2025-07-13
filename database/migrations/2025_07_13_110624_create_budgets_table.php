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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('period'); // monthly, weekly, yearly
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('spent', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('alert_threshold', 5, 2)->default(80.00); // Percentage
            $table->boolean('alert_enabled')->default(true);
            $table->json('rollover_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
