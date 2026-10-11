@props(['field', 'value'])
@if($value === null || $value === '')
    <span class="text-stone-400">Not provided</span>
@elseif($field === 'preferred_payment_method')
    {{ $value === 'spot_cash' ? 'Spot Cash' : 'Installment' }}
@elseif($field === 'proposed_down_payment')
    ₱{{ number_format((float) $value, 2) }}
@else
    {{ $value }}
@endif
