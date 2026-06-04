<!DOCTYPE html>
<html>
<head>
    <title>Q-LESS</title>
</head>
<body>

    <h2>Q-LESS</h2>

    <p>Tu pedido fue creado correctamente.</p>

    <h1>Pedido #{{ $orderNumber }}</h1>

    <p>
        Recibimos tu compra y la estamos preparando. Presenta este numero para reclamar tu producto.
    </p>

    <p>Total: {{ number_format((float) $order->total, 0, ',', '.') }} Pesos</p>

</body>
</html>
