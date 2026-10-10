@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => true])
@php($fieldValue = $type === 'password' ? '' : old($name, $value))
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}" @required($required)
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class(['form-input']) }}>
    @error($name)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
