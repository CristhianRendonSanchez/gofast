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
    
    // Cargar recargos fijos y variables para calcular recargos automáticos
    $recargos_fijos = $wpdb->get_results("SELECT id, nombre, valor_fijo FROM recargos WHERE activo = 1 AND tipo = 'fijo'");
    $recargo_fijo_por_envio = 0;
    foreach ((array) $recargos_fijos as $r) {
        $monto = intval($r->valor_fijo);
        if ($monto > 0) {
            $recargo_fijo_por_envio += $monto;
        }
    }
    
    $recargos_variables = $wpdb->get_results("SELECT r.id, r.nombre, rr.monto_min, rr.monto_max, rr.recargo FROM recargos r JOIN recargos_rangos rr ON rr.recargo_id = r.id WHERE r.activo = 1 AND r.tipo = 'por_valor' ORDER BY rr.monto_min ASC");
    $calcular_recargos_variables = function($valor) use ($recargos_variables) {
        $valor = intval($valor);
        $total_variable = 0;
        foreach ((array) $recargos_variables as $r) {
            $min = intval($r->monto_min);
            $max = intval($r->monto_max);
            $rec = intval($r->recargo);
            $cumple_min = ($valor >= $min);
            $cumple_max = ($max <= 0) ? true : ($valor <= $max);
            if ($cumple_min && $cumple_max && $rec > 0) {
                $total_variable += $rec;
            }
        }
        return $total_variable;
    };
    
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
        
        // Calcular recargos automáticos (fijos + variables)
        $recargo_variable = $calcular_recargos_variables($monto_destino);
        $recargo_automatico = $recargo_fijo_por_envio + $recargo_variable;
        
        // Obtener recargo seleccionable del JSON
        $recargo_seleccionable_valor = !empty($d['recargo_seleccionable_valor']) ? intval($d['recargo_seleccionable_valor']) : 0;
        $recargo_seleccionable_nombre = !empty($d['recargo_seleccionable_nombre']) ? $d['recargo_seleccionable_nombre'] : null;
        $recargo_seleccionable_id = !empty($d['recargo_seleccionable_id']) ? intval($d['recargo_seleccionable_id']) : null;
        
        $total_trayecto = $monto_destino + $recargo_automatico + $recargo_seleccionable_valor;
        
        $detalle_envios[] = [
            'id' => !empty($d['barrio_id']) ? intval($d['barrio_id']) : 0,
            'nombre' => $nombre_destino,
            'precio' => $monto_destino,
            'recargo' => $recargo_automatico,
            'recargo_seleccionable_valor' => $recargo_seleccionable_valor,
            'recargo_seleccionable_nombre' => $recargo_seleccionable_nombre,
            'recargo_seleccionable_id' => $recargo_seleccionable_id,
            'total' => $total_trayecto,
        ];
        
        $total_calculado += $total_trayecto;
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
    // Detectar si es servicio intermunicipal
    $es_intermunicipal = false;
    if (!empty($json['tipo_servicio']) && $json['tipo_servicio'] === 'intermunicipal') {
        $es_intermunicipal = true;
    }
    
    $telefono_empresa = "573194642513"; // +57 319 4642513
    
    // Obtener datos del origen
    $barrio_origen_nombre = '';
    if (!empty($origen_data['barrio_nombre'])) {
        $barrio_origen_nombre = $origen_data['barrio_nombre'];
    } elseif (!empty($nombre_origen)) {
        $barrio_origen_nombre = $nombre_origen;
    }
    
    // Extraer dirección de recogida (sin el barrio si está incluido)
    $direccion_recogida = $pedido->direccion_origen;
    if (!empty($origen_data['direccion']) && $origen_data['direccion'] !== $pedido->direccion_origen) {
        $direccion_recogida = $origen_data['direccion'];
    }
    
    // Construir mensaje según el formato solicitado (sin codificar, JavaScript lo codificará)
    if ($es_intermunicipal) {
        // Mensaje para servicios intermunicipales
        $destino_nombre = '';
        if (!empty($destinos[0]['barrio_nombre'])) {
            $destino_nombre = $destinos[0]['barrio_nombre'];
        } elseif (!empty($destinos[0]['direccion'])) {
            $destino_nombre = $destinos[0]['direccion'];
        }
        
        $dir_destino_inter = !empty($destinos[0]['direccion']) ? trim($destinos[0]['direccion']) : '';
        $barrio_destino_inter = !empty($destinos[0]['barrio_nombre']) ? trim($destinos[0]['barrio_nombre']) : '';
        
        $destino_display_inter = $dir_destino_inter ?: ($barrio_destino_inter ?: 'No especificado');
        
        $mensaje = "🚚 Hola! He solicitado un servicio INTERMUNICIPAL en GoFast.\n\n" .
            "📦 Servicio: #$id\n" .
            "📍 Recogida: " . ($direccion_recogida ?: 'No especificada') . "\n" .
            ($barrio_origen_nombre ? "🏙 Barrio: $barrio_origen_nombre\n" : "") .
            "👤 Envía: " . ($pedido->nombre_cliente ?: 'No especificado') . "\n" .
            "📞 Contacto: " . ($pedido->telefono_cliente ?: 'No especificado') . "\n\n" .
            "💲 Monto a pagar:\n" .
            "💰 Costo del envío: $" . number_format($pedido->total, 0, ',', '.') . "\n\n" .
            "🚫 IMPORTANTE:\n" .
            "Si ya no necesitas el servicio, recuerda cancelarlo antes de que el mensajero llegue al punto de recogida. En caso contrario, se aplicará un recargo por desplazamiento.\n\n" .
            "📍 Destino: $destino_display_inter\n" .
            ($barrio_destino_inter && $barrio_destino_inter !== $dir_destino_inter && $dir_destino_inter ? "🏙 Barrio: $barrio_destino_inter\n" : "");
    } else {
        // Mensaje para servicios normales (múltiples destinos posibles)
        $mensaje = "🚀 Hola! He solicitado un servicio en GoFast.\n\n";
        $mensaje .= "📦 Servicio: #$id\n";
        $mensaje .= "📍 Recogida: " . ($direccion_recogida ?: 'No especificada') . "\n";
        if ($barrio_origen_nombre) {
            $mensaje .= "🏙 Barrio: $barrio_origen_nombre\n";
        }
        $mensaje .= "👤 Envía: " . ($pedido->nombre_cliente ?: 'No especificado') . "\n";
        $mensaje .= "📞 Contacto: " . ($pedido->telefono_cliente ?: 'No especificado') . "\n\n";
        $mensaje .= "💲 Monto a pagar:\n";
        $mensaje .= "💰 Costo del envío: $" . number_format($pedido->total, 0, ',', '.') . "\n\n";
        $mensaje .= "🚫 IMPORTANTE:\n";
        $mensaje .= "Si ya no necesitas el servicio, recuerda cancelarlo antes de que el mensajero llegue al punto de recogida. En caso contrario, se aplicará un recargo por desplazamiento.\n\n";
        
        // Agregar información de cada destino
        if (!empty($destinos)) {
            foreach ($destinos as $idx => $dest) {
                $dir_destino = !empty($dest['direccion']) ? trim($dest['direccion']) : '';
                $barrio_destino = !empty($dest['barrio_nombre']) ? trim($dest['barrio_nombre']) : '';
                
                if ($idx > 0) {
                    $mensaje .= "\n";
                }
                
                // Destino: usar dirección si existe, sino el barrio
                $destino_display = $dir_destino ?: ($barrio_destino ?: 'No especificado');
                $mensaje .= "📍 Destino: $destino_display\n";
                
                // Barrio: solo mostrar si es diferente de la dirección
                if ($barrio_destino && $barrio_destino !== $dir_destino && $dir_destino) {
                    $mensaje .= "🏙 Barrio: $barrio_destino\n";
                } elseif ($barrio_destino && !$dir_destino) {
                    // Si solo hay barrio, no duplicar en Destino y Barrio
                    // Ya está en Destino, así que no lo repetimos
                }
                
                // Indicaciones: por ahora vacío, pero se puede agregar si hay un campo específico en el futuro
                // Si en el futuro se agrega un campo 'indicaciones' al JSON, se mostraría aquí
            }
        } else {
            // Si no hay destinos en el JSON, usar datos básicos
            $mensaje .= "📍 Destino: No especificado\n";
        }
    }

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
                                    <strong>🎯 <?= esc_html($d['nombre']) ?></strong>
                                    <?php if (!empty($d['recargo_seleccionable_id']) && !empty($d['recargo_seleccionable_valor'])): ?>
                                        <span style="background:#4CAF50;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:8px;font-weight:normal;">
                                            ⭐ Con recargo adicional
                                        </span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if ($d['precio'] > 0): ?>
                                        <small style="color:#666;">
                                            Base: $<?= number_format($d['precio'], 0, ',', '.') ?>
                                            <?php if ($d['recargo'] > 0): ?>
                                                | Recargo automático: $<?= number_format($d['recargo'], 0, ',', '.') ?>
                                            <?php endif; ?>
                                            <?php if (!empty($d['recargo_seleccionable_id']) && !empty($d['recargo_seleccionable_valor'])): ?>
                                                | <strong style="color:#4CAF50;">Recargo adicional: $<?= number_format($d['recargo_seleccionable_valor'], 0, ',', '.') ?></strong>
                                                <?php if (!empty($d['recargo_seleccionable_nombre'])): ?>
                                                    <span style="color:#666;">(<?= esc_html($d['recargo_seleccionable_nombre']) ?>)</span>
                                                <?php endif; ?>
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
        <?php 
        // Determinar si es intermunicipal para redirigir al cotizador correcto
        $es_intermunicipal = false;
        if (!empty($json['tipo_servicio']) && $json['tipo_servicio'] === 'intermunicipal') {
            $es_intermunicipal = true;
        }
        $url_cotizar = $es_intermunicipal 
            ? esc_url( home_url('/cotizar-intermunicipal') )
            : esc_url( home_url('/cotizar') );
        ?>
        <a href="<?php echo $url_cotizar; ?>" class="gofast-btn-action">🔄 Hacer otra cotización</a>
        <?php if (!empty($_SESSION["gofast_user_id"]) && empty($_SESSION["gofast_auto_linked"])): ?>
            <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-btn-action gofast-secondary">📦 Ver mis pedidos</a>
        <?php else: ?>
            <a href="<?php echo esc_url( home_url('/auth/?registro=1') ); ?>" class="gofast-btn-action gofast-secondary">👤 Crear cuenta para ver tus pedidos</a>
        <?php endif; ?>
    </div>

</div>

<script>
(function() {
    // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
    const tipoSelectExists = document.getElementById("tipo_negocio");
    const wrapperOtroExists = document.getElementById("tipo_otro_wrapper");
    
    // Si los elementos no existen, crear una función segura desde el inicio
    if (!tipoSelectExists || !wrapperOtroExists) {
        if (typeof window.toggleOtro === 'undefined') {
            window.toggleOtro = function() {
                // Función vacía segura - no hacer nada
                return;
            };
        } else if (typeof window.toggleOtro === 'function') {
            // Si existe y los elementos no están presentes, proteger la función
            const originalToggleOtro = window.toggleOtro;
            window.toggleOtro = function() {
                try {
                    const tipoSelect = document.getElementById("tipo_negocio");
                    const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                    if (tipoSelect && wrapperOtro && tipoSelect.parentNode && wrapperOtro.parentNode) {
                        return originalToggleOtro();
                    }
                } catch(e) {
                    // Silenciar error completamente
                    return;
                }
            };
        }
    }
    
    // También prevenir que setTimeout ejecute toggleOtro si no están los elementos
    (function() {
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(func, delay) {
            if (typeof func === 'function') {
                try {
                    const funcStr = func.toString();
                    if (funcStr.includes('toggleOtro')) {
                        const tipoSelect = document.getElementById("tipo_negocio");
                        const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                        if (!tipoSelect || !wrapperOtro) {
                            // Retornar un timeout vacío en lugar de null para evitar errores
                            return originalSetTimeout(function() {}, 0);
                        }
                    }
                } catch(e) {
                    // Si hay algún error al verificar, continuar normalmente
                }
            }
            return originalSetTimeout.apply(this, arguments);
        };
    })();
})();

document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("btnWhatsApp");
    const phone = "<?= $telefono_empresa ?>";
    const msg = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    // Codificar el mensaje correctamente para WhatsApp (maneja emojis Unicode)
    const msgEncoded = encodeURIComponent(msg);

    const url = isMobile
        ? `https://wa.me/${phone}?text=${msgEncoded}`
        : `https://web.whatsapp.com/send?phone=${phone}&text=${msgEncoded}`;

    if (btn) {
        btn.href = url;
    }

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

