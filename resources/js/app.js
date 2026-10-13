import './bootstrap';

function registerPwa() {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

function showLoading(label = 'Cargando…') {
    if (document.querySelector('.loading-overlay')) return;
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `<div class="loading-overlay__panel"><div class="loading-overlay__spinner" aria-hidden="true"></div><span>${label}</span></div>`;
    document.body.appendChild(overlay);
}

function initTopbarMenus() {
    const topbarMenu = document.querySelector('[data-topbar-menu]');
    const topbarMenuToggle = topbarMenu?.querySelector('[data-topbar-menu-toggle]');
    const topbarMenuPanel = topbarMenu?.querySelector('[data-topbar-menu-panel]');
    const notificationsMenu = document.querySelector('[data-notifications-menu]');
    const notificationsToggle = notificationsMenu?.querySelector('[data-notifications-menu-toggle]');
    const notificationsPanel = notificationsMenu?.querySelector('[data-notifications-menu-panel]');

    function setNotificationsMenu(open) {
        if (!notificationsToggle || !notificationsPanel) return;
        notificationsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        notificationsPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        notificationsMenu?.classList.toggle('is-open', open);
    }

    function setTopbarMenu(open) {
        if (!topbarMenuToggle || !topbarMenuPanel) return;
        topbarMenuPanel.hidden = !open;
        topbarMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        topbarMenu?.classList.toggle('is-open', open);
    }

    topbarMenuToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setNotificationsMenu(false);
        setTopbarMenu(topbarMenuPanel?.hidden !== false);
    });

    notificationsToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setTopbarMenu(false);
        setNotificationsMenu(!notificationsMenu?.classList.contains('is-open'));
    });

    document.addEventListener('click', (event) => {
        if (topbarMenu?.classList.contains('is-open')) {
            if (!(event.target instanceof Node && topbarMenu.contains(event.target))) {
                setTopbarMenu(false);
            }
        }
        if (notificationsMenu?.classList.contains('is-open')) {
            if (!(event.target instanceof Node && notificationsMenu.contains(event.target))) {
                setNotificationsMenu(false);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (notificationsMenu?.classList.contains('is-open')) {
            setNotificationsMenu(false);
        } else if (topbarMenu?.classList.contains('is-open')) {
            setTopbarMenu(false);
        }
    });

    setNotificationsMenu(false);
    setTopbarMenu(false);

    return { setTopbarMenu, setNotificationsMenu, notificationsMenu, topbarMenu };
}

function initSidebar() {
    const shell = document.querySelector('[data-app-shell]');
    if (!shell) return;

    const toggle = shell.querySelector('[data-sidebar-toggle]');
    const panel = shell.querySelector('[data-sidebar-panel]');
    const backdrop = shell.querySelector('[data-sidebar-backdrop]');
    const html = document.documentElement;

    function setOpen(open) {
        panel?.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-visible', open);
        backdrop?.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle?.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        html.classList.toggle('sidebar-open', open);
    }

    toggle?.addEventListener('click', () => setOpen(!panel?.classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setOpen(false));

    function setNavGroupOpen(group, open) {
        const btn = group.querySelector('[data-nav-group-btn]');
        const sub = group.querySelector('[data-nav-group-sub]');
        const chevron = group.querySelector('.sidebar-nav__chevron');
        btn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        group.classList.toggle('is-open', open);
        if (sub) {
            if (open) {
                sub.removeAttribute('hidden');
            } else {
                sub.setAttribute('hidden', '');
            }
        }
        if (chevron) {
            chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const btn = group.querySelector('[data-nav-group-btn]');
        const initiallyOpen = btn?.getAttribute('aria-expanded') === 'true';
        setNavGroupOpen(group, initiallyOpen);

        btn?.addEventListener('click', (event) => {
            event.preventDefault();
            const open = btn.getAttribute('aria-expanded') !== 'true';
            setNavGroupOpen(group, open);
        });
    });

    panel?.querySelectorAll('.sidebar-nav__link, .sidebar-nav__sublink').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                setOpen(false);
            }
        });
    });

    const modal = document.querySelector('[data-logout-modal]');
    function setLogout(open) {
        if (!modal) return;
        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    document.querySelectorAll('[data-logout-open]').forEach((openBtn) => {
        openBtn.addEventListener('click', (event) => {
            event.preventDefault();
            setLogout(true);
        });
    });

    modal?.querySelectorAll('[data-logout-close]').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setLogout(false);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (document.querySelector('[data-profile-modal]')) return;
        if (modal && !modal.hidden) setLogout(false);
        else setOpen(false);
    });

    const media = window.matchMedia('(min-width: 1024px)');
    media.addEventListener('change', () => {
        if (media.matches) setOpen(false);
    });
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const wrap = btn.closest('.password-input-wrap');
        const input = wrap?.querySelector('input');
        if (!input) return;
        btn.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
            btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    });
}

function initLoadingForms() {
    document.querySelectorAll('form:not([data-no-loading])').forEach((form) => {
        form.addEventListener('submit', () => {
            const label = form.getAttribute('data-loading-label') || 'Procesando…';
            showLoading(label);
        });
    });
}

function initWizard() {
    const cards = document.querySelectorAll('.wizard-tipo-card');
    const tipoInput = document.getElementById('tipo-input');
    const panelTipo = document.getElementById('panel-tipo');
    const panelDatos = document.getElementById('panel-datos');
    const progress = document.getElementById('wizard-progress-step-1');
    const continueBtn = document.querySelector('[data-wizard-continue]');
    const backBtn = document.querySelector('[data-wizard-back]');
    const anexoLabel = document.getElementById('anexo-label');
    const wizardTipoDesc = document.getElementById('wizard-tipo-desc');
    const instTipo = document.getElementById('institucion-medica-tipo');
    if (!cards.length || !tipoInput) return;

    const wizardDescDefault = wizardTipoDesc?.textContent?.trim() || '';

    function showFields(tipo) {
        document.querySelectorAll('.wizard-field').forEach((el) => {
            el.hidden = true;
        });
        const map = {
            enfermedad: ['inasistencia', 'enfermedad', 'motivo', 'observaciones'],
            calamidad_domestica: ['inasistencia', 'calamidad', 'motivo', 'observaciones'],
            viaje: ['viaje', 'observaciones'],
            falta_marcado: ['falta', 'falta-motivo', 'observaciones'],
            permiso: ['permiso', 'permiso-motivo', 'observaciones'],
            justificacion: ['justificacion-atraso', 'justificacion-motivo', 'observaciones'],
        };
        (map[tipo] || ['fechas', 'motivo', 'observaciones']).forEach((key) => {
            document.querySelectorAll(`.wizard-field--${key}`).forEach((el) => { el.hidden = false; });
        });
        if (wizardTipoDesc) {
            wizardTipoDesc.textContent = tipo === 'justificacion'
                ? 'La justificación por atraso aplica únicamente a la fecha de hoy.'
                : wizardDescDefault;
        }
        syncPermisoMotivoUi();
        syncJustificacionMotivoUi();
        syncFaltaMarcadoUi();
        if (anexoLabel) {
            anexoLabel.textContent = tipo === 'enfermedad'
                ? 'Adjuntar certificado o soporte médico *'
                : 'Adjuntar documento de respaldo (opcional)';
        }
        syncInstitucionNombre();
    }

    function syncFaltaMarcadoUi() {
        const tipoMarcacion = document.getElementById('tipo-marcacion-omitida-select');
        const ingresoWrap = document.querySelector('.wizard-falta-hora--ingreso');
        const salidaWrap = document.querySelector('.wizard-falta-hora--salida');
        const ingresoInput = ingresoWrap?.querySelector('input[name="hora_real_ingreso"]');
        const salidaInput = salidaWrap?.querySelector('input[name="hora_real_salida"]');
        const value = tipoMarcacion?.value || '';

        const showIngreso = value === 'entrada' || value === 'entrada_salida';
        const showSalida = value === 'salida' || value === 'entrada_salida';

        if (ingresoWrap) {
            ingresoWrap.hidden = !showIngreso;
        }
        if (salidaWrap) {
            salidaWrap.hidden = !showSalida;
        }
        if (ingresoInput) {
            ingresoInput.required = showIngreso;
            if (!showIngreso) {
                ingresoInput.value = '';
            }
        }
        if (salidaInput) {
            salidaInput.required = showSalida;
            if (!showSalida) {
                salidaInput.value = '';
            }
        }

        const motivoSelect = document.getElementById('motivo-falta-registro-select');
        const motivoHint = document.getElementById('motivo-falta-registro-hint');
        if (motivoSelect && motivoHint) {
            const option = motivoSelect.options[motivoSelect.selectedIndex];
            motivoHint.textContent = option?.dataset?.hint || '';
        }
    }

    function syncJustificacionMotivoUi() {
        const select = document.getElementById('motivo-atraso-select');
        const hint = document.getElementById('motivo-atraso-hint');
        if (!select || !hint) {
            return;
        }
        const option = select.options[select.selectedIndex];
        hint.textContent = option?.dataset?.hint || '';
    }

    function syncPermisoMotivoUi() {
        const select = document.getElementById('motivo-permiso-select');
        const hint = document.getElementById('motivo-permiso-hint');
        const otroWrap = document.querySelector('.wizard-field--permiso-motivo-otro');
        if (!select || !hint) {
            return;
        }
        const option = select.options[select.selectedIndex];
        hint.textContent = option?.dataset?.hint || '';
        if (otroWrap) {
            otroWrap.hidden = select.value !== 'otro';
        }
    }

    function syncInstitucionNombre() {
        const wrap = document.querySelector('.wizard-field--institucion-nombre');
        if (!wrap || !instTipo) return;
        const show = !document.querySelector('.wizard-field--enfermedad')?.hidden
            && instTipo.value
            && instTipo.value !== 'IESS';
        wrap.hidden = !show;
    }

    function selectTipo(tipo, card) {
        cards.forEach((c) => c.classList.remove('wizard-tipo-card--active', 'is-selected'));
        card.classList.add('wizard-tipo-card--active', 'is-selected');
        tipoInput.value = tipo;
        if (continueBtn) continueBtn.disabled = false;
        showFields(tipo);
    }

    function setStep(step) {
        if (panelTipo) panelTipo.hidden = step !== 0;
        if (panelDatos) panelDatos.hidden = step !== 1;
        if (progress) progress.classList.toggle('is-done', step >= 1);
        const top = document.querySelector('.page-header') || document.getElementById('wizard-form');
        top?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    cards.forEach((card) => {
        card.addEventListener('click', () => selectTipo(card.dataset.tipo, card));
    });

    document.getElementById('motivo-permiso-select')?.addEventListener('change', syncPermisoMotivoUi);
    document.getElementById('motivo-atraso-select')?.addEventListener('change', syncJustificacionMotivoUi);
    document.getElementById('tipo-marcacion-omitida-select')?.addEventListener('change', syncFaltaMarcadoUi);
    document.getElementById('motivo-falta-registro-select')?.addEventListener('change', syncFaltaMarcadoUi);
    continueBtn?.addEventListener('click', () => {
        if (tipoInput.value) setStep(1);
    });
    backBtn?.addEventListener('click', () => setStep(0));
    instTipo?.addEventListener('change', syncInstitucionNombre);

    if (tipoInput.value) {
        const selected = document.querySelector(`[data-tipo="${tipoInput.value}"]`);
        if (selected) {
            selectTipo(tipoInput.value, selected);
            setStep(1);
        }
    }
}

function initRechazoModal() {
    const modal = document.querySelector('[data-rechazo-modal]');
    const openBtn = document.querySelector('[data-rechazo-open]');
    if (!modal || !openBtn) return;

    function setOpen(open) {
        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) {
            modal.querySelector('textarea')?.focus();
        }
    }

    openBtn.addEventListener('click', () => setOpen(true));
    modal.querySelectorAll('[data-rechazo-close]').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            setOpen(false);
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            setOpen(false);
        }
    });
}

function initUserAdminModals() {
    if (window.__SAVA_USER_ADMIN_MODALS) {
        return;
    }

    const modal = document.querySelector('[data-user-admin-modal]');
    if (!modal) {
        return;
    }

    const titleEl = modal.querySelector('[data-user-admin-modal-title]');
    const subtitleEl = modal.querySelector('[data-user-admin-modal-subtitle]');
    const bodyEl = modal.querySelector('[data-user-admin-modal-body]');

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        bodyEl?.replaceChildren();
    }

    function openModal(templateEl) {
        if (!titleEl || !bodyEl || !templateEl?.content) {
            return;
        }

        titleEl.textContent = templateEl.dataset.modalTitle || 'Acción';
        const subtitle = templateEl.dataset.modalSubtitle || '';
        if (subtitleEl) {
            subtitleEl.textContent = subtitle;
            subtitleEl.hidden = subtitle === '';
        }

        bodyEl.replaceChildren();
        bodyEl.appendChild(templateEl.content.cloneNode(true));

        const form = bodyEl.querySelector('form');
        form?.addEventListener('submit', () => {
            showLoading(form.getAttribute('data-loading-label') || 'Procesando…');
        }, { once: true });

        modal.hidden = false;
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        bodyEl.querySelector('select, button, input, textarea')?.focus();
    }

    document.addEventListener('change', (event) => {
        const picker = event.target.closest('[data-user-action-picker]');
        if (!picker) {
            return;
        }

        const action = picker.value;
        if (!action) {
            return;
        }

        const userId = picker.getAttribute('data-user-id');
        const templateKey = `${userId}-${action}`;
        const templateEl = document.querySelector(`template[data-user-admin-template="${templateKey}"]`);

        picker.value = '';

        if (!templateEl) {
            return;
        }

        openModal(templateEl);
    });

    modal.addEventListener('click', (event) => {
        if (event.target.closest('[data-user-admin-modal-close]')) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function initProfileEditable() {
    const root = document.querySelector('[data-profile-editable]');
    if (!root) return;

    const form = root.querySelector('form');
    const toggle = root.querySelector('[data-profile-edit-toggle]');
    const saveBtn = root.querySelector('[data-profile-save]');
    const fields = () => root.querySelectorAll('[data-profile-field]');

    function setEditing(editing) {
        fields().forEach((field) => {
            field.disabled = !editing;
        });
        if (toggle) toggle.hidden = editing;
        if (saveBtn) saveBtn.hidden = !editing;
        root.classList.toggle('is-editing', editing);
    }

    toggle?.addEventListener('click', () => setEditing(true));

    form?.addEventListener('submit', () => {
        fields().forEach((field) => {
            field.disabled = false;
        });
    });

    if (root.querySelector('.alert--error')) {
        setEditing(true);
    }
}

function initDocViewers() {
    document.querySelectorAll('[data-doc-print]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const src = btn.getAttribute('data-doc-print');
            if (!src) return;
            window.open(src, '_blank', 'noopener,noreferrer')?.focus();
        });
    });
}

registerPwa();
document.addEventListener('DOMContentLoaded', () => {
    initTopbarMenus();
    initSidebar();
    initPasswordToggles();
    initLoadingForms();
    initWizard();
    initDocViewers();
    initRechazoModal();
    initUserAdminModals();
    initProfileEditable();
});
