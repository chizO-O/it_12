@props(['name', 'label', 'required' => false, 'errorBag' => 'default'])
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
        @error($name, $errorBag) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class(['form-input']) }}>{{ $slot }}</select>
    @error($name, $errorBag)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
