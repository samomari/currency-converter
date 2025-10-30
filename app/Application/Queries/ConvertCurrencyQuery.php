<?php

namespace App\Application\Queries;

class ConvertCurrencyQuery
{
    public string $from;
    public string $to;
    public float $amount;

    public function __construct(string $from, string $to, float $amount)
    {
        $this->from = strtoupper($from);
        $this->to = strtoupper($to);
        $this->amount = $amount;
    }
}
