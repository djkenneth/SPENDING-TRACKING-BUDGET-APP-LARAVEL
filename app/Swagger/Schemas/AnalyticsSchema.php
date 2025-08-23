<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="DashboardStats",
 *     type="object",
 *     @OA\Property(property="total_balance", type="number", example=150000.00),
 *     @OA\Property(property="total_income", type="number", example=50000.00),
 *     @OA\Property(property="total_expenses", type="number", example=35000.00),
 *     @OA\Property(property="net_worth", type="number", example=150000.00),
 *     @OA\Property(property="monthly_average_income", type="number", example=50000.00),
 *     @OA\Property(property="monthly_average_expense", type="number", example=35000.00),
 *     @OA\Property(property="savings_rate", type="number", example=30.0),
 *     @OA\Property(property="accounts_summary", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string", example="savings"),
 *         @OA\Property(property="count", type="integer", example=2),
 *         @OA\Property(property="total", type="number", example=100000.00)
 *     )),
 *     @OA\Property(property="recent_transactions", type="array", @OA\Items(ref="#/components/schemas/Transaction")),
 *     @OA\Property(property="budget_status", type="object",
 *         @OA\Property(property="total_budgets", type="integer", example=5),
 *         @OA\Property(property="over_budget", type="integer", example=1),
 *         @OA\Property(property="on_track", type="integer", example=3),
 *         @OA\Property(property="under_budget", type="integer", example=1)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SpendingAnalysis",
 *     type="object",
 *     @OA\Property(property="period", type="string", example="2025-01"),
 *     @OA\Property(property="categories", type="array", @OA\Items(
 *         @OA\Property(property="category_id", type="integer", example=1),
 *         @OA\Property(property="category_name", type="string", example="Food & Dining"),
 *         @OA\Property(property="total_spent", type="number", example=15000.00),
 *         @OA\Property(property="percentage", type="number", example=42.86),
 *         @OA\Property(property="transaction_count", type="integer", example=25),
 *         @OA\Property(property="average_transaction", type="number", example=600.00)
 *     )),
 *     @OA\Property(property="total_spent", type="number", example=35000.00),
 *     @OA\Property(property="top_merchants", type="array", @OA\Items(
 *         @OA\Property(property="name", type="string", example="SM Supermarket"),
 *         @OA\Property(property="amount", type="number", example=5000.00),
 *         @OA\Property(property="count", type="integer", example=4)
 *     ))
 * )
 */
class AnalyticsSchema
{
}
