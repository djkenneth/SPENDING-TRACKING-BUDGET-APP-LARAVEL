<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="DebtResource",
 *     type="object",
 *     title="Debt Resource",
 *     description="Debt resource representation",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Debt ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         description="User ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Debt name",
 *         example="Chase Credit Card"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"credit_card", "personal_loan", "mortgage", "auto_loan", "student_loan"},
 *         description="Type of debt",
 *         example="credit_card"
 *     ),
 *     @OA\Property(
 *         property="type_label",
 *         type="string",
 *         description="Human-readable debt type",
 *         example="Credit Card"
 *     ),
 *     @OA\Property(
 *         property="original_balance",
 *         type="number",
 *         format="float",
 *         description="Original debt balance",
 *         example=5000.00
 *     ),
 *     @OA\Property(
 *         property="current_balance",
 *         type="number",
 *         format="float",
 *         description="Current debt balance",
 *         example=3500.00
 *     ),
 *     @OA\Property(
 *         property="paid_amount",
 *         type="number",
 *         format="float",
 *         description="Amount paid so far",
 *         example=1500.00
 *     ),
 *     @OA\Property(
 *         property="progress_percentage",
 *         type="number",
 *         format="float",
 *         description="Percentage of debt paid off",
 *         example=30.0
 *     ),
 *     @OA\Property(
 *         property="interest_rate",
 *         type="number",
 *         format="float",
 *         description="Annual interest rate percentage",
 *         example=18.99
 *     ),
 *     @OA\Property(
 *         property="minimum_payment",
 *         type="number",
 *         format="float",
 *         description="Minimum monthly payment",
 *         example=125.00
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Monthly due date",
 *         example="2025-02-15"
 *     ),
 *     @OA\Property(
 *         property="days_until_due",
 *         type="integer",
 *         description="Days until next due date",
 *         example=7
 *     ),
 *     @OA\Property(
 *         property="payment_frequency",
 *         type="string",
 *         enum={"monthly", "weekly", "bi-weekly"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="payment_frequency_label",
 *         type="string",
 *         description="Human-readable payment frequency",
 *         example="Monthly"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "paid_off", "closed"},
 *         description="Debt status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="status_label",
 *         type="string",
 *         description="Human-readable status",
 *         example="Active"
 *     ),
 *     @OA\Property(
 *         property="is_overdue",
 *         type="boolean",
 *         description="Whether payment is overdue",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes",
 *         example="Balance transfer from another card"
 *     ),
 *     @OA\Property(
 *         property="payments",
 *         type="array",
 *         description="Payment history",
 *         @OA\Items(ref="#/components/schemas/DebtPaymentResource")
 *     ),
 *     @OA\Property(
 *         property="latest_payment",
 *         ref="#/components/schemas/DebtPaymentResource",
 *         description="Most recent payment"
 *     ),
 *     @OA\Property(
 *         property="total_payments",
 *         type="object",
 *         @OA\Property(property="count", type="integer", example=12),
 *         @OA\Property(property="total_amount", type="number", format="float", example=1500.00),
 *         @OA\Property(property="total_principal", type="number", format="float", example=1200.00),
 *         @OA\Property(property="total_interest", type="number", format="float", example=300.00)
 *     ),
 *     @OA\Property(
 *         property="estimated_payoff",
 *         type="object",
 *         @OA\Property(property="months_remaining", type="integer", example=28),
 *         @OA\Property(property="payoff_date", type="string", format="date", example="2027-06-15"),
 *         @OA\Property(property="total_interest", type="number", format="float", example=850.00)
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
 *     schema="DebtPaymentResource",
 *     type="object",
 *     title="Debt Payment Resource",
 *     description="Debt payment record",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Payment ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="debt_id",
 *         type="integer",
 *         description="Associated debt ID",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="transaction_id",
 *         type="integer",
 *         nullable=true,
 *         description="Associated transaction ID",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Total payment amount",
 *         example=150.00
 *     ),
 *     @OA\Property(
 *         property="principal",
 *         type="number",
 *         format="float",
 *         description="Principal portion of payment",
 *         example=120.00
 *     ),
 *     @OA\Property(
 *         property="interest",
 *         type="number",
 *         format="float",
 *         description="Interest portion of payment",
 *         example=30.00
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
 *         example="Extra payment toward principal"
 *     ),
 *     @OA\Property(
 *         property="transaction",
 *         ref="#/components/schemas/TransactionResource",
 *         description="Associated transaction details"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation timestamp",
 *         example="2025-01-15T10:00:00.000000Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update timestamp",
 *         example="2025-01-15T10:00:00.000000Z"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateDebtRequest",
 *     type="object",
 *     title="Create Debt Request",
 *     required={"name", "type", "original_balance", "current_balance", "interest_rate", "minimum_payment", "due_date", "payment_frequency"},
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=255,
 *         description="Debt name",
 *         example="Chase Sapphire Credit Card"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"credit_card", "personal_loan", "mortgage", "auto_loan", "student_loan"},
 *         description="Type of debt",
 *         example="credit_card"
 *     ),
 *     @OA\Property(
 *         property="original_balance",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Original debt amount",
 *         example=5000.00
 *     ),
 *     @OA\Property(
 *         property="current_balance",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Current balance",
 *         example=3500.00
 *     ),
 *     @OA\Property(
 *         property="interest_rate",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         maximum=100,
 *         description="Annual interest rate percentage",
 *         example=18.99
 *     ),
 *     @OA\Property(
 *         property="minimum_payment",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Minimum monthly payment",
 *         example=125.00
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Monthly due date",
 *         example="2025-02-15"
 *     ),
 *     @OA\Property(
 *         property="payment_frequency",
 *         type="string",
 *         enum={"monthly", "weekly", "bi-weekly"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "paid_off", "closed"},
 *         default="active",
 *         description="Debt status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes",
 *         example="0% APR until March 2025"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateDebtRequest",
 *     type="object",
 *     title="Update Debt Request",
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=255,
 *         description="Debt name",
 *         example="Chase Freedom Credit Card"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"credit_card", "personal_loan", "mortgage", "auto_loan", "student_loan"},
 *         description="Type of debt",
 *         example="credit_card"
 *     ),
 *     @OA\Property(
 *         property="original_balance",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Original debt amount",
 *         example=5000.00
 *     ),
 *     @OA\Property(
 *         property="current_balance",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Current balance",
 *         example=2800.00
 *     ),
 *     @OA\Property(
 *         property="interest_rate",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         maximum=100,
 *         description="Annual interest rate percentage",
 *         example=16.99
 *     ),
 *     @OA\Property(
 *         property="minimum_payment",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Minimum monthly payment",
 *         example=100.00
 *     ),
 *     @OA\Property(
 *         property="due_date",
 *         type="string",
 *         format="date",
 *         description="Monthly due date",
 *         example="2025-02-20"
 *     ),
 *     @OA\Property(
 *         property="payment_frequency",
 *         type="string",
 *         enum={"monthly", "weekly", "bi-weekly"},
 *         description="Payment frequency",
 *         example="monthly"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "paid_off", "closed"},
 *         description="Debt status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Additional notes",
 *         example="Planning to pay off by end of year"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="RecordPaymentRequest",
 *     type="object",
 *     title="Record Debt Payment Request",
 *     required={"amount", "payment_date"},
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         description="Payment amount",
 *         example=200.00
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
 *         example="Extra $75 toward principal"
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
 *     schema="DebtSummary",
 *     type="object",
 *     title="Debt Summary",
 *     description="Summary statistics for all debts",
 *     @OA\Property(
 *         property="total_debts",
 *         type="integer",
 *         description="Number of active debts",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="total_original_balance",
 *         type="number",
 *         format="float",
 *         description="Sum of all original balances",
 *         example=25000.00
 *     ),
 *     @OA\Property(
 *         property="total_current_balance",
 *         type="number",
 *         format="float",
 *         description="Sum of all current balances",
 *         example=18500.00
 *     ),
 *     @OA\Property(
 *         property="total_paid_off",
 *         type="number",
 *         format="float",
 *         description="Total amount paid off",
 *         example=6500.00
 *     ),
 *     @OA\Property(
 *         property="average_interest_rate",
 *         type="number",
 *         format="float",
 *         description="Average interest rate across all debts",
 *         example=15.75
 *     ),
 *     @OA\Property(
 *         property="total_minimum_payment",
 *         type="number",
 *         format="float",
 *         description="Sum of all minimum payments",
 *         example=650.00
 *     ),
 *     @OA\Property(
 *         property="debts_by_type",
 *         type="object",
 *         description="Breakdown by debt type",
 *         @OA\AdditionalProperties(
 *             type="object",
 *             @OA\Property(property="count", type="integer", example=2),
 *             @OA\Property(property="total_balance", type="number", format="float", example=8500.00),
 *             @OA\Property(property="average_interest_rate", type="number", format="float", example=18.99),
 *             @OA\Property(property="total_minimum_payment", type="number", format="float", example=250.00)
 *         )
 *     ),
 *     @OA\Property(
 *         property="highest_interest_debt",
 *         ref="#/components/schemas/DebtResource",
 *         description="Debt with highest interest rate"
 *     ),
 *     @OA\Property(
 *         property="largest_balance_debt",
 *         ref="#/components/schemas/DebtResource",
 *         description="Debt with largest balance"
 *     ),
 *     @OA\Property(
 *         property="next_due_debt",
 *         ref="#/components/schemas/DebtResource",
 *         description="Next debt payment due"
 *     ),
 *     @OA\Property(
 *         property="progress_percentage",
 *         type="number",
 *         format="float",
 *         description="Overall debt payoff progress",
 *         example=26.0
 *     ),
 *     @OA\Property(
 *         property="estimated_payoff_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Estimated date when all debts will be paid off",
 *         example="2027-12-15"
 *     ),
 *     @OA\Property(
 *         property="estimated_total_interest",
 *         type="number",
 *         format="float",
 *         description="Estimated total interest to be paid",
 *         example=4850.00
 *     ),
 *     @OA\Property(
 *         property="recent_payments",
 *         type="array",
 *         description="Recent payment history",
 *         @OA\Items(ref="#/components/schemas/DebtPaymentResource")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DebtPayoffSchedule",
 *     type="object",
 *     title="Debt Payoff Schedule",
 *     description="Detailed payoff schedule for a debt",
 *     @OA\Property(
 *         property="debt",
 *         ref="#/components/schemas/DebtResource",
 *         description="Debt information"
 *     ),
 *     @OA\Property(
 *         property="summary",
 *         type="object",
 *         @OA\Property(property="months_to_payoff", type="integer", example=36),
 *         @OA\Property(property="payoff_date", type="string", format="date", example="2028-01-15"),
 *         @OA\Property(property="total_payments", type="number", format="float", example=4500.00),
 *         @OA\Property(property="total_interest_paid", type="number", format="float", example=1000.00),
 *         @OA\Property(property="monthly_payment", type="number", format="float", example=125.00)
 *     ),
 *     @OA\Property(
 *         property="schedule",
 *         type="array",
 *         description="Monthly payment breakdown",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="payment_number", type="integer", example=1),
 *             @OA\Property(property="date", type="string", format="date", example="2025-02-15"),
 *             @OA\Property(property="beginning_balance", type="number", format="float", example=3500.00),
 *             @OA\Property(property="payment", type="number", format="float", example=125.00),
 *             @OA\Property(property="principal", type="number", format="float", example=70.00),
 *             @OA\Property(property="interest", type="number", format="float", example=55.00),
 *             @OA\Property(property="ending_balance", type="number", format="float", example=3430.00)
 *         )
 *     ),
 *     @OA\Property(
 *         property="extra_payment_scenario",
 *         type="object",
 *         nullable=true,
 *         description="Scenario with extra payments",
 *         @OA\Property(property="extra_payment_amount", type="number", format="float", example=50.00),
 *         @OA\Property(property="months_saved", type="integer", example=12),
 *         @OA\Property(property="interest_saved", type="number", format="float", example=350.00),
 *         @OA\Property(property="new_payoff_date", type="string", format="date", example="2027-01-15")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DebtConsolidationOptions",
 *     type="object",
 *     title="Debt Consolidation Options",
 *     description="Analysis of debt consolidation scenarios",
 *     @OA\Property(
 *         property="current_situation",
 *         type="object",
 *         @OA\Property(property="total_balance", type="number", format="float", example=18500.00),
 *         @OA\Property(property="total_minimum_payment", type="number", format="float", example=650.00),
 *         @OA\Property(property="average_interest_rate", type="number", format="float", example=15.75),
 *         @OA\Property(property="number_of_debts", type="integer", example=5),
 *         @OA\Property(property="debt_types", type="array", @OA\Items(type="string", example="credit_card"))
 *     ),
 *     @OA\Property(
 *         property="consolidation_options",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="loan_term_months", type="integer", example=36),
 *             @OA\Property(property="loan_term_years", type="number", format="float", example=3.0),
 *             @OA\Property(property="interest_rate", type="number", format="float", example=12.00),
 *             @OA\Property(property="monthly_payment", type="number", format="float", example=615.00),
 *             @OA\Property(property="total_payment", type="number", format="float", example=22140.00),
 *             @OA\Property(property="total_interest", type="number", format="float", example=3640.00),
 *             @OA\Property(
 *                 property="savings",
 *                 type="object",
 *                 @OA\Property(property="monthly_payment_difference", type="number", format="float", example=35.00),
 *                 @OA\Property(property="total_interest_saved", type="number", format="float", example=1200.00),
 *                 @OA\Property(property="months_saved", type="integer", example=6)
 *             ),
 *             @OA\Property(property="is_beneficial", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Property(
 *         property="recommendation",
 *         type="string",
 *         description="Recommendation based on analysis",
 *         example="Consider the 36-month consolidation loan at 12% APR for maximum savings"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DebtTypes",
 *     type="object",
 *     title="Debt Types Configuration",
 *     description="Available debt types and their configurations",
 *     @OA\AdditionalProperties(
 *         type="object",
 *         @OA\Property(property="name", type="string", example="Credit Card"),
 *         @OA\Property(property="description", type="string", example="Credit card debt"),
 *         @OA\Property(property="icon", type="string", example="credit_card"),
 *         @OA\Property(property="color", type="string", example="#F44336"),
 *         @OA\Property(property="typical_interest_rate", type="string", example="15-25%"),
 *         @OA\Property(property="payment_frequency", type="string", example="monthly")
 *     )
 * )
 */

class DebtSchema {}
