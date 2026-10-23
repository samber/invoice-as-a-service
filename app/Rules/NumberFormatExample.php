<?php

namespace App\Rules;

use App\Helpers\NumberFormat;
use Illuminate\Contracts\Validation\Rule;

class NumberFormatExample implements Rule
{
    public function passes($attribute, $value)
    {
        return NumberFormat::parse($value) !== null;
    }

    public function message()
    {
        return 'The :attribute must be an example of the number 1234.56, such as "1,234.56", "1.234,56" or "1 234,56".';
    }
}
