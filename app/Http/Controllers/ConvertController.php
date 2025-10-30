<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Queries\ConvertCurrencyQuery;
use App\Application\Handlers\ConvertCurrencyQueryHandler;
use App\Rules\SupportedCurrencyRule;

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

        $query = new ConvertCurrencyQuery($validated['from'], $validated['to'], (float)$validated['amount']);
        $response = $handler->handle($query);

        if (isset($response['error'])) {
            return response()->json($response, $response['status'] ?? 500);
        }

        $response['meta']['execution_time_ms'] = round((microtime(true) - $start) * 1000, 2);

        return response()->json($response);
    }
}
