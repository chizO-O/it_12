@props(['name', 'label', 'value' => null, 'required' => false, 'errorBag' => 'default'])
@php($fieldValue = old($name, $value))
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" @required($required)
        @error($name, $errorBag) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class(['form-input']) }}>{{ is_string($fieldValue) ? $fieldValue : '' }}</textarea>
    @error($name, $errorBag)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
