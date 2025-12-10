<?php
/*******************************************************
 * 🚚 GOFAST — SOLICITAR ENVÍO INTERMUNICIPAL
 * Shortcode: [gofast_solicitar_intermunicipal]
 * URL: /solicitar-intermunicipal
 * 
 * Página de confirmación y solicitud del servicio intermunicipal
 *******************************************************/

function gofast_solicitar_intermunicipal_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Inicializar variable de error
    $error = '';
    
    // Debug visible: mostrar si se recibió POST
    $debug_info = '';
    if (!empty($_POST)) {
        $debug_info = '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffc107;">';
        $debug_info .= '<strong>DEBUG POST:</strong> ';
        $debug_info .= 'confirmar_intermunicipal=' . (isset($_POST['confirmar_intermunicipal']) ? 'SI' : 'NO') . ' | ';
        $debug_info .= 'nombre=' . (isset($_POST['nombre']) ? $_POST['nombre'] : 'NO') . ' | ';
        $debug_info .= 'telefono=' . (isset($_POST['telefono']) ? $_POST['telefono'] : 'NO');
        $debug_info .= '</div>';
    }

    // Verificar que hay datos de cotización
    $cotizacion = isset($_SESSION['gofast_intermunicipal']) ? $_SESSION['gofast_intermunicipal'] : null;
    
    if (!$cotizacion || empty($cotizacion['destino'])) {
        // Redirigir al cotizador si no hay datos
        $url_cotizar = esc_url(home_url('/cotizar-intermunicipal'));
        return '<script>window.location.href = "'.$url_cotizar.'";</script>';
    }

    $origen = isset($cotizacion['origen']) ? $cotizacion['origen'] : 'Tuluá';
    $origen_direccion = isset($cotizacion['origen_direccion']) ? $cotizacion['origen_direccion'] : 'Tuluá';
    $origen_tipo = isset($cotizacion['origen_tipo']) ? $cotizacion['origen_tipo'] : 'tulua';
    $negocio_id = isset($cotizacion['negocio_id']) ? intval($cotizacion['negocio_id']) : null;
    $negocio_user_id = isset($cotizacion['negocio_user_id']) ? intval($cotizacion['negocio_user_id']) : null;
    $negocio_nombre = isset($cotizacion['negocio_nombre']) ? $cotizacion['negocio_nombre'] : null;
    $negocio_whatsapp = isset($cotizacion['negocio_whatsapp']) ? $cotizacion['negocio_whatsapp'] : null;
    $usuario_nombre = isset($cotizacion['usuario_nombre']) ? $cotizacion['usuario_nombre'] : '';
    $usuario_telefono = isset($cotizacion['usuario_telefono']) ? $cotizacion['usuario_telefono'] : '';
    $destino = isset($cotizacion['destino']) ? $cotizacion['destino'] : '';
    $valor_envio = isset($cotizacion['valor']) ? intval($cotizacion['valor']) : 0;
    $direccion_recogida = isset($cotizacion['direccion_recogida']) ? $cotizacion['direccion_recogida'] : '';

    // Obtener destinos intermunicipales desde la base de datos
    $destinos_intermunicipales = [];
    
    // Verificar si la tabla existe antes de consultar
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

    // Validar que el destino existe
    if (!isset($destinos_intermunicipales[$destino])) {
        unset($_SESSION['gofast_intermunicipal']);
        return '<div class="gofast-box">Error: Destino no válido. <a href="'.esc_url(home_url('/cotizar-intermunicipal')).'">Volver al cotizador</a></div>';
    }

    // Obtener usuario logueado si existe
    $usuario = null;
    $negocio_datos = null;
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? '');
    $es_mensajero_o_admin = ($rol === 'mensajero' || $rol === 'admin');
    
    if (!empty($_SESSION['gofast_user_id'])) {
        $user_id = intval($_SESSION['gofast_user_id']);
        if ($user_id > 0) {
            $usuario = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, telefono, email, rol 
                 FROM usuarios_gofast 
                 WHERE id = %d AND activo = 1",
                $user_id
            ));
            
            // Si se seleccionó un negocio, obtener sus datos
            if ($negocio_id && $negocio_id > 0) {
                // Si es mensajero/admin y hay negocio_user_id, buscar por ese user_id
                if ($es_mensajero_o_admin && $negocio_user_id) {
                    $negocio_datos = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.id, n.nombre, n.direccion_full, n.whatsapp, n.user_id
                         FROM negocios_gofast n
                         WHERE n.id = %d AND n.user_id = %d AND n.activo = 1",
                        $negocio_id,
                        $negocio_user_id
                    ));
                } else {
                    // Buscar negocio del usuario logueado
                    $negocio_datos = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, nombre, direccion_full, whatsapp
                         FROM negocios_gofast
                         WHERE id = %d AND user_id = %d AND activo = 1",
                        $negocio_id,
                        $user_id
                    ));
                }
            }
        }
    }

    // Procesar confirmación del servicio
    // IMPORTANTE: Esto debe ejecutarse ANTES de ob_start()
    if (!empty($_POST['confirmar_intermunicipal'])) {
        
        // Debug: verificar que el POST se está recibiendo
        error_log('=== GOFAST INTERMUNICIPAL DEBUG ===');
        error_log('POST recibido - confirmar_intermunicipal: ' . (isset($_POST['confirmar_intermunicipal']) ? 'SI' : 'NO'));
        
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');
        $direccion_destino = sanitize_text_field($_POST['direccion_destino'] ?? '');
        $direccion_recogida_nueva = sanitize_text_field($_POST['direccion_recogida'] ?? '');
        
        error_log('Datos POST - nombre: ' . ($nombre ?: 'VACIO') . ', telefono: ' . ($telefono ?: 'VACIO') . ', direccion: ' . ($direccion_destino ?: 'VACIO'));
        error_log('Destino: ' . ($destino ?: 'VACIO') . ', Valor: ' . $valor_envio);

        // Validaciones
        if (empty($nombre) || empty($telefono) || empty($direccion_destino)) {
            $error = 'Debes completar todos los campos obligatorios.';
        } elseif (empty($destino) || $valor_envio <= 0) {
            $error = 'Error: Datos de cotización incompletos. Por favor, vuelve a cotizar.';
            unset($_SESSION['gofast_intermunicipal']);
        } else {
            // Construir JSON de destinos (formato compatible con servicios_gofast)
            $direccion_origen_final = ($origen_tipo === 'negocio' && $negocio_datos) 
                ? $origen_direccion 
                : $origen;
            
            $destinos_json = json_encode([
                'origen' => [
                    'barrio_id' => ($negocio_datos && isset($negocio_datos->barrio_id)) ? intval($negocio_datos->barrio_id) : 0,
                    'barrio_nombre' => $origen,
                    'sector_id' => 0,
                    'direccion' => $direccion_origen_final,
                    'negocio_id' => $negocio_id,
                ],
                'destinos' => [[
                    'barrio_id' => 0,
                    'barrio_nombre' => $destino,
                    'sector_id' => 0,
                    'direccion' => $direccion_destino,
                    'monto' => $valor_envio,
                    'direccion_recogida' => !empty($direccion_recogida_nueva) ? $direccion_recogida_nueva : $direccion_recogida,
                ]],
                'tipo_servicio' => 'intermunicipal', // Indicador especial
            ], JSON_UNESCAPED_UNICODE);

            // Guardar servicio en la base de datos
            // Incluir el destino en direccion_origen para que se muestre correctamente
            $direccion_origen_servicio = ($origen_tipo === 'negocio' && $negocio_datos) 
                ? $origen . ' — ' . $origen_direccion . ' → ' . $destino . ' (Intermunicipal)'
                : $origen . ' → ' . $destino . ' (Intermunicipal)';
            
            // Determinar user_id: si es mensajero/admin y hay negocio_user_id, usar ese
            $user_id_servicio = $usuario ? $usuario->id : null;
            if ($es_mensajero_o_admin && $negocio_user_id) {
                $user_id_servicio = $negocio_user_id; // Asociar al cliente propietario del negocio
            } elseif ($negocio_datos && !$es_mensajero_o_admin) {
                // Si es cliente normal y seleccionó su negocio, usar su user_id
                $user_id_servicio = $usuario ? $usuario->id : null;
            }
            
            // Si hay negocio seleccionado, usar datos del NEGOCIO (no del cliente)
            $nombre_final = $nombre;
            $telefono_final = $telefono;
            if ($negocio_datos && $origen_tipo === 'negocio') {
                $nombre_final = $negocio_datos->nombre; // Nombre del negocio
                $telefono_final = $negocio_datos->whatsapp ?: ($negocio_datos->cliente_telefono ?? $telefono); // WhatsApp del negocio primero
            }
            
            // Si es mensajero, asignarse automáticamente
            $mensajero_id_servicio = null;
            $estado_servicio = 'pendiente';
            $tracking_estado_servicio = 'pendiente';
            if ($usuario && strtolower($usuario->rol) === 'mensajero') {
                $mensajero_id_servicio = $usuario->id;
                $estado_servicio = 'asignado';
                $tracking_estado_servicio = 'asignado';
            }
            
            $insertado = $wpdb->insert('servicios_gofast', [
                'nombre_cliente' => $nombre_final,
                'telefono_cliente' => $telefono_final,
                'direccion_origen' => $direccion_origen_servicio,
                'destinos' => $destinos_json,
                'total' => $valor_envio,
                'estado' => $estado_servicio,
                'tracking_estado' => $tracking_estado_servicio,
                'mensajero_id' => $mensajero_id_servicio,
                'user_id' => $user_id_servicio,
                'fecha' => gofast_date_mysql()
            ]);

            // ⚠️ Si falla el INSERT, mostramos el error en pantalla
            if ($insertado === false) {
                $error = 'Error al guardar el servicio: ' . esc_html($wpdb->last_error);
                error_log('Error al insertar servicio intermunicipal: ' . $wpdb->last_error);
            } else {
                $service_id = (int) $wpdb->insert_id;
                
                if (!$service_id || $service_id <= 0) {
                    $error = 'Error: No se pudo obtener el ID del servicio guardado.';
                    error_log('Error: insert_id no válido después de insertar servicio intermunicipal');
                } else {
                    // Limpiar sesión
                    unset($_SESSION['gofast_intermunicipal']);
                    
                    // Guardar en sesión para referencia
                    $_SESSION['gofast_pending_service'] = $service_id;
                    
                    // Si es admin o mensajero, NO redirigir a servicio-registrado, solo mostrar mensaje
                    if ($es_mensajero_o_admin) {
                        // Mostrar mensaje de éxito y limpiar
                        $success_message = '<div class="gofast-box" style="background:#d4edda;border-left:4px solid #28a745;padding:16px;margin:20px auto;max-width:600px;">';
                        $success_message .= '<h2 style="margin-top:0;color:#155724;">✅ Servicio Registrado Exitosamente</h2>';
                        $success_message .= '<p><strong>ID del Servicio:</strong> #' . esc_html($service_id) . '</p>';
                        $success_message .= '<p><strong>Total:</strong> $' . number_format($valor_envio, 0, ',', '.') . '</p>';
                        $success_message .= '<p style="margin-bottom:0;"><a href="' . esc_url(home_url('/mis-pedidos')) . '" class="gofast-btn" style="display:inline-block;margin-top:12px;">Ver mis pedidos</a></p>';
                        $success_message .= '</div>';
                        return $success_message;
                    } else {
                        // Cliente normal: redirigir a página de confirmación
                        $url_confirmacion = esc_url(home_url('/servicio-registrado?id=' . $service_id));
                        
                        // Usar wp_redirect si es posible, sino JavaScript
                        // Pero primero necesitamos evitar que se ejecute ob_start()
                        // Retornar el script de redirección inmediatamente
                        $redirect_script = '<script type="text/javascript">';
                        $redirect_script .= 'window.location.href = "'.esc_js($url_confirmacion).'";';
                        $redirect_script .= '</script>';
                        $redirect_script .= '<noscript>';
                        $redirect_script .= '<meta http-equiv="refresh" content="0;url='.esc_attr($url_confirmacion).'">';
                        $redirect_script .= '</noscript>';
                        
                        return $redirect_script;
                    }
                }
            }
        }
    }

    // Validar que tenemos los datos necesarios
    if (empty($destino) || $valor_envio <= 0) {
        unset($_SESSION['gofast_intermunicipal']);
        return '<div class="gofast-box">Error: Datos de cotización incompletos. <a href="'.esc_url(home_url('/cotizar-intermunicipal')).'">Volver al cotizador</a></div>';
    }

    // Si hay un error después de procesar el POST, continuar para mostrarlo
    // Si no hay error y se procesó correctamente, ya se hizo return arriba
    
    ob_start();
    ?>
    <div class="gofast-checkout-wrapper">
        
        <!-- Columna izquierda: Resumen -->
        <div class="gofast-box">
            <h2 style="margin-top: 0;">📦 Resumen del Envío Intermunicipal</h2>
            
            <div class="gofast-route-item">
                <div>
                    <strong>Origen:</strong> <?php echo esc_html($origen); ?>
                    <?php if ($origen_tipo === 'negocio' && $negocio_datos): ?>
                        <br><small style="color: #666; font-size: 12px;">
                            📍 <?php echo esc_html($origen_direccion); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gofast-route-item">
                <div>
                    <strong>Destino:</strong> <?php echo esc_html($destino); ?>
                </div>
            </div>

            <div class="gofast-total-box">
                💰 Total a pagar: $<?php echo number_format($valor_envio, 0, ',', '.'); ?>
            </div>

            <!-- Condiciones importantes -->
            <div class="gofast-box" style="background: #fff3cd; border-left: 5px solid var(--gofast-amber); padding: 16px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #856404; font-size: 16px;">⚠️ Condiciones Importantes</h3>
                <ul style="margin: 0; padding-left: 20px; color: #856404; font-size: 14px;">
                    <li>El pedido debe estar <strong>pago con anticipación</strong>.</li>
                    <li>El valor del envío debe ser <strong>cancelado antes</strong> de despachar.</li>
                    <li>Solo se aceptan envíos para <strong>zona urbana</strong>.</li>
                    <li>Se debe anexar la <strong>ubicación en tiempo real</strong> del cliente que recibe el domicilio en el destino.</li>
                    <li>La disponibilidad está sujeta a la administración.</li>
                    <li>En caso de devolución se cobrará recargo adicional.</li>
                </ul>
            </div>
        </div>

        <!-- Columna derecha: Formulario -->
        <div class="gofast-box">
            <h2 style="margin-top: 0;">📝 Datos del Cliente</h2>

            <?php if (!empty($error)): ?>
                <div class="gofast-box" style="background: #ffe5e5; border-left: 5px solid var(--gofast-danger); padding: 12px; margin-bottom: 16px;">
                    ⚠️ <strong>Error:</strong> <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" id="form-confirmar-intermunicipal">
                
                <label><strong>Nombre completo</strong> <span style="color: var(--gofast-danger);">*</span></label>
                <input type="text" name="nombre" 
                       value="<?php 
                           // Si es negocio, usar nombre del negocio; si es Tuluá, usar nombre del usuario
                           if ($origen_tipo === 'negocio' && $negocio_nombre) {
                               echo esc_attr($negocio_nombre);
                           } elseif ($origen_tipo === 'tulua' && !empty($usuario_nombre)) {
                               echo esc_attr($usuario_nombre);
                           } elseif ($usuario) {
                               echo esc_attr($usuario->nombre);
                           }
                       ?>" 
                       required 
                       class="gofast-box input"
                       placeholder="Ej: Juan Pérez">

                <label><strong>Teléfono / WhatsApp</strong> <span style="color: var(--gofast-danger);">*</span></label>
                <input type="text" name="telefono" 
                       value="<?php 
                           // Si es negocio, usar WhatsApp del negocio; si es Tuluá, usar teléfono del usuario
                           if ($origen_tipo === 'negocio' && $negocio_whatsapp) {
                               echo esc_attr($negocio_whatsapp);
                           } elseif ($origen_tipo === 'tulua' && !empty($usuario_telefono)) {
                               echo esc_attr($usuario_telefono);
                           } elseif ($usuario) {
                               echo esc_attr($usuario->telefono);
                           }
                       ?>" 
                       required 
                       class="gofast-box input"
                       placeholder="Ej: 3112345678">

                <label><strong>Dirección de Recogida</strong></label>
                <input type="text" name="direccion_recogida" 
                       value="<?php echo esc_attr($direccion_recogida); ?>"
                       class="gofast-box input"
                       placeholder="Ej: Calle 5 #10-20, Barrio Centro">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 4px; margin-bottom: 16px;">
                    Especifica la dirección exacta donde se recogerá el pedido en el origen.
                </small>

                <label><strong>Dirección de destino (zona urbana)</strong> <span style="color: var(--gofast-danger);">*</span></label>
                <textarea name="direccion_destino" 
                          required 
                          class="gofast-box input"
                          rows="3"
                          placeholder="Ej: Calle 10 # 5-20, Barrio Centro"></textarea>
                <small style="color: #666; font-size: 13px; display: block; margin-top: -12px; margin-bottom: 16px;">
                    Especifica la dirección completa en <?php echo esc_html($destino); ?> (solo zona urbana)
                </small>

                <div class="gofast-box" style="background: #e8f4ff; border-left: 4px solid #1f6feb; padding: 12px; margin-bottom: 16px;">
                    <strong style="color: #004085;">💡 Recordatorio:</strong>
                    <p style="margin: 8px 0 0 0; color: #004085; font-size: 13px;">
                        Asegúrate de que el pedido este pago con anticipación antes de confirmar. 
                        y pagarle al mensajero el valor del envío. Solo después de esto se despachará el pedido.
                        <strong>Recuerda:</strong> Se debe anexar la ubicación en tiempo real del cliente que recibe el domicilio en el destino.
                    </p>
                </div>

                <div class="gofast-btn-group">
                    <button type="submit" name="confirmar_intermunicipal" class="gofast-submit">
                        ✅ Confirmar y Solicitar Servicio
                    </button>
                    <a href="<?php echo esc_url(home_url('/cotizar-intermunicipal')); ?>" 
                       class="gofast-btn gofast-secondary" 
                       style="text-align: center; text-decoration: none; display: block;">
                        🔄 Hacer otra cotización
                    </a>
                </div>

            </form>
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
    </script>
    
    <?php
    return ob_get_clean();
}
add_shortcode('gofast_solicitar_intermunicipal', 'gofast_solicitar_intermunicipal_shortcode');

// Hook para procesar el POST ANTES de renderizar la página (similar a redireccion.php)
add_action('template_redirect', function() {
    // Solo procesar si hay POST de confirmar_intermunicipal
    if (!isset($_POST['confirmar_intermunicipal'])) {
        return;
    }
    
    // Verificar que estamos en la página correcta
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($current_url, '/solicitar-intermunicipal') === false) {
        return;
    }
    
    global $wpdb;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar que hay datos de cotización
    $cotizacion = isset($_SESSION['gofast_intermunicipal']) ? $_SESSION['gofast_intermunicipal'] : null;
    
    if (!$cotizacion || empty($cotizacion['destino'])) {
        return; // Dejar que el shortcode maneje el error
    }
    
    $origen = isset($cotizacion['origen']) ? $cotizacion['origen'] : 'Tuluá';
    $origen_direccion = isset($cotizacion['origen_direccion']) ? $cotizacion['origen_direccion'] : 'Tuluá';
    $origen_tipo = isset($cotizacion['origen_tipo']) ? $cotizacion['origen_tipo'] : 'tulua';
    $negocio_id = isset($cotizacion['negocio_id']) ? intval($cotizacion['negocio_id']) : null;
    $negocio_user_id = isset($cotizacion['negocio_user_id']) ? intval($cotizacion['negocio_user_id']) : null;
    $destino = isset($cotizacion['destino']) ? $cotizacion['destino'] : '';
    $valor_envio = isset($cotizacion['valor']) ? intval($cotizacion['valor']) : 0;
    
    // Obtener usuario y negocio
    $usuario = null;
    $negocio_datos = null;
    $rol = '';
    
    if (!empty($_SESSION['gofast_user_id'])) {
        $user_id = intval($_SESSION['gofast_user_id']);
        if ($user_id > 0) {
            $usuario = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, telefono, email, rol 
                 FROM usuarios_gofast 
                 WHERE id = %d AND activo = 1",
                $user_id
            ));
            
            if ($usuario) {
                $rol = strtolower($usuario->rol ?? '');
            }
            
            if ($negocio_id && $negocio_id > 0) {
                // Si es mensajero/admin y hay negocio_user_id, buscar por ese user_id
                if (($rol === 'mensajero' || $rol === 'admin') && $negocio_user_id) {
                    $negocio_datos = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono
                         FROM negocios_gofast n
                         INNER JOIN usuarios_gofast u ON n.user_id = u.id
                         WHERE n.id = %d AND n.user_id = %d AND n.activo = 1",
                        $negocio_id,
                        $negocio_user_id
                    ));
                } else {
                    // Buscar negocio del usuario logueado
                    $negocio_datos = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, nombre, direccion_full, whatsapp, barrio_id
                         FROM negocios_gofast
                         WHERE id = %d AND user_id = %d AND activo = 1",
                        $negocio_id,
                        $user_id
                    ));
                }
            }
        }
    }
    
    // Procesar datos del formulario
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $direccion_destino = sanitize_text_field($_POST['direccion_destino'] ?? '');
    
    // Validaciones
    if (empty($nombre) || empty($telefono) || empty($direccion_destino) || empty($destino) || $valor_envio <= 0) {
        return; // Dejar que el shortcode muestre el error
    }
    
    // Construir JSON de destinos
    $direccion_origen_final = ($origen_tipo === 'negocio' && $negocio_datos) 
        ? $origen_direccion 
        : $origen;
    
    $destinos_json = json_encode([
        'origen' => [
            'barrio_id' => ($negocio_datos && isset($negocio_datos->barrio_id)) ? intval($negocio_datos->barrio_id) : 0,
            'barrio_nombre' => $origen,
            'sector_id' => 0,
            'direccion' => $direccion_origen_final,
            'negocio_id' => $negocio_id,
        ],
        'destinos' => [[
            'barrio_id' => 0,
            'barrio_nombre' => $destino,
            'sector_id' => 0,
            'direccion' => $direccion_destino,
            'monto' => $valor_envio,
        ]],
        'tipo_servicio' => 'intermunicipal',
    ], JSON_UNESCAPED_UNICODE);
    
    // Guardar servicio
    // Incluir el destino en direccion_origen para que se muestre correctamente
    $direccion_origen_servicio = ($origen_tipo === 'negocio' && $negocio_datos) 
        ? $origen . ' — ' . $origen_direccion . ' → ' . $destino . ' (Intermunicipal)'
        : $origen . ' → ' . $destino . ' (Intermunicipal)';
    
            // Determinar user_id: si es mensajero/admin y hay negocio_user_id, usar ese
            $user_id_servicio = $usuario ? $usuario->id : null;
            $es_mensajero_o_admin = ($rol === 'mensajero' || $rol === 'admin');
            if ($es_mensajero_o_admin && $negocio_user_id) {
                $user_id_servicio = $negocio_user_id; // Asociar al cliente propietario del negocio
            } elseif ($negocio_datos && !$es_mensajero_o_admin) {
                // Si es cliente normal y seleccionó su negocio, usar su user_id
                $user_id_servicio = $usuario ? $usuario->id : null;
            }
            
            // Si hay negocio seleccionado, usar datos del NEGOCIO (no del cliente)
            $nombre_final = $nombre;
            $telefono_final = $telefono;
            if ($negocio_datos && $origen_tipo === 'negocio') {
                $nombre_final = $negocio_datos->nombre; // Nombre del negocio
                $telefono_final = $negocio_datos->whatsapp ?: ($negocio_datos->cliente_telefono ?? $telefono); // WhatsApp del negocio primero
            }
            
            // Si es mensajero, asignarse automáticamente
            $mensajero_id_servicio = null;
            $estado_servicio = 'pendiente';
            $tracking_estado_servicio = 'pendiente';
            if ($usuario && strtolower($usuario->rol) === 'mensajero') {
                $mensajero_id_servicio = $usuario->id;
                $estado_servicio = 'asignado';
                $tracking_estado_servicio = 'asignado';
            }
            
            $insertado = $wpdb->insert('servicios_gofast', [
                'nombre_cliente' => $nombre_final,
                'telefono_cliente' => $telefono_final,
                'direccion_origen' => $direccion_origen_servicio,
                'destinos' => $destinos_json,
                'total' => $valor_envio,
                'estado' => $estado_servicio,
                'tracking_estado' => $tracking_estado_servicio,
                'mensajero_id' => $mensajero_id_servicio,
                'user_id' => $user_id_servicio,
                'fecha' => gofast_date_mysql()
            ]);
    
    if ($insertado !== false) {
        $service_id = (int) $wpdb->insert_id;
        
        if ($service_id > 0) {
            // Limpiar sesión
            unset($_SESSION['gofast_intermunicipal']);
            $_SESSION['gofast_pending_service'] = $service_id;
            
            // Redirigir usando wp_safe_redirect (más confiable que JavaScript)
            $url_confirmacion = esc_url(home_url('/servicio-registrado?id=' . $service_id));
            wp_safe_redirect($url_confirmacion);
            exit;
        }
    }
}, 1); // Prioridad 1 para ejecutarse temprano

