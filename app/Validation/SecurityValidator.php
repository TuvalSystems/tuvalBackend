<?php

namespace App\Validation;

use Illuminate\Validation\Validator;

class SecurityValidator extends Validator
{
    public function validateEmail($attribute, $value, $parameters)
    {
        // Reject header separators before Laravel 10 / Egulias email validation.
        return is_string($value)
            && !preg_match('/[\r\n\x00]/', $value)
            && parent::validateEmail($attribute, $value, $parameters);
    }
}
