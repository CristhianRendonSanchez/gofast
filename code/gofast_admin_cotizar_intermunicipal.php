<?php
/*******************************************************
 * ⚙️ GOFAST — COTIZADOR ENVÍOS INTERMUNICIPALES PARA ADMIN
 * Shortcode: [gofast_admin_cotizar_intermunicipal]
 * URL: /admin-cotizar-intermunicipal
 * 
 * Características:
 * - Origen desde Tuluá o negocios
 * - Destinos fijos con valores predefinidos
 * - Selector de mensajero
 * - Pago anticipado requerido
 * - Solo zona urbana
 *******************************************************/

function gofast_admin_cotizar_intermunicipal_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar que sea admin
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión como administrador para usar esta función.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? '');

    // Verificar rol
    if ($rol !== 'admin') {
        $usuario = $wpdb->get_row($wpdb->prepare(
            "SELECT rol FROM usuarios_gofast WHERE id = %d AND activo = 1",
            $user_id
        ));
        if (!$usuario || strtolower($usuario->rol) !== 'admin') {
            return "<div class='gofast-box'>⚠️ Solo los administradores pueden usar esta función.</div>";
        }
        $_SESSION['gofast_user_rol'] = 'admin';
        $rol = 'admin';
    }

    // Obtener todos los mensajeros activos
    $mensajeros = $wpdb->get_results(
        "SELECT id, nombre, telefono 
         FROM usuarios_gofast 
         WHERE rol = 'mensajero' AND activo = 1 
         ORDER BY nombre ASC"
    );

    // Obtener destinos intermunicipales desde la base de datos
    $destinos_intermunicipales = [];
    
    try {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE 'destinos_intermunicipales'");
        
        if ($table_exists) {
            $destinos_db = $wpdb->get_results(
                "SELECT id, nombre, valor 
                 FROM destinos_intermunicipales 
                 WHERE activo = 1 
                 ORDER BY orden ASC, nombre ASC"
            );
            
            if ($destinos_db && is_array($destinos_db)) {
                foreach ($destinos_db as $destino_db) {
                    if (isset($destino_db->nombre) && isset($destino_db->valor)) {
                        $destinos_intermunicipales[$destino_db->nombre] = intval($destino_db->valor);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error al obtener destinos intermunicipales: ' . $e->getMessage());
    }
    
    // Fallback si la tabla no existe o no hay resultados
    if (empty($destinos_intermunicipales)) {
        $destinos_intermunicipales = [
            'Andalucía' => 20000,
            'Bugalagrande' => 25000,
            'Riofrío' => 20000,
            'Buga' => 35000,
            'San Pedro' => 25000,
            'Los chanchos' => 20000,
            'Salónica' => 35000,
            'La Marina' => 25000,
            'Presidente' => 30000,
            'La Paila' => 35000,
            'Zarzal' => 50000,
        ];
    }

    // Origen por defecto es Tuluá
    $origen_fijo = 'Tuluá';
    
    // Obtener TODOS los negocios registrados
    $todos_negocios = $wpdb->get_results(
        "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                u.nombre as cliente_nombre, u.telefono as cliente_telefono
         FROM negocios_gofast n
         INNER JOIN usuarios_gofast u ON n.user_id = u.id
         WHERE n.activo = 1 AND u.activo = 1
         ORDER BY n.nombre ASC"
    );
    
    // Recuperar última selección de origen
    $origen_seleccionado = isset($_SESSION['gofast_admin_intermunicipal_last_origen']) 
        ? $_SESSION['gofast_admin_intermunicipal_last_origen'] 
        : 'tulua';

    // Si se envía el formulario, redirigir a la página de solicitud
    if (isset($_POST['gofast_admin_cotizar_intermunicipal'])) {
        $destino_seleccionado = sanitize_text_field($_POST['destino_intermunicipal'] ?? '');
        $origen_seleccionado = sanitize_text_field($_POST['origen_intermunicipal'] ?? 'tulua');
        $mensajero_id = isset($_POST['mensajero_id']) ? intval($_POST['mensajero_id']) : 0;
        $negocio_id_cotizador = isset($_POST['negocio_id']) ? intval($_POST['negocio_id']) : 0;
        
        if (empty($destino_seleccionado) || !isset($destinos_intermunicipales[$destino_seleccionado])) {
            $error = 'Debes seleccionar un destino válido.';
        } elseif (empty($mensajero_id)) {
            $error = 'Debes seleccionar un mensajero.';
        } else {
            // Verificar que el mensajero existe y está activo
            $mensajero_seleccionado = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero' AND activo = 1",
                $mensajero_id
            ));
            
            if (!$mensajero_seleccionado) {
                $error = 'El mensajero seleccionado no es válido.';
            } else {
                // Determinar origen y datos del negocio
                $origen_nombre = $origen_fijo;
                $origen_direccion = $origen_fijo;
                $negocio_seleccionado = null;
                $negocio_user_id = null;
                
                if ($origen_seleccionado !== 'tulua') {
                    // Extraer ID del negocio del formato "negocio_X"
                    $negocio_id = 0;
                    if (preg_match('/^negocio_(\d+)$/', $origen_seleccionado, $matches)) {
                        $negocio_id = intval($matches[1]);
                    } else {
                        $negocio_id = intval($origen_seleccionado);
                    }
                    
                    foreach ($todos_negocios as $neg) {
                        if (intval($neg->id) === $negocio_id) {
                            $negocio_seleccionado = $neg;
                            $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                                "SELECT nombre FROM barrios WHERE id = %d",
                                $neg->barrio_id
                            ));
                            $origen_nombre = $neg->nombre . ' — ' . ($barrio_nombre ?: 'Tuluá');
                            $origen_direccion = $neg->direccion_full ?: $neg->nombre;
                            $negocio_user_id = $neg->user_id;
                            break;
                        }
                    }
                }
                
                // Obtener datos del admin para prellenar si es Tuluá
                $usuario_nombre = '';
                $usuario_telefono = '';
                if ($origen_seleccionado === 'tulua' && $user_id > 0) {
                    $usuario_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT nombre, telefono 
                         FROM usuarios_gofast 
                         WHERE id = %d AND activo = 1",
                        $user_id
                    ));
                    if ($usuario_data) {
                        $usuario_nombre = $usuario_data->nombre;
                        $usuario_telefono = $usuario_data->telefono;
                    }
                }
                
                // Guardar en sesión
                $_SESSION['gofast_admin_intermunicipal'] = [
                    'mensajero_id' => $mensajero_id,
                    'mensajero_nombre' => $mensajero_seleccionado->nombre,
                    'origen' => $origen_nombre,
                    'origen_direccion' => $origen_direccion,
                    'origen_tipo' => ($origen_seleccionado === 'tulua') ? 'tulua' : 'negocio',
                    'negocio_id' => $negocio_seleccionado ? $negocio_seleccionado->id : null,
                    'negocio_user_id' => $negocio_user_id,
                    'negocio_nombre' => $negocio_seleccionado ? $negocio_seleccionado->nombre : null,
                    'negocio_whatsapp' => $negocio_seleccionado ? ($negocio_seleccionado->whatsapp ?? null) : null,
                    'usuario_nombre' => $usuario_nombre,
                    'usuario_telefono' => $usuario_telefono,
                    'destino' => $destino_seleccionado,
                    'valor' => $destinos_intermunicipales[$destino_seleccionado],
                ];
                
                // Guardar última selección de origen
                $_SESSION['gofast_admin_intermunicipal_last_origen'] = $origen_seleccionado;
                
                // Redirigir a página de solicitud
                $url_solicitar = esc_url(home_url('/admin-solicitar-intermunicipal'));
                return '<script>window.location.href = "'.$url_solicitar.'";</script>';
            }
        }
    }

    ob_start();
    ?>
    <div class="gofast-form">
        
        <!-- Mensaje informativo -->
        <div class="gofast-box" style="background: #fff3cd; border-left: 5px solid var(--gofast-amber); padding: 16px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #856404;">📋 Condiciones del Servicio Intermunicipal</h3>
            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                <li>✅ El pedido debe estar <strong>pago con anticipación</strong> antes de solicitar el servicio.</li>
                <li>✅ El valor del envío también debe ser <strong>cancelado antes</strong> de despachar al mensajero.</li>
                <li>✅ Solo se aceptan envíos para <strong>zona urbana</strong>.</li>
                <li>📍 Se anexa vía WhatsApp la <strong>ubicación en tiempo real</strong> del mensajero que recibe el pedido.</li>
                <li>⚙️ La disponibilidad de este servicio está <strong>sujeta a la administración</strong>.</li>
                <li>⚠️ En caso de <strong>devolución del pedido</strong> se cobrará un recargo adicional.</li>
                <li>🚚 El envío es desde <strong>Tuluá</strong> o desde uno de los negocios registrados.</li>
            </ul>
        </div>

        <?php if (!empty($error)): ?>
            <div class="gofast-box" style="background: #ffe5e5; border-left: 5px solid var(--gofast-danger); padding: 12px; margin-bottom: 16px;">
                ⚠️ <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="gofast-form-admin-intermunicipal">
            
            <!-- Mensajero -->
            <div class="gofast-row">
                <div style="flex: 1;">
                    <label><strong>Mensajero <span style="color: var(--gofast-danger);">*</span></strong></label>
                    <select name="mensajero_id" class="gofast-select" id="mensajero_id" required>
                        <option value="">Seleccionar mensajero...</option>
                        <?php foreach ($mensajeros as $m): ?>
                            <option value="<?php echo esc_attr($m->id); ?>">
                                🚚 <?php echo esc_html($m->nombre); ?> (<?php echo esc_html($m->telefono); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Origen (Tuluá o negocios) -->
            <div class="gofast-row">
                <div style="flex: 1;">
                    <label><strong>Origen</strong></label>
                    <?php if (!empty($todos_negocios) || $user_id > 0): ?>
                        <select name="origen_intermunicipal" class="gofast-select" id="origen-intermunicipal" required>
                            <option value="tulua" <?php echo ($origen_seleccionado === 'tulua') ? 'selected' : ''; ?>>
                                🚚 Tuluá (Datos del cliente)
                            </option>
                            <?php 
                            if (!empty($todos_negocios)): 
                                foreach ($todos_negocios as $neg): 
                                    $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                                        "SELECT nombre FROM barrios WHERE id = %d",
                                        $neg->barrio_id
                                    ));
                                    
                                    $negocio_value = 'negocio_' . $neg->id;
                                    $isSelected = ($origen_seleccionado === $negocio_value);
                            ?>
                                <option value="<?php echo esc_attr($negocio_value); ?>" 
                                        data-negocio-id="<?php echo esc_attr($neg->id); ?>"
                                        data-negocio-nombre="<?php echo esc_attr($neg->nombre); ?>"
                                        data-negocio-direccion="<?php echo esc_attr($neg->direccion_full); ?>"
                                        data-negocio-whatsapp="<?php echo esc_attr($neg->whatsapp); ?>"
                                        data-cliente-id="<?php echo esc_attr($neg->user_id); ?>"
                                        <?php echo $isSelected ? 'selected' : ''; ?>>
                                    🏪 <?php echo esc_html($neg->nombre); ?> — <?php echo esc_html($barrio_nombre ?: 'Tuluá'); ?> (Cliente: <?php echo esc_html($neg->cliente_nombre); ?>)
                                </option>
                            <?php 
                                endforeach; 
                            endif; 
                            ?>
                        </select>
                        <small style="color: #666; font-size: 13px; display: block; margin-top: 4px;">
                            Si seleccionas un negocio, el servicio quedará asociado al cliente propietario y aparecerá en su historial.
                        </small>
                    <?php else: ?>
                        <input type="text" value="<?php echo esc_attr($origen_fijo); ?>" readonly 
                               style="background: #f5f5f5; cursor: not-allowed;" 
                               class="gofast-box input">
                        <input type="hidden" name="origen_intermunicipal" value="tulua">
                        <small style="color: #666; font-size: 13px; display: block; margin-top: 4px;">
                            El envío siempre es desde Tuluá.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Destino -->
            <div class="gofast-row">
                <div style="flex: 1;">
                    <label><strong>Destino</strong> <span style="color: var(--gofast-danger);">*</span></label>
                    <select name="destino_intermunicipal" class="gofast-select" id="destino-intermunicipal" required>
                        <option value="">Selecciona un destino...</option>
                        <?php foreach ($destinos_intermunicipales as $destino => $valor): ?>
                            <option value="<?php echo esc_attr($destino); ?>" 
                                    data-valor="<?php echo esc_attr($valor); ?>">
                                <?php echo esc_html($destino); ?> — $<?php echo number_format($valor, 0, ',', '.'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Resumen del valor -->
            <div id="resumen-valor" style="display: none; margin-top: 16px;">
                <div class="gofast-box" style="background: #fff9d6; border-left: 5px solid var(--gofast-yellow); padding: 14px;">
                    <div style="font-size: 18px; font-weight: 700; color: #000;">
                        💰 Valor del envío: <span id="valor-envio">$0</span>
                    </div>
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Recuerda que este valor debe ser cancelado antes de despachar al mensajero.
                    </div>
                </div>
            </div>

            <div class="gofast-btn-group" style="margin-top: 24px;">
                <button type="submit" name="gofast_admin_cotizar_intermunicipal" class="gofast-submit">
                    Cotizar Envío Intermunicipal 🚀
                </button>
            </div>

        </form>
    </div>

    <script>
    (function() {
        // Proteger contra errores de toggleOtro
        const tipoSelectExists = document.getElementById("tipo_negocio");
        const wrapperOtroExists = document.getElementById("tipo_otro_wrapper");
        
        if (!tipoSelectExists || !wrapperOtroExists) {
            window.toggleOtro = function() {
                return;
            };
        }

        const selectDestino = document.getElementById('destino-intermunicipal');
        const resumenValor = document.getElementById('resumen-valor');
        const valorEnvio = document.getElementById('valor-envio');

        if (selectDestino && resumenValor && valorEnvio) {
            selectDestino.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    const valor = parseInt(selectedOption.getAttribute('data-valor') || '0');
                    valorEnvio.textContent = '$' + valor.toLocaleString('es-CO');
                    resumenValor.style.display = 'block';
                } else {
                    resumenValor.style.display = 'none';
                }
            });
        }

        // Validación del formulario
        const form = document.getElementById('gofast-form-admin-intermunicipal');
        if (form) {
            form.addEventListener('submit', function(e) {
                const mensajero = document.getElementById('mensajero_id').value;
                const destino = selectDestino ? selectDestino.value : '';
                if (!mensajero) {
                    e.preventDefault();
                    alert('⚠️ Debes seleccionar un mensajero.');
                    return false;
                }
                if (!destino) {
                    e.preventDefault();
                    alert('⚠️ Debes seleccionar un destino para continuar.');
                    return false;
                }
            });
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_admin_cotizar_intermunicipal', 'gofast_admin_cotizar_intermunicipal_shortcode');

