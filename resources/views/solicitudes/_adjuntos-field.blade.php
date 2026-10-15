@php
    $existingCount = $existingCount ?? 0;
    $requiredWhenEnfermedad = $requiredWhenEnfermedad ?? false;
@endphp
<div
    class="stack"
    data-solicitud-adjuntos
    data-max-files="{{ \App\Support\SolicitudAdjuntoLimits::MAX_FILES }}"
    data-max-total-bytes="{{ \App\Support\SolicitudAdjuntoLimits::MAX_TOTAL_KB * 1024 }}"
    data-existing-count="{{ $existingCount }}"
    data-required-when="{{ $requiredWhenEnfermedad ? '1' : '0' }}"
    style="gap: 0.5rem;"
>
    <label class="field" style="margin: 0;">
        <span class="field__label" id="anexo-label">{{ $label ?? 'Adjuntar documento de respaldo (opcional)' }}</span>
        <input
            type="file"
            name="anexos[]"
            class="field__input"
            multiple
            accept=".pdf,.png,.jpg,.jpeg"
            data-solicitud-adjuntos-input
        >
        <span class="field-hint">
            PDF, PNG o JPG. Hasta {{ \App\Support\SolicitudAdjuntoLimits::MAX_FILES }} archivos y
            {{ (int) (\App\Support\SolicitudAdjuntoLimits::MAX_TOTAL_KB / 1024) }} MB de peso total entre todos.
            Puedes ir agregando archivos de uno en uno o varios a la vez; se listan abajo antes de enviar.
        </span>
    </label>
    <p class="field-hint" data-solicitud-adjuntos-message hidden style="margin: 0; color: var(--color-danger);"></p>
    <ul class="stack" data-solicitud-adjuntos-list hidden style="list-style: none; padding: 0; margin: 0; gap: 0.25rem;"></ul>
</div>
