<?php

namespace App\Infrastructure\Repositories;

use App\Domain\ValueObjects\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyRateRepository
{
    public function getRate(Currency $from, Currency $to): array
    {
        $cacheKey = "rate:{$from}_{$to}";
        $record = null;

        if ($cached = Cache::get($cacheKey)) {
            return [(float) $cached, 'cache', null];
        }

        $record = DB::table('currency_rates')
            ->where('base', (string) $from)
            ->where('quote', (string) $to)
            ->first();

        if ($record && $record->last_updated > now()->subHour()) {
            return [(float) $record->rate, 'local_db', $record];
        }

        [$rate, $source] = $this->fetchFromProviders($from, $to);

        if (!$rate) {
            Log::channel('exchange')->error("All providers failed for {$from}/{$to}");
            throw new \RuntimeException("All providers failed for {$from}/{$to}");
        }

        DB::table('currency_rates')->updateOrInsert(
            ['base' => (string) $from, 'quote' => (string) $to],
            ['rate' => $rate, 'last_updated' => now()]
        );

        Cache::put($cacheKey, $rate, now()->addSeconds(1));

        $record = DB::table('currency_rates')
            ->where('base', (string) $from)
            ->where('quote', (string) $to)
            ->first();

        return [$rate, $source, $record];
    }

    private function fetchFromProviders(Currency $from, Currency $to): array
    {
        $providers = [
            [
                'name' => 'external_api',
                'url' => "https://api.frankfurter.app/latest",
                'params' => ['from' => (string) $from, 'to' => (string) $to],
                'extract' => fn($json, $to) => $json['rates'][(string) $to] ?? null,
            ],
            [
                'name' => 'external_fallback_1',
                'url' => "https://api.freecurrencyapi.com/v1/latest",
                'params' => [
                    'base_currency' => (string) $from,
                    'currencies' => (string) $to,
                    'apikey' => env('FREECURRENCYAPI_KEY'),
                ],
                'extract' => fn($json, $to) => $json['data'][(string) $to] ?? null,
            ],
            [
                'name' => 'external_fallback_2',
                'url' => "https://api.currencyfreaks.com/v2.0/rates/latest",
                'params' => ['apikey' => env('CURRENCYFREAKS_KEY')],
                'extract' => function ($json, $to) use ($from) {
                    $rates = $json['rates'] ?? [];
                    $usdToTarget = $rates[(string) $to] ?? null;
                    $usdToBase = $rates[(string) $from] ?? null;
                    return ($usdToTarget && $usdToBase) ? $usdToTarget / $usdToBase : null;
                },
            ],
        ];

        foreach ($providers as $p) {
            $circuitKey = "circuit:{$p['name']}";
            $failKey = "failures:{$p['name']}";

            if (Cache::get($circuitKey) === 'open') {
                Log::channel('exchange')->warning("Skipping {$p['name']} – circuit open");
                continue;
            }

            try {
                $response = Http::retry(3, 200, throw: false)
                    ->timeout(0.5)
                    ->get($p['url'], $p['params']);

                if ($response->successful()) {
                    $json = $response->json();
                    $rate = $p['extract']($json, $to);

                    if ($rate) {
                        Cache::forget($failKey);
                        Log::channel('exchange')->info("Using provider: {$p['name']}", [
                            'pair' => "{$from}/{$to}",
                            'rate' => $rate,
                        ]);
                        return [(float) $rate, $p['name']];
                    }
                }

                throw new \RuntimeException('Bad response');
            } catch (\Throwable $e) {
                $fails = Cache::increment($failKey);
                Cache::put($failKey, $fails, now()->addMinutes(5));

                if ($fails >= 3) {
                    Cache::put($circuitKey, 'open', now()->addSeconds(60));
                    Cache::forget($failKey);
                    Log::channel('exchange')->warning("Circuit opened for {$p['name']} (60s)");
                } else {
                    Log::channel('exchange')->warning("Provider error: {$p['name']}", [
                        'message' => $e->getMessage(),
                        'fail_count' => $fails,
                    ]);
                }
            }
        }

        return [null, null];
    }
}
