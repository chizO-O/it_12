<?php

namespace App\Http\Requests;

use App\Support\BuyerAssessmentData;
use Illuminate\Foundation\Http\FormRequest;

class SaveBuyerAssessmentRequest extends FormRequest
{
    protected $errorBag = 'assessment';

    public function authorize(): bool
    {
        return $this->user()->can('updateAssessment', $this->route('reservation'));
    }

    public function rules(): array
    {
        $rules = BuyerAssessmentData::rules();
        foreach (['requested_installment_months', 'proposed_down_payment'] as $field) {
            array_unshift($rules[$field], 'exclude_unless:preferred_payment_method,installment');
        }

        return [...$rules, 'privacy_notice_confirmed' => ['required', 'accepted']];
    }
}
