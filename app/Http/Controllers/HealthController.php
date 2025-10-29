<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function index()
    {
        $status = [
            'database' => 'ok',
            'cache' => 'ok',
            'external_providers' => [],
        ];

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $status['database'] = 'fail';
        }

        try {
            Cache::put('health_check', 'ok', 2);
            if (Cache::get('health_check') !== 'ok') {
                $status['cache'] = 'fail';
            }
        } catch (\Throwable $e) {
            $status['cache'] = 'fail';
        }

        $providers = [
            'frankfurter' => "https://api.frankfurter.app/latest?from=USD&to=EUR",
            'freecurrencyapi' => "https://api.freecurrencyapi.com/v1/latest?base_currency=USD&currencies=EUR&apikey=" . env('FREECURRENCYAPI_KEY'),
            'currencyfreaks' => "https://api.currencyfreaks.com/v2.0/rates/latest?apikey=" . env('CURRENCYFREAKS_KEY') . "&symbols=EUR&base=USD",
        ];

        foreach ($providers as $name => $url) {
            try {
                $response = Http::timeout(1)->get($url);
                $status['external_providers'][$name] = $response->successful() ? 'ok' : 'fail';
            } catch (\Throwable $e) {
                $status['external_providers'][$name] = 'fail';
            }
        }

        $overall = collect($status)->flatten()->contains('fail') ? 'fail' : 'ok';

        return response()->json([
            'status' => $overall,
            'components' => $status,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
