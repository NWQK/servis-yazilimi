<?php
namespace App\Validation;

class SafeValidator extends \Illuminate\Validation\Validator
{
    public function validateEmail($attribute,$value,$parameters)
    {
        // Never allow RFC-folded addresses to introduce mail headers or SMTP commands.
        if(!is_string($value) || preg_match('/[\x00-\x1F\x7F]/', $value))return false;
        return parent::validateEmail($attribute,$value,$parameters);
    }
}
