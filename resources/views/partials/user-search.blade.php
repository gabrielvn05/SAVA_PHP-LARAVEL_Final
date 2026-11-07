@php
    $searchId = $id;
    $searchName = $name;
    $searchSelected = (string) ($selected ?? '');
    $searchAllowAll = (bool) ($allowAll ?? false);
    $searchAllLabel = $allLabel ?? 'Todos';
    $searchRequired = (bool) ($required ?? false);
    $searchPlaceholder = $placeholder ?? 'Buscar por nombre, correo o cédula…';
    $searchPeople = collect($people ?? [])->map(function ($persona): array {
        if (is_array($persona)) {
            return $persona;
        }

        $iniciales = collect(preg_split('/\s+/u', trim($persona->nombres.' '.$persona->apellidos)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('');

        return [
            'id' => $persona->id,
            'nombre' => $persona->nombreCompleto(),
            'iniciales' => $iniciales,
            'rol' => $persona->rol->label(),
            'email' => (string) $persona->email,
            'cedula' => (string) ($persona->cedula ?? ''),
            'activo' => (bool) $persona->activo,
        ];
    })->values();
@endphp
<div
    class="user-search"
    data-user-search
    data-allow-all="{{ $searchAllowAll ? '1' : '0' }}"
    data-all-label="{{ $searchAllLabel }}"
    data-required="{{ $searchRequired ? '1' : '0' }}"
    @if(! empty($hint)) data-hint="{{ $hint }}" @endif
    @if(! empty($hintEmpty)) data-hint-empty="{{ $hintEmpty }}" @endif
    @if(! empty($hintSelected)) data-hint-selected="{{ $hintSelected }}" @endif
>
    <input
        type="text"
        id="{{ $searchId }}"
        class="field__input"
        placeholder="{{ $searchPlaceholder }}"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="{{ $searchId }}-lista"
        data-user-search-input
    >
    <input type="hidden" name="{{ $searchName }}" value="{{ $searchSelected }}" data-user-search-value>
    <ul class="user-search__list user-search__list--people" id="{{ $searchId }}-lista" role="listbox" hidden data-user-search-list></ul>
    <script type="application/json" data-user-search-data>@json($searchPeople)</script>
</div>
