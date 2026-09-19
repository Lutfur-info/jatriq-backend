<?php

namespace App\Http\Requests\Concerns;

trait NormalizesMsisdn
{
    /**
     * Reduce the submitted msisdn to digits so "017 12-345678" and
     * "01712345678" resolve to the same account.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('msisdn'))) {
            $this->merge([
                'msisdn' => preg_replace('/\D/', '', $this->input('msisdn')),
            ]);
        }
    }
}
