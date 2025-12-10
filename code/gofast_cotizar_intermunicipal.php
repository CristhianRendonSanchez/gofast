<?php
/*******************************************************
 * 🚚 GOFAST — COTIZADOR ENVÍOS INTERMUNICIPALES
 * Shortcode: [gofast_cotizar_intermunicipal]
 * URL: /cotizar-intermunicipal
 * 
 * Características:
 * - Origen siempre desde Tuluá
 * - Destinos fijos con valores predefinidos
 * - Pago anticipado requerido
 * - Solo zona urbana
 *******************************************************/

function gofast_cotizar_intermunicipal_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Obtener destinos intermunicipales desde la base de datos
    $destinos_intermunicipales = [];
    
    try {
        // Verificar si la tabla existe antes de consultar (sin prefijo, como otras tablas gofast)
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
        // Silenciar error y usar fallback
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
    
    // Obtener negocios del usuario logueado
    $user_id = isset($_SESSION['gofast_user_id']) ? intval($_SESSION['gofast_user_id']) : 0;
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? '');
    $es_mensajero_o_admin = ($rol === 'mensajero' || $rol === 'admin');
    
    $mis_negocios = [];
    if ($user_id > 0) {
        $mis_negocios = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nombre, direccion_full, barrio_id, whatsapp
             FROM negocios_gofast
             WHERE user_id = %d AND activo = 1
             ORDER BY id DESC",
            $user_id
        ));
    }
    
    // Si es mensajero o admin, obtener TODOS los negocios
    $todos_negocios = [];
    if ($es_mensajero_o_admin) {
        $todos_negocios = $wpdb->get_results(
            "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                    u.nombre as cliente_nombre, u.telefono as cliente_telefono
             FROM negocios_gofast n
             INNER JOIN usuarios_gofast u ON n.user_id = u.id
             WHERE n.activo = 1 AND u.activo = 1
             ORDER BY n.nombre ASC"
        );
    }
    
    // Recuperar última selección de origen
    $origen_seleccionado = isset($_SESSION['gofast_intermunicipal_last_origen']) 
        ? $_SESSION['gofast_intermunicipal_last_origen'] 
        : 'tulua';

    // Si se envía el formulario, redirigir a la página de solicitud
    if (isset($_POST['gofast_cotizar_intermunicipal'])) {
        $destino_seleccionado = sanitize_text_field($_POST['destino_intermunicipal'] ?? '');
        $origen_seleccionado = sanitize_text_field($_POST['origen_intermunicipal'] ?? 'tulua');
        $direccion_recogida = sanitize_text_field($_POST['direccion_recogida'] ?? '');
        $negocio_id_cotizador = isset($_POST['negocio_id']) ? intval($_POST['negocio_id']) : 0;
        
        if (empty($destino_seleccionado) || !isset($destinos_intermunicipales[$destino_seleccionado])) {
            $error = 'Debes seleccionar un destino válido.';
        } else {
            // Determinar origen y datos del negocio
            $origen_nombre = $origen_fijo;
            $origen_direccion = $origen_fijo;
            $negocio_seleccionado = null;
            $negocio_user_id = null; // ID del cliente propietario del negocio
            
            if ($origen_seleccionado !== 'tulua') {
                // Extraer ID del negocio del formato "negocio_X"
                $negocio_id = 0;
                if (preg_match('/^negocio_(\d+)$/', $origen_seleccionado, $matches)) {
                    $negocio_id = intval($matches[1]);
                } else {
                    // Fallback: intentar como número directo
                    $negocio_id = intval($origen_seleccionado);
                }
                
                // Buscar en los negocios del usuario o en todos los negocios
                $negocios_disponibles = $es_mensajero_o_admin ? $todos_negocios : $mis_negocios;
                
                foreach ($negocios_disponibles as $neg) {
                    if (intval($neg->id) === $negocio_id) {
                        $negocio_seleccionado = $neg;
                        $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                            "SELECT nombre FROM barrios WHERE id = %d",
                            $neg->barrio_id
                        ));
                        $origen_nombre = $neg->nombre . ' — ' . ($barrio_nombre ?: 'Tuluá');
                        $origen_direccion = $neg->direccion_full ?: $neg->nombre;
                        $negocio_user_id = $neg->user_id; // Guardar el ID del cliente propietario
                        break;
                    }
                }
            }
            
            // Obtener datos del usuario para prellenar si es Tuluá
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
            $_SESSION['gofast_intermunicipal'] = [
                'origen' => $origen_nombre,
                'origen_direccion' => $origen_direccion,
                'origen_tipo' => ($origen_seleccionado === 'tulua') ? 'tulua' : 'negocio',
                'negocio_id' => $negocio_seleccionado ? $negocio_seleccionado->id : null,
                'negocio_user_id' => $negocio_user_id, // ID del cliente propietario
                'negocio_nombre' => $negocio_seleccionado ? $negocio_seleccionado->nombre : null,
                'negocio_whatsapp' => $negocio_seleccionado ? ($negocio_seleccionado->whatsapp ?? null) : null,
                'usuario_nombre' => $usuario_nombre,
                'usuario_telefono' => $usuario_telefono,
                'destino' => $destino_seleccionado,
                'valor' => $destinos_intermunicipales[$destino_seleccionado],
                'direccion_recogida' => $direccion_recogida,
            ];
            
            // Guardar última selección de origen
            $_SESSION['gofast_intermunicipal_last_origen'] = $origen_seleccionado;
            
            // Redirigir a página de solicitud
            $url_solicitar = esc_url(home_url('/solicitar-intermunicipal'));
            return '<script>window.location.href = "'.$url_solicitar.'";</script>';
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
                <li>🚚 El envío es desde <strong>Tuluá</strong> o desde uno de tus negocios registrados.</li>
            </ul>
        </div>

        <?php if (!empty($error)): ?>
            <div class="gofast-box" style="background: #ffe5e5; border-left: 5px solid var(--gofast-danger); padding: 12px; margin-bottom: 16px;">
                ⚠️ <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="gofast-form-intermunicipal">
            
            <!-- Origen (Tuluá o negocio del usuario/mensajero/admin) -->
            <div class="gofast-row">
                <div style="flex: 1;">
                    <label><strong>Origen</strong></label>
                    <?php if (!empty($mis_negocios) || !empty($todos_negocios) || $user_id > 0): ?>
                        <select name="origen_intermunicipal" class="gofast-select" id="origen-intermunicipal" required>
                            <option value="tulua" <?php echo ($origen_seleccionado === 'tulua') ? 'selected' : ''; ?>>
                                🚚 Tuluá (Datos del cliente)
                            </option>
                            <?php 
                            // Mostrar todos los negocios si es mensajero/admin, sino solo los del usuario
                            $negocios_a_mostrar = $es_mensajero_o_admin && !empty($todos_negocios) ? $todos_negocios : $mis_negocios;
                            
                            if (!empty($negocios_a_mostrar)): 
                                foreach ($negocios_a_mostrar as $neg): 
                                    $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                                        "SELECT nombre FROM barrios WHERE id = %d",
                                        $neg->barrio_id
                                    ));
                                    
                                    // Para todos los negocios (mensajero/admin), usar formato diferente
                                    if ($es_mensajero_o_admin && isset($neg->cliente_nombre)) {
                                        $negocio_value = 'negocio_' . $neg->id;
                                        $isSelected = ($origen_seleccionado === $negocio_value);
                            ?>
                                <option value="<?php echo esc_attr($negocio_value); ?>" 
                                        data-negocio-id="<?php echo esc_attr($neg->id); ?>"
                                        data-negocio-nombre="<?php echo esc_attr($neg->nombre); ?>"
                                        data-negocio-direccion="<?php echo esc_attr($neg->direccion_full); ?>"
                                        data-negocio-whatsapp="<?php echo esc_attr($neg->cliente_telefono); ?>"
                                        data-cliente-id="<?php echo esc_attr($neg->user_id); ?>"
                                        <?php echo $isSelected ? 'selected' : ''; ?>>
                                    🏪 <?php echo esc_html($neg->nombre); ?> — <?php echo esc_html($barrio_nombre ?: 'Tuluá'); ?>
                                </option>
                            <?php 
                                    } else {
                                        // Para negocios del usuario (formato normal)
                                        $negocio_value = 'negocio_' . $neg->id;
                                        $isSelected = ($origen_seleccionado === $negocio_value);
                            ?>
                                <option value="<?php echo esc_attr($negocio_value); ?>" 
                                        data-negocio-id="<?php echo esc_attr($neg->id); ?>"
                                        data-negocio-nombre="<?php echo esc_attr($neg->nombre); ?>"
                                        data-negocio-direccion="<?php echo esc_attr($neg->direccion_full); ?>"
                                        data-negocio-whatsapp="<?php echo esc_attr($neg->whatsapp); ?>"
                                        <?php echo $isSelected ? 'selected' : ''; ?>>
                                    🏪 <?php echo esc_html($neg->nombre); ?> — <?php echo esc_html($barrio_nombre ?: 'Tuluá'); ?>
                                </option>
                            <?php 
                                    }
                                endforeach; 
                            endif; 
                            ?>
                        </select>
                        <small style="color: #666; font-size: 13px; display: block; margin-top: 4px;">
                            <?php if ($es_mensajero_o_admin && !empty($todos_negocios)): ?>
                                Si seleccionas un negocio, el servicio quedará asociado al cliente propietario y aparecerá en su historial.
                            <?php elseif (!empty($mis_negocios)): ?>
                                Selecciona "Tuluá" para usar tus datos personales, o uno de tus negocios para usar los datos del negocio.
                            <?php else: ?>
                                Selecciona "Tuluá" para usar tus datos personales. <a href="<?php echo esc_url(home_url('/mi-negocio')); ?>">Registra un negocio</a> para más opciones.
                            <?php endif; ?>
                        </small>
                    <?php else: ?>
                        <input type="text" value="<?php echo esc_attr($origen_fijo); ?>" readonly 
                               style="background: #f5f5f5; cursor: not-allowed;" 
                               class="gofast-box input">
                        <input type="hidden" name="origen_intermunicipal" value="tulua">
                        <small style="color: #666; font-size: 13px; display: block; margin-top: 4px;">
                            El envío siempre es desde Tuluá. <a href="<?php echo esc_url(home_url('/mi-negocio')); ?>">Registra un negocio</a> para usar direcciones personalizadas.
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
                                <?php echo esc_html($destino); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Dirección de Recogida -->
            <div class="gofast-row">
                <div style="flex: 1;">
                    <label><strong>Dirección de Recogida</strong></label>
                    <input type="text" name="direccion_recogida" class="gofast-box input" 
                           placeholder="Ej: Calle 5 #10-20, Barrio Centro" 
                           value="">
                    <small style="color: #666; font-size: 13px; display: block; margin-top: 4px;">
                        Especifica la dirección exacta donde se recogerá el pedido.
                    </small>
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
                <button type="submit" name="gofast_cotizar_intermunicipal" class="gofast-submit">
                    Cotizar Envío Intermunicipal 🚀
                </button>
            </div>

        </form>
    </div>

    <script>
    (function() {
        // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
        const tipoSelectExists = document.getElementById("tipo_negocio");
        const wrapperOtroExists = document.getElementById("tipo_otro_wrapper");
        
        // Si los elementos no existen, crear una función segura desde el inicio
        if (!tipoSelectExists || !wrapperOtroExists) {
            window.toggleOtro = function() {
                // Función vacía segura - no hacer nada
                return;
            };
        } else if (typeof window.toggleOtro === 'function') {
            // Si existe y los elementos están presentes, mantener la función original con protección
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
        const form = document.getElementById('gofast-form-intermunicipal');
        if (form) {
            form.addEventListener('submit', function(e) {
                const destino = selectDestino ? selectDestino.value : '';
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
add_shortcode('gofast_cotizar_intermunicipal', 'gofast_cotizar_intermunicipal_shortcode');

