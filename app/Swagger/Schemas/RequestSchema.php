<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password123"),
 *     @OA\Property(property="remember", type="boolean", example=true, description="Remember me token")
 * )
 *
 * @OA\Schema(
 *     schema="RegisterRequest",
 *     type="object",
 *     required={"name", "email", "password", "password_confirmation"},
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password123", minLength=8),
 *     @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
 *     @OA\Property(property="currency", type="string", example="PHP", description="Default currency"),
 *     @OA\Property(property="timezone", type="string", example="Asia/Manila", description="User timezone"),
 *     @OA\Property(property="language", type="string", example="en", description="Preferred language")
 * )
 *
 * @OA\Schema(
 *     schema="CreateTransactionRequest",
 *     type="object",
 *     required={"account_id", "category_id", "amount", "type", "date", "description"},
 *     @OA\Property(property="account_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=1500.50),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}, example="expense"),
 *     @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="description", type="string", example="Grocery shopping"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Weekly groceries"),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"groceries", "food"}),
 *     @OA\Property(property="transfer_account_id", type="integer", nullable=true, description="Required for transfer type")
 * )
 *
 * @OA\Schema(
 *     schema="UpdateTransactionRequest",
 *     type="object",
 *     @OA\Property(property="account_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=1500.50),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}, example="expense"),
 *     @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="description", type="string", example="Grocery shopping"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Weekly groceries"),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"groceries", "food"})
 * )
 *
 * @OA\Schema(
 *     schema="CreateBudgetRequest",
 *     type="object",
 *     required={"name", "amount", "period", "start_date"},
 *     @OA\Property(property="name", type="string", example="Monthly Food Budget"),
 *     @OA\Property(property="category_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="period", type="string", enum={"daily", "weekly", "monthly", "yearly", "custom"}, example="monthly"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2025-01-31"),
 *     @OA\Property(property="rollover", type="boolean", example=false),
 *     @OA\Property(property="alert_threshold", type="integer", example=80)
 * )
 */
class RequestSchema
{
}
