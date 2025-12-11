/*******************************************************
 * ⚙️ GOFAST — COTIZACIÓN RÁPIDA PARA ADMINISTRADORES
 * Shortcode: [gofast_admin_cotizar]
 * URL: /admin-cotizar
 *
 * Funcionalidad:
 * - Paso 1: Seleccionar mensajero, origen y destinos
 * - Paso 2: Resumen editable (eliminar/agregar destinos sin recotizar)
 * - Botones aceptar/rechazar
 * - Al aceptar: crea servicio y lo asigna al mensajero seleccionado
 *******************************************************/

function gofast_admin_cotizar_shortcode() {
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

    // Obtener datos del admin
    $admin = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nombre, telefono, email FROM usuarios_gofast WHERE id = %d AND activo = 1",
        $user_id
    ));

    if (!$admin) {
        return "<div class='gofast-box'>Error: Usuario no encontrado.</div>";
    }
    
    // Obtener todos los mensajeros activos
    $mensajeros = $wpdb->get_results(
        "SELECT id, nombre, telefono 
         FROM usuarios_gofast 
         WHERE rol = 'mensajero' AND activo = 1 
         ORDER BY nombre ASC"
    );
    
    // Obtener TODOS los negocios registrados
    $todos_negocios = $wpdb->get_results(
        "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                u.nombre as cliente_nombre, u.telefono as cliente_telefono
         FROM negocios_gofast n
         INNER JOIN usuarios_gofast u ON n.user_id = u.id
         WHERE n.activo = 1 AND u.activo = 1
         ORDER BY n.nombre ASC"
    );

    /*******************************************************
     * PROCESAR ACEPTAR/RECHAZAR
     *******************************************************/
    if (isset($_POST['gofast_admin_aceptar']) || isset($_POST['gofast_admin_rechazar'])) {
        
        if (isset($_POST['gofast_admin_rechazar'])) {
            // Rechazar: volver al paso 1
            unset($_SESSION['gofast_admin_cotizacion']);
            // Continuar para mostrar el formulario (no redirigir)
        } elseif (isset($_POST['gofast_admin_aceptar'])) {
            // Aceptar: crear servicio
            if (empty($_POST['origen']) || empty($_POST['destinos_finales']) || empty($_POST['mensajero_id'])) {
                return "<div class='gofast-box'>Error: Faltan datos del servicio (mensajero, origen o destinos).</div>";
            }

            $mensajero_id = intval($_POST['mensajero_id']);
            $origen = intval($_POST['origen']);
            
            // Verificar que el mensajero existe y está activo
            $mensajero_seleccionado = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero' AND activo = 1",
                $mensajero_id
            ));
            
            if (!$mensajero_seleccionado) {
                return "<div class='gofast-box'>Error: El mensajero seleccionado no es válido.</div>";
            }
            
            // Obtener negocio_id de la sesión o detectar por barrio_id
            $negocio_id = isset($_SESSION['gofast_admin_cotizacion']['negocio_id']) ? intval($_SESSION['gofast_admin_cotizacion']['negocio_id']) : 0;
            $negocio_user_id = isset($_SESSION['gofast_admin_cotizacion']['negocio_user_id']) ? intval($_SESSION['gofast_admin_cotizacion']['negocio_user_id']) : null;
            
            // Si no hay negocio_id en sesión, intentar detectarlo por barrio_id
            if ($negocio_id == 0 && $origen > 0 && !empty($todos_negocios)) {
                foreach ($todos_negocios as $neg) {
                    if (intval($neg->barrio_id) === $origen) {
                        $negocio_id = intval($neg->id);
                        $negocio_user_id = intval($neg->user_id);
                        break;
                    }
                }
            }
            
            $destinos_finales = array_map('intval', explode(',', $_POST['destinos_finales']));
            $destinos_finales = array_filter($destinos_finales);

            if (empty($destinos_finales)) {
                return "<div class='gofast-box'>Debes tener al menos un destino.</div>";
            }
            
            // Obtener recargos seleccionables por destino
            $recargos_seleccionables_por_destino = [];
            if (!empty($_POST['recargo_seleccionable']) && is_array($_POST['recargo_seleccionable'])) {
                foreach ($_POST['recargo_seleccionable'] as $destino_id => $recargo_id) {
                    $destino_id = intval($destino_id);
                    $recargo_id = intval($recargo_id);
                    if ($destino_id > 0 && $recargo_id > 0) {
                        $recargos_seleccionables_por_destino[$destino_id] = $recargo_id;
                    }
                }
            }
            
            // Obtener datos del negocio si se seleccionó uno
            $negocio_seleccionado = null;
            $cliente_propietario = null;
            if ($negocio_id > 0) {
                if ($negocio_user_id) {
                    // Buscar por negocio_id y user_id del cliente propietario
                    $negocio_seleccionado = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono
                         FROM negocios_gofast n
                         INNER JOIN usuarios_gofast u ON n.user_id = u.id
                         WHERE n.id = %d AND n.user_id = %d AND n.activo = 1 AND u.activo = 1",
                        $negocio_id,
                        $negocio_user_id
                    ));
                } else {
                    // Buscar solo por negocio_id
                    $negocio_seleccionado = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono
                         FROM negocios_gofast n
                         INNER JOIN usuarios_gofast u ON n.user_id = u.id
                         WHERE n.id = %d AND n.activo = 1 AND u.activo = 1",
                        $negocio_id
                    ));
                }
                
                if ($negocio_seleccionado) {
                    $cliente_propietario = $negocio_seleccionado->user_id;
                }
            }

            // Calcular tarifas y recargos
            $sector_origen = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $origen)));
            $nombre_origen = $wpdb->get_var($wpdb->prepare("SELECT nombre FROM barrios WHERE id = %d", $origen));

            // Recargos fijos
            $recargos_fijos = $wpdb->get_results("SELECT id, nombre, valor_fijo FROM recargos WHERE activo = 1 AND tipo = 'fijo'");
            $recargo_fijo_por_envio = 0;
            foreach ((array) $recargos_fijos as $r) {
                $monto = intval($r->valor_fijo);
                if ($monto > 0) {
                    $recargo_fijo_por_envio += $monto;
                }
            }

            // Recargos variables
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

            // Calcular total
            $total = 0;
            $destinos_completos = [];
            
            foreach ($destinos_finales as $dest_id) {
                $sector_destino = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $dest_id)));
                $nombre_destino = $wpdb->get_var($wpdb->prepare("SELECT nombre FROM barrios WHERE id = %d", $dest_id));

                $precio = $wpdb->get_var($wpdb->prepare(
                    "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                    $sector_origen,
                    $sector_destino
                ));
                $precio = $precio ? intval($precio) : 0;

                $recargo_variable = $calcular_recargos_variables($precio);
                $recargo_total = $recargo_fijo_por_envio + $recargo_variable;
                
                // Agregar recargo seleccionable si existe para este destino
                $recargo_seleccionable_valor = 0;
                $recargo_seleccionable_id = null;
                $recargo_seleccionable_nombre = null;
                if (isset($recargos_seleccionables_por_destino[$dest_id])) {
                    $recargo_seleccionable_id = $recargos_seleccionables_por_destino[$dest_id];
                    $recargo_seleccionable = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, nombre, valor_fijo FROM recargos WHERE id = %d AND tipo = 'por_volumen_peso' AND activo = 1",
                        $recargo_seleccionable_id
                    ));
                    if ($recargo_seleccionable) {
                        $recargo_seleccionable_valor = intval($recargo_seleccionable->valor_fijo);
                        $recargo_seleccionable_nombre = $recargo_seleccionable->nombre;
                    }
                }
                
                $total_trayecto = $precio + $recargo_total + $recargo_seleccionable_valor;
                $total += $total_trayecto;

                $destinos_completos[] = [
                    'barrio_id' => $dest_id,
                    'barrio_nombre' => $nombre_destino,
                    'sector_id' => $sector_destino,
                    'direccion' => '',
                    'monto' => 0,
                    'recargo_seleccionable_id' => $recargo_seleccionable_id,
                    'recargo_seleccionable_valor' => $recargo_seleccionable_valor,
                    'recargo_seleccionable_nombre' => $recargo_seleccionable_nombre,
                ];
            }

            // JSON final - Dirección para el JSON (puede ser diferente de direccion_origen_servicio)
            $direccion_origen_json = '';
            if ($negocio_seleccionado) {
                $direccion_origen_json = $negocio_seleccionado->direccion_full ?: '';
            }
            
            $origen_completo = [
                'barrio_id' => $origen,
                'barrio_nombre' => $nombre_origen,
                'sector_id' => $sector_origen,
                'direccion' => $direccion_origen_json,
                'negocio_id' => $negocio_seleccionado ? $negocio_seleccionado->id : null,
            ];

            $json_final = json_encode([
                'origen' => $origen_completo,
                'destinos' => $destinos_completos,
            ], JSON_UNESCAPED_UNICODE);

            // Verificar si existe el campo asignado_por_user_id
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
            
            // Determinar nombre y teléfono del cliente
            $nombre_cliente = $admin->nombre . ' (Admin)';
            $telefono_cliente = $admin->telefono;
            $user_id_servicio = $admin->id; // Por defecto el admin
            
            // Construir direccion_origen_servicio según reglas:
            // 1. Solo barrio (si no hay negocio)
            // 2. Negocio + dirección (si hay negocio)
            if ($negocio_seleccionado) {
                // Usar datos del NEGOCIO (no del cliente)
                $nombre_cliente = $negocio_seleccionado->nombre; // Nombre del negocio
                $telefono_cliente = $negocio_seleccionado->whatsapp ?: $negocio_seleccionado->cliente_telefono; // WhatsApp del negocio primero
                $user_id_servicio = $cliente_propietario; // Asociar al cliente propietario del negocio
                
                // Caso 2: Negocio + dirección
                $dir_origen_negocio = $negocio_seleccionado->direccion_full ?: '';
                if (!empty($dir_origen_negocio)) {
                    $direccion_origen_servicio = $negocio_seleccionado->nombre . ' — ' . $dir_origen_negocio;
                } else {
                    $direccion_origen_servicio = $negocio_seleccionado->nombre;
                }
            } else {
                // Caso 1: Solo barrio
                $direccion_origen_servicio = $nombre_origen;
            }
            
            $data_insert = [
                'nombre_cliente' => $nombre_cliente,
                'telefono_cliente' => $telefono_cliente,
                'direccion_origen' => $direccion_origen_servicio,
                'destinos' => $json_final,
                'total' => $total,
                'estado' => 'asignado',
                'tracking_estado' => 'asignado',
                'mensajero_id' => $mensajero_id, // Mensajero seleccionado por el admin
                'user_id' => $user_id_servicio, // Cliente propietario del negocio o admin
                'fecha' => gofast_current_time('mysql'),
            ];
            
            // Si existe el campo, guardar quién asignó (el admin)
            if (!empty($column_exists)) {
                $data_insert['asignado_por_user_id'] = $admin->id;
            }
            
            // Crear servicio y asignarlo al mensajero seleccionado
            $insertado = $wpdb->insert('servicios_gofast', $data_insert);

            if ($insertado === false) {
                return "<div class='gofast-box'><b>Error al crear el servicio:</b><br>" . esc_html($wpdb->last_error) . "</div>";
            }

            $service_id = (int) $wpdb->insert_id;
            unset($_SESSION['gofast_admin_cotizacion']);
            
            // Guardar mensaje de éxito en sesión
            $_SESSION['gofast_admin_success'] = [
                'message' => '✅ Servicio creado exitosamente y asignado a ' . esc_html($mensajero_seleccionado->nombre),
                'service_id' => $service_id,
                'total' => $total
            ];
            
            // Continuar para mostrar el formulario de nuevo (no redirigir)
            // El mensaje se mostrará en el paso 1
        }
    }

    /*******************************************************
     * PASO 2: MOSTRAR RESUMEN EDITABLE
     *******************************************************/
    if (isset($_POST['gofast_admin_cotizar']) || !empty($_SESSION['gofast_admin_cotizacion'])) {
        
        // Guardar en sesión
        if (isset($_POST['gofast_admin_cotizar'])) {
            $mensajero_id = intval($_POST['mensajero_id'] ?? 0);
            $origen = intval($_POST['origen'] ?? 0);
            $negocio_id = isset($_POST['negocio_id']) ? intval($_POST['negocio_id']) : 0;
            $destinos = array_map('intval', (array) ($_POST['destino'] ?? []));
            
            // Eliminar duplicados y valores vacíos/cero
            $destinos = array_filter($destinos, function($id) {
                return $id > 0;
            });
            $destinos = array_unique($destinos);
            $destinos = array_values($destinos); // Reindexar array
            
            // Guardar recargos seleccionables si vienen en el POST
            $recargos_seleccionables_guardar = [];
            if (!empty($_POST['recargo_seleccionable']) && is_array($_POST['recargo_seleccionable'])) {
                foreach ($_POST['recargo_seleccionable'] as $destino_id => $recargo_id) {
                    $destino_id = intval($destino_id);
                    $recargo_id = intval($recargo_id);
                    if ($destino_id > 0 && $recargo_id > 0) {
                        $recargos_seleccionables_guardar[$destino_id] = $recargo_id;
                    }
                }
            }
            
            // Mantener recargos seleccionables existentes de la sesión si no vienen en POST
            $cotizacion_anterior = $_SESSION['gofast_admin_cotizacion'] ?? null;
            if (!empty($cotizacion_anterior['recargos_seleccionables']) && empty($recargos_seleccionables_guardar)) {
                $recargos_seleccionables_guardar = $cotizacion_anterior['recargos_seleccionables'];
            }
            
            if ($mensajero_id > 0 && $origen > 0 && !empty($destinos)) {
                $_SESSION['gofast_admin_cotizacion'] = [
                    'mensajero_id' => $mensajero_id,
                    'origen' => $origen,
                    'destinos' => $destinos,
                    'negocio_id' => $negocio_id,
                    'recargos_seleccionables' => $recargos_seleccionables_guardar,
                ];
            }
        }

        $cotizacion = $_SESSION['gofast_admin_cotizacion'] ?? null;
        
        if (!$cotizacion || empty($cotizacion['origen']) || empty($cotizacion['destinos']) || empty($cotizacion['mensajero_id'])) {
            // Volver al paso 1
            unset($_SESSION['gofast_admin_cotizacion']);
        } else {
            // Eliminar duplicados antes de mostrar resumen
            $destinos_unicos = array_values(array_unique(array_filter($cotizacion['destinos'], function($id) {
                return $id > 0;
            })));
            $cotizacion['destinos'] = $destinos_unicos;
            $_SESSION['gofast_admin_cotizacion'] = $cotizacion;
            
            // Mostrar resumen editable
            return gofast_admin_mostrar_resumen($cotizacion['mensajero_id'], $cotizacion['origen'], $destinos_unicos);
        }
    }

    /*******************************************************
     * PASO 1: FORMULARIO DE COTIZACIÓN
     *******************************************************/
    
    // Obtener barrios
    $barrios_all = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
    $barrios = $barrios_all ?: [];

    // Recuperar última cotización
    $old_data = $_SESSION['gofast_admin_last_quote'] ?? ['mensajero_id' => '', 'origen' => '', 'destinos' => []];

    // Mostrar mensaje de éxito si existe
    $success_message = '';
    if (!empty($_SESSION['gofast_admin_success'])) {
        $success = $_SESSION['gofast_admin_success'];
        $success_message = "<div class='gofast-box' style='background:#d4edda;border-left:4px solid #28a745;padding:12px;margin-bottom:16px;'>";
        $success_message .= "<strong>✅ " . esc_html($success['message']) . "</strong><br>";
        $success_message .= "<small>ID del servicio: #" . esc_html($success['service_id']) . " | Total: $" . number_format($success['total'], 0, ',', '.') . "</small>";
        $success_message .= "</div>";
        unset($_SESSION['gofast_admin_success']);
    }

    ob_start();
    ?>

    <div class="gofast-form">
        <?php if (!empty($success_message)): ?>
            <?= $success_message ?>
        <?php endif; ?>
        
        <div class="gofast-box" style="background:#e3f2fd;border-left:4px solid #2196F3;padding:12px;margin-bottom:16px;">
            <strong>⚙️ Modo Administrador</strong><br>
            <small>Crea servicios y asígnalos a cualquier mensajero. Si seleccionas un negocio, el servicio quedará en el historial del cliente propietario.</small>
        </div>

        <form method="post" action="" id="gofast-admin-form">
            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Mensajero <span style="color: var(--gofast-danger);">*</span></strong></label>
                    <select name="mensajero_id" class="gofast-select" id="mensajero_id" required>
                        <option value="">Seleccionar mensajero...</option>
                        <?php foreach ($mensajeros as $m): ?>
                            <option value="<?= esc_attr($m->id) ?>"
                                <?= ((string)$old_data['mensajero_id'] === (string)$m->id ? 'selected' : '') ?>>
                                🚚 <?= esc_html($m->nombre) ?> (<?= esc_html($m->telefono) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Origen</strong></label>
                    <select name="origen" class="gofast-select" id="origen" required>
                        <option value="">Buscar barrio...</option>
                        <?php 
                        // Combinar negocios y barrios en un solo array y ordenar alfabéticamente
                        $opciones_origen = [];
                        
                        // Agregar negocios
                        if (!empty($todos_negocios)): 
                            foreach ($todos_negocios as $neg): 
                                $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                                    "SELECT nombre FROM barrios WHERE id = %d",
                                    $neg->barrio_id
                                ));
                                $opciones_origen[] = [
                                    'tipo' => 'negocio',
                                    'valor' => $neg->barrio_id,
                                    'texto' => '🏪 ' . $neg->nombre . ' — ' . ($barrio_nombre ?: 'Sin barrio'),
                                    'data' => [
                                        'is-negocio' => 'true',
                                        'negocio-id' => $neg->id,
                                        'negocio-nombre' => $neg->nombre,
                                        'negocio-direccion' => $neg->direccion_full,
                                        'cliente-id' => $neg->user_id,
                                        'cliente-nombre' => $neg->cliente_nombre,
                                        'cliente-telefono' => $neg->cliente_telefono,
                                    ],
                                    'selected' => ((string)$old_data['origen'] === (string)$neg->barrio_id)
                                ];
                            endforeach;
                        endif;
                        
                        // Agregar barrios
                        foreach ($barrios as $b): 
                            $opciones_origen[] = [
                                'tipo' => 'barrio',
                                'valor' => $b->id,
                                'texto' => $b->nombre,
                                'data' => [],
                                'selected' => ((string)$old_data['origen'] === (string)$b->id)
                            ];
                        endforeach;
                        
                        // Ordenar alfabéticamente por texto (sin el emoji para comparar)
                        usort($opciones_origen, function($a, $b) {
                            $texto_a = preg_replace('/^🏪 /', '', $a['texto']);
                            $texto_b = preg_replace('/^🏪 /', '', $b['texto']);
                            return strcasecmp($texto_a, $texto_b);
                        });
                        
                        // Mostrar opciones ordenadas
                        foreach ($opciones_origen as $opcion): 
                        ?>
                            <option value="<?= esc_attr($opcion['valor']) ?>"
                                    <?php foreach ($opcion['data'] as $key => $value): ?>
                                        data-<?= esc_attr($key) ?>="<?= esc_attr($value) ?>"
                                    <?php endforeach; ?>
                                    <?= $opcion['selected'] ? 'selected' : '' ?>>
                                <?= esc_html($opcion['texto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($todos_negocios)): ?>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
                            Si seleccionas un negocio, el servicio quedará asociado al cliente propietario y aparecerá en su historial.
                        </small>
                    <?php endif; ?>
                </div>

                <div style="flex:1;">
                    <label><strong>Primer destino</strong></label>
                    <select name="destino[]" class="gofast-select" id="destino-principal" required>
                        <option value="">Buscar barrio...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>"
                                <?= in_array($b->id, $old_data['destinos'], true) ? 'selected' : '' ?>>
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="gofast-destinos-grid">
                <div class="gofast-destinos-label">
                    <label><strong>Destinos adicionales</strong></label>
                </div>
                <div id="destinos-wrapper">
                    <div id="destinos-extra"></div>
                    <button type="button" id="btn-add-destino" class="gofast-add-button" onclick="addDestinoAdmin()">
                        ➕ Agregar destino adicional
                    </button>
                </div>
            </div>

            <template id="tpl-destino-admin">
                <div class="gofast-destino-item">
                    <select name="destino[]" class="gofast-select" required>
                        <option value="">Buscar barrio...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="gofast-remove" onclick="removeDestinoAdmin(this)">❌</button>
                </div>
            </template>

            <div class="gofast-btn-group">
                <button type="submit" name="gofast_admin_cotizar" class="gofast-submit" id="btn-submit">
                    Ver resumen 🚀
                </button>
            </div>
        </form>
    </div>

    <script>
    // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
    (function() {
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
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(func, delay) {
            if (typeof func === 'function') {
                const funcStr = func.toString();
                if (funcStr.includes('toggleOtro')) {
                    const tipoSelect = document.getElementById("tipo_negocio");
                    const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                    if (!tipoSelect || !wrapperOtro) {
                        return null; // No ejecutar si no existen los elementos
                    }
                }
            }
            return originalSetTimeout.apply(this, arguments);
        };
    })();
    
    document.addEventListener('DOMContentLoaded', function(){
        if (window.jQuery && jQuery.fn.select2) {
            initSelect2Admin('.gofast-form');
        }

        const form = document.getElementById('gofast-admin-form');
        if (form) {
            form.addEventListener('submit', function(e){
                const mensajero = document.getElementById('mensajero_id').value;
                const origen = document.getElementById('origen').value;
                const destino = document.getElementById('destino-principal').value;
                if (!mensajero || !origen || !destino){
                    e.preventDefault();
                    alert('Debes seleccionar mensajero, origen y al menos un destino ⚠️');
                    return false;
                }
            });
        }
    });

    function addDestinoAdmin(){
        const tpl = document.getElementById('tpl-destino-admin');
        const cont = document.getElementById('destinos-extra');
        const btn = document.getElementById('btn-add-destino');
        if (tpl && cont) {
            cont.appendChild(tpl.content.cloneNode(true));
            cont.parentNode.appendChild(btn);
            if (window.jQuery && jQuery.fn.select2) {
                initSelect2Admin('#destinos-extra');
            }
        }
    }

    function removeDestinoAdmin(btn){
        btn.parentElement.remove();
    }

    function initSelect2Admin(container){
        if (!window.jQuery || !jQuery.fn.select2) return;
        
        const normalize = s => (s || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
        
        function matcherDestinos(params, data){
            if (!data) return null;
            if (data.children && Array.isArray(data.children)) {
                return data;
            }
            if (!data.id) {
                if (!params.term || !params.term.trim()) {
                    return data;
                }
                return null;
            }
            if (!data.text) return null;
            if (!params.term || !params.term.trim()) {
                data.matchScore = 0;
                return data;
            }

            const term = normalize(params.term);
            if (!term) {
                data.matchScore = 0;
                return data;
            }

            const text = normalize(data.text);
            
            if (text === term) {
                data.matchScore = 10000;
                return data;
            }
            if (text.indexOf(term) === 0) {
                data.matchScore = 9500;
                return data;
            }
            
            const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
            const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
                return word.length > 2 && !stopWords.includes(word);
            });
            
            if (searchWords.length === 0) {
                if (text.indexOf(term) !== -1) {
                    data.matchScore = 7000;
                    return data;
                }
                return null;
            }
            
            const significantMatches = searchWords.filter(word => {
                if (word.length <= 2) {
                    return text.split(/\s+/).some(textWord => textWord.indexOf(word) === 0);
                }
                return text.indexOf(word) !== -1;
            });
            
            if (significantMatches.length === 0) return null;
            
            const allSignificantMatch = searchWords.length === significantMatches.length;
            let score = allSignificantMatch ? 5000 : 3000;
            
            data.matchScore = score;
            return data;
        }
        
        jQuery(container).find('.gofast-select').each(function(){
            if (jQuery(this).data('select2')) return;
            
            jQuery(this).select2({
                placeholder: '🔍 Escribe para buscar...',
                width: '100%',
                dropdownParent: jQuery('body'),
                allowClear: true,
                minimumResultsForSearch: 0,
                matcher: matcherDestinos,
                sorter: function(results) {
                    return results.sort(function(a, b) {
                        const scoreA = a.matchScore || 0;
                        const scoreB = b.matchScore || 0;
                        return scoreB - scoreA;
                    });
                }
            });
        });
    }
    </script>

    <?php
    return ob_get_clean();
}

/*******************************************************
 * FUNCIÓN: MOSTRAR RESUMEN EDITABLE
 *******************************************************/
function gofast_admin_mostrar_resumen($mensajero_id, $origen, $destinos) {
    global $wpdb;
    
    // Obtener negocio_id de la sesión si existe
    $cotizacion = $_SESSION['gofast_admin_cotizacion'] ?? null;
    $negocio_id = isset($cotizacion['negocio_id']) ? intval($cotizacion['negocio_id']) : 0;
    $negocio_user_id = isset($cotizacion['negocio_user_id']) ? intval($cotizacion['negocio_user_id']) : null;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Obtener datos del mensajero
    $mensajero = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero' AND activo = 1",
        $mensajero_id
    ));

    if (!$mensajero) {
        return "<div class='gofast-box'>Error: Mensajero no encontrado.</div>";
    }

    // Datos del origen
    $sector_origen = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $origen)));
    $nombre_origen = $wpdb->get_var($wpdb->prepare("SELECT nombre FROM barrios WHERE id = %d", $origen));

    // Recargos
    $recargos_fijos = $wpdb->get_results("SELECT id, nombre, valor_fijo FROM recargos WHERE activo = 1 AND tipo = 'fijo'");
    $recargo_fijo_por_envio = 0;
    $recargos_fijos_nombres = [];
    foreach ((array) $recargos_fijos as $r) {
        $monto = intval($r->valor_fijo);
        if ($monto > 0) {
            $recargo_fijo_por_envio += $monto;
            $recargos_fijos_nombres[] = $r->nombre;
        }
    }
    
    // Recargos seleccionables (por_volumen_peso)
    $recargos_seleccionables = $wpdb->get_results("SELECT id, nombre, valor_fijo FROM recargos WHERE activo = 1 AND tipo = 'por_volumen_peso' ORDER BY nombre ASC");

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

    // Calcular detalle
    $detalle_envios = [];
    $total = 0;
    $lista_recargos = [];

    // Obtener recargos seleccionables de la sesión
    $cotizacion = $_SESSION['gofast_admin_cotizacion'] ?? null;
    $recargos_seleccionables_sesion = isset($cotizacion['recargos_seleccionables']) ? $cotizacion['recargos_seleccionables'] : [];
    
    foreach ($destinos as $dest_id) {
        $sector_destino = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $dest_id)));
        $nombre_destino = $wpdb->get_var($wpdb->prepare("SELECT nombre FROM barrios WHERE id = %d", $dest_id));

        $precio = $wpdb->get_var($wpdb->prepare(
            "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
            $sector_origen,
            $sector_destino
        ));
        $precio = $precio ? intval($precio) : 0;

        $recargo_variable = $calcular_recargos_variables($precio);
        $recargo_total = $recargo_fijo_por_envio + $recargo_variable;
        
        // Recargo seleccionable: recuperar de sesión si existe
        $recargo_seleccionable_valor = 0;
        $recargo_seleccionable_id = null;
        if (isset($recargos_seleccionables_sesion[$dest_id])) {
            $recargo_seleccionable_id = intval($recargos_seleccionables_sesion[$dest_id]);
            $recargo_seleccionable = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, valor_fijo FROM recargos WHERE id = %d AND tipo = 'por_volumen_peso' AND activo = 1",
                $recargo_seleccionable_id
            ));
            if ($recargo_seleccionable) {
                $recargo_seleccionable_valor = intval($recargo_seleccionable->valor_fijo);
            }
        }
        
        $total_trayecto = $precio + $recargo_total + $recargo_seleccionable_valor;
        $total += $total_trayecto;

        $detalle_envios[] = [
            'id' => $dest_id,
            'nombre' => $nombre_destino,
            'precio' => $precio,
            'recargo' => $recargo_total,
            'recargo_seleccionable' => $recargo_seleccionable_valor,
            'recargo_seleccionable_id' => $recargo_seleccionable_id,
            'total' => $total_trayecto,
        ];
    }

    // Lista de recargos únicos
    foreach ($recargos_fijos_nombres as $nombre) {
        if (!in_array($nombre, $lista_recargos, true)) {
            $lista_recargos[] = $nombre;
        }
    }

    // Obtener todos los barrios para agregar nuevos
    $barrios_all = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");

    ob_start();
    ?>

    <div class="gofast-checkout-wrapper">
        <div class="gofast-box" style="max-width:800px;margin:0 auto;">
            
            <div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:12px;margin-bottom:20px;border-radius:8px;">
                <strong>⚙️ Resumen del Servicio</strong><br>
                <small>Mensajero asignado: <strong><?= esc_html($mensajero->nombre) ?></strong></small><br>
                <small>Puedes eliminar destinos o agregar nuevos antes de aceptar.</small>
            </div>

            <h3 style="margin-top:0;">📍 Origen: <?= esc_html($nombre_origen) ?></h3>

            <div id="destinos-resumen">
                <?php foreach ($detalle_envios as $idx => $d): ?>
                    <div class="gofast-destino-resumen-item" 
                         data-destino-id="<?= esc_attr($d['id']) ?>"
                         data-precio="<?= esc_attr($d['precio']) ?>"
                         data-recargo="<?= esc_attr($d['recargo']) ?>"
                         data-recargo-seleccionable="<?= esc_attr($d['recargo_seleccionable'] ?? 0) ?>"
                         data-recargo-seleccionable-id="<?= esc_attr($d['recargo_seleccionable_id'] ?? '') ?>"
                         data-total="<?= esc_attr($d['total']) ?>">
                        <div style="padding:12px;background:#f8f9fa;border-radius:8px;margin-bottom:10px;border-left:4px solid #F4C524;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <div style="flex:1;">
                                    <strong>🎯 <?= esc_html($d['nombre']) ?></strong>
                                    <?php if (!empty($d['recargo_seleccionable_id']) && !empty($d['recargo_seleccionable'])): ?>
                                        <span style="background:#4CAF50;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:8px;font-weight:normal;">
                                            ⭐ Con recargo adicional
                                        </span>
                                    <?php endif; ?>
                                    <br>
                                    <small style="color:#666;">
                                        Base: $<?= number_format($d['precio'], 0, ',', '.') ?> | 
                                        Recargo automático: $<?= number_format($d['recargo'], 0, ',', '.') ?>
                                        <?php if (!empty($d['recargo_seleccionable_id']) && !empty($d['recargo_seleccionable'])): ?>
                                            | <strong style="color:#4CAF50;">Recargo adicional: $<?= number_format($d['recargo_seleccionable'], 0, ',', '.') ?></strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <button type="button" class="gofast-btn-delete" onclick="eliminarDestinoResumen(<?= esc_attr($d['id']) ?>)" style="margin-left:12px;padding:8px 12px;">
                                    ❌ Eliminar
                                </button>
                            </div>
                            <?php if (!empty($recargos_seleccionables)): ?>
                                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #ddd;">
                                    <label style="display:block;margin-bottom:4px;font-size:13px;color:#666;">
                                        <strong>➕ Recargo adicional (opcional):</strong>
                                    </label>
                                    <select name="recargo_seleccionable[<?= esc_attr($d['id']) ?>]" 
                                            class="recargo-seleccionable-select" 
                                            data-destino-id="<?= esc_attr($d['id']) ?>"
                                            style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                                        <option value="">Sin recargo adicional</option>
                                        <?php foreach ($recargos_seleccionables as $rs): ?>
                                            <option value="<?= esc_attr($rs->id) ?>" 
                                                    data-valor="<?= esc_attr($rs->valor_fijo) ?>"
                                                    data-nombre="<?= esc_attr($rs->nombre) ?>"
                                                    <?= (isset($d['recargo_seleccionable_id']) && $d['recargo_seleccionable_id'] == $rs->id) ? 'selected' : '' ?>>
                                                <?= esc_html($rs->nombre) ?> (+$<?= number_format($rs->valor_fijo, 0, ',', '.') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top:8px;text-align:right;">
                                <strong style="color:#4CAF50;font-size:18px;" class="destino-total-display">
                                    $<?= number_format($d['total'], 0, ',', '.') ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($lista_recargos)): ?>
                <div class="gofast-recargo-box" style="margin:16px 0;">
                    🟡 <b>Recargos aplicados:</b><br>
                    <ul style="margin:8px 0 0 20px;">
                        <?php foreach ($lista_recargos as $nombre): ?>
                            <li><?= esc_html($nombre) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="gofast-total-box" style="margin:20px 0;text-align:center;" id="total-box">
                💰 <strong style="font-size:24px;" id="total-amount">Total: $<?= number_format($total, 0, ',', '.') ?></strong>
            </div>

            <!-- Agregar nuevo destino -->
            <div style="background:#f8f9fa;padding:16px;border-radius:8px;margin:20px 0;">
                <label><strong>➕ Agregar nuevo destino</strong></label>
                <select id="nuevo-destino-select" class="gofast-select" style="margin-top:8px;">
                    <option value="">Seleccionar barrio...</option>
                    <?php foreach ($barrios_all as $b): ?>
                        <?php if (!in_array($b->id, array_column($detalle_envios, 'id'))): ?>
                            <option value="<?= esc_attr($b->id) ?>" data-nombre="<?= esc_attr($b->nombre) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btn-agregar-destino-resumen" class="gofast-btn-mini" style="margin-top:8px;width:100%;">
                    ➕ Agregar destino
                </button>
            </div>

            <!-- Formulario oculto para enviar -->
            <form method="post" id="form-aceptar-rechazar">
                <input type="hidden" name="mensajero_id" value="<?= esc_attr($mensajero_id) ?>">
                <input type="hidden" name="origen" id="input-origen" value="<?= esc_attr($origen) ?>">
                <input type="hidden" name="destinos_finales" id="input-destinos" value="<?= esc_attr(implode(',', array_column($detalle_envios, 'id'))) ?>">
                <!-- Los recargos seleccionables se agregarán dinámicamente con JavaScript -->

                <div class="gofast-btn-group" style="margin-top:24px;">
                    <button type="submit" name="gofast_admin_aceptar" class="gofast-btn-request" style="background:#4CAF50;color:#fff;">
                        ✅ Aceptar y crear servicio
                    </button>
                    <button type="submit" name="gofast_admin_rechazar" class="gofast-btn-action gofast-secondary">
                        ❌ Rechazar y volver
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
    // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
    (function() {
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
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(func, delay) {
            if (typeof func === 'function') {
                const funcStr = func.toString();
                if (funcStr.includes('toggleOtro')) {
                    const tipoSelect = document.getElementById("tipo_negocio");
                    const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                    if (!tipoSelect || !wrapperOtro) {
                        return null; // No ejecutar si no existen los elementos
                    }
                }
            }
            return originalSetTimeout.apply(this, arguments);
        };
    })();
    
    // Datos para JavaScript
    const datosResumen = {
        origen: <?= json_encode($origen) ?>,
        sector_origen: <?= json_encode($sector_origen) ?>,
        recargo_fijo: <?= json_encode($recargo_fijo_por_envio) ?>,
        recargos_variables: <?= json_encode(array_map(function($r) {
            return [
                'min' => intval($r->monto_min),
                'max' => intval($r->monto_max),
                'recargo' => intval($r->recargo)
            ];
        }, $recargos_variables)) ?>
    };
    
    // Mapa de barrios con sus sectores
    const barriosMap = <?= json_encode(array_map(function($b) use ($wpdb) {
        $sector = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $b->id)));
        return ['id' => $b->id, 'nombre' => $b->nombre, 'sector_id' => $sector];
    }, $barrios_all)) ?>;
    
    // Mapa de tarifas
    const tarifasMap = {};
    <?php
    $tarifas_all = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
    foreach ($tarifas_all as $t) {
        if (!isset($tarifasMap[$t->origen_sector_id])) {
            $tarifasMap[$t->origen_sector_id] = [];
        }
        $tarifasMap[$t->origen_sector_id][$t->destino_sector_id] = intval($t->precio);
    }
    ?>
    const tarifasData = <?= json_encode($tarifasMap) ?>;
    
    document.addEventListener('DOMContentLoaded', function(){
        // Inicializar Select2 para nuevo destino
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#nuevo-destino-select').select2({
                placeholder: '🔍 Buscar barrio...',
                width: '100%',
                dropdownParent: jQuery('body'),
                minimumResultsForSearch: 0,
            });
        }

        // Agregar nuevo destino
        const btnAgregar = document.getElementById('btn-agregar-destino-resumen');
        if (btnAgregar) {
            btnAgregar.addEventListener('click', function(){
                const select = document.getElementById('nuevo-destino-select');
                const destinoId = parseInt(select.value);
                
                if (!destinoId) {
                    alert('Selecciona un destino primero');
                    return;
                }
                
                // Verificar duplicados - convertir a números para comparación
                const destinosActuales = obtenerDestinosActuales().map(id => parseInt(id));
                if (destinosActuales.includes(destinoId)) {
                    alert('Este destino ya está en el resumen');
                    select.value = '';
                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery(select).val(null).trigger('change');
                    }
                    return;
                }
                
                agregarDestinoResumen(destinoId);
            });
        }
    });

    function eliminarDestinoResumen(destinoId) {
        if (!confirm('¿Eliminar este destino del resumen?')) return;
        
        const item = document.querySelector('[data-destino-id="' + destinoId + '"]');
        if (item) {
            item.remove();
            actualizarDestinosInput();
            recalcularTotalJS();
        }
    }

    function agregarDestinoResumen(destinoId) {
        // Verificar nuevamente antes de agregar (por si acaso)
        const destinosActuales = obtenerDestinosActuales().map(id => parseInt(id));
        if (destinosActuales.includes(destinoId)) {
            alert('Este destino ya está en el resumen');
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputMensajero = document.createElement('input');
        inputMensajero.type = 'hidden';
        inputMensajero.name = 'mensajero_id';
        const mensajeroInput = document.querySelector('input[name="mensajero_id"]');
        if (mensajeroInput) {
            inputMensajero.value = mensajeroInput.value;
            form.appendChild(inputMensajero);
        }
        
        const inputOrigen = document.createElement('input');
        inputOrigen.type = 'hidden';
        inputOrigen.name = 'origen';
        const origenInput = document.getElementById('input-origen');
        if (origenInput) {
            inputOrigen.value = origenInput.value;
            form.appendChild(inputOrigen);
        }
        
        // Agregar todos los destinos actuales (sin duplicados)
        const destinosUnicos = [...new Set(destinosActuales)];
        destinosUnicos.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'destino[]';
            input.value = id;
            form.appendChild(input);
        });
        
        // Agregar el nuevo destino solo si no está ya en la lista
        if (!destinosUnicos.includes(destinoId)) {
            const nuevoDestinoInput = document.createElement('input');
            nuevoDestinoInput.type = 'hidden';
            nuevoDestinoInput.name = 'destino[]';
            nuevoDestinoInput.value = destinoId;
            form.appendChild(nuevoDestinoInput);
        }
        
        // Agregar recargos seleccionables actuales para preservarlos
        document.querySelectorAll('.recargo-seleccionable-select').forEach(function(select) {
            const destinoIdSelect = select.getAttribute('data-destino-id');
            const recargoId = select.value;
            if (destinoIdSelect && recargoId && recargoId !== '') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'recargo_seleccionable[' + destinoIdSelect + ']';
                input.value = recargoId;
                form.appendChild(input);
            }
        });
        
        const submit = document.createElement('input');
        submit.type = 'hidden';
        submit.name = 'gofast_admin_cotizar';
        submit.value = '1';
        form.appendChild(submit);
        
        document.body.appendChild(form);
        form.submit();
    }

    function obtenerDestinosActuales() {
        const items = document.querySelectorAll('.gofast-destino-resumen-item[data-destino-id]');
        const ids = [];
        const idsSet = new Set(); // Usar Set para evitar duplicados más eficientemente
        items.forEach(function(item) {
            const id = item.getAttribute('data-destino-id');
            if (id && id !== 'null' && id !== 'undefined' && !idsSet.has(id)) {
                idsSet.add(id);
                ids.push(id);
            }
        });
        return ids;
    }

    function actualizarDestinosInput() {
        const destinos = obtenerDestinosActuales();
        document.getElementById('input-destinos').value = destinos.join(',');
    }

    function recalcularTotalJS() {
        const items = document.querySelectorAll('.gofast-destino-resumen-item');
        let total = 0;
        
        items.forEach(function(item) {
            const precioBase = parseFloat(item.getAttribute('data-precio')) || 0;
            const recargoAuto = parseFloat(item.getAttribute('data-recargo')) || 0;
            const recargoSeleccionable = parseFloat(item.getAttribute('data-recargo-seleccionable')) || 0;
            const itemTotal = precioBase + recargoAuto + recargoSeleccionable;
            total += itemTotal;
            
            // Actualizar display del total del destino
            const totalDisplay = item.querySelector('.destino-total-display');
            if (totalDisplay) {
                totalDisplay.textContent = '$' + itemTotal.toLocaleString('es-CO');
            }
        });
        
        const totalBox = document.getElementById('total-amount');
        if (totalBox) {
            totalBox.textContent = 'Total: $' + total.toLocaleString('es-CO');
        }
        
        if (items.length === 0) {
            alert('Debes tener al menos un destino');
            window.location.href = '<?= esc_url(home_url('/admin-cotizar')) ?>';
        }
    }
    
    // Manejar cambio de recargo seleccionable
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('recargo-seleccionable-select')) {
            const select = e.target;
            const destinoId = select.getAttribute('data-destino-id');
            const item = document.querySelector('[data-destino-id="' + destinoId + '"]');
            
            if (item) {
                const option = select.options[select.selectedIndex];
                const valor = option ? parseFloat(option.getAttribute('data-valor') || 0) : 0;
                const recargoId = select.value || '';
                item.setAttribute('data-recargo-seleccionable', valor);
                item.setAttribute('data-recargo-seleccionable-id', recargoId);
                recalcularTotalJS();
            }
        }
    });
    
    // Agregar recargos seleccionables al formulario antes de enviar
    const formAceptar = document.getElementById('form-aceptar-rechazar');
    if (formAceptar) {
        formAceptar.addEventListener('submit', function(e) {
            const form = this;
            
            // Eliminar inputs de recargos anteriores si existen
            form.querySelectorAll('input[name^="recargo_seleccionable"]').forEach(function(input) {
                input.remove();
            });
            
            // Agregar recargos seleccionables actuales
            document.querySelectorAll('.recargo-seleccionable-select').forEach(function(select) {
                const destinoId = select.getAttribute('data-destino-id');
                const recargoId = select.value;
                if (destinoId && recargoId && recargoId !== '') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'recargo_seleccionable[' + destinoId + ']';
                    input.value = recargoId;
                    form.appendChild(input);
                }
            });
        });
    }
    </script>

    <?php
    return ob_get_clean();
}

add_shortcode('gofast_admin_cotizar', 'gofast_admin_cotizar_shortcode');

