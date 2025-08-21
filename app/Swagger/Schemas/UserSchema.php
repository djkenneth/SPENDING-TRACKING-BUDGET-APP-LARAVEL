<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     required={"id", "name", "email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="avatars/user1.jpg"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+639123456789"),
 *     @OA\Property(property="date_of_birth", type="string", format="date", nullable=true, example="1990-01-15"),
 *     @OA\Property(property="currency", type="string", example="PHP", description="3-letter currency code"),
 *     @OA\Property(property="timezone", type="string", example="Asia/Manila"),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="preferences", type="object", nullable=true,
 *         @OA\Property(property="theme", type="string", example="dark"),
 *         @OA\Property(property="notifications", type="boolean", example=true),
 *         @OA\Property(property="email_alerts", type="boolean", example=true)
 *     ),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T08:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T08:00:00Z")
 * )
 */
class UserSchema
{
}
