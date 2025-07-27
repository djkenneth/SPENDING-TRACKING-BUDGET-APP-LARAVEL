<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'date' => 'date',
        ];
    }

    /**
     * Get exchange rate between two currencies for a specific date
     */
    public static function getRate(string $fromCurrency, string $toCurrency, ?string $date = null): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now()->format('Y-m-d');

        $rate = static::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($rate) {
            return $rate->rate;
        }

        // Try reverse rate
        $reverseRate = static::where('from_currency', $toCurrency)
            ->where('to_currency', $fromCurrency)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($reverseRate && $reverseRate->rate > 0) {
            return 1 / $reverseRate->rate;
        }

        return null;
    }

    /**
     * Convert amount from one currency to another
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency, ?string $date = null): ?float
    {
        $rate = static::getRate($fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Get the latest exchange rate for a currency pair
     */
    public static function getLatestRate(string $fromCurrency, string $toCurrency): ?ExchangeRate
    {
        return static::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->orderBy('date', 'desc')
            ->first();
    }

    /**
     * Check if exchange rate exists for a currency pair
     */
    public static function hasRate(string $fromCurrency, string $toCurrency, ?string $date = null): bool
    {
        return static::getRate($fromCurrency, $toCurrency, $date) !== null;
    }

    /**
     * Get formatted rate with currency symbols
     */
    public function getFormattedRateAttribute(): string
    {
        $currencies = config('user.currencies', []);
        $fromSymbol = $currencies[$this->from_currency]['symbol'] ?? $this->from_currency;
        $toSymbol = $currencies[$this->to_currency]['symbol'] ?? $this->to_currency;

        return "1 {$fromSymbol} = {$this->rate} {$toSymbol}";
    }

    /**
     * Update or create exchange rate
     */
    public static function updateRate(string $fromCurrency, string $toCurrency, float $rate, ?string $date = null, string $source = 'api'): ExchangeRate
    {
        $date = $date ?? now()->format('Y-m-d');

        return static::updateOrCreate(
            [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'date' => $date,
            ],
            [
                'rate' => $rate,
                'source' => $source,
            ]
        );
    }

    /**
     * Get historical rates for a currency pair
     */
    public static function getHistoricalRates(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        return static::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->map(function ($rate) {
                return [
                    'date' => $rate->date->format('Y-m-d'),
                    'rate' => $rate->rate,
                    'formatted_date' => $rate->date->format('M j, Y'),
                    'source' => $rate->source,
                ];
            })
            ->toArray();
    }
}
