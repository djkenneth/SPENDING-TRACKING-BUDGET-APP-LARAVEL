<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get list of supported currencies
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $currencies = Cache::remember('supported_currencies', 3600, function () {
                return config('user.currencies', []);
            });

            $formattedCurrencies = collect($currencies)->map(function ($currency, $code) {
                return [
                    'code' => $code,
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'decimal_places' => $currency['decimal_places'] ?? 2,
                    'is_default' => $code === config('user.default_currency', 'USD'),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $formattedCurrencies,
                'count' => $formattedCurrencies->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch currencies: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch currencies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current exchange rates
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getExchangeRates(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'base_currency' => 'sometimes|string|size:3',
                'target_currencies' => 'sometimes|array',
                'target_currencies.*' => 'string|size:3',
                'date' => 'sometimes|date|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $baseCurrency = $request->input('base_currency', auth()->user()->currency ?? 'USD');
            $targetCurrencies = $request->input('target_currencies', []);
            $date = $request->input('date', now()->format('Y-m-d'));

            // If no target currencies specified, get rates for all supported currencies
            if (empty($targetCurrencies)) {
                $currencies = config('user.currencies', []);
                $targetCurrencies = array_keys($currencies);
            }

            $rates = [];
            foreach ($targetCurrencies as $targetCurrency) {
                if ($targetCurrency === $baseCurrency) {
                    $rates[$targetCurrency] = 1.0;
                    continue;
                }

                $rate = ExchangeRate::getRate($baseCurrency, $targetCurrency, $date);
                if ($rate !== null) {
                    $rates[$targetCurrency] = $rate;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'base_currency' => $baseCurrency,
                    'date' => $date,
                    'rates' => $rates,
                    'last_updated' => ExchangeRate::where('date', $date)->max('updated_at'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch exchange rates: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exchange rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh exchange rates from external API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshExchangeRates(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'currencies' => 'sometimes|array',
                'currencies.*' => 'string|size:3',
                'force' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $currencies = $request->input('currencies', []);
            $force = $request->input('force', false);

            // Check if rates were recently updated (within last hour)
            if (!$force) {
                $lastUpdate = Cache::get('exchange_rates_last_update');
                if ($lastUpdate && now()->diffInMinutes($lastUpdate) < 60) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Exchange rates are already up to date',
                        'last_updated' => $lastUpdate,
                    ]);
                }
            }

            $result = $this->currencyService->refreshExchangeRates($currencies);

            if ($result['success']) {
                Cache::put('exchange_rates_last_update', now(), 3600);

                return response()->json([
                    'success' => true,
                    'message' => 'Exchange rates refreshed successfully',
                    'data' => $result['data'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh exchange rates',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to refresh exchange rates: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh exchange rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get exchange rate history for a currency pair
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getExchangeRateHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_currency' => 'required|string|size:3',
                'to_currency' => 'required|string|size:3',
                'days' => 'sometimes|integer|min:1|max:365',
                'start_date' => 'sometimes|date|date_format:Y-m-d',
                'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $fromCurrency = $request->input('from_currency');
            $toCurrency = $request->input('to_currency');
            $days = $request->input('days', 30);

            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                $startDate = now()->subDays($days)->format('Y-m-d');
                $endDate = now()->format('Y-m-d');
            }

            $history = ExchangeRate::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->get()
                ->map(function ($rate) {
                    return [
                        'date' => $rate->date->format('Y-m-d'),
                        'rate' => $rate->rate,
                        'source' => $rate->source,
                        'updated_at' => $rate->updated_at->toISOString(),
                    ];
                });

            // Calculate statistics
            $rates = $history->pluck('rate')->toArray();
            $statistics = [];

            if (count($rates) > 0) {
                $statistics = [
                    'min' => min($rates),
                    'max' => max($rates),
                    'average' => round(array_sum($rates) / count($rates), 6),
                    'change' => count($rates) >= 2 ? round($rates[count($rates) - 1] - $rates[0], 6) : 0,
                    'change_percentage' => count($rates) >= 2 && $rates[0] != 0
                        ? round((($rates[count($rates) - 1] - $rates[0]) / $rates[0]) * 100, 2)
                        : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'days' => now()->parse($startDate)->diffInDays(now()->parse($endDate)),
                    ],
                    'history' => $history,
                    'statistics' => $statistics,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch exchange rate history: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exchange rate history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert amount between currencies
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function convert(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'from_currency' => 'required|string|size:3',
                'to_currency' => 'required|string|size:3',
                'date' => 'sometimes|date|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $fromCurrency = $request->input('from_currency');
            $toCurrency = $request->input('to_currency');
            // $date = $request->input('date', now()->format('Y-m-d'));

            // $convertedAmount = ExchangeRate::convert($amount, $fromCurrency, $toCurrency, $date);

            // if ($convertedAmount === null) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Exchange rate not available for the specified currencies and date',
            //     ], 404);
            // }

            // $rate = ExchangeRate::getRate($fromCurrency, $toCurrency, $date);

            $currencies = config('user.currencies', []);
            $fromSymbol = $currencies[$fromCurrency]['symbol'] ?? $fromCurrency;
            $toSymbol = $currencies[$toCurrency]['symbol'] ?? $toCurrency;

            return response()->json([
                'success' => true,
                'data' => [
                    'original' => [
                        'amount' => $amount,
                        'currency' => $fromCurrency,
                        'formatted' => $fromSymbol . ' ' . number_format($amount, 2),
                    ],
                    'converted' => [
                        // 'amount' => round($convertedAmount, 2),
                        'currency' => $toCurrency,
                        // 'formatted' => $toSymbol . ' ' . number_format($convertedAmount, 2),
                    ],
                    // 'rate' => $rate,
                    // 'date' => $date,
                    // 'calculation' => "{$amount} × {$rate} = {$convertedAmount}",
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to convert currency: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to convert currency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
