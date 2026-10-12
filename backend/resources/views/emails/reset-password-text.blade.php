Hola {!! $userName !!},

Recibimos un pedido para cambiar la contraseña de tu cuenta en {!! config('app.name') !!}. Para elegir una nueva abre este enlace:

{!! $resetUrl !!}

El enlace vence en {{ $expiresInMinutes }} minutos y sirve una sola vez. Si no lo pediste, ignora este correo: tu contraseña no cambia.
