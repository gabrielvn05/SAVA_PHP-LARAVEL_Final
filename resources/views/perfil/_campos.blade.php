@php
    $user = $user ?? auth()->user();
    $showCedula = $showCedula ?? true;
    $readonly = $readonly ?? false;
    $profileLayout = $profileLayout ?? false;
    $showEmail = $showEmail ?? false;
    $fieldLock = $readonly ? 'disabled data-profile-field' : '';
    $inputClass = 'field__input'.($profileLayout ? ' profile-field__input' : '');
@endphp
<div class="form-grid form-grid--2 {{ $profileLayout ? 'profile-form-grid' : '' }}">
    @if($showCedula)
        <label class="field {{ $profileLayout ? 'field--full' : '' }}">
            <span class="field__label">Cédula de identidad *</span>
            <input
                type="text"
                name="cedula"
                class="{{ $inputClass }}"
                inputmode="numeric"
                maxlength="10"
                required
                autocomplete="off"
                value="{{ old('cedula', $user->cedula) }}"
                @if($readonly) readonly data-profile-field @endif
            >
            @error('cedula') <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p> @enderror
        </label>
    @endif
    <label class="field {{ $profileLayout ? 'field--full' : '' }}">
        <span class="field__label">Carrera *</span>
        <select name="carrera" class="{{ $inputClass }}" required {!! $fieldLock !!}>
            <option value="">Seleccionar…</option>
            @foreach($carreras as $carrera)
                <option value="{{ $carrera['value'] }}" @selected(old('carrera', $user->carrera) === $carrera['value'])>
                    {{ $carrera['label'] }}
                </option>
            @endforeach
        </select>
        @error('carrera') <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p> @enderror
    </label>
    <label class="field">
        <span class="field__label">Número celular *</span>
        <input
            type="tel"
            name="celular"
            class="{{ $inputClass }}"
            inputmode="tel"
            maxlength="15"
            required
            autocomplete="tel"
            value="{{ old('celular', $user->celular) }}"
            {!! $fieldLock !!}
        >
        @error('celular') <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p> @enderror
    </label>
    @if($showEmail)
        <label class="field">
            <span class="field__label">Correo electrónico</span>
            <input type="email" class="{{ $inputClass }}" value="{{ $user->email }}" readonly disabled>
        </label>
    @endif
</div>
