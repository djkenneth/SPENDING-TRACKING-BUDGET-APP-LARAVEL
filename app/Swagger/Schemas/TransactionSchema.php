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
 *
 * @OA\Schema(
 *     schema="CreateTransactionRequest",
 *     type="object",
 *     title="Create Transaction Request",
 *     description="Request body for creating a new transaction",
 *     required={"account_id", "category_id", "description", "amount", "type", "date"},
 *     @OA\Property(
 *         property="account_id",
 *         type="integer",
 *         description="ID of the account",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="ID of the category",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="transfer_account_id",
 *         type="integer",
 *         nullable=true,
 *         description="ID of transfer destination account (required for transfers)",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Transaction description",
 *         maxLength=255,
 *         example="Weekly groceries"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Transaction amount",
 *         minimum=0.01,
 *         example=125.50
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Transaction type",
 *         enum={"income", "expense", "transfer"},
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         description="Transaction date (cannot be future)",
 *         example="2024-01-15"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes",
 *         maxLength=1000,
 *         example="Bought vegetables, fruits, and dairy products"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(type="string", maxLength=50),
 *         description="Transaction tags",
 *         example={"groceries", "food", "essential"}
 *     ),
 *     @OA\Property(
 *         property="reference_number",
 *         type="string",
 *         nullable=true,
 *         description="Reference or invoice number",
 *         maxLength=50,
 *         example="INV-2024-001"
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="string",
 *         nullable=true,
 *         description="Transaction location",
 *         maxLength=255,
 *         example="Walmart Superstore, Main Street"
 *     ),
 *     @OA\Property(
 *         property="attachments",
 *         type="array",
 *         @OA\Items(
 *             type="string",
 *             format="binary"
 *         ),
 *         description="File attachments (jpg, jpeg, png, pdf, doc, docx - max 2MB each)"
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         description="Whether transaction is recurring",
 *         default=false,
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="recurring_type",
 *         type="string",
 *         nullable=true,
 *         description="Recurring frequency (required if is_recurring is true)",
 *         enum={"weekly", "monthly", "quarterly", "yearly"},
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="recurring_interval",
 *         type="integer",
 *         nullable=true,
 *         description="Recurring interval (required if is_recurring is true)",
 *         minimum=1,
 *         maximum=12,
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="recurring_end_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="When recurring transactions should end",
 *         example="2024-12-31"
 *     ),
 *     @OA\Property(
 *         property="is_cleared",
 *         type="boolean",
 *         description="Whether transaction is cleared/reconciled",
 *         default=true,
 *         example=true
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateTransactionRequest",
 *     type="object",
 *     title="Update Transaction Request",
 *     description="Request body for updating an existing transaction. All fields are optional.",
 *     @OA\Property(
 *         property="account_id",
 *         type="integer",
 *         description="ID of the account",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="ID of the category",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="transfer_account_id",
 *         type="integer",
 *         nullable=true,
 *         description="ID of transfer destination account",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Transaction description",
 *         maxLength=255,
 *         example="Updated grocery shopping"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Transaction amount",
 *         minimum=0.01,
 *         example=150.75
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Transaction type",
 *         enum={"income", "expense", "transfer"},
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         description="Transaction date",
 *         example="2024-01-15"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         maxLength=1000,
 *         example="Updated notes"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(type="string", maxLength=50),
 *         example={"groceries", "updated"}
 *     ),
 *     @OA\Property(
 *         property="reference_number",
 *         type="string",
 *         nullable=true,
 *         maxLength=50,
 *         example="INV-2024-002"
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="string",
 *         nullable=true,
 *         maxLength=255,
 *         example="Updated location"
 *     ),
 *     @OA\Property(
 *         property="attachments",
 *         type="array",
 *         @OA\Items(type="string", format="binary")
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="recurring_type",
 *         type="string",
 *         nullable=true,
 *         enum={"weekly", "monthly", "quarterly", "yearly"}
 *     ),
 *     @OA\Property(
 *         property="recurring_interval",
 *         type="integer",
 *         nullable=true,
 *         minimum=1,
 *         maximum=12
 *     ),
 *     @OA\Property(
 *         property="recurring_end_date",
 *         type="string",
 *         format="date",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="is_cleared",
 *         type="boolean",
 *         example=true
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BulkCreateTransactionRequest",
 *     type="object",
 *     title="Bulk Create Transactions Request",
 *     description="Request body for creating multiple transactions at once",
 *     required={"transactions"},
 *     @OA\Property(
 *         property="transactions",
 *         type="array",
 *         minItems=1,
 *         maxItems=100,
 *         @OA\Items(
 *             type="object",
 *             required={"account_id", "category_id", "description", "amount", "type", "date"},
 *             @OA\Property(property="account_id", type="integer"),
 *             @OA\Property(property="category_id", type="integer"),
 *             @OA\Property(property="transfer_account_id", type="integer", nullable=true),
 *             @OA\Property(property="description", type="string", maxLength=255),
 *             @OA\Property(property="amount", type="number", format="float", minimum=0.01),
 *             @OA\Property(property="type", type="string", enum={"income", "expense", "transfer"}),
 *             @OA\Property(property="date", type="string", format="date"),
 *             @OA\Property(property="notes", type="string", nullable=true, maxLength=1000),
 *             @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="reference_number", type="string", nullable=true, maxLength=50),
 *             @OA\Property(property="location", type="string", nullable=true, maxLength=255),
 *             @OA\Property(property="is_cleared", type="boolean")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BulkDeleteTransactionRequest",
 *     type="object",
 *     title="Bulk Delete Transactions Request",
 *     description="Request body for deleting multiple transactions",
 *     required={"transaction_ids"},
 *     @OA\Property(
 *         property="transaction_ids",
 *         type="array",
 *         minItems=1,
 *         @OA\Items(type="integer"),
 *         description="Array of transaction IDs to delete",
 *         example={123, 124, 125}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ImportTransactionRequest",
 *     type="object",
 *     title="Import Transactions Request",
 *     description="Request body for importing transactions from CSV",
 *     required={"csv_file"},
 *     @OA\Property(
 *         property="csv_file",
 *         type="string",
 *         format="binary",
 *         description="CSV file containing transactions"
 *     ),
 *     @OA\Property(
 *         property="column_mappings",
 *         type="object",
 *         description="Mapping of CSV columns to transaction fields",
 *         @OA\Property(property="date", type="string", example="Transaction Date"),
 *         @OA\Property(property="description", type="string", example="Description"),
 *         @OA\Property(property="amount", type="string", example="Amount"),
 *         @OA\Property(property="type", type="string", example="Type"),
 *         @OA\Property(property="category", type="string", example="Category"),
 *         @OA\Property(property="account", type="string", example="Account")
 *     ),
 *     @OA\Property(
 *         property="import_options",
 *         type="object",
 *         @OA\Property(property="skip_duplicates", type="boolean", default=true),
 *         @OA\Property(property="date_format", type="string", default="Y-m-d", example="Y-m-d"),
 *         @OA\Property(property="default_account_id", type="integer", example=1),
 *         @OA\Property(property="default_category_id", type="integer", example=5)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="TransactionResource",
 *     type="object",
 *     title="Transaction Resource",
 *     description="Transaction resource response",
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
 *         example=125.50
 *     ),
 *     @OA\Property(
 *         property="formatted_amount",
 *         type="string",
 *         description="Formatted amount with currency and sign",
 *         example="-$125.50"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"income", "expense", "transfer"},
 *         description="Transaction type",
 *         example="expense"
 *     ),
 *     @OA\Property(
 *         property="type_label",
 *         type="string",
 *         description="Human-readable type label",
 *         example="Expense"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         description="Transaction date",
 *         example="2024-01-15"
 *     ),
 *     @OA\Property(
 *         property="formatted_date",
 *         type="string",
 *         description="Human-readable date",
 *         example="Jan 15, 2024"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Transaction notes",
 *         example="Bought vegetables and fruits"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(type="string"),
 *         description="Transaction tags",
 *         example={"groceries", "food", "essential"}
 *     ),
 *     @OA\Property(
 *         property="reference_number",
 *         type="string",
 *         nullable=true,
 *         description="Reference number",
 *         example="INV-2024-001"
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="string",
 *         nullable=true,
 *         description="Transaction location",
 *         example="Walmart Superstore"
 *     ),
 *     @OA\Property(
 *         property="attachments",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="size", type="integer"),
 *             @OA\Property(property="type", type="string"),
 *             @OA\Property(property="url", type="string"),
 *             @OA\Property(property="thumbnail", type="string", nullable=true),
 *             @OA\Property(property="is_image", type="boolean"),
 *             @OA\Property(property="uploaded_at", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         description="Whether transaction is recurring",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="recurring_type",
 *         type="string",
 *         nullable=true,
 *         enum={"weekly", "monthly", "quarterly", "yearly"},
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="recurring_interval",
 *         type="integer",
 *         nullable=true,
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="recurring_end_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example="2024-12-31"
 *     ),
 *     @OA\Property(
 *         property="is_cleared",
 *         type="boolean",
 *         description="Whether transaction is cleared",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="cleared_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         example="2024-01-15T10:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="sync_id",
 *         type="string",
 *         nullable=true,
 *         description="External sync ID"
 *     ),
 *     @OA\Property(
 *         property="synced_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Last sync timestamp"
 *     ),
 *     @OA\Property(
 *         property="account",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Checking Account"),
 *         @OA\Property(property="type", type="string", example="checking"),
 *         @OA\Property(property="color", type="string", example="#2196F3"),
 *         @OA\Property(property="icon", type="string", example="account_balance"),
 *         @OA\Property(property="currency", type="string", example="USD")
 *     ),
 *     @OA\Property(
 *         property="category",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Groceries"),
 *         @OA\Property(property="type", type="string", example="expense"),
 *         @OA\Property(property="color", type="string", example="#4CAF50"),
 *         @OA\Property(property="icon", type="string", example="shopping_cart")
 *     ),
 *     @OA\Property(
 *         property="transfer_account",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="type", type="string"),
 *         @OA\Property(property="color", type="string"),
 *         @OA\Property(property="icon", type="string"),
 *         @OA\Property(property="currency", type="string")
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2024-01-15T10:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         example="2024-01-15T10:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="created_at_human",
 *         type="string",
 *         description="Human-readable creation time",
 *         example="2 hours ago"
 *     ),
 *     @OA\Property(
 *         property="updated_at_human",
 *         type="string",
 *         description="Human-readable update time",
 *         example="30 minutes ago"
 *     ),
 *     @OA\Property(
 *         property="is_transfer",
 *         type="boolean",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="is_income",
 *         type="boolean",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="is_expense",
 *         type="boolean",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="has_attachments",
 *         type="boolean",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="attachment_count",
 *         type="integer",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="has_tags",
 *         type="boolean",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="tag_count",
 *         type="integer",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="has_notes",
 *         type="boolean",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="is_future",
 *         type="boolean",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="is_today",
 *         type="boolean",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="days_ago",
 *         type="integer",
 *         description="Days since the transaction",
 *         example=5
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="TransactionSummary",
 *     type="object",
 *     title="Transaction Summary",
 *     description="Summary statistics for transactions",
 *     @OA\Property(
 *         property="total_income",
 *         type="number",
 *         format="float",
 *         example=5000.00
 *     ),
 *     @OA\Property(
 *         property="total_expenses",
 *         type="number",
 *         format="float",
 *         example=3250.75
 *     ),
 *     @OA\Property(
 *         property="net_amount",
 *         type="number",
 *         format="float",
 *         example=1749.25
 *     ),
 *     @OA\Property(
 *         property="transaction_count",
 *         type="integer",
 *         example=42
 *     ),
 *     @OA\Property(
 *         property="average_income",
 *         type="number",
 *         format="float",
 *         example=2500.00
 *     ),
 *     @OA\Property(
 *         property="average_expense",
 *         type="number",
 *         format="float",
 *         example=325.08
 *     ),
 *     @OA\Property(
 *         property="largest_income",
 *         type="number",
 *         format="float",
 *         example=3000.00
 *     ),
 *     @OA\Property(
 *         property="largest_expense",
 *         type="number",
 *         format="float",
 *         example=850.50
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ImportTransactionResult",
 *     type="object",
 *     title="Import Transaction Result",
 *     description="Result of transaction import operation",
 *     @OA\Property(
 *         property="imported",
 *         type="integer",
 *         description="Number of successfully imported transactions",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="skipped",
 *         type="integer",
 *         description="Number of skipped transactions (duplicates)",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="line", type="integer", example=5),
 *             @OA\Property(property="error", type="string", example="Invalid date format"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Property(
 *         property="total_processed",
 *         type="integer",
 *         description="Total number of rows processed",
 *         example=28
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="TransactionValidationError",
 *     type="object",
 *     title="Transaction Validation Error Response",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\Property(
 *             property="account_id",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The selected account does not belong to you."
 *             )
 *         ),
 *         @OA\Property(
 *             property="category_id",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Category type does not match transaction type."
 *             )
 *         ),
 *         @OA\Property(
 *             property="transfer_account_id",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Transfer account is required for transfer transactions."
 *             )
 *         ),
 *         @OA\Property(
 *             property="amount",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The amount must be at least 0.01."
 *             )
 *         ),
 *         @OA\Property(
 *             property="date",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The transaction date cannot be in the future."
 *             )
 *         ),
 *         @OA\Property(
 *             property="transaction",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="Cannot modify critical fields of cleared transactions older than 30 days."
 *             )
 *         )
 *     )
 * )
 */

class TransactionSchema
{
}
