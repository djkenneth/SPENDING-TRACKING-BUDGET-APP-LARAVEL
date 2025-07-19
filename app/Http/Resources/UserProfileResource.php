<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserProfileResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'currency' => $this->currency,
            'currency_symbol' => $this->getCurrencySymbol(),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'preferences' => $this->preferences,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'statistics' => [
                'net_worth' => $this->net_worth,
                'total_accounts' => $this->accounts()->where('is_active', true)->count(),
                'total_transactions' => $this->transactions()->count(),
                'unread_notifications' => $this->getUnreadNotificationsCount(),
            ]
        ];
    }
}
