Hola {!! $userName !!},

Gracias por registrarte en {!! config('app.name') !!}. Para confirmar tu correo abre este enlace:

{!! $verificationUrl !!}

El enlace vence en {{ $expiresInHours }} horas. Si no creaste esta cuenta, ignora este correo.
