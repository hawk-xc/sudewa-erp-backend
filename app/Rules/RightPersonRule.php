<?php

namespace App\Rules;

use App\Models\Person;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RightPersonRule implements ValidationRule
{
    protected string $type;
    
    public function __construct(string $type) {
        $this->type = $type;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $person = Person::find($value);

        if (! $person) {
            $fail("The selected :attribute is invalid.");
            return;
        }

        if ($person->type !== $this->type) {
            $fail("The selected :attribute must be of type {$this->type}.");
        }
    }
}
