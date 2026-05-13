@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'help' => null,
    'options' => [],
    'rows' => 3
])

<div class="form-group mb-3">
    <label for="{{ $name }}" class="form-label">
        {{ __($label) }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>

    @if($type === 'select')
        <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->merge(['class' => 'form-select']) }} @if($required) required @endif>
            <option value="">{{ __('Select') }}</option>
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $name }}" {{ $attributes->merge(['class' => 'form-control', 'rows' => $rows]) }} @if($required) required @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->merge(['class' => 'form-control']) }} @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif>
    @endif

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if($help)
        <small class="form-text text-muted">{{ $help }}</small>
    @endif
</div>
