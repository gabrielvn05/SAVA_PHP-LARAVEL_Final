@php
    use App\Support\SolicitudAdjuntos;

    $existingCount = $existingCount ?? count($anexos ?? []);
    $requiredWhenEnfermedad = $requiredWhenEnfermedad ?? false;
    $anexos = $anexos ?? [];
@endphp
<div
    class="stack stack--tight"
    data-solicitud-adjuntos
    data-max-files="{{ \App\Support\SolicitudAdjuntoLimits::MAX_FILES }}"
    data-max-total-bytes="{{ \App\Support\SolicitudAdjuntoLimits::MAX_TOTAL_KB * 1024 }}"
    data-existing-count="{{ $existingCount }}"
    data-required-when="{{ $requiredWhenEnfermedad ? '1' : '0' }}"
    data-required-message="{{ $requiredMessage ?? '' }}"
>
    @if($anexos !== [] && isset($solicitud))
        <fieldset class="stack fieldset-reset">
            <legend class="field__label">Archivos actuales</legend>
            <p class="field-hint field-hint--spaced">
                Marca solo los que quieres <strong>quitar</strong> al guardar. Luego agrega los nuevos abajo (no reemplazan solos).
            </p>
            <ul class="adjuntos-actuales">
                @foreach($anexos as $anexo)
                    @php
                        $path = (string) ($anexo['path'] ?? '');
                        $nombre = (string) ($anexo['nombre'] ?? 'Documento');
                        $accesible = $path !== '' && SolicitudAdjuntos::diskConArchivo($path) !== null;
                    @endphp
                    <li class="adjuntos-actuales__item">
                        <label class="adjuntos-actuales__check">
                            <input
                                type="checkbox"
                                name="eliminar_anexos[]"
                                value="{{ $path }}"
                                data-solicitud-adjuntos-remove-existing
                                @checked(in_array($path, old('eliminar_anexos', []), true))
                            >
                            <span class="adjuntos-actuales__nombre">{{ $nombre }}</span>
                        </label>
                        @if($accesible)
                            <a href="{{ SolicitudAdjuntos::urlVista($solicitud, $path) }}" class="btn btn--secondary btn--sm adjuntos-actuales__ver" target="_blank" rel="noopener">
                                Ver archivo
                            </a>
                        @else
                            <span class="adjuntos-actuales__falta">Archivo no disponible (se limpiará al guardar)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endif

    <label class="field">
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
            PDF, PNG o JPG. Hasta {{ \App\Support\SolicitudAdjuntoLimits::MAX_FILES }} archivos
            (incluido el documento generado por el sistema) y
            {{ (int) (\App\Support\SolicitudAdjuntoLimits::MAX_TOTAL_KB / 1024) }} MB en total.
        </span>
    </label>
    <p class="field-hint field-hint--danger" data-solicitud-adjuntos-message hidden></p>
    <ul class="stack stack--tight plain-list" data-solicitud-adjuntos-list hidden></ul>
</div>
