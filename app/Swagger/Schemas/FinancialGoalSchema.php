<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="FinancialGoalResource",
 *     type="object",
 *     title="Financial Goal Resource",
 *     description="Financial goal resource representation",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Goal ID",
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
 *         description="Goal name",
 *         example="Emergency Fund"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Goal description",
 *         example="Save 6 months of living expenses for emergencies"
 *     ),
 *     @OA\Property(
 *         property="target_amount",
 *         type="number",
 *         format="float",
 *         description="Target amount to save",
 *         example=10000.00
 *     ),
 *     @OA\Property(
 *         property="current_amount",
 *         type="number",
 *         format="float",
 *         description="Current saved amount",
 *         example=3500.00
 *     ),
 *     @OA\Property(
 *         property="remaining_amount",
 *         type="number",
 *         format="float",
 *         description="Amount remaining to reach goal",
 *         example=6500.00
 *     ),
 *     @OA\Property(
 *         property="progress_percentage",
 *         type="number",
 *         format="float",
 *         description="Percentage of goal completed",
 *         example=35.0
 *     ),
 *     @OA\Property(
 *         property="target_date",
 *         type="string",
 *         format="date",
 *         description="Target completion date",
 *         example="2025-12-31"
 *     ),
 *     @OA\Property(
 *         property="days_remaining",
 *         type="integer",
 *         description="Days until target date",
 *         example=320
 *     ),
 *     @OA\Property(
 *         property="months_remaining",
 *         type="integer",
 *         description="Months until target date",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         enum={"high", "medium", "low"},
 *         description="Goal priority level",
 *         example="high"
 *     ),
 *     @OA\Property(
 *         property="priority_label",
 *         type="string",
 *         description="Human-readable priority",
 *         example="High Priority"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "completed", "paused", "cancelled"},
 *         description="Goal status",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="status_label",
 *         type="string",
 *         description="Human-readable status",
 *         example="Active"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Goal color for UI display",
 *         example="#2196F3"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Goal icon identifier",
 *         example="flag"
 *     ),
 *     @OA\Property(
 *         property="monthly_target",
 *         type="number",
 *         format="float",
 *         nullable=true,
 *         description="Suggested monthly contribution",
 *         example=650.00
 *     ),
 *     @OA\Property(
 *         property="required_monthly_contribution",
 *         type="number",
 *         format="float",
 *         description="Required monthly contribution to meet target",
 *         example=650.00
 *     ),
 *     @OA\Property(
 *         property="milestone_settings",
 *         type="object",
 *         nullable=true,
 *         description="Milestone configuration",
 *         @OA\Property(
 *             property="milestones",
 *             type="array",
 *             @OA\Items(type="integer", example=25),
 *             description="Percentage milestones"
 *         ),
 *         @OA\Property(
 *             property="notifications_enabled",
 *             type="boolean",
 *             description="Whether milestone notifications are enabled",
 *             example=true
 *         )
 *     ),
 *     @OA\Property(
 *         property="current_milestone",
 *         type="integer",
 *         description="Current achieved milestone percentage",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="next_milestone",
 *         type="integer",
 *         nullable=true,
 *         description="Next milestone percentage",
 *         example=50
 *     ),
 *     @OA\Property(
 *         property="is_on_track",
 *         type="boolean",
 *         description="Whether goal is on track to meet target date",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="projected_completion_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Projected completion based on current progress",
 *         example="2025-11-15"
 *     ),
 *     @OA\Property(
 *         property="contributions",
 *         type="array",
 *         description="Goal contributions",
 *         @OA\Items(ref="#/components/schemas/GoalContributionResource")
 *     ),
 *     @OA\Property(
 *         property="latest_contribution",
 *         ref="#/components/schemas/GoalContributionResource",
 *         description="Most recent contribution"
 *     ),
 *     @OA\Property(
 *         property="contributions_summary",
 *         type="object",
 *         @OA\Property(property="total_contributions", type="integer", example=15),
 *         @OA\Property(property="total_amount", type="number", format="float", example=3500.00),
 *         @OA\Property(property="average_contribution", type="number", format="float", example=233.33),
 *         @OA\Property(property="largest_contribution", type="number", format="float", example=500.00),
 *         @OA\Property(property="this_month_total", type="number", format="float", example=650.00),
 *         @OA\Property(property="last_month_total", type="number", format="float", example=600.00)
 *     ),
 *     @OA\Property(
 *         property="completed_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Completion timestamp",
 *         example="2025-10-15T14:30:00.000000Z"
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
 *     schema="GoalContributionResource",
 *     type="object",
 *     title="Goal Contribution Resource",
 *     description="Goal contribution record",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Contribution ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="financial_goal_id",
 *         type="integer",
 *         description="Associated goal ID",
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
 *         description="Contribution amount",
 *         example=500.00
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         description="Contribution date",
 *         example="2025-01-15"
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         nullable=true,
 *         description="Contribution notes",
 *         example="Bonus contribution from tax refund"
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
 */

class FinancialGoalSchema {}
