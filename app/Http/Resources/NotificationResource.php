<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'channel' => $this->channel,
            'channel_label' => $this->getChannelLabel(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at->diffForHumans(),
            'read_at_human' => $this->read_at ? $this->read_at->diffForHumans() : null,

            // Additional computed fields
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => $this->getActionUrl(),
        ];
    }

    /**
     * Get human-readable type label
     */
    private function getTypeLabel(): string
    {
        $labels = [
            'budget_alert' => 'Budget Alert',
            'bill_reminder' => 'Bill Reminder',
            'goal_milestone' => 'Goal Milestone',
            'low_balance' => 'Low Balance',
            'system' => 'System',
            'transaction' => 'Transaction',
        ];

        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get human-readable priority label
     */
    private function getPriorityLabel(): string
    {
        $labels = [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
        ];

        return $labels[$this->priority] ?? ucfirst($this->priority);
    }

    /**
     * Get human-readable channel label
     */
    private function getChannelLabel(): string
    {
        $labels = [
            'app' => 'In-App',
            'email' => 'Email',
            'sms' => 'SMS',
        ];

        return $labels[$this->channel] ?? ucfirst($this->channel);
    }

    /**
     * Get appropriate icon based on notification type
     */
    private function getIcon(): string
    {
        $icons = [
            'budget_alert' => 'trending_up',
            'bill_reminder' => 'receipt_long',
            'goal_milestone' => 'flag',
            'low_balance' => 'account_balance_wallet',
            'system' => 'info',
            'transaction' => 'receipt',
        ];

        return $icons[$this->type] ?? 'notifications';
    }

    /**
     * Get appropriate color based on priority
     */
    private function getColor(): string
    {
        // Priority-based colors
        if ($this->priority === 'high') {
            return '#F44336'; // Red
        }

        // Type-based colors
        $colors = [
            'budget_alert' => '#FF9800', // Orange
            'bill_reminder' => '#2196F3', // Blue
            'goal_milestone' => '#4CAF50', // Green
            'low_balance' => '#F44336', // Red
            'system' => '#9E9E9E', // Grey
            'transaction' => '#673AB7', // Purple
        ];

        return $colors[$this->type] ?? '#2196F3'; // Default blue
    }

    /**
     * Get action URL based on notification type and data
     */
    private function getActionUrl(): ?string
    {
        if (empty($this->data)) {
            return null;
        }

        $baseUrl = '/'; // Adjust based on your frontend routing

        switch ($this->type) {
            case 'budget_alert':
                return isset($this->data['budget_id'])
                    ? "{$baseUrl}budgets/{$this->data['budget_id']}"
                    : "{$baseUrl}budgets";

            case 'bill_reminder':
                return isset($this->data['bill_id'])
                    ? "{$baseUrl}bills/{$this->data['bill_id']}"
                    : "{$baseUrl}bills";

            case 'goal_milestone':
                return isset($this->data['goal_id'])
                    ? "{$baseUrl}goals/{$this->data['goal_id']}"
                    : "{$baseUrl}goals";

            case 'low_balance':
                return isset($this->data['account_id'])
                    ? "{$baseUrl}accounts/{$this->data['account_id']}"
                    : "{$baseUrl}accounts";

            case 'transaction':
                return isset($this->data['transaction_id'])
                    ? "{$baseUrl}transactions/{$this->data['transaction_id']}"
                    : "{$baseUrl}transactions";

            case 'system':
                return "{$baseUrl}settings";

            default:
                return null;
        }
    }
}
