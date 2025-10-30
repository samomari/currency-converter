<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCurrencyRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [5, 15, 45, 135, 405];

    public function handle(): void
    {
        Log::info('Starting currency rates sync job...');

        $pairs = [
            ['from' => 'USD', 'to' => 'EUR'],
            ['from' => 'EUR', 'to' => 'GBP'],
            ['from' => 'USD', 'to' => 'GBP'],
        ];

        $providers = [
            [
                'name' => 'frankfurter',
                'url' => 'https://api.frankfurter.app/latest',
                'extract' => fn($json, $to) => $json['rates'][$to] ?? null,
                'params' => fn($from, $to) => ['from' => $from, 'to' => $to],
            ],
            [
                'name' => 'freecurrencyapi',
                'url' => 'https://api.freecurrencyapi.com/v1/latest',
                'extract' => fn($json, $to) => $json['data'][$to] ?? null,
                'params' => fn($from, $to) => [
                    'base_currency' => $from,
                    'currencies' => $to,
                    'apikey' => env('FREECURRENCYAPI_KEY'),
                ],
            ],
            [
                'name' => 'currencyfreaks',
                'url' => 'https://api.currencyfreaks.com/v2.0/rates/latest',
                'extract' => fn($json, $to) => $json['rates'][$to] ?? null,
                'params' => fn($from, $to) => [
                    'apikey' => env('CURRENCYFREAKS_KEY'),
                    'symbols' => $to,
                    'base' => $from,
                ],
            ],
        ];

        $results = [];

        foreach ($pairs as $pair) {
            $from = $pair['from'];
            $to = $pair['to'];
            $rates = [];

            $responses = Http::pool(fn($pool) =>
                collect($providers)->mapWithKeys(function ($p) use ($pool, $from, $to) {
                    return [
                        $p['name'] => $pool->as($p['name'])
                            ->timeout(1)
                            ->get($p['url'], $p['params']($from, $to)),
                    ];
                })->all()
            );

            foreach ($responses as $name => $response) {
                try {
                    if ($response && $response->successful()) {
                        $provider = collect($providers)->firstWhere('name', $name);
                        $json = $response->json();
                        $rate = $provider['extract']($json, $to);

                        if ($rate) {
                            $rates[] = (float) $rate;
                            Log::info("{$name} returned rate {$rate} for {$from}/{$to}");
                        }
                    } else {
                        Log::warning("Provider {$name} failed or returned bad response");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Provider {$name} error: " . $e->getMessage());
                }
            }

            sort($rates);
            $count = count($rates);

            $median = $count ? (
                $count % 2
                    ? $rates[floor($count / 2)]
                    : ($rates[$count / 2 - 1] + $rates[$count / 2]) / 2
            ) : null;

            if ($median) {
                $results[] = [
                    'base' => $from,
                    'quote' => $to,
                    'rate' => $median,
                    'last_updated' => now(),
                ];
            } else {
                Log::error("All providers failed for {$from}/{$to}");
            }
        }

        DB::transaction(function () use ($results) {
            foreach ($results as $r) {
                DB::table('currency_rates')->updateOrInsert(
                    ['base' => $r['base'], 'quote' => $r['quote']],
                    ['rate' => $r['rate'], 'last_updated' => $r['last_updated']]
                );
            }
        });

        Log::info('Currency rates sync job completed', ['count' => count($results)]);
    }
}
