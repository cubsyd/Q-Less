<!DOCTYPE html>
<html>
<head>
    <title>Verifica tu correo - Q-LESS</title>
</head>
<body>
    <h2>Q-LESS</h2>

    <p>Hola {{ $user->name }},</p>

    <p>
        Para activar tu cuenta, confirma tu correo electronico con el siguiente enlace:
    </p>

    <p>
        <a href="{{ $verificationUrl }}">Verificar correo</a>
    </p>

    <p>
        Si el boton no funciona, copia y pega este enlace en tu navegador:
    </p>

    <p>{{ $verificationUrl }}</p>
</body>
</html>