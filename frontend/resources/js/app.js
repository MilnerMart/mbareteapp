import 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';

// hacer global jQuery
window.$ = window.jQuery = $;


window.Swal = Swal;

// Formularios con data-confirm-delete: piden escribir "eliminar" antes de enviarse.
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
        html: `${form.dataset.confirmText || ''}<br>Escribi <b></b> para confirmar.`,
        input: 'text',
        inputPlaceholder: CONFIRM_DELETE_WORD,
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        didOpen: (popup) => {
            popup.querySelector('.swal2-html-container b').textContent = CONFIRM_DELETE_WORD;
        },
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

// Botones con data-confirm-reject: confirman el rechazo de una solicitud antes de enviar su formaction.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-confirm-reject]');

    if (!button) {
        return;
    }

    event.preventDefault();

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Rechazar solicitud',
        text: `Rechazaras la solicitud ${button.dataset.ticketNumber} de ${button.dataset.requesterName}. ¿Estas seguro?`,
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        focusCancel: true,
    });

    if (result.isConfirmed) {
        const form = button.form;
        form.action = button.formAction;
        form.submit();
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
