<?php

namespace App\Rules;

use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class Base64Image implements Rule
{

    private $mimes = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($mimes = [])
    {
        $this->mimes = $mimes;
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
        if ($this->checkMime($value) == false)
            return false;

        $result = validator(
            ['file' => $this->createTemporaryFile($value)], 
            ['file' => 'image']
        )->passes();
        fclose($this->file);
        return $result;
    }

    protected function checkMime($value) {
        foreach ($this->mimes as $mime) {
            if (Str::startsWith($value, "data:image/{$mime};base64,"))
                return true;
        }
        return false;
    }

    protected function createTemporaryFile($data)
    {
        $this->file = tmpfile();
        fwrite($this->file, base64_decode(Str::after($data, 'base64,')));
        return new UploadedFile(stream_get_meta_data($this->file)['uri'], 'image', 'text/plain', null, null, true);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $mimes = implode(', ', $this->mimes);
        return "The :attribute must be a valid {$mimes} image";
    }
}
