<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Currency;
use App\Domain\ValueObjects\Money;
use InvalidArgumentException;

class ExchangeRate
{
    private Currency $base;
    private Currency $quote;
    private float $rate;

    public function __construct(Currency $base, Currency $quote, float $rate)
    {
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be positive.');
        }

        $this->base = $base;
        $this->quote = $quote;
        $this->rate = $rate;
    }

    public function getBase(): Currency
    {
        return $this->base;
    }

    public function getQuote(): Currency
    {
        return $this->quote;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function convert(Money $money): Money
    {
        if (! $money->getCurrency()->equals($this->base)) {
            throw new InvalidArgumentException(
                "Cannot convert from {$money->getCurrency()} using base {$this->base}"
            );
        }

        $converted = bcmul($money->getAmount(), (string) $this->rate, 6);

        return new Money($converted, $this->quote);
    }
}
