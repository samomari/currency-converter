<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

class Currency
{
    private string $code;

    public function __construct(string $code)
    {
        $code = strtoupper(trim($code));

        if (!in_array($code, config('currencies.supported', []))) {
            throw new InvalidArgumentException("Unsupported currency: {$code}");
        }

        $this->code = $code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->getCode();
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
