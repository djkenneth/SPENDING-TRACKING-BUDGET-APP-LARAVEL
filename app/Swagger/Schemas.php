<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="currency", type="string", example="PHP"),
 *     @OA\Property(property="timezone", type="string", example="Asia/Manila"),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     title="Transaction",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="description", type="string", example="Grocery shopping"),
 *     @OA\Property(property="amount", type="number", format="float", example=1500.50),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}),
 *     @OA\Property(property="date", type="string", format="date"),
 *     @OA\Property(property="category_id", type="integer"),
 *     @OA\Property(property="account_id", type="integer"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items()),
 *     @OA\Property(property="links", type="object",
 *         @OA\Property(property="first", type="string"),
 *         @OA\Property(property="last", type="string"),
 *         @OA\Property(property="prev", type="string", nullable=true),
 *         @OA\Property(property="next", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="from", type="integer"),
 *         @OA\Property(property="last_page", type="integer"),
 *         @OA\Property(property="path", type="string"),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="to", type="integer"),
 *         @OA\Property(property="total", type="integer")
 *     )
 * )
 */
class Schemas
{
}
