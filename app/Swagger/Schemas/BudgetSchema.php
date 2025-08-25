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
 *
 * @OA\Schema(
 *     schema="CreateBudgetRequest",
 *     type="object",
 *     title="Create Budget Request",
 *     description="Request body for creating a new budget",
 *     required={"category_id", "name", "amount", "period", "start_date", "end_date"},
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="ID of the category this budget belongs to",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the budget",
 *         maxLength=255,
 *         example="Monthly Groceries Budget"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Budget amount",
 *         minimum=0.01,
 *         maximum=999999999.99,
 *         example=500.00
 *     ),
 *     @OA\Property(
 *         property="period",
 *         type="string",
 *         description="Budget period type",
 *         enum={"weekly", "monthly", "yearly"},
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         description="Budget start date",
 *         example="2024-01-01"
 *     ),
 *     @OA\Property(
 *         property="end_date",
 *         type="string",
 *         format="date",
 *         description="Budget end date (must be after start_date)",
 *         example="2024-01-31"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the budget is active",
 *         default=true,
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="alert_threshold",
 *         type="number",
 *         format="float",
 *         description="Percentage threshold for budget alerts",
 *         minimum=0,
 *         maximum=100,
 *         example=80
 *     ),
 *     @OA\Property(
 *         property="alert_enabled",
 *         type="boolean",
 *         description="Whether alerts are enabled for this budget",
 *         default=true,
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="rollover_settings",
 *         type="object",
 *         description="Settings for budget rollover",
 *         @OA\Property(
 *             property="enabled",
 *             type="boolean",
 *             description="Whether rollover is enabled",
 *             example=true
 *         ),
 *         @OA\Property(
 *             property="carry_over_unused",
 *             type="boolean",
 *             description="Whether to carry over unused budget amount",
 *             example=true
 *         ),
 *         @OA\Property(
 *             property="reset_on_overspend",
 *             type="boolean",
 *             description="Whether to reset on overspend",
 *             example=false
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateBudgetRequest",
 *     type="object",
 *     title="Update Budget Request",
 *     description="Request body for updating an existing budget. All fields are optional.",
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="ID of the category this budget belongs to",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the budget",
 *         maxLength=255,
 *         example="Updated Monthly Groceries Budget"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Budget amount",
 *         minimum=0.01,
 *         maximum=999999999.99,
 *         example=600.00
 *     ),
 *     @OA\Property(
 *         property="period",
 *         type="string",
 *         description="Budget period type",
 *         enum={"weekly", "monthly", "yearly"},
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         description="Budget start date",
 *         example="2024-02-01"
 *     ),
 *     @OA\Property(
 *         property="end_date",
 *         type="string",
 *         format="date",
 *         description="Budget end date (must be after start_date)",
 *         example="2024-02-29"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the budget is active",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="alert_threshold",
 *         type="number",
 *         format="float",
 *         description="Percentage threshold for budget alerts",
 *         minimum=0,
 *         maximum=100,
 *         example=75
 *     ),
 *     @OA\Property(
 *         property="alert_enabled",
 *         type="boolean",
 *         description="Whether alerts are enabled for this budget",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="rollover_settings",
 *         type="object",
 *         description="Settings for budget rollover",
 *         @OA\Property(
 *             property="enabled",
 *             type="boolean",
 *             description="Whether rollover is enabled",
 *             example=true
 *         ),
 *         @OA\Property(
 *             property="carry_over_unused",
 *             type="boolean",
 *             description="Whether to carry over unused budget amount",
 *             example=false
 *         ),
 *         @OA\Property(
 *             property="reset_on_overspend",
 *             type="boolean",
 *             description="Whether to reset on overspend",
 *             example=true
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BudgetResource",
 *     type="object",
 *     title="Budget Resource",
 *     description="Budget resource response",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Budget ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         description="User ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="Category ID",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Budget name",
 *         example="Monthly Groceries Budget"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Budget amount",
 *         example=500.00
 *     ),
 *     @OA\Property(
 *         property="spent",
 *         type="number",
 *         format="float",
 *         description="Amount spent",
 *         example=325.50
 *     ),
 *     @OA\Property(
 *         property="remaining",
 *         type="number",
 *         format="float",
 *         description="Remaining budget amount",
 *         example=174.50
 *     ),
 *     @OA\Property(
 *         property="percentage_used",
 *         type="number",
 *         format="float",
 *         description="Percentage of budget used",
 *         example=65.1
 *     ),
 *     @OA\Property(
 *         property="period",
 *         type="string",
 *         description="Budget period",
 *         enum={"weekly", "monthly", "yearly"},
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         description="Budget start date",
 *         example="2024-01-01"
 *     ),
 *     @OA\Property(
 *         property="end_date",
 *         type="string",
 *         format="date",
 *         description="Budget end date",
 *         example="2024-01-31"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether budget is active",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="alert_threshold",
 *         type="number",
 *         format="float",
 *         description="Alert threshold percentage",
 *         example=80
 *     ),
 *     @OA\Property(
 *         property="alert_enabled",
 *         type="boolean",
 *         description="Whether alerts are enabled",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="rollover_settings",
 *         type="object",
 *         description="Rollover settings",
 *         @OA\Property(property="enabled", type="boolean"),
 *         @OA\Property(property="carry_over_unused", type="boolean"),
 *         @OA\Property(property="reset_on_overspend", type="boolean")
 *     ),
 *     @OA\Property(
 *         property="category",
 *         ref="#/components/schemas/CategoryResource",
 *         description="Associated category"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation timestamp",
 *         example="2024-01-01T00:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update timestamp",
 *         example="2024-01-15T10:30:00Z"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BudgetValidationError",
 *     type="object",
 *     title="Budget Validation Error Response",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Error message",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Validation errors by field",
 *         @OA\Property(
 *             property="category_id",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The selected category is invalid or does not belong to you."
 *             )
 *         ),
 *         @OA\Property(
 *             property="name",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Budget name cannot exceed 255 characters."
 *             )
 *         ),
 *         @OA\Property(
 *             property="amount",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Budget amount must be at least 0.01."
 *             )
 *         ),
 *         @OA\Property(
 *             property="period",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Budget period must be weekly, monthly, or yearly."
 *             )
 *         ),
 *         @OA\Property(
 *             property="start_date",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="A budget for this category already exists for the specified period."
 *             )
 *         ),
 *         @OA\Property(
 *             property="end_date",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="End date must be after the start date."
 *             )
 *         ),
 *         @OA\Property(
 *             property="alert_threshold",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Alert threshold cannot exceed 100%."
 *             )
 *         )
 *     )
 * )
 */
class BudgetSchema {}
