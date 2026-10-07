import 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';

// hacer global jQuery
window.$ = window.jQuery = $;


window.Swal = Swal;

// Formularios con data-confirm-name: piden escribir ese nombre antes de enviarse.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-name]');

    if (!form || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();
    const name = form.dataset.confirmName;

    const result = await Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmTitle || 'Confirmar eliminacion',
        html: `${form.dataset.confirmText || ''}<br>Escribi <b></b> para confirmar.`,
        input: 'text',
        inputPlaceholder: name,
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#780000',
        didOpen: (popup) => {
            popup.querySelector('.swal2-html-container b').textContent = name;
        },
        inputValidator: (value) => {
            if (value.trim() !== name) {
                return 'El nombre no coincide.';
            }
        },
    });

    if (result.isConfirmed) {
        form.dataset.confirmed = 'true';
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
