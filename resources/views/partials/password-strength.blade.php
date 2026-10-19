<div class="password-strength" data-password-strength="{{ $inputId }}">
    <div class="password-strength__meter" aria-hidden="true">
        <span class="password-strength__bar" data-password-strength-bar></span>
    </div>
    <p class="password-strength__label" data-password-strength-label>Nivel de seguridad</p>
    <ul class="password-strength__rules">
        <li data-rule="length">Más de 8 caracteres</li>
        <li data-rule="upper">Una letra mayúscula</li>
        <li data-rule="lower">Una letra minúscula</li>
        <li data-rule="number">Un número</li>
        <li data-rule="special">Un carácter especial</li>
    </ul>
</div>
