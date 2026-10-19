const RULES = {
    length: (value) => value.length > 8,
    upper: (value) => /[A-Z]/.test(value),
    lower: (value) => /[a-z]/.test(value),
    number: (value) => /\d/.test(value),
    special: (value) => /[^A-Za-z0-9]/.test(value),
};

const LEVELS = [
    { max: 0, label: 'Nivel de seguridad', tone: 'empty', width: '0%' },
    { max: 2, label: 'Débil', tone: 'weak', width: '28%' },
    { max: 3, label: 'Media', tone: 'fair', width: '55%' },
    { max: 4, label: 'Fuerte', tone: 'good', width: '78%' },
    { max: 5, label: 'Muy segura', tone: 'strong', width: '100%' },
];

function levelFor(score) {
    return LEVELS.find((level) => score <= level.max) ?? LEVELS[LEVELS.length - 1];
}

export function initPasswordStrength() {
    document.querySelectorAll('[data-password-strength]').forEach((box) => {
        const input = document.getElementById(box.getAttribute('data-password-strength') || '');
        const bar = box.querySelector('[data-password-strength-bar]');
        const label = box.querySelector('[data-password-strength-label]');
        if (!input || !bar || !label) {
            return;
        }

        const paint = () => {
            const value = input.value;
            let score = 0;
            box.querySelectorAll('[data-rule]').forEach((item) => {
                const met = value !== '' && RULES[item.getAttribute('data-rule')]?.(value) === true;
                item.classList.toggle('is-met', met);
                if (met) {
                    score += 1;
                }
            });

            const level = value === '' ? LEVELS[0] : levelFor(score);
            bar.style.width = level.width;
            label.textContent = level.label;
            box.dataset.tone = level.tone;
            input.setCustomValidity(value !== '' && score < 5
                ? 'La contraseña debe tener más de 8 caracteres, con mayúscula, minúscula, número y carácter especial.'
                : '');
        };

        input.addEventListener('input', paint);
        paint();
    });
}
