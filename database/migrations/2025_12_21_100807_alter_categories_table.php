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
        Schema::table('categories', function (Blueprint $table) {
            // Add parent_id for hierarchical categories (self-referencing foreign key)
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('categories')
                ->onDelete('cascade');

            // Add budget_amount for category-level budgets
            $table->decimal('budget_amount', 15, 2)
                ->nullable()
                ->after('description');

            // Add index for faster parent lookups
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');

            // Drop budget_amount column
            $table->dropColumn('budget_amount');
        });
    }
};
