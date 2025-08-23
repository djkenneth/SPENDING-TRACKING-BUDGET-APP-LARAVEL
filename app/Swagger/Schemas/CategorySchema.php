<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Category",
 *     description="Transaction category model",
 *     required={"id", "user_id", "name", "type"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null, description="Parent category ID for subcategories"),
 *     @OA\Property(property="name", type="string", example="Food & Dining"),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "both"}, example="expense"),
 *     @OA\Property(property="color", type="string", example="#FF5722"),
 *     @OA\Property(property="icon", type="string", example="restaurant"),
 *     @OA\Property(property="description", type="string", nullable=true, example="All food-related expenses"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_system", type="boolean", example=false, description="System-defined category"),
 *     @OA\Property(property="sort_order", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CategorySchema
{
}
