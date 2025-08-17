<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialGoal\CreateFinancialGoalRequest;
use App\Http\Requests\FinancialGoal\UpdateFinancialGoalRequest;
use App\Http\Requests\FinancialGoal\ContributeToGoalRequest;
use App\Http\Resources\FinancialGoalResource;
use App\Http\Resources\GoalProgressResource;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use App\Services\FinancialGoalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialGoalController extends Controller
{
    protected FinancialGoalService $goalService;

    public function __construct(FinancialGoalService $goalService)
    {
        $this->goalService = $goalService;
    }

    /**
     * Get all financial goals
     * GET /api/goals
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:active,completed,paused,cancelled'],
            'priority' => ['nullable', 'string', 'in:high,medium,low'],
            'include_completed' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name,target_date,target_amount,priority,progress'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = $request->user()->financialGoals();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (!$request->boolean('include_completed')) {
            $query->whereIn('status', ['active', 'paused']);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'target_date');
        $sortOrder = $request->input('sort_order', 'asc');

        if ($sortBy === 'progress') {
            $query->selectRaw('*, (current_amount / target_amount * 100) as progress_percentage')
                  ->orderBy('progress_percentage', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $goals = $query->with('contributions')->get();

        // Calculate additional metrics
        $totalTargetAmount = $goals->sum('target_amount');
        $totalCurrentAmount = $goals->sum('current_amount');
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'data' => FinancialGoalResource::collection($goals),
            'meta' => [
                'total' => $goals->count(),
                'active_goals' => $activeGoalsCount,
                'completed_goals' => $completedGoalsCount,
                'total_target_amount' => $totalTargetAmount,
                'total_current_amount' => $totalCurrentAmount,
                'overall_progress' => $totalTargetAmount > 0
                    ? round(($totalCurrentAmount / $totalTargetAmount) * 100, 2)
                    : 0,
                'currency' => $request->user()->currency,
                'currency_symbol' => $request->user()->getCurrencySymbol(),
            ]
        ]);
    }

    /**
     * Create new financial goal
     * POST /api/goals
     */
    public function store(CreateFinancialGoalRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $goalData = $request->validated();

            // Calculate monthly target if not provided
            if (!isset($goalData['monthly_target'])) {
                $monthsRemaining = now()->diffInMonths(Carbon::parse($goalData['target_date']));
                if ($monthsRemaining > 0) {
                    $goalData['monthly_target'] = round($goalData['target_amount'] / $monthsRemaining, 2);
                }
            }

            // Set default milestone settings if not provided
            if (!isset($goalData['milestone_settings'])) {
                $goalData['milestone_settings'] = [
                    'milestones' => [25, 50, 75, 100],
                    'notifications_enabled' => true,
                ];
            }

            $goal = $request->user()->financialGoals()->create($goalData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Financial goal created successfully',
                'data' => new FinancialGoalResource($goal)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create financial goal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific financial goal
     * GET /api/goals/{id}
     */
    public function show(Request $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        $goal->load('contributions');

        // Calculate additional progress metrics
        $progressPercentage = $goal->target_amount > 0
            ? ($goal->current_amount / $goal->target_amount) * 100
            : 0;

        $daysRemaining = now()->diffInDays($goal->target_date, false);
        $monthsRemaining = now()->diffInMonths($goal->target_date, false);

        // Calculate required monthly contribution
        $remainingAmount = max(0, $goal->target_amount - $goal->current_amount);
        $requiredMonthlyContribution = $monthsRemaining > 0
            ? round($remainingAmount / $monthsRemaining, 2)
            : $remainingAmount;

        // Get contribution history
        $contributionHistory = $goal->contributions()
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => new FinancialGoalResource($goal),
            'metrics' => [
                'progress_percentage' => round($progressPercentage, 2),
                'days_remaining' => max(0, $daysRemaining),
                'months_remaining' => max(0, $monthsRemaining),
                'remaining_amount' => $remainingAmount,
                'required_monthly_contribution' => $requiredMonthlyContribution,
                // 'average_monthly_contribution' => $this->goalService->calculateAverageMonthlyContribution($goal),
                'is_on_track' => $this->goalService->isGoalOnTrack($goal),
                // 'projected_completion_date' => $this->goalService->projectCompletionDate($goal),
            ],
            'contribution_history' => $contributionHistory,
        ]);
    }

    /**
     * Update financial goal
     * PUT /api/goals/{id}
     */
    public function update(UpdateFinancialGoalRequest $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->validated();

            // Recalculate monthly target if target amount or date changed
            if (isset($updateData['target_amount']) || isset($updateData['target_date'])) {
                $targetAmount = $updateData['target_amount'] ?? $goal->target_amount;
                $targetDate = isset($updateData['target_date'])
                    ? Carbon::parse($updateData['target_date'])
                    : $goal->target_date;

                $monthsRemaining = now()->diffInMonths($targetDate);
                if ($monthsRemaining > 0) {
                    $remainingAmount = max(0, $targetAmount - $goal->current_amount);
                    $updateData['monthly_target'] = round($remainingAmount / $monthsRemaining, 2);
                }
            }

            // Handle status change to completed
            if (isset($updateData['status']) && $updateData['status'] === 'completed' && $goal->status !== 'completed') {
                $updateData['completed_at'] = now();
            }

            $goal->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Financial goal updated successfully',
                'data' => new FinancialGoalResource($goal->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update financial goal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete financial goal
     * DELETE /api/goals/{id}
     */
    public function destroy(Request $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Store goal info for response
            $goalName = $goal->name;
            $goalId = $goal->id;

            // Soft delete the goal (contributions will be cascade deleted)
            $goal->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Financial goal '{$goalName}' deleted successfully",
                'data' => [
                    'deleted_goal_id' => $goalId
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete financial goal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add money to goal (contribute)
     * POST /api/goals/{id}/contribute
     */
    public function contribute(ContributeToGoalRequest $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        // Check if goal is active
        if ($goal->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot contribute to a goal that is not active'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $contributionData = $request->validated();

            // Create the contribution
            $contribution = $goal->contributions()->create([
                'amount' => $contributionData['amount'],
                'date' => $contributionData['date'] ?? now()->toDateString(),
                'transaction_id' => $contributionData['transaction_id'] ?? null,
                'notes' => $contributionData['notes'] ?? null,
            ]);

            // Update goal's current amount
            $goal->increment('current_amount', $contributionData['amount']);

            // Check if goal is now completed
            if ($goal->current_amount >= $goal->target_amount) {
                $goal->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Create notification for goal completion
                $this->goalService->createGoalCompletionNotification($goal);
            } else {
                // Check for milestone achievements
                $this->goalService->checkMilestoneAchievements($goal);
            }

            DB::commit();

            $goal->load('contributions');

            return response()->json([
                'success' => true,
                'message' => 'Contribution added successfully',
                'data' => [
                    'goal' => new FinancialGoalResource($goal),
                    'contribution' => $contribution,
                    'is_completed' => $goal->status === 'completed',
                    'progress_percentage' => round(($goal->current_amount / $goal->target_amount) * 100, 2),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add contribution: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get goal progress details
     * GET /api/goals/{id}/progress
     */
    public function progress(Request $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        $request->validate([
            'period' => ['nullable', 'string', 'in:weekly,monthly,yearly,all'],
        ]);

        $period = $request->input('period', 'monthly');

        // Get contribution statistics
        // $contributionStats = $this->goalService->getContributionStatistics($goal, $period);

        // Get progress timeline
        $progressTimeline = $this->goalService->getProgressTimeline($goal, $period);

        // Calculate projection
        // $projection = $this->goalService->calculateGoalProjection($goal);

        // Get milestones status
        $milestones = $this->goalService->getMilestoneStatus($goal);

        return response()->json([
            'success' => true,
            // 'data' => new GoalProgressResource($goal),
            // 'statistics' => $contributionStats,
            'timeline' => $progressTimeline,
            // 'projection' => $projection,
            'milestones' => $milestones,
        ]);
    }

    /**
     * Mark goal as completed
     * POST /api/goals/{id}/complete
     */
    public function complete(Request $request, FinancialGoal $goal): JsonResponse
    {
        // Check if the goal belongs to the authenticated user
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this goal'
            ], 403);
        }

        // Check if goal is already completed
        if ($goal->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'This goal is already marked as completed'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Mark goal as completed
            $goal->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_amount' => $goal->target_amount, // Set current to target
            ]);

            // Create completion notification
            $this->goalService->createGoalCompletionNotification($goal);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Goal marked as completed successfully',
                'data' => new FinancialGoalResource($goal->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete goal: ' . $e->getMessage()
            ], 500);
        }
    }
}
