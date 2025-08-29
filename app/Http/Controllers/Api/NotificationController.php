<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Get all notifications with filtering and pagination
     *
     * @OA\Get(
     *     path="/api/notifications",
     *     summary="Get all notifications with filtering and pagination",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by notification type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"budget_alert","bill_reminder","goal_milestone","low_balance","system","transaction"})
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low","normal","high"})
     *     ),
     *     @OA\Parameter(
     *         name="is_read",
     *         in="query",
     *         description="Filter by read status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="channel",
     *         in="query",
     *         description="Filter by channel",
     *         required=false,
     *         @OA\Schema(type="string", enum={"app","email","sms"})
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter notifications from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter notifications until this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/NotificationResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="unread_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'in:budget_alert,bill_reminder,goal_milestone,low_balance,system,transaction'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'is_read' => ['nullable', 'boolean'],
            'channel' => ['nullable', 'string', 'in:app,email,sms'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $query = $request->user()->notifications();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Date filtering
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Sort by created_at descending (newest first) by default
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 20);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $request->user()->notifications()->where('is_read', false)->count(),
            ]
        ]);
    }

    /**
     * Create a new notification
     *
     * @OA\Post(
     *     path="/api/notifications",
     *     summary="Create a new notification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","title","message"},
     *             @OA\Property(property="type", type="string", enum={"budget_alert","bill_reminder","goal_milestone","low_balance","system","transaction"}),
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="priority", type="string", enum={"low","normal","high"}),
     *             @OA\Property(property="channel", type="string", enum={"app","email","sms"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Notification created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:budget_alert,bill_reminder,goal_milestone,low_balance,system,transaction'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'channel' => ['nullable', 'string', 'in:app,email,sms'],
        ]);

        $notification = $request->user()->notifications()->create([
            'type' => $request->type,
            'title' => $request->title,
            'message' => $request->message,
            'data' => $request->input('data', []),
            'priority' => $request->input('priority', 'normal'),
            'channel' => $request->input('channel', 'app'),
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => new NotificationResource($notification)
        ], 201);
    }

    /**
     * Get a specific notification
     *
     * @OA\Get(
     *     path="/api/notifications/{id}",
     *     summary="Get a specific notification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, Notification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification)
        ]);
    }

    /**
     * Mark a notification as read
     *
     * @OA\Put(
     *     path="/api/notifications/{id}/read",
     *     summary="Mark a notification as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => new NotificationResource($notification)
        ]);
    }

    /**
     * Delete a notification
     *
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     summary="Delete a notification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @OA\Put(
     *     path="/api/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="updated_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updated} notifications as read",
            'data' => [
                'updated_count' => $updated
            ]
        ]);
    }

    /**
     * Get unread notifications count
     *
     * @OA\Get(
     *     path="/api/notifications/status/unread-count",
     *     summary="Get unread notifications count",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_unread", type="integer"),
     *                 @OA\Property(property="by_type", type="object"),
     *                 @OA\Property(property="by_priority", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()
            ->where('is_read', false)
            ->count();

        $countByType = $request->user()->notifications()
            ->where('is_read', false)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type');

        $countByPriority = $request->user()->notifications()
            ->where('is_read', false)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return response()->json([
            'success' => true,
            'data' => [
                'total_unread' => $count,
                'by_type' => $countByType,
                'by_priority' => $countByPriority
            ]
        ]);
    }

    /**
     * Get notification settings
     *
     * @OA\Get(
     *     path="/api/notifications/user/settings",
     *     summary="Get notification settings",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="channels", type="object",
     *                     @OA\Property(property="app", type="boolean"),
     *                     @OA\Property(property="email", type="boolean"),
     *                     @OA\Property(property="sms", type="boolean")
     *                 ),
     *                 @OA\Property(property="types", type="object",
     *                     @OA\Property(property="budget_alert", type="boolean"),
     *                     @OA\Property(property="bill_reminder", type="boolean"),
     *                     @OA\Property(property="goal_milestone", type="boolean"),
     *                     @OA\Property(property="low_balance", type="boolean"),
     *                     @OA\Property(property="system", type="boolean"),
     *                     @OA\Property(property="transaction", type="boolean")
     *                 ),
     *                 @OA\Property(property="preferences", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = [
            'channels' => [
                'app' => $user->getSetting('notifications_app', true),
                'email' => $user->getSetting('notifications_email', true),
                'sms' => $user->getSetting('notifications_sms', false),
            ],
            'types' => [
                'budget_alert' => $user->getSetting('notify_budget_alerts', true),
                'bill_reminder' => $user->getSetting('notify_bill_reminders', true),
                'goal_milestone' => $user->getSetting('notify_goal_milestones', true),
                'low_balance' => $user->getSetting('notify_low_balance', true),
                'transaction' => $user->getSetting('notify_transactions', false),
                'system' => $user->getSetting('notify_system', true),
            ],
            'preferences' => [
                'quiet_hours_enabled' => $user->getSetting('quiet_hours_enabled', false),
                'quiet_hours_start' => $user->getSetting('quiet_hours_start', '22:00'),
                'quiet_hours_end' => $user->getSetting('quiet_hours_end', '08:00'),
                'reminder_days_before' => $user->getSetting('reminder_days_before', 3),
                'low_balance_threshold' => $user->getSetting('low_balance_threshold', 1000),
                'budget_alert_percentage' => $user->getSetting('budget_alert_percentage', 80),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update notification settings
     *
     * @OA\Put(
     *     path="/api/notifications/user/settings",
     *     summary="Update notification settings",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="channels", type="object",
     *                 @OA\Property(property="app", type="boolean"),
     *                 @OA\Property(property="email", type="boolean"),
     *                 @OA\Property(property="sms", type="boolean")
     *             ),
     *             @OA\Property(property="types", type="object",
     *                 @OA\Property(property="budget_alert", type="boolean"),
     *                 @OA\Property(property="bill_reminder", type="boolean"),
     *                 @OA\Property(property="goal_milestone", type="boolean"),
     *                 @OA\Property(property="low_balance", type="boolean"),
     *                 @OA\Property(property="system", type="boolean"),
     *                 @OA\Property(property="transaction", type="boolean")
     *             ),
     *             @OA\Property(property="preferences", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.app' => ['nullable', 'boolean'],
            'channels.email' => ['nullable', 'boolean'],
            'channels.sms' => ['nullable', 'boolean'],

            'types' => ['nullable', 'array'],
            'types.budget_alert' => ['nullable', 'boolean'],
            'types.bill_reminder' => ['nullable', 'boolean'],
            'types.goal_milestone' => ['nullable', 'boolean'],
            'types.low_balance' => ['nullable', 'boolean'],
            'types.transaction' => ['nullable', 'boolean'],
            'types.system' => ['nullable', 'boolean'],

            'preferences' => ['nullable', 'array'],
            'preferences.quiet_hours_enabled' => ['nullable', 'boolean'],
            'preferences.quiet_hours_start' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'preferences.quiet_hours_end' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'preferences.reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'preferences.low_balance_threshold' => ['nullable', 'numeric', 'min:0'],
            'preferences.budget_alert_percentage' => ['nullable', 'integer', 'min:50', 'max:100'],
        ]);

        $user = $request->user();

        // Update channel settings
        if ($request->has('channels')) {
            foreach ($request->input('channels', []) as $channel => $enabled) {
                $user->setSetting("notifications_{$channel}", $enabled);
            }
        }

        // Update type settings
        if ($request->has('types')) {
            foreach ($request->input('types', []) as $type => $enabled) {
                $user->setSetting("notify_{$type}", $enabled);
            }
        }

        // Update preference settings
        if ($request->has('preferences')) {
            foreach ($request->input('preferences', []) as $key => $value) {
                $user->setSetting($key, $value);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully'
        ]);
    }

    /**
     * Delete multiple notifications
     * DELETE /api/notifications/bulk
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => ['required', 'array', 'min:1'],
            'notification_ids.*' => ['required', 'integer', 'exists:notifications,id'],
        ]);

        $deleted = $request->user()->notifications()
            ->whereIn('id', $request->notification_ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} notifications",
            'data' => [
                'deleted_count' => $deleted
            ]
        ]);
    }

    /**
     * Get notification statistics
     * GET /api/notifications/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:today,week,month,year'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'month');

        // Determine date range based on period
        $startDate = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $query = $user->notifications()
            ->where('created_at', '>=', $startDate);

        $stats = [
            'total_notifications' => $query->count(),
            'unread_notifications' => $query->where('is_read', false)->count(),
            'read_notifications' => $query->where('is_read', true)->count(),

            'by_type' => $user->notifications()
                ->where('created_at', '>=', $startDate)
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type'),

            'by_priority' => $user->notifications()
                ->where('created_at', '>=', $startDate)
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),

            'by_channel' => $user->notifications()
                ->where('created_at', '>=', $startDate)
                ->select('channel', DB::raw('count(*) as count'))
                ->groupBy('channel')
                ->pluck('count', 'channel'),

            'daily_trend' => $this->getDailyTrend($user, $startDate),

            'average_read_time' => $this->getAverageReadTime($user, $startDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
        ]);
    }

    /**
     * Test notification sending (for development/testing)
     * POST /api/notifications/test
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:budget_alert,bill_reminder,goal_milestone,low_balance,system,transaction'],
            'channel' => ['nullable', 'string', 'in:app,email,sms'],
        ]);

        $type = $request->type;
        $channel = $request->input('channel', 'app');

        // Create test notification based on type
        $testData = $this->getTestNotificationData($type);

        $notification = $request->user()->notifications()->create([
            'type' => $type,
            'title' => $testData['title'],
            'message' => $testData['message'],
            'data' => $testData['data'],
            'priority' => $testData['priority'],
            'channel' => $channel,
            'is_read' => false,
        ]);

        // Here you would normally trigger the actual notification sending
        // For example, sending email, SMS, or push notification
        // This is just creating the notification record

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'data' => new NotificationResource($notification)
        ]);
    }

    /**
     * Get daily notification trend
     */
    private function getDailyTrend($user, Carbon $startDate): array
    {
        $endDate = Carbon::now();
        $days = $startDate->diffInDays($endDate) + 1;

        $trend = [];
        for ($i = 0; $i < min($days, 30); $i++) {
            $date = $startDate->copy()->addDays($i);
            $count = $user->notifications()
                ->whereDate('created_at', $date)
                ->count();

            $trend[] = [
                'date' => $date->toDateString(),
                'count' => $count
            ];
        }

        return $trend;
    }

    /**
     * Calculate average time to read notifications
     */
    private function getAverageReadTime($user, Carbon $startDate): ?string
    {
        $readNotifications = $user->notifications()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('read_at')
            ->get();

        if ($readNotifications->isEmpty()) {
            return null;
        }

        $totalMinutes = 0;
        $count = 0;

        foreach ($readNotifications as $notification) {
            $minutes = $notification->created_at->diffInMinutes($notification->read_at);
            if ($minutes < 10080) { // Ignore if more than 7 days (likely not accurate)
                $totalMinutes += $minutes;
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        $averageMinutes = $totalMinutes / $count;

        if ($averageMinutes < 60) {
            return round($averageMinutes) . ' minutes';
        } elseif ($averageMinutes < 1440) {
            return round($averageMinutes / 60, 1) . ' hours';
        } else {
            return round($averageMinutes / 1440, 1) . ' days';
        }
    }

    /**
     * Get test notification data based on type
     */
    private function getTestNotificationData(string $type): array
    {
        $templates = [
            'budget_alert' => [
                'title' => 'Budget Alert: Groceries',
                'message' => 'You have used 85% of your Groceries budget for this month.',
                'data' => ['budget_id' => 1, 'percentage' => 85, 'category' => 'Groceries'],
                'priority' => 'high',
            ],
            'bill_reminder' => [
                'title' => 'Bill Due Soon: Internet',
                'message' => 'Your Internet bill of ₱1,500 is due in 3 days.',
                'data' => ['bill_id' => 1, 'amount' => 1500, 'days_until_due' => 3],
                'priority' => 'normal',
            ],
            'goal_milestone' => [
                'title' => 'Goal Milestone Reached!',
                'message' => 'Congratulations! You\'ve reached 50% of your Vacation Fund goal.',
                'data' => ['goal_id' => 1, 'percentage' => 50, 'goal_name' => 'Vacation Fund'],
                'priority' => 'normal',
            ],
            'low_balance' => [
                'title' => 'Low Balance Alert',
                'message' => 'Your Savings Account balance is below ₱1,000.',
                'data' => ['account_id' => 1, 'balance' => 850, 'threshold' => 1000],
                'priority' => 'high',
            ],
            'system' => [
                'title' => 'System Update',
                'message' => 'New features have been added to improve your experience.',
                'data' => ['update_version' => '1.2.0'],
                'priority' => 'low',
            ],
            'transaction' => [
                'title' => 'New Transaction Added',
                'message' => 'A transaction of ₱500 has been recorded in your Cash account.',
                'data' => ['transaction_id' => 1, 'amount' => 500, 'account' => 'Cash'],
                'priority' => 'low',
            ],
        ];

        return $templates[$type] ?? $templates['system'];
    }
}
