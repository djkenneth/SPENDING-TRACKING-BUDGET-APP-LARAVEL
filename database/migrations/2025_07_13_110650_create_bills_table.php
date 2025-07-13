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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('frequency'); // monthly, weekly, quarterly, annually
            $table->integer('reminder_days')->default(3);
            $table->string('status')->default('active'); // active, paid, overdue, cancelled
            $table->boolean('is_recurring')->default(true);
            $table->string('color')->default('#2196F3');
            $table->string('icon')->default('receipt');
            $table->text('notes')->nullable();
            $table->json('payment_history')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
