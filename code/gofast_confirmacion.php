<?php
/*******************************************************
 * ✅ GOFAST — CONFIRMACIÓN DE SERVICIO
 * Shortcode: [gofast_confirmacion]
 * URL: /servicio-registrado?id=XXX
 *******************************************************/

add_shortcode("gofast_confirmacion", function() {

    if (session_status() === PHP_SESSION_NONE) session_start();
    global $wpdb;

    $table = "servicios_gofast";

    /* ==========================================================
       1. Validar ID
    ========================================================== */
    if (empty($_GET["id"])) {
        return "<div class='gofast-box'>❌ No se encontró el pedido.</div>";
    }

    $id = intval($_GET["id"]);
    $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$pedido) {
        return "<div class='gofast-box'>⚠️ Pedido no encontrado.</div>";
    }

    /* ==========================================================
       2. Vincular usuario automáticamente por teléfono
    ========================================================== */
    if (!empty($pedido->telefono_cliente) && empty($_SESSION["gofast_user_id"])) {
        $tel = trim($pedido->telefono_cliente);

        $u = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM usuarios_gofast WHERE telefono = %s LIMIT 1",
            $tel
        ));

        if ($u) {
            // ⚠️ Asocia en DB pero no inicia sesión "visible"
            $wpdb->update($table, ["user_id" => $u->id], ["id" => $id]);
            $_SESSION["gofast_auto_linked"] = true;
            $_SESSION["gofast_user_id"] = intval($u->id);
        }
    }

    /* ==========================================================
       3. Decodificar JSON de destinos y preparar datos
    ========================================================== */
    $json = json_decode($pedido->destinos, true);
    $destinos = $json["destinos"] ?? [];
    $origen_data = $json["origen"] ?? [];
    
    // Obtener nombre del origen
    $nombre_origen = '';
    if (!empty($origen_data['barrio_nombre'])) {
        $nombre_origen = $origen_data['barrio_nombre'];
    } elseif (!empty($origen_data['direccion'])) {
        $nombre_origen = $origen_data['direccion'];
    } elseif (!empty($pedido->direccion_origen)) {
        $nombre_origen = $pedido->direccion_origen;
    }
    
    // Preparar detalle de destinos (similar al mensajero)
    $detalle_envios = [];
    $total_calculado = 0;
    
    foreach ($destinos as $d) {
        $nombre_destino = '';
        if (!empty($d['barrio_nombre'])) {
            $nombre_destino = $d['barrio_nombre'];
        } elseif (!empty($d['direccion'])) {
            $nombre_destino = $d['direccion'];
        }
        
        if (empty($nombre_destino)) continue;
        
        // Obtener monto (si está en el JSON)
        $monto_destino = !empty($d['monto']) ? intval($d['monto']) : 0;
        
        // Si no hay monto en el JSON, intentar calcularlo
        if ($monto_destino == 0 && !empty($origen_data['sector_id']) && !empty($d['sector_id'])) {
            $precio = $wpdb->get_var($wpdb->prepare(
                "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                intval($origen_data['sector_id']),
                intval($d['sector_id'])
            ));
            $monto_destino = $precio ? intval($precio) : 0;
        }
        
        $detalle_envios[] = [
            'id' => !empty($d['barrio_id']) ? intval($d['barrio_id']) : 0,
            'nombre' => $nombre_destino,
            'precio' => $monto_destino,
            'recargo' => 0, // Los recargos ya están incluidos en el total del servicio
            'total' => $monto_destino,
        ];
        
        $total_calculado += $monto_destino;
    }
    
    // Si no hay destinos con monto, usar el total del servicio dividido entre destinos
    if ($total_calculado == 0 && !empty($detalle_envios) && $pedido->total > 0) {
        $monto_por_destino = intval($pedido->total / count($detalle_envios));
        foreach ($detalle_envios as &$de) {
            $de['precio'] = $monto_por_destino;
            $de['total'] = $monto_por_destino;
        }
        $total_calculado = $pedido->total;
    }

    /* ==========================================================
       4. Preparar mensaje para WhatsApp
    ========================================================== */
    $telefono_empresa = "573004452422";
    $mensaje = urlencode(
        "🚀 Hola, acabo de solicitar un servicio en GoFast.\n\n" .
        "📦 Servicio: #$id\n" .
        "📍 Origen: {$pedido->direccion_origen}\n" .
        "💰 Total: $" . number_format($pedido->total, 0, ',', '.') . "\n\n" .
        "Por favor confirmar la recogida. Gracias."
    );

    /* ==========================================================
       5. INTERFAZ VISUAL
    ========================================================== */
    ob_start();
    ?>

<div class="gofast-box" style="max-width:650px;margin:25px auto;padding:20px;">
    <!-- ⚠️ ALERTA IMPORTANTE -->
    <div style="background:#fff9d6;border-left:5px solid #F4C524;padding:14px 16px;margin-bottom:25px;border-radius:8px;line-height:1.5;">
        <b>Importante:</b><br>
        • Un coordinador te contactará pronto para asignar el mensajero.<br>
        • Si deseas cancelar, hazlo lo antes posible.<br>
        • Si ya fue asignado un mensajero, deberás cubrir el valor del servicio.
    </div>

    <!-- 💬 BOTÓN PRINCIPAL -->
    <div style="text-align:center;margin-bottom:30px;">
        <h2 style="margin-bottom:10px;font-weight:800;color:#25D366;">
            ✅ ¡Servicio registrado con éxito!
        </h2>
        <p style="font-size:17px;margin-bottom:20px;">
            Número de servicio: <b>#<?= $pedido->id ?></b><br>
            <span style="font-size:15px;">Confirma tu pedido tocando el botón verde 👇</span>
        </p>

        <a id="btnWhatsApp"
           href="#"
           target="_blank"
           style="display:inline-block;background:#25D366;color:white;font-size:20px;font-weight:800;padding:18px 36px;border-radius:12px;text-decoration:none;box-shadow:0 4px 8px rgba(0,0,0,0.15);transition:all .2s ease;">
           💬 Confirmar por WhatsApp
        </a>

        <p style="margin-top:12px;color:#555;font-size:14px;">
            Si no se abre automáticamente, toca el botón de nuevo.
        </p>
    </div>

    <!-- 📍 RESUMEN DEL SERVICIO (Estilo mensajero) -->
    <div class="gofast-checkout-wrapper" style="margin-top:25px;">
        <div class="gofast-box" style="max-width:800px;margin:0 auto;">
            
            <div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:12px;margin-bottom:20px;border-radius:8px;">
                <strong>🚚 Resumen del Servicio</strong><br>
                <small>Detalle de tu pedido registrado.</small>
            </div>

            <?php if (!empty($nombre_origen)): ?>
                <h3 style="margin-top:0;">📍 Origen: <?= esc_html($nombre_origen) ?></h3>
            <?php endif; ?>

            <div id="destinos-resumen">
                <?php if (!empty($detalle_envios)): ?>
                    <?php foreach ($detalle_envios as $idx => $d): ?>
                        <div class="gofast-destino-resumen-item" 
                             data-destino-id="<?= esc_attr($d['id']) ?>"
                             data-precio="<?= esc_attr($d['precio']) ?>"
                             data-recargo="<?= esc_attr($d['recargo']) ?>"
                             data-total="<?= esc_attr($d['total']) ?>">
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f8f9fa;border-radius:8px;margin-bottom:10px;border-left:4px solid #F4C524;">
                                <div style="flex:1;">
                                    <strong>🎯 <?= esc_html($d['nombre']) ?></strong><br>
                                    <?php if ($d['precio'] > 0): ?>
                                        <small style="color:#666;">
                                            Base: $<?= number_format($d['precio'], 0, ',', '.') ?>
                                            <?php if ($d['recargo'] > 0): ?>
                                                | Recargo: $<?= number_format($d['recargo'], 0, ',', '.') ?>
                                            <?php endif; ?>
                                        </small><br>
                                    <?php endif; ?>
                                    <?php if ($d['total'] > 0): ?>
                                        <strong style="color:#4CAF50;font-size:18px;">
                                            $<?= number_format($d['total'], 0, ',', '.') ?>
                                        </strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#666;">(No se registraron destinos)</p>
                <?php endif; ?>
            </div>

            <div class="gofast-total-box" style="margin:20px 0;text-align:center;" id="total-box">
                💰 <strong style="font-size:24px;" id="total-amount">Total: $<?= number_format($pedido->total > 0 ? $pedido->total : $total_calculado, 0, ',', '.') ?></strong>
            </div>

        </div>
    </div>

    <!-- 👤 RESUMEN DEL CLIENTE -->
    <div style="margin-top:25px;background:#fafafa;border-radius:10px;padding:14px 18px;line-height:1.6;font-size:15px;">
        <h3 style="margin-top:0;font-size:17px;">👤 Detalle del cliente</h3>
        <p><strong>Nombre:</strong> <?= esc_html($pedido->nombre_cliente) ?></p>
        <p><strong>Teléfono:</strong> <?= esc_html($pedido->telefono_cliente) ?></p>
        <p><strong>Dirección origen:</strong> <?= esc_html($pedido->direccion_origen) ?></p>
        <p><strong>Total:</strong> $<?= number_format($pedido->total, 0) ?></p>
        <p><strong>Estado:</strong> <?= ucfirst($pedido->tracking_estado) ?></p>
    </div>

    <!-- 🔄 BOTONES INFERIORES -->
    <div class="gofast-btn-group" style="margin-top:25px;text-align:center;">
        <a href="<?php echo esc_url( home_url('/cotizar') ); ?>" class="gofast-btn-action">🔄 Hacer otra cotización</a>
        <?php if (!empty($_SESSION["gofast_user_id"]) && empty($_SESSION["gofast_auto_linked"])): ?>
            <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-btn-action gofast-secondary">📦 Ver mis pedidos</a>
        <?php else: ?>
            <a href="<?php echo esc_url( home_url('/auth/?registro=1') ); ?>" class="gofast-btn-action gofast-secondary">👤 Crear cuenta para ver tus pedidos</a>
        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("btnWhatsApp");
    const phone = "<?= $telefono_empresa ?>";
    const msg = "<?= $mensaje ?>";
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    const url = isMobile
        ? `https://wa.me/${phone}?text=${msg}`
        : `https://web.whatsapp.com/send?phone=${phone}&text=${msg}`;

    btn.href = url;

    setTimeout(() => {
        if (!document.hidden) {
            alert("Si WhatsApp no se abrió automáticamente, toca el botón verde para confirmar tu pedido.");
        }
    }, 5000);
});
</script>

<?php
    return ob_get_clean();
});

