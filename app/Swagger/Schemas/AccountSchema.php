<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Account",
 *     type="object",
 *     title="Account",
 *     description="Financial account model",
 *     required={"id", "user_id", "name", "type", "balance", "currency"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Main Savings Account"),
 *     @OA\Property(property="type", type="string", enum={"checking", "savings", "credit_card", "investment", "loan", "cash", "other"}, example="savings"),
 *     @OA\Property(property="balance", type="number", format="float", example=50000.00),
 *     @OA\Property(property="currency", type="string", example="PHP"),
 *     @OA\Property(property="institution", type="string", nullable=true, example="BDO"),
 *     @OA\Property(property="account_number", type="string", nullable=true, example="****1234"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Primary savings account for emergency fund"),
 *     @OA\Property(property="color", type="string", nullable=true, example="#4CAF50"),
 *     @OA\Property(property="icon", type="string", nullable=true, example="bank"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_included_in_totals", type="boolean", example=true),
 *     @OA\Property(property="sort_order", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class AccountSchema
{
}
