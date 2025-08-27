<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BillResource",
 *     type="object",
 *     title="Bill Resource",
 *     description="Bill resource representation",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Bill ID",
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
 *         description="Bill name",
 *         example="Netflix Subscription"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Bill amount",
 *         example=15.99
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Bill due date",
 *         example="2025-02-15"
 *     ),
 *     @OA\Property(
 *         property="frequency",
 *         type="string",
 *         enum={"weekly", "bi-weekly", "monthly", "quarterly", "semi-annually", "annually"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="reminder_days",
 *         type="integer",
 *         description="Days before due date to send reminder",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "paid", "overdue", "cancelled"},
 *         description="Bill status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         description="Whether the bill is recurring",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Bill color for UI display",
 *         example="#2196F3"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Bill icon identifier",
 *         example="receipt"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes about the bill",
 *         example="Includes family plan with 4 screens"
 *     ),
 *     @OA\Property(
 *         property="payment_history",
 *         type="array",
 *         nullable=true,
 *         description="History of payments for this bill",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
 *             @OA\Property(property="amount", type="number", format="float", example=15.99),
 *             @OA\Property(property="status", type="string", example="paid"),
 *             @OA\Property(property="transaction_id", type="integer", nullable=true, example=123)
 *         )
 *     ),
 *     @OA\Property(
 *         property="category",
 *         ref="#/components/schemas/CategoryResource",
 *         description="Associated category"
 *     ),
 *     @OA\Property(
 *         property="next_due_date",
 *         type="string",
 *         format="date",
 *         description="Next calculated due date for recurring bills",
 *         example="2025-03-15"
 *     ),
 *     @OA\Property(
 *         property="days_until_due",
 *         type="integer",
 *         description="Number of days until the bill is due",
 *         example=7
 *     ),
 *     @OA\Property(
 *         property="is_overdue",
 *         type="boolean",
 *         description="Whether the bill is currently overdue",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="last_paid_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Date when the bill was last paid",
 *         example="2025-01-15"
 *     ),
 *     @OA\Property(
 *         property="total_paid",
 *         type="number",
 *         format="float",
 *         description="Total amount paid for this bill historically",
 *         example=159.90
 *     ),
 *     @OA\Property(
 *         property="payment_count",
 *         type="integer",
 *         description="Number of times this bill has been paid",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation timestamp",
 *         example="2024-01-01T00:00:00.000000Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update timestamp",
 *         example="2025-01-15T12:30:00.000000Z"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateBillRequest",
 *     type="object",
 *     title="Create Bill Request",
 *     required={"name", "amount", "due_date", "frequency", "category_id"},
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=255,
 *         description="Bill name",
 *         example="Netflix Subscription"
 *     ),
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="Category ID",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Bill amount",
 *         example=15.99
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Bill due date",
 *         example="2025-02-15"
 *     ),
 *     @OA\Property(
 *         property="frequency",
 *         type="string",
 *         enum={"weekly", "bi-weekly", "monthly", "quarterly", "semi-annually", "annually"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="reminder_days",
 *         type="integer",
 *         minimum=0,
 *         maximum=30,
 *         default=3,
 *         description="Days before due date to send reminder",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         default=true,
 *         description="Whether the bill is recurring",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         default="#2196F3",
 *         description="Bill color for UI display",
 *         example="#FF5722"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         default="receipt",
 *         description="Bill icon identifier",
 *         example="streaming"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes about the bill",
 *         example="Includes family plan with 4 screens"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateBillRequest",
 *     type="object",
 *     title="Update Bill Request",
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=255,
 *         description="Bill name",
 *         example="Netflix Premium"
 *     ),
 *     @OA\Property(
 *         property="category_id",
 *         type="integer",
 *         description="Category ID",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Bill amount",
 *         example=19.99
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Bill due date",
 *         example="2025-02-20"
 *     ),
 *     @OA\Property(
 *         property="frequency",
 *         type="string",
 *         enum={"weekly", "bi-weekly", "monthly", "quarterly", "semi-annually", "annually"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="reminder_days",
 *         type="integer",
 *         minimum=0,
 *         maximum=30,
 *         description="Days before due date to send reminder",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "paid", "overdue", "cancelled"},
 *         description="Bill status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="is_recurring",
 *         type="boolean",
 *         description="Whether the bill is recurring",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Bill color for UI display",
 *         example="#4CAF50"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Bill icon identifier",
 *         example="entertainment"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes about the bill",
 *         example="Upgraded to premium plan"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MarkAsPaidRequest",
 *     type="object",
 *     title="Mark Bill As Paid Request",
 *     required={"amount", "payment_date"},
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Payment amount",
 *         example=15.99
 *     ),
 *     @OA\Property(
 *         property="payment_date",
 *         type="string",
 *         format="date",
 *         description="Date of payment",
 *         example="2025-01-15"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Payment notes",
 *         example="Paid via credit card"
 *     ),
 *     @OA\Property(
 *         property="create_transaction",
 *         type="boolean",
 *         default=true,
 *         description="Whether to create a transaction record",
 *         example=true
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BillCollection",
 *     type="object",
 *     title="Bill Collection",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BillResource")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="from", type="integer", example=1),
 *         @OA\Property(property="last_page", type="integer", example=5),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="to", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=75)
 *     ),
 *     @OA\Property(
 *         property="summary",
 *         type="object",
 *         @OA\Property(property="total_monthly", type="number", format="float", example=450.50),
 *         @OA\Property(property="total_upcoming", type="number", format="float", example=150.00),
 *         @OA\Property(property="total_overdue", type="number", format="float", example=45.99),
 *         @OA\Property(property="active_bills_count", type="integer", example=8),
 *         @OA\Property(property="overdue_bills_count", type="integer", example=2)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BillStatistics",
 *     type="object",
 *     title="Bill Statistics",
 *     @OA\Property(
 *         property="period",
 *         type="string",
 *         description="Statistics period",
 *         example="month"
 *     ),
 *     @OA\Property(
 *         property="total_bills",
 *         type="integer",
 *         description="Total number of bills",
 *         example=15
 *     ),
 *     @OA\Property(
 *         property="total_amount",
 *         type="number",
 *         format="float",
 *         description="Total amount for all bills",
 *         example=750.50
 *     ),
 *     @OA\Property(
 *         property="paid_amount",
 *         type="number",
 *         format="float",
 *         description="Total paid amount",
 *         example=500.00
 *     ),
 *     @OA\Property(
 *         property="pending_amount",
 *         type="number",
 *         format="float",
 *         description="Total pending amount",
 *         example=250.50
 *     ),
 *     @OA\Property(
 *         property="overdue_amount",
 *         type="number",
 *         format="float",
 *         description="Total overdue amount",
 *         example=45.99
 *     ),
 *     @OA\Property(
 *         property="bills_by_status",
 *         type="object",
 *         @OA\Property(property="active", type="integer", example=8),
 *         @OA\Property(property="paid", type="integer", example=5),
 *         @OA\Property(property="overdue", type="integer", example=2),
 *         @OA\Property(property="cancelled", type="integer", example=0)
 *     ),
 *     @OA\Property(
 *         property="bills_by_frequency",
 *         type="object",
 *         @OA\Property(property="monthly", type="integer", example=10),
 *         @OA\Property(property="weekly", type="integer", example=2),
 *         @OA\Property(property="quarterly", type="integer", example=2),
 *         @OA\Property(property="annually", type="integer", example=1)
 *     ),
 *     @OA\Property(
 *         property="bills_by_category",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="category_id", type="integer", example=5),
 *             @OA\Property(property="category_name", type="string", example="Entertainment"),
 *             @OA\Property(property="count", type="integer", example=3),
 *             @OA\Property(property="total_amount", type="number", format="float", example=45.97)
 *         )
 *     ),
 *     @OA\Property(
 *         property="average_bill_amount",
 *         type="number",
 *         format="float",
 *         description="Average bill amount",
 *         example=50.03
 *     ),
 *     @OA\Property(
 *         property="payment_completion_rate",
 *         type="number",
 *         format="float",
 *         description="Percentage of bills paid on time",
 *         example=85.5
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BillPaymentHistory",
 *     type="object",
 *     title="Bill Payment History",
 *     @OA\Property(
 *         property="payment_id",
 *         type="integer",
 *         description="Payment ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="bill_id",
 *         type="integer",
 *         description="Bill ID",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Payment amount",
 *         example=15.99
 *     ),
 *     @OA\Property(
 *         property="payment_date",
 *         type="string",
 *         format="date",
 *         description="Date of payment",
 *         example="2025-01-15"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="Payment status",
 *         example="completed"
 *     ),
 *     @OA\Property(
 *         property="transaction_id",
 *         type="integer",
 *         nullable=true,
 *         description="Associated transaction ID",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Payment notes",
 *         example="Paid via bank transfer"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Payment record creation time",
 *         example="2025-01-15T10:00:00.000000Z"
 *     )
 * )
 */

class BillSchema
{
}
