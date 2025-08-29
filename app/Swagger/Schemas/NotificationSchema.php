<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="NotificationResource",
 *     title="Notification Resource",
 *     description="Notification resource representation",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Notification ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Notification type",
 *         enum={"budget_alert", "bill_reminder", "goal_milestone", "low_balance", "system", "transaction"},
 *         example="budget_alert"
 *     ),
 *     @OA\Property(
 *         property="type_label",
 *         type="string",
 *         description="Human-readable type label",
 *         example="Budget Alert"
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Notification title",
 *         maxLength=255,
 *         example="Budget Alert: Groceries"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Notification message",
 *         example="You have used 85% of your Groceries budget for this month."
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Additional notification data",
 *         nullable=true,
 *         example={"budget_id": 1, "percentage": 85, "category": "Groceries"}
 *     ),
 *     @OA\Property(
 *         property="is_read",
 *         type="boolean",
 *         description="Read status",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="read_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when notification was read",
 *         nullable=true,
 *         example="2025-01-15T14:30:00.000000Z"
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         description="Notification priority",
 *         enum={"low", "normal", "high"},
 *         example="high"
 *     ),
 *     @OA\Property(
 *         property="priority_label",
 *         type="string",
 *         description="Human-readable priority label",
 *         example="High"
 *     ),
 *     @OA\Property(
 *         property="channel",
 *         type="string",
 *         description="Notification channel",
 *         enum={"app", "email", "sms"},
 *         example="app"
 *     ),
 *     @OA\Property(
 *         property="channel_label",
 *         type="string",
 *         description="Human-readable channel label",
 *         example="In-App"
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
 *     ),
 *     @OA\Property(
 *         property="created_at_human",
 *         type="string",
 *         description="Human-readable creation time",
 *         example="2 hours ago"
 *     ),
 *     @OA\Property(
 *         property="read_at_human",
 *         type="string",
 *         description="Human-readable read time",
 *         nullable=true,
 *         example="30 minutes ago"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         description="Icon identifier for the notification type",
 *         example="trending_up"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         description="Color for the notification type",
 *         example="warning"
 *     ),
 *     @OA\Property(
 *         property="action_url",
 *         type="string",
 *         description="URL for related action",
 *         nullable=true,
 *         example="/budgets/1"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="NotificationCollection",
 *     title="Notification Collection",
 *     description="Collection of notification resources",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/NotificationResource")
 *     ),
 *     @OA\Property(
 *         property="links",
 *         type="object",
 *         @OA\Property(property="first", type="string", example="http://api.example.com/notifications?page=1"),
 *         @OA\Property(property="last", type="string", example="http://api.example.com/notifications?page=10"),
 *         @OA\Property(property="prev", type="string", nullable=true, example=null),
 *         @OA\Property(property="next", type="string", nullable=true, example="http://api.example.com/notifications?page=2")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="from", type="integer", example=1),
 *         @OA\Property(property="last_page", type="integer", example=10),
 *         @OA\Property(property="links", type="array",
 *             @OA\Items(
 *                 @OA\Property(property="url", type="string", nullable=true),
 *                 @OA\Property(property="label", type="string"),
 *                 @OA\Property(property="active", type="boolean")
 *             )
 *         ),
 *         @OA\Property(property="path", type="string", example="http://api.example.com/notifications"),
 *         @OA\Property(property="per_page", type="integer", example=20),
 *         @OA\Property(property="to", type="integer", example=20),
 *         @OA\Property(property="total", type="integer", example=200)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="NotificationCreateRequest",
 *     title="Notification Create Request",
 *     description="Request body for creating a notification",
 *     type="object",
 *     required={"type", "title", "message"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Notification type",
 *         enum={"budget_alert", "bill_reminder", "goal_milestone", "low_balance", "system", "transaction"},
 *         example="budget_alert"
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Notification title",
 *         maxLength=255,
 *         example="Budget Alert: Groceries"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Notification message",
 *         example="You have used 85% of your Groceries budget for this month."
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Additional notification data",
 *         nullable=true,
 *         example={"budget_id": 1, "percentage": 85}
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         description="Notification priority",
 *         enum={"low", "normal", "high"},
 *         default="normal",
 *         example="high"
 *     ),
 *     @OA\Property(
 *         property="channel",
 *         type="string",
 *         description="Notification channel",
 *         enum={"app", "email", "sms"},
 *         default="app",
 *         example="app"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="NotificationSettingsResponse",
 *     title="Notification Settings Response",
 *     description="User notification settings",
 *     type="object",
 *     @OA\Property(
 *         property="channels",
 *         type="object",
 *         description="Enabled notification channels",
 *         @OA\Property(property="app", type="boolean", example=true),
 *         @OA\Property(property="email", type="boolean", example=true),
 *         @OA\Property(property="sms", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="types",
 *         type="object",
 *         description="Enabled notification types",
 *         @OA\Property(property="budget_alert", type="boolean", example=true),
 *         @OA\Property(property="bill_reminder", type="boolean", example=true),
 *         @OA\Property(property="goal_milestone", type="boolean", example=true),
 *         @OA\Property(property="low_balance", type="boolean", example=true),
 *         @OA\Property(property="system", type="boolean", example=true),
 *         @OA\Property(property="transaction", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="preferences",
 *         type="object",
 *         description="Additional preferences",
 *         @OA\Property(property="quiet_hours_enabled", type="boolean", example=true),
 *         @OA\Property(property="quiet_hours_start", type="string", example="22:00"),
 *         @OA\Property(property="quiet_hours_end", type="string", example="08:00"),
 *         @OA\Property(property="alert_threshold_percentage", type="integer", example=75),
 *         @OA\Property(property="bill_reminder_days", type="integer", example=3)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="NotificationUnreadCountResponse",
 *     title="Notification Unread Count Response",
 *     description="Unread notification count breakdown",
 *     type="object",
 *     @OA\Property(
 *         property="total_unread",
 *         type="integer",
 *         description="Total number of unread notifications",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="by_type",
 *         type="object",
 *         description="Unread count by notification type",
 *         @OA\Property(property="budget_alert", type="integer", example=2),
 *         @OA\Property(property="bill_reminder", type="integer", example=1),
 *         @OA\Property(property="goal_milestone", type="integer", example=0),
 *         @OA\Property(property="low_balance", type="integer", example=1),
 *         @OA\Property(property="system", type="integer", example=1),
 *         @OA\Property(property="transaction", type="integer", example=0)
 *     ),
 *     @OA\Property(
 *         property="by_priority",
 *         type="object",
 *         description="Unread count by priority",
 *         @OA\Property(property="low", type="integer", example=1),
 *         @OA\Property(property="normal", type="integer", example=2),
 *         @OA\Property(property="high", type="integer", example=2)
 *     )
 * )
 */

class NotificationSchema
{
}
