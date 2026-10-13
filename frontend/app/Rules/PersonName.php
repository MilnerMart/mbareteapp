<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    const pattern = "/^[\pL\pM]+(?:(?:\. ?|[ '’-])[\pL\pM]+)*\.?$/u";

    function __construct(private string $label)
    {
    }

    static function normalize(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/\s+/u', ' ', trim($value)) : $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match(self::pattern, $value)) {
            $fail('El '.$this->label.' solo puede tener letras, espacios, apóstrofes o guiones, sin números, correos ni símbolos.');
        }
    }
}
