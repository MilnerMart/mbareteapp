import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import './searchable-select';

// hacer global jQuery
window.$ = window.jQuery = $;


window.Swal = Swal;

// para abrir modales desde el codigo (ej. los logros al llegar desde la campanita)
window.bootstrap = bootstrap;

// Texto del aviso armado con nodos: los nombres que muestra (ej. un alumno) nunca se interpretan como HTML.
const buildConfirmContent = (text, ...extraNodes) => {
    const content = document.createElement('div');
    if (text) {
        content.append(text);
    }
    extraNodes.forEach((node) => content.append(node));
    return content;
};

// Formularios con data-confirm-delete: piden escribir "eliminar" antes de enviarse (lo que no tiene vuelta atras
// o afecta a otra persona). data-confirm-button cambia el texto del boton (ej. "Quitar").
// Con data-confirm-word="confirmar" piden escribir esa palabra para cambios que afectan a otros sin borrar nada.
const CONFIRM_DELETE_WORD = 'eliminar';

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-delete], form[data-confirm-word]');

    if (!form || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    const isDelete = 'confirmDelete' in form.dataset;
    const confirmWord = form.dataset.confirmWord || CONFIRM_DELETE_WORD;
    const result = await Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmTitle || (isDelete ? 'Confirmar eliminacion' : 'Confirmar'),
        html: buildConfirmContent(form.dataset.confirmText, document.createElement('br'), 'Escribi ',
            Object.assign(document.createElement('b'), { textContent: confirmWord }), ' para confirmar.'),
        input: 'text',
        inputPlaceholder: confirmWord,
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || (isDelete ? 'Eliminar' : 'Confirmar'),
        cancelButtonText: 'Cancelar',
        confirmButtonColor: isDelete ? '#780000' : '#c1121f',
        focusCancel: true,
        inputValidator: (value) => {
            if (value.trim().toLowerCase() !== confirmWord) {
                return `Escribi "${confirmWord}" para confirmar.`;
            }
        },
    });

    if (result.isConfirmed) {
        form.dataset.confirmed = 'true';
        form.submit();
    }
});

// Formularios con data-confirm-remove: confirmacion simple (sin escribir nada) para quitar o borrar algo liviano,
// como una anotacion o un registro. data-confirm-button cambia el texto del boton.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-remove]');

    if (!form || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    const result = await Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmTitle || 'Confirmar',
        html: buildConfirmContent(form.dataset.confirmText),
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        focusCancel: true,
    });

    if (result.isConfirmed) {
        form.dataset.confirmed = 'true';
        form.submit();
    }
});

// Botones con data-confirm-reject: confirman el rechazo de una solicitud antes de enviar su formaction.
$(document).on('click', 'button[data-confirm-reject]', async function (event) {
    event.preventDefault();
    const $button = $(this);

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Rechazar solicitud',
        text: `Rechazaras la solicitud ${$button.data('ticketNumber')} de ${$button.data('requesterName')}. ¿Estas seguro?`,
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        focusCancel: true,
    });

    if (result.isConfirmed) {
        const $form = $button.closest('form');
        $form.attr('action', $button.attr('formaction'));
        $form.get(0).submit();
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');

    if (!button) {
        return;
    }

    const input = document.querySelector(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(isHidden));
    button.setAttribute('aria-label', isHidden ? 'Ocultar contrasena' : 'Mostrar contrasena');

    const icon = button.querySelector('i');

    if (icon) {
        icon.classList.toggle('fa-eye', !isHidden);
        icon.classList.toggle('fa-eye-slash', isHidden);
    }
});

// Casillas con data-toggle-fields: muestran u ocultan un grupo de campos. Los ocultos se deshabilitan y no se envian.
const syncToggleFields = (checkbox) => {
    const group = document.getElementById(checkbox.dataset.toggleFields);

    if (!group) {
        return;
    }

    group.hidden = !checkbox.checked;
    group.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !checkbox.checked;
    });
};

document.querySelectorAll('[data-toggle-fields]').forEach(syncToggleFields);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-toggle-fields]')) {
        syncToggleFields(event.target);
    }
});

// Botones con data-date-preset: ponen la fecha de fin (data-preset-end) contando desde la de inicio (data-preset-start)
// del mismo formulario, con data-preset-days o data-preset-months. Se apagan si el fin quedaria antes del minimo permitido.
const parseLocalDate = (value) => {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatLocalDate = (date) => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
].join('-');

const buildPresetEndDate = (button, startValue) => {
    const endDate = parseLocalDate(startValue);
    endDate.setMonth(endDate.getMonth() + Number(button.dataset.presetMonths || 0));
    endDate.setDate(endDate.getDate() + Number(button.dataset.presetDays || 0) - 1);
    return formatLocalDate(endDate);
};

const syncDatePresets = (form) => {
    const startInput = form.querySelector('[data-preset-start]');
    const endInput = form.querySelector('[data-preset-end]');

    if (!startInput || !endInput) {
        return;
    }

    form.querySelectorAll('[data-date-preset]').forEach((button) => {
        const endValue = startInput.value ? buildPresetEndDate(button, startInput.value) : null;
        button.disabled = !endValue || (endInput.min !== '' && endValue < endInput.min);
        button.classList.toggle('is-active', endValue === endInput.value);
    });
};

document.querySelectorAll('form').forEach((form) => {
    if (form.querySelector('[data-date-preset]')) {
        syncDatePresets(form);
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-date-preset]');
    const form = button?.closest('form');
    const startInput = form?.querySelector('[data-preset-start]');

    if (!startInput?.value) {
        return;
    }

    form.querySelector('[data-preset-end]').value = buildPresetEndDate(button, startInput.value);
    syncDatePresets(form);
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-preset-start], [data-preset-end]')) {
        syncDatePresets(event.target.closest('form'));
    }
});

// Interruptor de tema (data-theme-switch): la perilla se desliza y el tema cambia al momento; despues se guarda la
// eleccion (en la cuenta o en este navegador) enviando el formulario.
document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-theme-switch]');

    if (!form || form.dataset.submitting === 'true') {
        return;
    }

    event.preventDefault();
    form.dataset.submitting = 'true';
    const nextTheme = form.querySelector('input[name="theme"]').value;
    const button = form.querySelector('.theme-switch');
    button.classList.toggle('is-light', nextTheme === 'light');
    button.setAttribute('aria-checked', String(nextTheme === 'light'));
    document.documentElement.dataset.theme = nextTheme;
    setTimeout(() => form.submit(), 250);
});

// Formularios con data-confirm-submit: piden confirmar antes de guardar.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-submit]');

    if (!form || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    const result = await Swal.fire({
        icon: 'question',
        title: form.dataset.confirmTitle || 'Confirmar',
        text: form.dataset.confirmText || '',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Seguir editando',
        confirmButtonColor: '#c1121f',
    });

    if (result.isConfirmed) {
        form.dataset.confirmed = 'true';
        form.submit();
    }
});
