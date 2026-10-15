@php
    use App\Support\SolicitudAdjuntos;

    $existingCount = $existingCount ?? count($anexos ?? []);
    $requiredWhenEnfermedad = $requiredWhenEnfermedad ?? false;
    $anexos = $anexos ?? [];
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
    @if($anexos !== [] && isset($solicitud))
        <fieldset class="stack" style="border: 0; padding: 0; margin: 0;">
            <legend class="field__label">Archivos actuales</legend>
            <p class="field-hint" style="margin: 0 0 0.5rem;">
                Marca solo los que quieres <strong>quitar</strong> al guardar. Luego agrega los nuevos abajo (no reemplazan solos).
            </p>
            <ul class="stack" style="list-style: none; padding: 0; margin: 0; gap: 0.65rem;">
                @foreach($anexos as $anexo)
                    @php
                        $path = (string) ($anexo['path'] ?? '');
                        $nombre = (string) ($anexo['nombre'] ?? 'Documento');
                        $accesible = $path !== '' && SolicitudAdjuntos::diskConArchivo($path) !== null;
                    @endphp
                    <li class="row" style="align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <label class="row" style="align-items: center; gap: 0.5rem; margin: 0;">
                            <input
                                type="checkbox"
                                name="eliminar_anexos[]"
                                value="{{ $path }}"
                                data-solicitud-adjuntos-remove-existing
                                @checked(in_array($path, old('eliminar_anexos', []), true))
                            >
                            <span>{{ $nombre }}</span>
                        </label>
                        @if($accesible)
                            <a href="{{ SolicitudAdjuntos::urlVista($solicitud, $path) }}" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">
                                Ver archivo
                            </a>
                        @else
                            <span class="field-hint" style="margin: 0; color: var(--color-danger);">Archivo no disponible (se limpiará al guardar)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endif

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
