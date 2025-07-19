<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'balance' => $this->balance,
            'formatted_balance' => $this->getFormattedBalance(),
            'credit_limit' => $this->credit_limit,
            'formatted_credit_limit' => $this->getFormattedCreditLimit(),
            'available_credit' => $this->getAvailableCredit(),
            'formatted_available_credit' => $this->getFormattedAvailableCredit(),
            'currency' => $this->currency,
            'currency_symbol' => $this->getCurrencySymbol(),
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'include_in_net_worth' => $this->include_in_net_worth,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'statistics' => $this->when($request->routeIs('accounts.show'), function () {
                return [
                    'transaction_count' => $this->transactions()->count(),
                    'last_transaction_date' => $this->transactions()->latest('date')->first()?->date,
                    'monthly_average' => $this->getMonthlyAverage(),
                    'credit_utilization' => $this->getCreditUtilization(),
                ];
            }),
        ];
    }

    /**
     * Get the account type label
     */
    private function getTypeLabel(): string
    {
        $labels = [
            'cash' => 'Cash',
            'bank' => 'Bank Account',
            'credit_card' => 'Credit Card',
            'investment' => 'Investment',
            'ewallet' => 'E-Wallet',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get formatted balance
     */
    private function getFormattedBalance(): string
    {
        $symbol = $this->getCurrencySymbol();
        $balance = $this->balance;

        if ($this->type === 'credit_card' && $balance < 0) {
            return $symbol . number_format(abs($balance), 2);
        }

        return ($balance < 0 ? '-' : '') . $symbol . number_format(abs($balance), 2);
    }

    /**
     * Get formatted credit limit
     */
    private function getFormattedCreditLimit(): ?string
    {
        if (!$this->credit_limit) {
            return null;
        }

        return $this->getCurrencySymbol() . number_format($this->credit_limit, 2);
    }

    /**
     * Get available credit
     */
    private function getAvailableCredit(): ?float
    {
        if (!$this->credit_limit || $this->type !== 'credit_card') {
            return null;
        }

        return $this->credit_limit - abs($this->balance);
    }

    /**
     * Get formatted available credit
     */
    private function getFormattedAvailableCredit(): ?string
    {
        $availableCredit = $this->getAvailableCredit();

        if ($availableCredit === null) {
            return null;
        }

        return $this->getCurrencySymbol() . number_format($availableCredit, 2);
    }

    /**
     * Get currency symbol
     */
    private function getCurrencySymbol(): string
    {
        $currencies = config('user.currencies', []);
        return $currencies[$this->currency]['symbol'] ?? $this->currency;
    }

    /**
     * Get monthly average spending/income for this account
     */
    private function getMonthlyAverage(): array
    {
        $startDate = now()->subMonths(6)->startOfMonth();
        $endDate = now()->endOfMonth();

        $income = $this->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->avg('amount') ?? 0;

        $expenses = $this->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->avg('amount') ?? 0;

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($income - $expenses, 2),
        ];
    }

    /**
     * Get credit utilization percentage
     */
    private function getCreditUtilization(): ?float
    {
        if ($this->type !== 'credit_card' || !$this->credit_limit) {
            return null;
        }

        $utilization = (abs($this->balance) / $this->credit_limit) * 100;
        return round($utilization, 2);
    }
}
