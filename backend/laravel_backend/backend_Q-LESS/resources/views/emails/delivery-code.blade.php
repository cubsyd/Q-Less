<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Q-LESS</title>
</head>
<body style="margin:0;padding:32px;background:#edf7df;font-family:Arial,sans-serif;color:#1f3d24;">
    @php
        $items = is_array($order->items) ? $order->items : [];
        $total = number_format((float) $order->total, 0, ',', '.');
    @endphp

    <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #cce8b2;box-shadow:0 16px 36px rgba(47,143,53,.16);">
        <div style="padding:28px;background:linear-gradient(135deg,#35a043,#79c81a);color:#ffffff;text-align:center;">
            <h1 style="margin:0;font-size:34px;letter-spacing:1px;">Q-LESS</h1>
            <p style="margin:8px 0 0;font-size:16px;">Pedido creado correctamente</p>
        </div>

        <div style="padding:30px;">
            <p style="margin:0 0 10px;color:#4b5d43;line-height:1.6;">Recibimos tu compra y la estamos preparando.</p>

            <div style="margin:20px 0;padding:20px;border-radius:16px;background:#f4faee;border:1px solid #d9efc8;text-align:center;">
                <p style="margin:0 0 8px;color:#5d6a58;font-size:14px;">Numero de pedido</p>
                <h2 style="margin:0;color:#2f8f35;font-size:34px;">#{{ $orderNumber }}</h2>
                <p style="margin:10px 0 0;color:#4b5d43;">Presenta este numero para reclamar tu producto.</p>
            </div>

            <h3 style="margin:0 0 12px;color:#1f3d24;">Productos del pedido</h3>

            <table style="width:100%;border-collapse:collapse;border:1px solid #d9efc8;border-radius:12px;overflow:hidden;">
                <thead>
                    <tr style="background:#d9efcb;color:#1f3d24;">
                        <th style="padding:12px;text-align:left;">Producto</th>
                        <th style="padding:12px;text-align:center;">Cant.</th>
                        <th style="padding:12px;text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $name = is_array($item) ? ($item['nombre'] ?? 'Producto Q-LESS') : 'Producto Q-LESS';
                            $quantity = is_array($item) ? max(1, (int) ($item['cantidad'] ?? 1)) : 1;
                            $subtotal = is_array($item) ? number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') : '0';
                            $discountLabel = is_array($item) ? (string) ($item['discount_label'] ?? '') : '';
                            $discountAmount = is_array($item) ? (float) ($item['descuento'] ?? 0) : 0;
                        @endphp
                        <tr>
                            <td style="padding:12px;border-bottom:1px solid #e3efda;color:#1f3d24;font-weight:700;">
                                {{ $name }}
                                @if ($discountLabel !== '' || $discountAmount > 0)
                                    <div style="margin-top:6px;color:#2f8f35;font-size:13px;font-weight:700;">
                                        {{ $discountLabel !== '' ? $discountLabel : 'Descuento aplicado' }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding:12px;border-bottom:1px solid #e3efda;text-align:center;color:#4b5d43;">x{{ $quantity }}</td>
                            <td style="padding:12px;border-bottom:1px solid #e3efda;text-align:right;color:#1f3d24;font-weight:700;">{{ $subtotal }} Pesos</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding:12px;border-bottom:1px solid #e3efda;color:#4b5d43;">Productos registrados en el pedido.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:22px;padding:18px;border-radius:14px;background:linear-gradient(135deg,#f4faee,#ffffff);border:1px solid #d9efc8;display:block;text-align:right;">
                <span style="display:block;color:#5d6a58;font-size:14px;">Total del pedido</span>
                <strong style="display:block;margin-top:4px;color:#2f8f35;font-size:26px;">{{ $total }} Pesos</strong>
            </div>

            <p style="margin:22px 0 0;color:#4b5d43;line-height:1.6;">
                Gracias por usar Q-LESS. Puedes consultar el estado de este pedido desde la seccion Mis pedidos.
            </p>
        </div>
    </div>
</body>
</html>
