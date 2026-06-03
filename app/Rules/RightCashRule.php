<?php

namespace App\Rules;

use App\Models\Cash;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RightCashRule implements ValidationRule
{
    protected mixed $companyId;

    /**
     * Create a new rule instance.
     *
     * @param  mixed  $companyId  The expected company ID or a Closure returning it.
     */
    public function __construct(mixed $companyId)
    {
        $this->companyId = $companyId;
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

        $cash = Cash::find($value);

        if (!$cash) {
            $fail("The selected :attribute is invalid.");
            return;
        }

        $expectedCompanyId = $this->companyId instanceof Closure
            ? ($this->companyId)()
            : $this->companyId;

        if ($expectedCompanyId && (int) $cash->company_id !== (int) $expectedCompanyId) {
            $fail("The selected :attribute does not belong to the selected company.");
        }
    }
}
