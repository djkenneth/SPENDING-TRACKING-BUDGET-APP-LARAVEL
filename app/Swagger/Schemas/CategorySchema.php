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
 *
 * @OA\Schema(
 *     schema="CreateCategoryRequest",
 *     type="object",
 *     title="Create Category Request",
 *     description="Request body for creating a new category",
 *     required={"name", "type"},
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Category name",
 *         maxLength=255,
 *         example="Groceries"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Category type",
 *         enum={"income", "expense", "transfer"},
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Hex color code for the category",
 *         pattern="^#[a-fA-F0-9]{6}$",
 *         example="#4CAF50"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Icon name for the category",
 *         maxLength=50,
 *         example="shopping_cart"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Category description",
 *         maxLength=500,
 *         example="Monthly grocery expenses"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the category is active",
 *         default=true,
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="sort_order",
 *         type="integer",
 *         description="Sort order for display",
 *         minimum=0,
 *         example=1
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateCategoryRequest",
 *     type="object",
 *     title="Update Category Request",
 *     description="Request body for updating an existing category. All fields are optional.",
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Category name",
 *         maxLength=255,
 *         example="Updated Groceries"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Category type (cannot be changed if category has transactions)",
 *         enum={"income", "expense", "transfer"},
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Hex color code for the category",
 *         pattern="^#[a-fA-F0-9]{6}$",
 *         example="#2196F3"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Icon name for the category",
 *         maxLength=50,
 *         example="local_grocery_store"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Category description",
 *         maxLength=500,
 *         example="Updated monthly grocery expenses"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the category is active (cannot deactivate if has recent transactions)",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="sort_order",
 *         type="integer",
 *         description="Sort order for display",
 *         minimum=0,
 *         example=2
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategoryResource",
 *     type="object",
 *     title="Category Resource",
 *     description="Category resource response",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Category ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Category name",
 *         example="Groceries"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Category type",
 *         enum={"income", "expense", "transfer"},
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="type_label",
 *         type="string",
 *         description="Human-readable type label",
 *         example="Expense"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Hex color code",
 *         example="#4CAF50"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Icon name",
 *         example="shopping_cart"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Category description",
 *         example="Monthly grocery expenses"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether category is active",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="sort_order",
 *         type="integer",
 *         description="Display sort order",
 *         example=1
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
 *     ),
 *     @OA\Property(
 *         property="created_at_human",
 *         type="string",
 *         description="Human-readable creation time",
 *         example="2 weeks ago"
 *     ),
 *     @OA\Property(
 *         property="updated_at_human",
 *         type="string",
 *         description="Human-readable update time",
 *         example="3 days ago"
 *     ),
 *     @OA\Property(
 *         property="statistics",
 *         type="object",
 *         description="Category statistics (only included on show endpoint)",
 *         @OA\Property(property="transaction_count", type="integer", example=42),
 *         @OA\Property(property="total_amount", type="number", format="float", example=1250.50),
 *         @OA\Property(property="current_month_amount", type="number", format="float", example=350.25),
 *         @OA\Property(property="last_transaction_date", type="string", format="date", example="2024-01-14"),
 *         @OA\Property(property="average_transaction", type="number", format="float", example=29.77),
 *         @OA\Property(property="budget_count", type="integer", example=2),
 *         @OA\Property(property="active_budget_count", type="integer", example=1)
 *     ),
 *     @OA\Property(
 *         property="has_transactions",
 *         type="boolean",
 *         description="Whether category has any transactions",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="has_budgets",
 *         type="boolean",
 *         description="Whether category has any budgets",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="can_delete",
 *         type="boolean",
 *         description="Whether category can be deleted",
 *         example=false
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategoryTransactionResource",
 *     type="object",
 *     title="Category Transaction Resource",
 *     description="Transaction resource for category transactions listing",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Transaction ID",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Transaction description",
 *         example="Weekly groceries"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Transaction amount",
 *         example=85.50
 *     ),
 *     @OA\Property(
 *         property="formatted_amount",
 *         type="string",
 *         description="Formatted amount with currency and sign",
 *         example="-$85.50"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"income", "expense", "transfer"},
 *         description="Transaction type",
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         description="Transaction date",
 *         example="2024-01-14"
 *     ),
 *     @OA\Property(
 *         property="formatted_date",
 *         type="string",
 *         description="Human-readable date",
 *         example="Jan 14, 2024"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         description="Transaction notes",
 *         example="Bought vegetables and fruits"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(type="string"),
 *         description="Transaction tags",
 *         example={"food", "essential"}
 *     ),
 *     @OA\Property(
 *         property="reference_number",
 *         type="string",
 *         description="Reference number",
 *         example="INV-2024-001"
 *     ),
 *     @OA\Property(
 *         property="is_cleared",
 *         type="boolean",
 *         description="Whether transaction is cleared",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         description="Whether transaction is recurring",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="account",
 *         type="object",
 *         description="Account information",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Checking Account"),
 *         @OA\Property(property="type", type="string", example="checking"),
 *         @OA\Property(property="color", type="string", example="#2196F3"),
 *         @OA\Property(property="icon", type="string", example="account_balance")
 *     ),
 *     @OA\Property(
 *         property="transfer_account",
 *         type="object",
 *         nullable=true,
 *         description="Transfer destination account (only for transfers)",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="type", type="string"),
 *         @OA\Property(property="color", type="string"),
 *         @OA\Property(property="icon", type="string")
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2024-01-14T10:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         example="2024-01-14T10:00:00Z"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategorySpendingAnalysis",
 *     type="object",
 *     title="Category Spending Analysis",
 *     description="Spending analysis data for categories",
 *     @OA\Property(
 *         property="category",
 *         ref="#/components/schemas/CategoryResource"
 *     ),
 *     @OA\Property(
 *         property="current_month",
 *         type="object",
 *         @OA\Property(property="spent", type="number", format="float", example=450.75),
 *         @OA\Property(property="budget", type="number", format="float", example=500.00),
 *         @OA\Property(property="percentage", type="number", format="float", example=90.15),
 *         @OA\Property(property="transaction_count", type="integer", example=12)
 *     ),
 *     @OA\Property(
 *         property="last_month",
 *         type="object",
 *         @OA\Property(property="spent", type="number", format="float", example=380.50),
 *         @OA\Property(property="budget", type="number", format="float", example=500.00),
 *         @OA\Property(property="percentage", type="number", format="float", example=76.10),
 *         @OA\Property(property="transaction_count", type="integer", example=10)
 *     ),
 *     @OA\Property(
 *         property="average_monthly",
 *         type="number",
 *         format="float",
 *         description="Average monthly spending",
 *         example=425.25
 *     ),
 *     @OA\Property(
 *         property="trend",
 *         type="string",
 *         enum={"increasing", "decreasing", "stable"},
 *         description="Spending trend",
 *         example="increasing"
 *     ),
 *     @OA\Property(
 *         property="trend_percentage",
 *         type="number",
 *         format="float",
 *         description="Trend percentage change",
 *         example=18.5
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategoryTrend",
 *     type="object",
 *     title="Category Trend",
 *     description="Category trend data over time",
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="Category ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="category_name",
 *         type="string",
 *         description="Category name",
 *         example="Groceries"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="date", type="string", format="date", example="2024-01-01"),
 *             @OA\Property(property="amount", type="number", format="float", example=125.50),
 *             @OA\Property(property="transaction_count", type="integer", example=5)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CategoryIconsColors",
 *     type="object",
 *     title="Category Icons and Colors",
 *     description="Available icons and colors for categories",
 *     @OA\Property(
 *         property="icons",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="name", type="string", example="shopping_cart"),
 *             @OA\Property(property="label", type="string", example="Shopping Cart"),
 *             @OA\Property(property="category", type="string", example="commerce")
 *         )
 *     ),
 *     @OA\Property(
 *         property="colors",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="value", type="string", example="#4CAF50"),
 *             @OA\Property(property="label", type="string", example="Green")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BulkUpdateCategoriesRequest",
 *     type="object",
 *     title="Bulk Update Categories Request",
 *     description="Request body for bulk updating categories",
 *     required={"categories"},
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         minItems=1,
 *         @OA\Items(
 *             type="object",
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Updated Name"),
 *             @OA\Property(property="color", type="string", example="#FF5722"),
 *             @OA\Property(property="icon", type="string", example="new_icon"),
 *             @OA\Property(property="is_active", type="boolean", example=true),
 *             @OA\Property(property="sort_order", type="integer", example=3)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ReorderCategoriesRequest",
 *     type="object",
 *     title="Reorder Categories Request",
 *     description="Request body for reordering categories",
 *     required={"categories"},
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         minItems=1,
 *         @OA\Items(
 *             type="object",
 *             required={"id", "sort_order"},
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="sort_order", type="integer", example=0)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MergeCategoriesRequest",
 *     type="object",
 *     title="Merge Categories Request",
 *     description="Request body for merging two categories",
 *     required={"source_category_id", "target_category_id"},
 *     @OA\Property(
 *         property="source_category_id",
 *         type="integer",
 *         description="ID of the source category to merge from",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="target_category_id",
 *         type="integer",
 *         description="ID of the target category to merge into",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="delete_source",
 *         type="boolean",
 *         description="Whether to delete the source category after merge",
 *         default=false,
 *         example=true
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MergeCategoriesResponse",
 *     type="object",
 *     title="Merge Categories Response",
 *     @OA\Property(
 *         property="moved_transactions",
 *         type="integer",
 *         description="Number of transactions moved",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="moved_budgets",
 *         type="integer",
 *         description="Number of budgets moved",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="source_category",
 *         type="string",
 *         description="Source category name",
 *         example="Old Groceries"
 *     ),
 *     @OA\Property(
 *         property="target_category",
 *         type="string",
 *         description="Target category name",
 *         example="Groceries"
 *     ),
 *     @OA\Property(
 *         property="source_deleted",
 *         type="boolean",
 *         description="Whether source category was deleted",
 *         example=true
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DefaultCategory",
 *     type="object",
 *     title="Default Category",
 *     @OA\Property(property="name", type="string", example="Salary"),
 *     @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}),
 *     @OA\Property(property="color", type="string", example="#4CAF50"),
 *     @OA\Property(property="icon", type="string", example="attach_money")
 * )
 *
 * @OA\Schema(
 *     schema="CategoryValidationError",
 *     type="object",
 *     title="Category Validation Error Response",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Error message",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\Property(
 *             property="name",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="A category with this name already exists for this type."
 *             )
 *         ),
 *         @OA\Property(
 *             property="type",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Cannot change category type. This category has 42 transactions."
 *             )
 *         ),
 *         @OA\Property(
 *             property="color",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The color must be a valid hex color code (e.g., #FF0000)."
 *             )
 *         ),
 *         @OA\Property(
 *             property="is_active",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Cannot deactivate category with 5 transactions in the last 30 days."
 *             )
 *         )
 *     )
 * )
 */
class CategorySchema {}
