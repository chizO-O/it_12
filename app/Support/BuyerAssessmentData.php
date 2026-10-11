<?php

namespace App\Support;

use App\Models\BuyerAssessment;
use Illuminate\Validation\Rule;

class BuyerAssessmentData
{
    public static function rules(): array
    {
        return [
            'employment_status' => ['nullable', 'string', 'max:120'],
            'source_of_income' => ['nullable', 'string', 'max:255'],
            'monthly_income_range' => ['nullable', 'string', 'max:120'],
            'preferred_payment_method' => ['nullable', Rule::in(['spot_cash', 'installment'])],
            'requested_installment_months' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'proposed_down_payment' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'additional_clarifications' => ['nullable', 'string', 'max:4000'],
            'agent_remarks' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public static function normalized(array $data): array
    {
        $values = array_replace(array_fill_keys([...array_keys(BuyerAssessment::FACT_LABELS), 'agent_remarks'], null), $data);
        if ($values['preferred_payment_method'] !== 'installment') {
            $values['requested_installment_months'] = null;
            $values['proposed_down_payment'] = null;
        }

        return $values;
    }
}
