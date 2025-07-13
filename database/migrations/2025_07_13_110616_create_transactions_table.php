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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('transfer_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('type'); // income, expense, transfer
            $table->date('date');
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('location')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_type')->nullable(); // weekly, monthly, yearly
            $table->integer('recurring_interval')->nullable();
            $table->date('recurring_end_date')->nullable();
            $table->boolean('is_cleared')->default(true);
            $table->timestamp('cleared_at')->nullable();
            $table->string('sync_id')->nullable()->unique();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'date']);
            $table->index(['account_id', 'date']);
            $table->index(['category_id', 'date']);
            $table->index(['type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
