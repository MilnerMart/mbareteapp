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
const CONFIRM_DELETE_WORD = 'eliminar';

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-delete]');

    if (!form || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    const result = await Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmTitle || 'Confirmar eliminacion',
        html: buildConfirmContent(form.dataset.confirmText, document.createElement('br'), 'Escribi ',
            Object.assign(document.createElement('b'), { textContent: CONFIRM_DELETE_WORD }), ' para confirmar.'),
        input: 'text',
        inputPlaceholder: CONFIRM_DELETE_WORD,
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        focusCancel: true,
        inputValidator: (value) => {
            if (value.trim().toLowerCase() !== CONFIRM_DELETE_WORD) {
                return `Escribi "${CONFIRM_DELETE_WORD}" para confirmar.`;
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
