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
     * @OA\Get(
     *     path="/api/currencies",
     *     summary="Get list of supported currencies",
     *     tags={"Currency"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="code", type="string", example="USD"),
     *                     @OA\Property(property="name", type="string", example="US Dollar"),
     *                     @OA\Property(property="symbol", type="string", example="$"),
     *                     @OA\Property(property="decimal_places", type="integer", example=2),
     *                     @OA\Property(property="is_default", type="boolean", example=false)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to fetch currencies"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/exchange-rates",
     *     summary="Get current exchange rates",
     *     tags={"Exchange Rates"},
     *     @OA\Parameter(
     *         name="base_currency",
     *         in="query",
     *         description="Base currency code",
     *         required=false,
     *         @OA\Schema(type="string", minLength=3, maxLength=3)
     *     ),
     *     @OA\Parameter(
     *         name="target_currencies[]",
     *         in="query",
     *         description="Target currency codes",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="string"))
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date for exchange rates",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="base_currency", type="string", example="USD"),
     *                 @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(
     *                     property="rates",
     *                     type="object",
     *                     example={"EUR": 0.855, "GBP": 0.73, "JPY": 110.25}
     *                 ),
     *                 @OA\Property(property="last_updated", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/exchange-rates/refresh",
     *     summary="Refresh exchange rates from external API",
     *     tags={"Exchange Rates"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="currencies", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="force", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Exchange rates refreshed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="updated_count", type="integer"),
     *                 @OA\Property(property="rates", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="provider", type="string"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/exchange-rates/history",
     *     summary="Get exchange rate history for a currency pair",
     *     tags={"Exchange Rates"},
     *     @OA\Parameter(
     *         name="from_currency",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", minLength=3, maxLength=3)
     *     ),
     *     @OA\Parameter(
     *         name="to_currency",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", minLength=3, maxLength=3)
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days of history (1-365)",
     *         @OA\Schema(type="integer", minimum=1, maximum=365, default=30)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="from_currency", type="string"),
     *                 @OA\Property(property="to_currency", type="string"),
     *                 @OA\Property(
     *                     property="period",
     *                     type="object",
     *                     @OA\Property(property="start_date", type="string", format="date"),
     *                     @OA\Property(property="end_date", type="string", format="date"),
     *                     @OA\Property(property="days", type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="history",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="rate", type="number"),
     *                         @OA\Property(property="source", type="string"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(property="min", type="number"),
     *                     @OA\Property(property="max", type="number"),
     *                     @OA\Property(property="average", type="number"),
     *                     @OA\Property(property="change", type="number"),
     *                     @OA\Property(property="change_percentage", type="number")
     *                 )
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/currencies/convert",
     *     summary="Convert amount between currencies",
     *     tags={"Currency"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "from_currency", "to_currency"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.00),
     *             @OA\Property(property="from_currency", type="string", minLength=3, maxLength=3, example="USD"),
     *             @OA\Property(property="to_currency", type="string", minLength=3, maxLength=3, example="EUR"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-01-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="original",
     *                     type="object",
     *                     @OA\Property(property="amount", type="number", example=100),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="formatted", type="string", example="$ 100.00")
     *                 ),
     *                 @OA\Property(
     *                     property="converted",
     *                     type="object",
     *                     @OA\Property(property="amount", type="number", example=85.50),
     *                     @OA\Property(property="currency", type="string", example="EUR"),
     *                     @OA\Property(property="formatted", type="string", example="€ 85.50")
     *                 ),
     *                 @OA\Property(property="rate", type="number", example=0.855),
     *                 @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="calculation", type="string", example="100 × 0.855 = 85.5")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
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
