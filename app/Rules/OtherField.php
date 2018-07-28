<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class OtherField implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (gettype($value) == "string")
            return true;
        if (gettype($value) == "array" && array_key_exists('title', $value) && array_key_exists('content', $value))
            return true;
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be an array of strings and/or objects{title, content}.';
    }
}
