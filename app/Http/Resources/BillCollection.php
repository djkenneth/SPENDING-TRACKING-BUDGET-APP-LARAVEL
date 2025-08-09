<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BillCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_amount' => $this->collection->sum('amount'),
                'active_bills' => $this->collection->where('status', 'active')->count(),
                'overdue_bills' => $this->collection->where('status', 'overdue')->count(),
                'paid_bills' => $this->collection->where('status', 'paid')->count(),
            ],
        ];
    }
}
