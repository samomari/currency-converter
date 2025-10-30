<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

class Money
{
    private string $amount;
    private Currency $currency;

    public function __construct(string $amount, Currency $currency)
    {
        if (bccomp($amount, '0', 6) < 0) {
            throw new InvalidArgumentException('Amount must be non-negative.');
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function multiply(float $rate): Money
    {
        $result = bcmul($this->amount, (string) $rate, 6);
        return new Money($result, $this->currency);
    }

    public function format(): string
    {
        return number_format((float) $this->amount, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
