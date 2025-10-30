<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Queries\ConvertCurrencyQuery;
use App\Application\Handlers\ConvertCurrencyQueryHandler;
use App\Rules\SupportedCurrencyRule;
use Illuminate\Support\Facades\Log;

class ConvertController extends Controller
{
    public function convert(Request $request, ConvertCurrencyQueryHandler $handler)
    {
        $start = microtime(true);
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'from' => ['required', 'string', 'size:3', new SupportedCurrencyRule()],
            'to' => ['required', 'string', 'size:3', 'different:from', new SupportedCurrencyRule()],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
          $query = new ConvertCurrencyQuery($validated['from'], $validated['to'], (string)$validated['amount']);
          $response = $handler->handle($query);
        } catch (\InvalidArgumentException $e) {
          return response()->json([
            'error' => $e->getMessage(),
          ], 422);
        } catch (\RuntimeException $e) {
          return response()->json([
            'error' => $e->getMessage(),
          ], 503);
        }
        
        $response['meta']['execution_time_ms'] = round((microtime(true) - $start) * 1000, 2);

        Log::channel('exchange')->info('convert_request', [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'amount' => $validated['amount'],
            'rate_source' => $response['meta']['source'] ?? null,
            'execution_ms' => $response['meta']['execution_time_ms'],
        ]);

        return response()->json($response);
    }
}
