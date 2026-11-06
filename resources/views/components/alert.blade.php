@props(['type' => 'success', 'autohide' => false])

<div
    {{ $attributes->class(['alert', 'alert--'.$type, 'alert--dismissible']) }}
    role="{{ $type === 'success' ? 'status' : 'alert' }}"
    @if($autohide) data-autohide @endif
>
    <div class="alert__body">{{ $slot }}</div>
    <button type="button" class="alert__close" data-alert-close aria-label="Cerrar aviso">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </button>
</div>
