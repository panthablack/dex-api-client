@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'options' => null,
    'placeholder' => '',
    'value' => null,
    'help' => null,
    'class' => 'col-md-6',
    'rows' => 4,
    'min' => null,
    'max' => null,
    'maxlength' => null,
    'checkboxValue' => '1'
])

<div class="{{ $class }} mb-3">
    @if($type !== 'checkbox')
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    @if($type === 'text' || $type === 'email' || $type === 'date' || $type === 'number')
        <input 
            type="{{ $type }}" 
            class="form-control @error($name) is-invalid @enderror" 
            id="{{ $name }}" 
            name="{{ $name }}" 
            value="{{ $value ?? old($name) }}"
            @if($required) required @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($min !== null) min="{{ $min }}" @endif
            @if($max !== null) max="{{ $max }}" @endif
            @if($maxlength !== null) maxlength="{{ $maxlength }}" @endif
        >
    @elseif($type === 'select')
        <select 
            class="form-select @error($name) is-invalid @enderror" 
            id="{{ $name }}" 
            name="{{ $name }}"
            @if($required) required @endif
        >
            <option value="">Select {{ $label }}</option>
            @if($options)
                @foreach($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" {{ (($value ?? old($name)) == $optionValue) ? 'selected' : '' }}>
                        {{ $optionLabel }}
                    </option>
                @endforeach
            @endif
        </select>
    @elseif($type === 'textarea')
        <textarea 
            class="form-control @error($name) is-invalid @enderror" 
            id="{{ $name }}" 
            name="{{ $name }}" 
            rows="{{ $rows }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
        >{{ $value ?? old($name) }}</textarea>
    @elseif($type === 'checkbox')
        <div class="form-check">
            <input 
                class="form-check-input @error($name) is-invalid @enderror" 
                type="checkbox" 
                id="{{ $name }}" 
                name="{{ $name }}" 
                value="{{ $checkboxValue }}"
                {{ (($value ?? old($name)) == $checkboxValue) ? 'checked' : '' }}
            >
            <label class="form-check-label" for="{{ $name }}">
                {{ $label }}
                @if($required)
                    <span class="text-danger">*</span>
                @endif
            </label>
        </div>
    @endif

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>