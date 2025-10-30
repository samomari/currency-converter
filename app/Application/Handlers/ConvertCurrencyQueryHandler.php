<?php

namespace App\Application\Handlers;

use App\Application\Queries\ConvertCurrencyQuery;
use App\Domain\ValueObjects\Currency;
use App\Domain\ValueObjects\Money;
use App\Domain\Entities\ExchangeRate;
use App\Infrastructure\Repositories\CurrencyRateRepository;
use Carbon\Carbon;

class ConvertCurrencyQueryHandler
{
    private CurrencyRateRepository $repo;

    public function __construct(CurrencyRateRepository $repo)
    {
        $this->repo = $repo;
    }

    public function handle(ConvertCurrencyQuery $query): array
    {
        $fromCurrency = new Currency($query->from);
        $toCurrency = new Currency($query->to);
        $money = new Money($query->amount, $fromCurrency);

        [$rate, $source, $record] = $this->repo->getRate($fromCurrency, $toCurrency);

        $exchangeRate = new ExchangeRate($fromCurrency, $toCurrency, $rate);
        $resultMoney = $exchangeRate->convert($money);

        return [
            'data' => [
                'from' => (string) $fromCurrency,
                'to' => (string) $toCurrency,
                'amount' => $money->format(),
                'result' => $resultMoney->format(),
                'rate' => (string) $rate,
                'last_updated' => Carbon::parse(optional($record)->last_updated ?? now())->toISOString(),
            ],
            'meta' => [
                'source' => $source,
            ],
        ];
    }
}
