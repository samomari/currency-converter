<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ConvertController extends Controller
{
    public function convert(Request $request)
    {
        $start = microtime(true);

        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3|different:from',
            'amount' => 'required|numeric|min:0',
        ]);

        $from = strtoupper($validated['from']);
        $to = strtoupper($validated['to']);
        $amount = (float) $validated['amount'];

        $cacheKey = "rate:{$from}_{$to}";
        $record = null;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $rate = (float) $cached;
            $source = 'cache';
        } else {
            $record = DB::table('currency_rates')
                ->where('base', $from)
                ->where('quote', $to)
                ->first();

            if ($record && $record->last_updated > now()->subHour()) {
                $rate = (float) $record->rate;
                $source = 'local_db';
            } else {
                $providers = [
                    [
                        'name' => 'external_api',
                        'url' => "https://api.frankfurter.app/latest",
                        'params' => ['from' => $from, 'to' => $to],
                        'extract' => fn($json, $to) => $json['rates'][$to] ?? null,
                    ],
                    [
                        'name' => 'external_fallback_1',
                        'url' => "https://api.freecurrencyapi.com/v1/latest",
                        'params' => [
                            'base_currency' => $from,
                            'currencies' => $to,
                            'apikey' => env('FREECURRENCYAPI_KEY'),
                        ],
                        'extract' => fn($json, $to) => $json['data'][$to] ?? null,
                    ],
                    [
                        'name' => 'external_fallback_2',
                        'url' => "https://api.currencyfreaks.com/v2.0/rates/latest",
                        'params' => [
                            'apikey' => env('CURRENCYFREAKS_KEY'),
                            'symbols' => $to,
                            'base' => $from,
                        ],
                        'extract' => fn($json, $to) => $json['rates'][$to] ?? null,
                    ],
                ];

                $rate = null;
                $source = null;

                foreach ($providers as $provider) {
                    try {
                        $response = Http::timeout(0.5)->get($provider['url'], $provider['params']);
                        if ($response->successful()) {
                            $json = $response->json();
                            $rate = $provider['extract']($json, $to);
                            if ($rate) {
                                $source = $provider['name'];
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }

                if (!$rate) {
                    return response()->json(['error' => 'All external providers failed'], 503);
                }

                DB::table('currency_rates')->updateOrInsert(
                    ['base' => $from, 'quote' => $to],
                    ['rate' => $rate, 'last_updated' => now()]
                );

                $record = DB::table('currency_rates')
                    ->where('base', $from)
                    ->where('quote', $to)
                    ->first();
            }

            Cache::put($cacheKey, $rate, now()->addSeconds(1));
        }

        $result = $amount * $rate;
        $execTime = round((microtime(true) - $start) * 1000, 2);

        return response()->json([
            'data' => [
                'from' => $from,
                'to' => $to,
                'amount' => number_format($amount, 2, '.', ''),
                'result' => number_format($result, 2, '.', ''),
                'rate' => (string) $rate,
                'last_updated' => Carbon::parse(optional($record)->last_updated ?? now())->toISOString(),
            ],
            'meta' => [
                'source' => $source,
                'execution_time_ms' => $execTime,
            ],
        ]);
    }
}
