<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     title="Transaction",
 *     description="Financial transaction model",
 *     required={"id", "user_id", "account_id", "category_id", "amount", "type", "date", "description"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="account_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=1500.50),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}, example="expense"),
 *     @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="description", type="string", example="Grocery shopping at SM Supermarket"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Weekly groceries"),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"groceries", "food", "essentials"}),
 *     @OA\Property(property="reference_number", type="string", nullable=true, example="REF-2025-001"),
 *     @OA\Property(property="location", type="string", nullable=true, example="SM North EDSA"),
 *     @OA\Property(property="receipt_url", type="string", nullable=true, example="receipts/2025/01/receipt1.jpg"),
 *     @OA\Property(property="is_recurring", type="boolean", example=false),
 *     @OA\Property(property="recurring_id", type="integer", nullable=true),
 *     @OA\Property(property="transfer_account_id", type="integer", nullable=true, description="For transfer transactions"),
 *     @OA\Property(property="is_cleared", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TransactionSchema
{
}
