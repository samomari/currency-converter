<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedCurrencyRule implements ValidationRule
{
    /**
     * @var array
     */
    protected array $supportedCurrencies;

    public function __construct(array $supportedCurrencies = [])
    {
        $this->supportedCurrencies = $supportedCurrencies ?: config('currencies.supported');
    }

    /**
     * Validate the attribute.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array(strtoupper($value), $this->supportedCurrencies)) {
            $fail("Currency {$value} is not supported by this API.");
        }
    }
}
