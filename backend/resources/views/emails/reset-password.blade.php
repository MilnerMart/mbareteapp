<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia tu contraseña</title>
</head>
<body style="margin:0;padding:0;background-color:#0b0b0b;font-family:Arial,Helvetica,sans-serif;color:#e5e5e5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0b0b0b;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#161616;border:1px solid #2a2a2a;border-radius:12px;">
                    <tr>
                        <td style="padding:28px 28px 8px;border-top:4px solid #c1121f;border-radius:12px 12px 0 0;">
                            <h1 style="margin:0;font-size:22px;color:#ffffff;">Cambia tu contraseña</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 0;font-size:15px;line-height:1.6;color:#d8d8d8;">
                            <p style="margin:0 0 12px;">Hola {{ $userName }},</p>
                            <p style="margin:0 0 20px;">Recibimos un pedido para cambiar la contraseña de tu cuenta en {{ config('app.name') }}. Toca el botón para elegir una nueva.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 28px 24px;">
                            <a href="{{ $resetUrl }}" style="display:inline-block;background-color:#c1121f;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 28px;border-radius:8px;">Cambiar contraseña</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;font-size:13px;line-height:1.5;color:#a8a8a8;">
                            <p style="margin:0 0 8px;">El enlace vence en {{ $expiresInMinutes }} minutos y sirve una sola vez. Si el botón no funciona, copia esta dirección en tu navegador:</p>
                            <p style="margin:0 0 16px;word-break:break-all;"><a href="{{ $resetUrl }}" style="color:#ff4d5a;">{{ $resetUrl }}</a></p>
                            <p style="margin:0;">Si no lo pediste, ignora este correo: tu contraseña no cambia.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
