{{-- avisos generales de la cuenta: confirmacion del correo y mensajes de una sola vez --}}
@if (session('auth_notice'))
    <div class="alert alert-success account-notice">{{ session('auth_notice') }}</div>
@endif
@if (session('auth_error'))
    <div class="alert alert-danger account-notice">{{ session('auth_error') }}</div>
@endif
@if (session('auth_user') && session('auth_user.email_verified') === false)
    <div class="alert account-notice account-verify-notice">
        <span>
            <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
            Confirma tu correo <strong>{{ session('auth_user.email') }}</strong> con el enlace que te enviamos.
        </span>
        <form method="POST" action="{{ route('user.verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light">Reenviar correo</button>
        </form>
    </div>
@endif
