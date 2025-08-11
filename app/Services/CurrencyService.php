<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CurrencyService
{
    protected string $apiProvider;
    protected ?string $apiKey;
    protected string $apiUrl;
    protected array $supportedCurrencies;

    public function __construct()
    {
        $this->apiProvider = config('services.currency.provider', 'exchangerate-api');
        $this->apiKey = config('services.currency.api_key');
        $this->apiUrl = config('services.currency.api_url', 'https://api.exchangerate-api.com/v4/latest/');
        $this->supportedCurrencies = array_keys(config('user.currencies', []));
    }

    /**
     * Refresh exchange rates from external API
     *
     * @param array $currencies
     * @return array
     */
    public function refreshExchangeRates(array $currencies = []): array
    {
        try {
            if (empty($currencies)) {
                $currencies = $this->supportedCurrencies;
            }

            $baseCurrency = config('user.default_currency', 'USD');
            $rates = $this->fetchRatesFromAPI($baseCurrency);

            if (!$rates['success']) {
                return $rates;
            }

            $updatedRates = [];
            $date = now()->format('Y-m-d');

            foreach ($currencies as $fromCurrency) {
                foreach ($currencies as $toCurrency) {
                    if ($fromCurrency === $toCurrency) {
                        continue;
                    }

                    $rate = $this->calculateCrossRate(
                        $fromCurrency,
                        $toCurrency,
                        $rates['data'],
                        $baseCurrency
                    );

                    if ($rate !== null) {
                        ExchangeRate::updateRate(
                            $fromCurrency,
                            $toCurrency,
                            $rate,
                            $date,
                            $this->apiProvider
                        );

                        $updatedRates[] = [
                            'from' => $fromCurrency,
                            'to' => $toCurrency,
                            'rate' => $rate,
                        ];
                    }
                }
            }

            // Clear rate cache
            Cache::tags(['exchange_rates'])->flush();

            return [
                'success' => true,
                'data' => [
                    'updated_count' => count($updatedRates),
                    'rates' => $updatedRates,
                    'provider' => $this->apiProvider,
                    'timestamp' => now()->toISOString(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to refresh exchange rates: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch rates from external API
     *
     * @param string $baseCurrency
     * @return array
     */
    protected function fetchRatesFromAPI(string $baseCurrency): array
    {
        try {
            $cacheKey = "exchange_rates_api_{$baseCurrency}";

            // Check cache first (1 hour TTL)
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return [
                    'success' => true,
                    'data' => $cached,
                ];
            }

            $response = match($this->apiProvider) {
                'exchangerate-api' => $this->fetchFromExchangeRateAPI($baseCurrency),
                'fixer' => $this->fetchFromFixer($baseCurrency),
                'currencyapi' => $this->fetchFromCurrencyAPI($baseCurrency),
                'openexchangerates' => $this->fetchFromOpenExchangeRates($baseCurrency),
                default => $this->fetchFromExchangeRateAPI($baseCurrency),
            };

            if ($response['success']) {
                Cache::put($cacheKey, $response['data'], 3600);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('API fetch failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch from ExchangeRate-API (free tier)
     *
     * @param string $baseCurrency
     * @return array
     */
    protected function fetchFromExchangeRateAPI(string $baseCurrency): array
    {
        try {
            $url = "https://api.exchangerate-api.com/v4/latest/{$baseCurrency}";

            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'data' => $data['rates'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch rates from ExchangeRate-API',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch from Fixer.io
     *
     * @param string $baseCurrency
     * @return array
     */
    protected function fetchFromFixer(string $baseCurrency): array
    {
        try {
            if (!$this->apiKey) {
                return [
                    'success' => false,
                    'error' => 'Fixer API key not configured',
                ];
            }

            $url = "http://data.fixer.io/api/latest";

            $response = Http::timeout(10)->get($url, [
                'access_key' => $this->apiKey,
                'base' => $baseCurrency,
                'symbols' => implode(',', $this->supportedCurrencies),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['success'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['rates'] ?? [],
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch rates from Fixer',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch from CurrencyAPI
     *
     * @param string $baseCurrency
     * @return array
     */
    protected function fetchFromCurrencyAPI(string $baseCurrency): array
    {
        try {
            if (!$this->apiKey) {
                return [
                    'success' => false,
                    'error' => 'CurrencyAPI key not configured',
                ];
            }

            $url = "https://api.currencyapi.com/v3/latest";

            $response = Http::timeout(10)
                ->withHeaders(['apikey' => $this->apiKey])
                ->get($url, [
                    'base_currency' => $baseCurrency,
                    'currencies' => implode(',', $this->supportedCurrencies),
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $rates = [];
                foreach ($data['data'] ?? [] as $currency => $info) {
                    $rates[$currency] = $info['value'] ?? 0;
                }

                return [
                    'success' => true,
                    'data' => $rates,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch rates from CurrencyAPI',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch from Open Exchange Rates
     *
     * @param string $baseCurrency
     * @return array
     */
    protected function fetchFromOpenExchangeRates(string $baseCurrency): array
    {
        try {
            if (!$this->apiKey) {
                return [
                    'success' => false,
                    'error' => 'Open Exchange Rates API key not configured',
                ];
            }

            // Note: Free tier only supports USD as base
            $url = "https://openexchangerates.org/api/latest.json";

            $response = Http::timeout(10)->get($url, [
                'app_id' => $this->apiKey,
                'base' => 'USD', // Free tier limitation
                'symbols' => implode(',', $this->supportedCurrencies),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['rates'] ?? [];

                // Convert to requested base currency if not USD
                if ($baseCurrency !== 'USD' && isset($rates[$baseCurrency])) {
                    $baseRate = $rates[$baseCurrency];
                    $convertedRates = [];

                    foreach ($rates as $currency => $rate) {
                        $convertedRates[$currency] = $rate / $baseRate;
                    }

                    return [
                        'success' => true,
                        'data' => $convertedRates,
                    ];
                }

                return [
                    'success' => true,
                    'data' => $rates,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch rates from Open Exchange Rates',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cross rate between two currencies
     *
     * @param string $from
     * @param string $to
     * @param array $rates
     * @param string $base
     * @return float|null
     */
    protected function calculateCrossRate(string $from, string $to, array $rates, string $base): ?float
    {
        // Direct conversion if base currency matches
        if ($from === $base && isset($rates[$to])) {
            return (float) $rates[$to];
        }

        if ($to === $base && isset($rates[$from]) && $rates[$from] > 0) {
            return 1 / (float) $rates[$from];
        }

        // Cross rate calculation
        if (isset($rates[$from]) && isset($rates[$to]) && $rates[$from] > 0) {
            return (float) $rates[$to] / (float) $rates[$from];
        }

        return null;
    }

    /**
     * Get historical rates from API
     *
     * @param string $from
     * @param string $to
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function fetchHistoricalRates(string $from, string $to, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $rates = [];
            $current = $startDate->copy();

            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');

                // Check if rate exists in database
                $existingRate = ExchangeRate::where('from_currency', $from)
                    ->where('to_currency', $to)
                    ->where('date', $dateStr)
                    ->first();

                if ($existingRate) {
                    $rates[] = [
                        'date' => $dateStr,
                        'rate' => $existingRate->rate,
                    ];
                } else {
                    // Fetch from API if not in database
                    $apiRate = $this->fetchHistoricalRateFromAPI($from, $to, $dateStr);

                    if ($apiRate !== null) {
                        ExchangeRate::updateRate($from, $to, $apiRate, $dateStr, $this->apiProvider);

                        $rates[] = [
                            'date' => $dateStr,
                            'rate' => $apiRate,
                        ];
                    }
                }

                $current->addDay();
            }

            return [
                'success' => true,
                'data' => $rates,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch historical rates: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch historical rate from API for specific date
     *
     * @param string $from
     * @param string $to
     * @param string $date
     * @return float|null
     */
    protected function fetchHistoricalRateFromAPI(string $from, string $to, string $date): ?float
    {
        try {
            // Most free APIs don't support historical rates
            // This is a placeholder for paid API implementations

            // For now, return the latest rate as approximation
            $latestRate = ExchangeRate::getRate($from, $to);

            return $latestRate;
        } catch (\Exception $e) {
            Log::error('Failed to fetch historical rate: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Validate currency code
     *
     * @param string $currency
     * @return bool
     */
    public function isValidCurrency(string $currency): bool
    {
        return in_array($currency, $this->supportedCurrencies);
    }

    /**
     * Get all supported currencies with details
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        $currencies = config('user.currencies', []);
        $result = [];

        foreach ($currencies as $code => $details) {
            $result[] = [
                'code' => $code,
                'name' => $details['name'],
                'symbol' => $details['symbol'],
                'decimal_places' => $details['decimal_places'] ?? 2,
            ];
        }

        return $result;
    }
}
