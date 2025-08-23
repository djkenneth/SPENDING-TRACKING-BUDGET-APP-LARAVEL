<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Budget",
 *     type="object",
 *     title="Budget",
 *     description="Budget model",
 *     required={"id", "user_id", "name", "amount", "period", "start_date", "end_date"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="name", type="string", example="Monthly Food Budget"),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="period", type="string", enum={"daily", "weekly", "monthly", "yearly", "custom"}, example="monthly"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-01-31"),
 *     @OA\Property(property="spent_amount", type="number", format="float", example=4500.00, description="Amount spent so far"),
 *     @OA\Property(property="remaining_amount", type="number", format="float", example=5500.00, description="Remaining budget"),
 *     @OA\Property(property="rollover", type="boolean", example=false, description="Rollover unused amount to next period"),
 *     @OA\Property(property="alert_threshold", type="integer", nullable=true, example=80, description="Alert when X% of budget is reached"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BudgetSchema
{
}
