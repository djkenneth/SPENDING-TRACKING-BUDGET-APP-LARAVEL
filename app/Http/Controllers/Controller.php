<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Budget App API",
 *     description="API documentation for Budget Management Application",
 *     @OA\Contact(
 *         email="admin@budgetapp.com"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter token in format (Bearer <token>)"
 * )
 *
 * @OA\PathItem(
 *     path="/api"
 * )
 *
 * @OA\Tag(name="Authentication", description="Authentication endpoints")
 * @OA\Tag(name="Users", description="User management endpoints")
 * @OA\Tag(name="Accounts", description="Account management endpoints")
 * @OA\Tag(name="Transactions", description="Transaction management endpoints")
 * @OA\Tag(name="Categories", description="Category management endpoints")
 * @OA\Tag(name="Budgets", description="Budget management endpoints")
 * @OA\Tag(name="Goals", description="Financial goals endpoints")
 * @OA\Tag(name="Debts", description="Debt management endpoints")
 * @OA\Tag(name="Bills", description="Bills and subscriptions endpoints")
 * @OA\Tag(name="Notifications", description="Notification endpoints")
 * @OA\Tag(name="Analytics", description="Analytics and reporting endpoints")
 */

abstract class Controller
{
    //
}
