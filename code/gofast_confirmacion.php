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
       3. Decodificar JSON de destinos
    ========================================================== */
    $json = json_decode($pedido->destinos, true);
    $destinos = $json["destinos"] ?? [];

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

    <!-- 🗺️ DESTINOS -->
    <div style="margin-top:15px;">
        <h3 style="margin-bottom:10px;font-size:18px;">🚛 Destinos</h3>

        <?php
        $mostro_destinos = false;
        if (!empty($destinos)):
            foreach ($destinos as $d):
                // Mostrar destino si tiene dirección O barrio
                $tiene_direccion = !empty($d["direccion"]);
                $tiene_barrio = !empty($d["barrio_nombre"]);
                
                if ($tiene_direccion || $tiene_barrio) {
                    $mostro_destinos = true; ?>
                    <div class="gofast-route-item" style="background:#f8f8f8;padding:10px 12px;border-radius:8px;margin-bottom:8px;border-left:4px solid #F4C524;">
                        <?php if ($tiene_direccion): ?>
                            <strong><?= esc_html($d["direccion"]) ?></strong>
                            <?php if ($tiene_barrio): ?>
                                <br><small style="color:#666;">📍 <?= esc_html($d["barrio_nombre"]) ?></small>
                            <?php endif; ?>
                        <?php elseif ($tiene_barrio): ?>
                            <strong>📍 <?= esc_html($d["barrio_nombre"]) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($d["monto"]) && intval($d["monto"]) > 0): ?>
                            <br><b style="color:#4CAF50;">💰 $<?= number_format($d["monto"], 0, ',', '.') ?></b>
                        <?php endif; ?>
                    </div>
        <?php   }
            endforeach;
        endif;
        if (!$mostro_destinos): ?>
            <p style="color:#666;">(No se registraron destinos)</p>
        <?php endif; ?>
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
        <a href="/" class="gofast-btn-action">🔄 Hacer otra cotización</a>
        <?php if (!empty($_SESSION["gofast_user_id"]) && empty($_SESSION["gofast_auto_linked"])): ?>
            <a href="/mis-pedidos" class="gofast-btn-action gofast-secondary">📦 Ver mis pedidos</a>
        <?php else: ?>
            <a href="/auth?registro=1" class="gofast-btn-action gofast-secondary">👤 Crear cuenta para ver tus pedidos</a>
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

