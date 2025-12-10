/*******************************************************
 * 🚚 GOFAST — COTIZACIÓN INTERMUNICIPAL PARA MENSAJEROS
 * Shortcode: [gofast_mensajero_cotizar_intermunicipal]
 * URL: /mensajero-cotizar-intermunicipal
 *
 * Funcionalidad:
 * - Seleccionar origen (Tuluá o negocio) y destino intermunicipal
 * - Resumen con valor del envío
 * - Botones aceptar/rechazar
 * - Al aceptar: crea servicio intermunicipal y lo asigna automáticamente al mensajero
 *******************************************************/

function gofast_mensajero_cotizar_intermunicipal_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar que sea mensajero o admin
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión como mensajero o administrador para usar esta función.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? '');

    // Verificar rol
    if ($rol !== 'mensajero' && $rol !== 'admin') {
        $usuario = $wpdb->get_row($wpdb->prepare(
            "SELECT rol FROM usuarios_gofast WHERE id = %d AND activo = 1",
            $user_id
        ));
        if (!$usuario || (strtolower($usuario->rol) !== 'mensajero' && strtolower($usuario->rol) !== 'admin')) {
            return "<div class='gofast-box'>⚠️ Solo los mensajeros y administradores pueden usar esta función.</div>";
        }
        $_SESSION['gofast_user_rol'] = strtolower($usuario->rol);
        $rol = $_SESSION['gofast_user_rol'];
    }

    // Obtener datos del mensajero/admin
    $mensajero = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nombre, telefono, email FROM usuarios_gofast WHERE id = %d AND activo = 1",
        $user_id
    ));

    if (!$mensajero) {
        return "<div class='gofast-box'>Error: Usuario no encontrado.</div>";
    }
    
    // Obtener TODOS los negocios registrados para mensajero/admin
    $todos_negocios = $wpdb->get_results(
        "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                u.nombre as cliente_nombre, u.telefono as cliente_telefono
         FROM negocios_gofast n
         INNER JOIN usuarios_gofast u ON n.user_id = u.id
         WHERE n.activo = 1 AND u.activo = 1
         ORDER BY n.nombre ASC"
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

    /*******************************************************
     * PROCESAR ACEPTAR/RECHAZAR
     *******************************************************/
    if (isset($_POST['gofast_mensajero_aceptar_intermunicipal']) || isset($_POST['gofast_mensajero_rechazar_intermunicipal'])) {
        
        if (isset($_POST['gofast_mensajero_rechazar_intermunicipal'])) {
            // Rechazar: volver al paso 1
            unset($_SESSION['gofast_mensajero_cotizacion_intermunicipal']);
            // Continuar para mostrar el formulario (no redirigir)
        } elseif (isset($_POST['gofast_mensajero_aceptar_intermunicipal'])) {
            // Aceptar: crear servicio
            if (empty($_POST['origen_intermunicipal']) || empty($_POST['destino_intermunicipal'])) {
                return "<div class='gofast-box'>Error: Faltan datos del servicio.</div>";
            }

            $origen_seleccionado = sanitize_text_field($_POST['origen_intermunicipal']);
            $destino_seleccionado = sanitize_text_field($_POST['destino_intermunicipal']);
            $direccion_recogida = sanitize_text_field($_POST['direccion_recogida'] ?? '');
            
            if (!isset($destinos_intermunicipales[$destino_seleccionado])) {
                return "<div class='gofast-box'>Error: Destino no válido.</div>";
            }

            $valor_envio = $destinos_intermunicipales[$destino_seleccionado];
            
            // Determinar origen y datos del negocio
            $origen_nombre = 'Tuluá';
            $origen_direccion = 'Tuluá';
            $negocio_seleccionado = null;
            $cliente_propietario = null;
            
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
                        $cliente_propietario = $neg->user_id;
                        break;
                    }
                }
            }

            // Construir JSON de destinos (formato intermunicipal)
            $destinos_json = json_encode([
                'origen' => [
                    'tipo' => ($origen_seleccionado === 'tulua') ? 'tulua' : 'negocio',
                    'nombre' => $origen_nombre,
                    'direccion' => $origen_direccion,
                    'negocio_id' => $negocio_seleccionado ? $negocio_seleccionado->id : null,
                ],
                'destinos' => [
                    [
                        'destino' => $destino_seleccionado,
                        'valor' => $valor_envio,
                        'direccion_recogida' => $direccion_recogida,
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);

            // Determinar nombre y teléfono del cliente
            $nombre_cliente = $mensajero->nombre . ' (' . ($rol === 'admin' ? 'Admin' : 'Mensajero') . ')';
            $telefono_cliente = $mensajero->telefono;
            $direccion_origen_servicio = $origen_nombre . ' (Intermunicipal)';
            $user_id_servicio = $mensajero->id;
            
            if ($negocio_seleccionado) {
                // Usar datos del NEGOCIO (no del cliente)
                $nombre_cliente = $negocio_seleccionado->nombre;
                $telefono_cliente = $negocio_seleccionado->whatsapp ?: $negocio_seleccionado->cliente_telefono;
                $direccion_origen_servicio = $origen_nombre . ' — ' . $origen_direccion . ' (Intermunicipal)';
                $user_id_servicio = $cliente_propietario;
            }
            
            // Verificar si existe el campo asignado_por_user_id
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
            
            $data_insert = [
                'nombre_cliente' => $nombre_cliente,
                'telefono_cliente' => $telefono_cliente,
                'direccion_origen' => $direccion_origen_servicio,
                'destinos' => $destinos_json,
                'total' => $valor_envio,
                'estado' => 'asignado',
                'tracking_estado' => 'asignado',
                'mensajero_id' => $mensajero->id,
                'user_id' => $user_id_servicio,
                'fecha' => gofast_current_time('mysql'),
            ];
            
            // Si existe el campo, guardar quién asignó (el mensajero se auto-asignó)
            if (!empty($column_exists)) {
                $data_insert['asignado_por_user_id'] = $mensajero->id;
            }
            
            // Crear servicio y asignarlo automáticamente al mensajero
            $insertado = $wpdb->insert('servicios_gofast', $data_insert);

            if ($insertado === false) {
                return "<div class='gofast-box'><b>Error al crear el servicio:</b><br>" . esc_html($wpdb->last_error) . "</div>";
            }

            $service_id = (int) $wpdb->insert_id;
            unset($_SESSION['gofast_mensajero_cotizacion_intermunicipal']);
            
            // Guardar mensaje de éxito en sesión
            $_SESSION['gofast_mensajero_success_intermunicipal'] = [
                'message' => '✅ Servicio intermunicipal creado exitosamente',
                'service_id' => $service_id,
                'total' => $valor_envio
            ];
            
            // Continuar para mostrar el formulario de nuevo (no redirigir)
        }
    }

    /*******************************************************
     * PASO 2: MOSTRAR RESUMEN
     *******************************************************/
    if (isset($_POST['gofast_mensajero_cotizar_intermunicipal']) || !empty($_SESSION['gofast_mensajero_cotizacion_intermunicipal'])) {
        
        // Guardar en sesión
        if (isset($_POST['gofast_mensajero_cotizar_intermunicipal'])) {
            $origen_seleccionado = sanitize_text_field($_POST['origen_intermunicipal'] ?? 'tulua');
            $destino_seleccionado = sanitize_text_field($_POST['destino_intermunicipal'] ?? '');
            $direccion_recogida = sanitize_text_field($_POST['direccion_recogida'] ?? '');
            
            if (!empty($destino_seleccionado) && isset($destinos_intermunicipales[$destino_seleccionado])) {
                $_SESSION['gofast_mensajero_cotizacion_intermunicipal'] = [
                    'origen' => $origen_seleccionado,
                    'destino' => $destino_seleccionado,
                    'direccion_recogida' => $direccion_recogida,
                    'valor' => $destinos_intermunicipales[$destino_seleccionado],
                ];
            }
        }

        $cotizacion = $_SESSION['gofast_mensajero_cotizacion_intermunicipal'] ?? null;
        
        if (!$cotizacion || empty($cotizacion['destino'])) {
            // Volver al paso 1
            unset($_SESSION['gofast_mensajero_cotizacion_intermunicipal']);
        } else {
            // Mostrar resumen
            return gofast_mensajero_mostrar_resumen_intermunicipal($cotizacion);
        }
    }

    /*******************************************************
     * PASO 1: FORMULARIO DE COTIZACIÓN
     *******************************************************/
    
    // Recuperar última cotización
    $old_data = $_SESSION['gofast_mensajero_last_quote_intermunicipal'] ?? ['origen' => 'tulua', 'destino' => ''];

    // Mostrar mensaje de éxito si existe
    $success_message = '';
    if (!empty($_SESSION['gofast_mensajero_success_intermunicipal'])) {
        $success = $_SESSION['gofast_mensajero_success_intermunicipal'];
        $success_message = "<div class='gofast-box' style='background:#d4edda;border-left:4px solid #28a745;padding:12px;margin-bottom:16px;'>";
        $success_message .= "<strong>✅ " . esc_html($success['message']) . "</strong><br>";
        $success_message .= "<small>ID del servicio: #" . esc_html($success['service_id']) . " | Total: $" . number_format($success['total'], 0, ',', '.') . "</small>";
        $success_message .= "</div>";
        unset($_SESSION['gofast_mensajero_success_intermunicipal']);
    }

    ob_start();
    ?>

    <div class="gofast-form">
        <?php if (!empty($success_message)): ?>
            <?= $success_message ?>
        <?php endif; ?>
        
        <div class="gofast-box" style="background:#e3f2fd;border-left:4px solid #2196F3;padding:12px;margin-bottom:16px;">
            <strong>🚚 Modo <?= $rol === 'admin' ? 'Administrador' : 'Mensajero' ?> - Intermunicipal</strong><br>
            <small>Crea servicios intermunicipales rápidamente. Al aceptar, el servicio se asignará automáticamente a ti. Si seleccionas un negocio, el servicio quedará en el historial del cliente propietario.</small>
        </div>

        <form method="post" action="" id="gofast-mensajero-form-intermunicipal">
            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Origen</strong></label>
                    <select name="origen_intermunicipal" class="gofast-select" id="origen-intermunicipal" required>
                        <option value="tulua" <?= ($old_data['origen'] === 'tulua') ? 'selected' : '' ?>>
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
                                $isSelected = ($old_data['origen'] === $negocio_value);
                        ?>
                            <option value="<?= esc_attr($negocio_value) ?>" 
                                    data-negocio-id="<?= esc_attr($neg->id) ?>"
                                    data-negocio-nombre="<?= esc_attr($neg->nombre) ?>"
                                    data-negocio-direccion="<?= esc_attr($neg->direccion_full) ?>"
                                    data-cliente-id="<?= esc_attr($neg->user_id) ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                🏪 <?= esc_html($neg->nombre) ?> — <?= esc_html($barrio_nombre ?: 'Tuluá') ?>
                            </option>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </select>
                    <?php if (!empty($todos_negocios)): ?>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
                            Si seleccionas un negocio, el servicio quedará asociado al cliente propietario y aparecerá en su historial.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Destino Intermunicipal</strong> <span style="color: var(--gofast-danger);">*</span></label>
                    <select name="destino_intermunicipal" class="gofast-select" id="destino-intermunicipal" required>
                        <option value="">Selecciona un destino...</option>
                        <?php foreach ($destinos_intermunicipales as $destino => $valor): ?>
                            <option value="<?= esc_attr($destino) ?>" 
                                    data-valor="<?= esc_attr($valor) ?>"
                                    <?= ($old_data['destino'] === $destino) ? 'selected' : '' ?>>
                                <?= esc_html($destino) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Dirección de Recogida</strong></label>
                    <input type="text" name="direccion_recogida" class="gofast-box input" 
                           placeholder="Ej: Calle 5 #10-20, Barrio Centro" 
                           value="<?= esc_attr($old_data['direccion_recogida'] ?? '') ?>">
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
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
                </div>
            </div>

            <div class="gofast-btn-group">
                <button type="submit" name="gofast_mensajero_cotizar_intermunicipal" class="gofast-submit" id="btn-submit">
                    Ver resumen 🚀
                </button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('.gofast-select').select2({
                placeholder: '🔍 Escribe para buscar...',
                width: '100%',
                dropdownParent: jQuery('body'),
                allowClear: true,
                minimumResultsForSearch: 0,
            });
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

        const form = document.getElementById('gofast-mensajero-form-intermunicipal');
        if (form) {
            form.addEventListener('submit', function(e){
                const destino = selectDestino ? selectDestino.value : '';
                if (!destino){
                    e.preventDefault();
                    alert('Debes seleccionar un destino ⚠️');
                    return false;
                }
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}

/*******************************************************
 * FUNCIÓN: MOSTRAR RESUMEN
 *******************************************************/
function gofast_mensajero_mostrar_resumen_intermunicipal($cotizacion) {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $origen_seleccionado = $cotizacion['origen'] ?? 'tulua';
    $destino_seleccionado = $cotizacion['destino'] ?? '';
    $direccion_recogida = $cotizacion['direccion_recogida'] ?? '';
    $valor_envio = $cotizacion['valor'] ?? 0;

    // Obtener todos los negocios para mostrar el origen
    $todos_negocios = $wpdb->get_results(
        "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                u.nombre as cliente_nombre, u.telefono as cliente_telefono
         FROM negocios_gofast n
         INNER JOIN usuarios_gofast u ON n.user_id = u.id
         WHERE n.activo = 1 AND u.activo = 1
         ORDER BY n.nombre ASC"
    );

    // Determinar nombre del origen
    $origen_nombre = 'Tuluá';
    if ($origen_seleccionado !== 'tulua') {
        $negocio_id = 0;
        if (preg_match('/^negocio_(\d+)$/', $origen_seleccionado, $matches)) {
            $negocio_id = intval($matches[1]);
        }
        
        foreach ($todos_negocios as $neg) {
            if (intval($neg->id) === $negocio_id) {
                $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                    "SELECT nombre FROM barrios WHERE id = %d",
                    $neg->barrio_id
                ));
                $origen_nombre = $neg->nombre . ' — ' . ($barrio_nombre ?: 'Tuluá');
                break;
            }
        }
    }

    ob_start();
    ?>

    <div class="gofast-checkout-wrapper">
        <div class="gofast-box" style="max-width:800px;margin:0 auto;">
            
            <div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:12px;margin-bottom:20px;border-radius:8px;">
                <strong>🚚 Resumen del Servicio Intermunicipal</strong><br>
                <small>Revisa los datos antes de aceptar.</small>
            </div>

            <h3 style="margin-top:0;">📍 Origen: <?= esc_html($origen_nombre) ?></h3>
            <h3>🎯 Destino: <?= esc_html($destino_seleccionado) ?></h3>
            
            <?php if (!empty($direccion_recogida)): ?>
                <div style="margin:16px 0;padding:12px;background:#f8f9fa;border-radius:8px;">
                    <strong>📮 Dirección de Recogida:</strong><br>
                    <?= esc_html($direccion_recogida) ?>
                </div>
            <?php endif; ?>

            <div class="gofast-total-box" style="margin:20px 0;text-align:center;">
                💰 <strong style="font-size:24px;">Total: $<?= number_format($valor_envio, 0, ',', '.') ?></strong>
            </div>

            <!-- Formulario oculto para enviar -->
            <form method="post" id="form-aceptar-rechazar-intermunicipal">
                <input type="hidden" name="origen_intermunicipal" value="<?= esc_attr($origen_seleccionado) ?>">
                <input type="hidden" name="destino_intermunicipal" value="<?= esc_attr($destino_seleccionado) ?>">
                <input type="hidden" name="direccion_recogida" value="<?= esc_attr($direccion_recogida) ?>">

                <div class="gofast-btn-group" style="margin-top:24px;">
                    <button type="submit" name="gofast_mensajero_aceptar_intermunicipal" class="gofast-btn-request" style="background:#4CAF50;color:#fff;">
                        ✅ Aceptar y crear servicio
                    </button>
                    <button type="submit" name="gofast_mensajero_rechazar_intermunicipal" class="gofast-btn-action gofast-secondary">
                        ❌ Rechazar y volver
                    </button>
                </div>
            </form>

        </div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('gofast_mensajero_cotizar_intermunicipal', 'gofast_mensajero_cotizar_intermunicipal_shortcode');

