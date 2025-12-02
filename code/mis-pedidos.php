/*******************************************************
 * GOFAST – LISTADO DE PEDIDOS (CLIENTE / ADMIN / MENSAJERO)
 * Shortcode: [gofast_pedidos]
 *******************************************************/
function gofast_pedidos_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para ver tus pedidos.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol     = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    $mensaje_estado = '';

    // Lista de mensajeros (solo para admin)
    $mensajeros = [];
    // Lista de barrios para filtros (solo para admin)
    $barrios = [];
    // Lista de usuarios con negocios (solo para admin)
    $usuarios_negocios = [];
    
    if ($rol === 'admin') {
        $mensajeros = $wpdb->get_results("
            SELECT id, nombre 
            FROM usuarios_gofast
            WHERE rol = 'mensajero' AND activo = 1
            ORDER BY nombre ASC
        ");
        
        $barrios = $wpdb->get_results("
            SELECT id, nombre 
            FROM barrios
            ORDER BY nombre ASC
        ");
        
        // Obtener negocios (no usuarios) para el filtro
        $negocios = $wpdb->get_results("
            SELECT DISTINCT n.id, n.nombre, n.user_id
            FROM negocios_gofast n
            WHERE n.activo = 1
            ORDER BY n.nombre ASC
        ");
    }

    /****************************************
     * 0. GESTIÓN DE ELIMINAR SERVICIO (POST)
     ****************************************/
    if (
        !empty($_POST['gofast_eliminar_id']) &&
        !empty($_POST['gofast_eliminar_nonce']) &&
        wp_verify_nonce($_POST['gofast_eliminar_nonce'], 'gofast_eliminar_servicio') &&
        $rol === 'admin'
    ) {
        $servicio_id = (int) $_POST['gofast_eliminar_id'];
        $eliminado = $wpdb->delete('servicios_gofast', ['id' => $servicio_id]);
        if ($eliminado) {
            $mensaje_estado = 'Servicio eliminado correctamente.';
        } else {
            $mensaje_estado = 'Error al eliminar el servicio.';
        }
    }

    /****************************************
     * 0.1. GESTIÓN DE EDITAR DESTINOS (POST)
     ****************************************/
    if (
        (!empty($_POST['gofast_editar_destinos']) || !empty($_POST['gofast_editar_destinos_id'])) &&
        !empty($_POST['gofast_editar_destinos_nonce']) &&
        wp_verify_nonce($_POST['gofast_editar_destinos_nonce'], 'gofast_editar_destinos') &&
        $rol === 'admin'
    ) {
        $servicio_id = !empty($_POST['gofast_editar_destinos_id']) ? (int) $_POST['gofast_editar_destinos_id'] : 0;
        
        if ($servicio_id > 0) {
            $servicio = $wpdb->get_row($wpdb->prepare("SELECT * FROM servicios_gofast WHERE id = %d", $servicio_id));
            
            if ($servicio) {
                $json_destinos = json_decode($servicio->destinos, true);
                
                if (is_array($json_destinos) && !empty($_POST['destinos_editados'])) {
                    $destinos_editados_json = stripslashes($_POST['destinos_editados']);
                    $destinos_editados = json_decode($destinos_editados_json, true);
                    
                    // Verificar errores de JSON
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $mensaje_estado = 'Error al decodificar JSON de destinos: ' . json_last_error_msg() . '. JSON recibido: ' . substr($destinos_editados_json, 0, 200);
                    } elseif (is_array($destinos_editados) && !empty($destinos_editados['destinos'])) {
                    // Recalcular total
                    $total_nuevo = 0;
                    $destinos_finales = [];
                    
                    foreach ($destinos_editados['destinos'] as $dest) {
                        if (!empty($dest['barrio_id'])) {
                            $barrio_id = (int) $dest['barrio_id'];
                            $sector_destino = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT sector_id FROM barrios WHERE id = %d", $barrio_id
                            ));
                            $sector_origen = !empty($json_destinos['origen']['sector_id']) 
                                ? (int) $json_destinos['origen']['sector_id'] 
                                : 0;
                            
                            $precio = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                                $sector_origen, $sector_destino
                            ));
                            
                            $monto_extra = isset($dest['monto']) ? (int) $dest['monto'] : 0;
                            $total_nuevo += $precio + $monto_extra;
                            
                            $destinos_finales[] = [
                                'barrio_id' => $barrio_id,
                                'barrio_nombre' => $dest['barrio_nombre'] ?? '',
                                'sector_id' => $sector_destino,
                                'direccion' => $dest['direccion'] ?? '',
                                'monto' => $monto_extra
                            ];
                        }
                    }
                    
                    $json_destinos['destinos'] = $destinos_finales;
                    $json_final = json_encode($json_destinos, JSON_UNESCAPED_UNICODE);
                    
                        $actualizado = $wpdb->update(
                            'servicios_gofast',
                            [
                                'destinos' => $json_final,
                                'total' => $total_nuevo
                            ],
                            ['id' => $servicio_id],
                            ['%s', '%d'],
                            ['%d']
                        );
                        
                        if ($actualizado !== false) {
                            $mensaje_estado = '✅ Destinos actualizados correctamente.';
                        } else {
                            $mensaje_estado = '❌ Error al actualizar destinos: ' . esc_html($wpdb->last_error);
                        }
                    } else {
                        $mensaje_estado = 'Error: Los destinos editados no tienen el formato correcto.';
                    }
                } else {
                    $mensaje_estado = 'Error: No se recibieron destinos editados.';
                }
            } else {
                $mensaje_estado = 'Error: Servicio no encontrado.';
            }
        } else {
            $mensaje_estado = 'Error: ID de servicio inválido.';
        }
    }

    /****************************************
     * 0.2. GESTIÓN DE CAMBIO DE ESTADO / MENSAJERO (POST)
     ****************************************/
    if (
        !empty($_POST['gofast_estado_id']) &&
        !empty($_POST['gofast_estado_nonce']) &&
        wp_verify_nonce($_POST['gofast_estado_nonce'], 'gofast_cambiar_estado')
    ) {
        $pedido_id = (int) $_POST['gofast_estado_id'];

        $pedido = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM servicios_gofast WHERE id = %d", $pedido_id)
        );

        if (!$pedido) {
            $mensaje_estado = 'Pedido no encontrado.';
        } else {
            $estados_validos = ['pendiente','asignado','en_ruta','entregado','cancelado'];

            // Estado nuevo (si viene en POST, si no, se deja igual)
            $nuevo_estado = $pedido->tracking_estado;
            if (!empty($_POST['gofast_estado_nuevo'])) {
                $tmp_estado = sanitize_text_field($_POST['gofast_estado_nuevo']);
                if (in_array($tmp_estado, $estados_validos, true)) {
                    $nuevo_estado = $tmp_estado;
                } else {
                    $mensaje_estado = 'Estado no válido.';
                }
            }

            // Mensajero nuevo
            $nuevo_mensajero_id = $pedido->mensajero_id;
            $asignado_por_user_id = null; // Quién asignó el mensajero

            if ($rol === 'admin') {
                // Admin puede asignar cualquier mensajero
                if (isset($_POST['gofast_mensajero_id'])) {
                    $tmp_m = (int) $_POST['gofast_mensajero_id'];
                    $nuevo_mensajero_id = $tmp_m > 0 ? $tmp_m : null;
                    // Si se está asignando un mensajero (no quitándolo), guardar quién lo asignó
                    if ($nuevo_mensajero_id > 0 && $nuevo_mensajero_id != $pedido->mensajero_id) {
                        $asignado_por_user_id = $user_id; // Admin asignó
                    }
                }
            } elseif ($rol === 'mensajero') {
                // Mensajero se auto-asigna si el pedido no tiene mensajero y está tocando el estado
                if (empty($pedido->mensajero_id) && !empty($_POST['gofast_estado_nuevo'])) {
                    $nuevo_mensajero_id = $user_id;
                    $asignado_por_user_id = $user_id; // Mensajero se auto-asignó
                }
            }

            // Permisos
            $puede = false;
            if ($rol === 'admin') {
                $puede = true;
            } elseif ($rol === 'mensajero') {
                // puede tocar pendientes o los suyos
                if ($pedido->tracking_estado === 'pendiente' || (int) $pedido->mensajero_id === $user_id) {
                    $puede = true;
                }
            } else {
                $puede = false;
            }

            if (!$puede) {
                $mensaje_estado = 'No tienes permisos para modificar este pedido.';
            } else {
                $data = [];

                if ($nuevo_estado !== $pedido->tracking_estado) {
                    $data['tracking_estado'] = $nuevo_estado;
                }

                // Comparación con null/int
                if ((string) $nuevo_mensajero_id !== (string) $pedido->mensajero_id) {
                    $data['mensajero_id'] = $nuevo_mensajero_id;
                    // Guardar quién asignó el mensajero (si se está asignando)
                    if ($asignado_por_user_id !== null && $nuevo_mensajero_id > 0) {
                        // Verificar si el campo existe, si no, intentar agregarlo
                        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                        if (empty($column_exists)) {
                            // Agregar el campo si no existe
                            $wpdb->query("ALTER TABLE servicios_gofast ADD COLUMN asignado_por_user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER mensajero_id");
                        }
                        $data['asignado_por_user_id'] = $asignado_por_user_id;
                    } elseif ($nuevo_mensajero_id === null || $nuevo_mensajero_id == 0) {
                        // Si se quita el mensajero, limpiar quién lo asignó
                        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                        if (!empty($column_exists)) {
                            $data['asignado_por_user_id'] = null;
                        }
                    }
                }

                if (!empty($data)) {
                    $ok = $wpdb->update('servicios_gofast', $data, ['id' => $pedido_id]);
                    if ($ok === false) {
                        $mensaje_estado = 'Error al actualizar: ' . esc_html($wpdb->last_error);
                    } else {
                        $mensaje_estado = 'Pedido actualizado correctamente.';
                    }
                }
            }
        }
    }

    /****************************************
     * 1. Filtros (GET)
     ****************************************/
    $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $buscar = isset($_GET['q'])      ? sanitize_text_field($_GET['q'])      : '';
    $desde  = isset($_GET['desde'])  ? sanitize_text_field($_GET['desde'])  : '';
    $hasta  = isset($_GET['hasta'])  ? sanitize_text_field($_GET['hasta'])  : '';
    
    // Filtros adicionales solo para admin
    $filtro_mensajero = isset($_GET['filtro_mensajero']) ? (int) $_GET['filtro_mensajero'] : 0;
    $filtro_origen = isset($_GET['filtro_origen']) ? (int) $_GET['filtro_origen'] : 0;
    $filtro_destino = isset($_GET['filtro_destino']) ? (int) $_GET['filtro_destino'] : 0;
    $filtro_negocio = isset($_GET['filtro_negocio']) ? (int) $_GET['filtro_negocio'] : 0;
    $filtro_asignado_por = isset($_GET['filtro_asignado_por']) ? (int) $_GET['filtro_asignado_por'] : 0;

    // Predefinir fecha al día actual si no hay filtros de fecha (para todos los usuarios)
    if (empty($_GET['desde']) && empty($_GET['hasta'])) {
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d');
    }

    if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
    if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

    /****************************************
     * 2. WHERE según rol
     ****************************************/
    $where  = "1=1";
    $params = [];

    if ($rol === 'admin') {
        // ve todo
    } elseif ($rol === 'mensajero') {
        $where   .= " AND (tracking_estado = %s OR mensajero_id = %d)";
        $params[] = 'pendiente';
        $params[] = $user_id;
    } else {
        $where   .= " AND user_id = %d";
        $params[] = $user_id;
    }

    if ($estado !== '' && $estado !== 'todos') {
        $where   .= " AND tracking_estado = %s";
        $params[] = $estado;
    }

    // búsqueda por nombre o teléfono
    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where   .= " AND (nombre_cliente LIKE %s OR telefono_cliente LIKE %s)";
        $params[] = $like;
        $params[] = $like;
    }

    if ($desde !== '') {
        $where   .= " AND fecha >= %s";
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where   .= " AND fecha <= %s";
        $params[] = $hasta . ' 23:59:59';
    }
    
    // Filtros adicionales solo para admin
    if ($rol === 'admin') {
        if ($filtro_mensajero > 0) {
            $where   .= " AND mensajero_id = %d";
            $params[] = $filtro_mensajero;
        }
        
        if ($filtro_negocio > 0) {
            // Buscar servicios donde el user_id corresponde al dueño del negocio
            $negocio = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM negocios_gofast WHERE id = %d AND activo = 1",
                $filtro_negocio
            ));
            if ($negocio) {
                $where   .= " AND user_id = %d";
                $params[] = $negocio->user_id;
            }
        }
        
        if ($filtro_asignado_por > 0) {
            // Buscar servicios donde el user_id corresponde al que asignó
            $where   .= " AND user_id = %d";
            $params[] = $filtro_asignado_por;
        }
        
        // Filtro por origen (buscar en JSON)
        if ($filtro_origen > 0) {
            $where   .= " AND JSON_EXTRACT(destinos, '$.origen.barrio_id') = %d";
            $params[] = $filtro_origen;
        }
        
        // Filtro por destino (buscar en JSON array)
        if ($filtro_destino > 0) {
            $where   .= " AND JSON_CONTAINS(destinos, JSON_OBJECT('barrio_id', %d), '$.destinos')";
            $params[] = $filtro_destino;
        }
    }

    /****************************************
     * 3. Paginación
     ****************************************/
    $por_pagina = 15;
    $pagina     = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    $offset     = ($pagina - 1) * $por_pagina;

    if (!empty($params)) {
        $sql_count = $wpdb->prepare(
            "SELECT COUNT(*) FROM servicios_gofast WHERE $where",
            $params
        );
    } else {
        $sql_count = "SELECT COUNT(*) FROM servicios_gofast WHERE $where";
    }

    $total_registros = (int) $wpdb->get_var($sql_count);
    $total_paginas   = max(1, (int) ceil($total_registros / $por_pagina));

    $params_datos   = $params;
    $params_datos[] = $por_pagina;
    $params_datos[] = $offset;

    $sql_datos = $wpdb->prepare(
        "SELECT * FROM servicios_gofast 
         WHERE $where
         ORDER BY fecha DESC
         LIMIT %d OFFSET %d",
        $params_datos
    );

    $pedidos = $wpdb->get_results($sql_datos);

    // opciones de estado reutilizables
    $estado_opts = [
        'pendiente' => 'Pendiente',
        'asignado'  => 'Asignado',
        'en_ruta'   => 'En Ruta',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];

    /****************************************
     * 4. Render
     ****************************************/
    ob_start();
    ?>

<div class="gofast-home">
    <?php if (!empty($mensaje_estado)): ?>
        <div class="gofast-box" style="margin-bottom:16px;padding:12px;background:<?php echo strpos($mensaje_estado, '✅') !== false ? '#d4edda' : '#f8d7da'; ?>;border:1px solid <?php echo strpos($mensaje_estado, '✅') !== false ? '#c3e6cb' : '#f5c6cb'; ?>;border-radius:6px;color:<?php echo strpos($mensaje_estado, '✅') !== false ? '#155724' : '#721c24'; ?>;">
            <?php echo esc_html($mensaje_estado); ?>
        </div>
    <?php endif; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">📦 Pedidos</h1>
            <p class="gofast-home-text" style="margin:0;">
                <?php if ($rol === 'admin'): ?>
                    Gestiona todos los pedidos del sistema.
                <?php elseif ($rol === 'mensajero'): ?>
                    Pedidos pendientes y asignados a ti.
                <?php else: ?>
                    Tus pedidos y servicios solicitados.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($rol === 'admin'): ?>
            <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
                ← Volver al Dashboard
            </a>
        <?php endif; ?>
    </div>

    <div class="gofast-box">
        <?php if ($mensaje_estado): ?>
            <div class="gofast-alert-info" style="margin-bottom:16px;">
                <?php echo esc_html($mensaje_estado); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="get" class="gofast-pedidos-filtros">
            <div class="gofast-pedidos-filtros-row">
                <div>
                    <label>Estado</label>
                    <select name="estado">
                        <option value="todos"<?php selected($estado, 'todos'); ?>>Todos</option>
                        <?php foreach ($estado_opts as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>"<?php selected($estado, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Nombre / Teléfono</label>
                    <input type="text" name="q" placeholder="Ej: Juan o 300..." value="<?php echo esc_attr($buscar); ?>">
                </div>

                <div>
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?php echo esc_attr($desde); ?>">
                </div>

                <div>
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?php echo esc_attr($hasta); ?>">
                </div>

                <?php if ($rol === 'admin'): ?>
                    <div>
                        <label>Mensajero</label>
                        <select name="filtro_mensajero">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?php echo (int) $m->id; ?>"<?php selected($filtro_mensajero, $m->id); ?>>
                                    <?php echo esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Origen</label>
                        <select name="filtro_origen" class="gofast-select-filtro" data-placeholder="Todos los orígenes">
                            <option value="0">Todos</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?php echo (int) $b->id; ?>"<?php selected($filtro_origen, $b->id); ?>>
                                    <?php echo esc_html($b->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Destino</label>
                        <select name="filtro_destino" class="gofast-select-filtro" data-placeholder="Todos los destinos">
                            <option value="0">Todos</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?php echo (int) $b->id; ?>"<?php selected($filtro_destino, $b->id); ?>>
                                    <?php echo esc_html($b->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Negocio</label>
                        <select name="filtro_negocio">
                            <option value="0">Todos</option>
                            <?php if (!empty($negocios)): ?>
                                <?php foreach ($negocios as $neg): ?>
                                    <option value="<?php echo (int) $neg->id; ?>"<?php selected($filtro_negocio, $neg->id); ?>>
                                        <?php echo esc_html($neg->nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label>Asignado por</label>
                        <select name="filtro_asignado_por">
                            <option value="0">Todos</option>
                            <?php 
                            $asignadores = $wpdb->get_results("
                                SELECT DISTINCT user_id 
                                FROM servicios_gofast 
                                WHERE user_id IS NOT NULL
                            ");
                            foreach ($asignadores as $a): 
                                $usuario = $wpdb->get_row($wpdb->prepare(
                                    "SELECT id, nombre FROM usuarios_gofast WHERE id = %d", 
                                    $a->user_id
                                ));
                                if ($usuario):
                            ?>
                                <option value="<?php echo (int) $usuario->id; ?>"<?php selected($filtro_asignado_por, $usuario->id); ?>>
                                    <?php echo esc_html($usuario->nombre); ?>
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="gofast-pedidos-filtros-actions">
                    <button type="submit" class="gofast-btn-mini">Filtrar</button>
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
                </div>
            </div>
        </form>

        <?php if ($total_registros === 0): ?>
            <p style="margin-top:20px;text-align:center;color:#666;">No se encontraron pedidos con los filtros seleccionados.</p>
        <?php else: ?>

            <div class="gofast-table-wrap" style="margin-top:20px;overflow-x:auto;-webkit-overflow-scrolling:touch;">
                <table class="gofast-table" style="min-width:800px;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Origen</th>
                            <th>Destinos</th>
                            <th>Mensajero</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Ver</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $p):

                        // Decodificar JSON
                        $origen_barrio   = '';
                        $destinos_barris = [];

                        if (!empty($p->destinos)) {
                            $json = json_decode($p->destinos, true);
                            if (is_array($json)) {
                                if (!empty($json['origen']['barrio_nombre'])) {
                                    $origen_barrio = $json['origen']['barrio_nombre'];
                                }
                                if (!empty($json['destinos']) && is_array($json['destinos'])) {
                                    foreach ($json['destinos'] as $d) {
                                        if (!empty($d['barrio_nombre'])) {
                                            $destinos_barris[] = $d['barrio_nombre'];
                                        }
                                    }
                                }
                            }
                        }

                        if ($origen_barrio === '') {
                            $origen_barrio = $p->direccion_origen ?: '—';
                        }
                        $destinos_text = !empty($destinos_barris)
                            ? implode(', ', array_unique($destinos_barris))
                            : '—';

                        // Mensajero y quién lo asignó
                        $mensajero_nombre = '—';
                        $asignado_por = '—';
                        $asignado_por_tipo = '';
                        
                        if (!empty($p->mensajero_id)) {
                            $mensajero_nombre = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                    $p->mensajero_id
                                )
                            ) ?: '—';
                            
                            // Determinar quién asignó el mensajero
                            // Primero verificar si existe el campo asignado_por_user_id
                            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                            
                            if (!empty($column_exists) && !empty($p->asignado_por_user_id)) {
                                // Usar el campo de la base de datos
                                if ((int) $p->asignado_por_user_id === (int) $p->mensajero_id) {
                                    $asignado_por = 'Auto-asignado';
                                    $asignado_por_tipo = 'auto';
                                } else {
                                    $asignador_nombre = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                            $p->asignado_por_user_id
                                        )
                                    );
                                    $asignador_rol = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT rol FROM usuarios_gofast WHERE id = %d",
                                            $p->asignado_por_user_id
                                        )
                                    );
                                    if (strtolower($asignador_rol) === 'admin') {
                                        $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Admin');
                                        $asignado_por_tipo = 'admin';
                                    } else {
                                        $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Usuario');
                                        $asignado_por_tipo = 'otro';
                                    }
                                }
                            } else {
                                // Fallback: inferir basado en lógica antigua
                                if ((int) $p->mensajero_id === (int) $p->user_id) {
                                    $asignado_por = 'Auto-asignado';
                                    $asignado_por_tipo = 'auto';
                                } else {
                                    $asignado_por = 'Asignado por Admin';
                                    $asignado_por_tipo = 'admin';
                                }
                            }
                        }

                        $estado_actual = $p->tracking_estado;
                        $estado_class  = 'gofast-badge-estado-' . esc_attr($estado_actual);
                        $detalle_url   = esc_url( home_url('/servicio-registrado?id=' . $p->id) );
                        ?>
                        <tr>
                            <td>#<?php echo (int) $p->id; ?></td>
                            <td><?php echo esc_html( date_i18n('Y-m-d H:i', strtotime($p->fecha)) ); ?></td>
                            <td><?php echo esc_html($p->nombre_cliente ?: '—'); ?></td>
                            <td><?php echo esc_html($p->telefono_cliente ?: '—'); ?></td>

                            <td><?php echo esc_html($origen_barrio); ?></td>
                            <td><?php echo esc_html($destinos_text); ?></td>

                            <!-- Mensajero -->
                            <td>
                                <?php if ($rol === 'admin'): ?>
                                    <form method="post" class="gofast-estado-form">
                                        <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                        <input type="hidden" name="gofast_estado_id" value="<?php echo (int) $p->id; ?>">
                                        <!-- mantener estado actual para que no cambie si solo se asigna mensajero -->
                                        <input type="hidden" name="gofast_estado_nuevo" value="<?php echo esc_attr($estado_actual); ?>">

                                        <select name="gofast_mensajero_id" class="gofast-mensajero-select" onchange="this.form.submit()">
                                            <option value="">Sin asignar</option>
                                            <?php foreach ($mensajeros as $m): ?>
                                                <option value="<?php echo (int) $m->id; ?>"<?php selected($p->mensajero_id, $m->id); ?>>
                                                    <?php echo esc_html($m->nombre); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                            <div style="font-size:10px;color:#666;margin-top:2px;">
                                                <?php if ($asignado_por_tipo === 'auto'): ?>
                                                    <span style="color:#28a745;">✓ Auto-asignado</span>
                                                <?php else: ?>
                                                    <span style="color:#007bff;">✓ Por Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <div>
                                        <?php echo esc_html($mensajero_nombre); ?>
                                        <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                            <div style="font-size:10px;color:#666;margin-top:2px;">
                                                <?php if ($asignado_por_tipo === 'auto'): ?>
                                                    <span style="color:#28a745;">(Auto-asignado)</span>
                                                <?php else: ?>
                                                    <span style="color:#007bff;">(Asignado por Admin)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Total -->
                            <td>$<?php echo number_format($p->total, 0, ',', '.'); ?></td>

                            <!-- Estado -->
                            <td>
                                <?php if ($rol === 'admin' || $rol === 'mensajero'): ?>
                                    <form method="post" class="gofast-estado-form">
                                        <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                        <input type="hidden" name="gofast_estado_id" value="<?php echo (int) $p->id; ?>">
                                        <!-- si hay mensajero, lo mantenemos; para mensajero auto-asignado ya lo pone el backend -->
                                        <?php if ($rol === 'admin' && !empty($p->mensajero_id)): ?>
                                            <input type="hidden" name="gofast_mensajero_id" value="<?php echo (int) $p->mensajero_id; ?>">
                                        <?php endif; ?>

                                        <select name="gofast_estado_nuevo" onchange="this.form.submit()">
                                            <?php foreach ($estado_opts as $val => $label): ?>
                                                <option value="<?php echo esc_attr($val); ?>"<?php selected($estado_actual, $val); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="gofast-badge-estado <?php echo $estado_class; ?>">
                                        <?php echo esc_html($estado_opts[$estado_actual] ?? $estado_actual); ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td><a href="<?php echo $detalle_url; ?>" class="gofast-link-ver">Ver</a></td>
                            
                            <?php if ($rol === 'admin'): ?>
                                <td style="white-space:nowrap;">
                                    <button type="button" 
                                            class="gofast-btn-mini gofast-btn-editar-destinos" 
                                            data-servicio-id="<?php echo (int) $p->id; ?>"
                                            data-destinos='<?php echo esc_attr($p->destinos); ?>'
                                            data-cliente="<?php echo esc_attr($p->nombre_cliente); ?>"
                                            data-fecha="<?php echo esc_attr(date_i18n('Y-m-d H:i', strtotime($p->fecha))); ?>"
                                            data-origen="<?php echo esc_attr($origen_barrio); ?>"
                                            data-total="<?php echo number_format($p->total, 0, ',', '.'); ?>">
                                        ✏️ Editar
                                    </button>
                                    <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este servicio? Esta acción no se puede deshacer.');">
                                        <?php wp_nonce_field('gofast_eliminar_servicio', 'gofast_eliminar_nonce'); ?>
                                        <input type="hidden" name="gofast_eliminar_id" value="<?php echo (int) $p->id; ?>">
                                        <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                            🗑️ Eliminar
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="gofast-pagination" style="margin-top:20px;">
                    <?php
                    $base_url   = get_permalink();
                    $query_args = $_GET;

                    for ($i = 1; $i <= $total_paginas; $i++):
                        $query_args['pg'] = $i;
                        $url    = esc_url( add_query_arg($query_args, $base_url) );
                        $active = ($i === $pagina) ? 'gofast-page-current' : '';
                        ?>
                        <a href="<?php echo $url; ?>" class="gofast-page-link <?php echo $active; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php if ($rol === 'admin'): ?>
<!-- Modal para editar destinos -->
<div id="modal-editar-destinos" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:700px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Destinos del Servicio</h2>
        
        <!-- Información del servicio -->
        <div id="servicio-info" style="background:#f8f9fa;border-left:4px solid var(--gofast-yellow);padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;">
            <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 12px;align-items:start;">
                <strong style="color:#666;">Servicio #:</strong>
                <span id="info-servicio-id" style="font-weight:600;color:#000;"></span>
                
                <strong style="color:#666;">Cliente:</strong>
                <span id="info-cliente" style="font-weight:600;color:#000;"></span>
                
                <strong style="color:#666;">Fecha:</strong>
                <span id="info-fecha" style="color:#000;"></span>
                
                <strong style="color:#666;">Origen:</strong>
                <span id="info-origen" style="font-weight:600;color:#000;"></span>
                
                <strong style="color:#666;">Total actual:</strong>
                <span id="info-total" style="font-weight:700;color:var(--gofast-yellow);">$0</span>
            </div>
        </div>
        
        <div style="margin-bottom:12px;padding:10px;background:#fff3cd;border-radius:6px;font-size:12px;color:#856404;">
            <strong>📍 Origen:</strong> <span id="info-origen-text"></span><br>
            <strong>🎯 Total de destinos:</strong> <span id="info-destinos-count">0</span> destino(s) configurado(s)
        </div>
        
        <form method="post" id="form-editar-destinos" action="">
            <?php wp_nonce_field('gofast_editar_destinos', 'gofast_editar_destinos_nonce'); ?>
            <input type="hidden" name="gofast_editar_destinos" value="1">
            <input type="hidden" name="gofast_editar_destinos_id" id="editar-destinos-id">
            <input type="hidden" name="destinos_editados" id="destinos-editados-json">
            
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Destinos del servicio:</label>
            </div>
            
            <div id="destinos-container" style="margin-bottom:16px;">
                <!-- Los destinos se cargarán aquí dinámicamente -->
            </div>
            
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar()">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
    // Sobrescribir completamente la función para evitar que se ejecute en esta página
    if (typeof toggleOtro === 'function') {
        const originalToggleOtro = toggleOtro;
        window.toggleOtro = function() {
            try {
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                if (tipoSelect && wrapperOtro && tipoSelect.parentNode && wrapperOtro.parentNode) {
                    originalToggleOtro();
                }
            } catch(e) {
                // Silenciar error completamente
                return;
            }
        };
    }
    
    // También prevenir que setTimeout ejecute toggleOtro si no están los elementos
    const originalSetTimeout = window.setTimeout;
    window.setTimeout = function(func, delay) {
        if (typeof func === 'function' && func.toString().includes('toggleOtro')) {
            const tipoSelect = document.getElementById("tipo_negocio");
            const wrapperOtro = document.getElementById("tipo_otro_wrapper");
            if (!tipoSelect || !wrapperOtro) {
                return null; // No ejecutar si no existen los elementos
            }
        }
        return originalSetTimeout.apply(this, arguments);
    };
    
    const barrios = <?php echo json_encode($barrios); ?>;
    
    // Función para normalizar texto (sin tildes, minúsculas) - HACER GLOBAL
    window.normalize = s => (s || "")
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
    
    // Función matcher mejorada para búsqueda de barrios - HACER GLOBAL
    window.matcherDestinos = function(params, data) {
        // Si no hay data, retornar null
        if (!data) return null;
        
        // Si es un optgroup (tiene children), dejarlo pasar
        if (data.children && Array.isArray(data.children)) {
            return data;
        }
        
        // Si no tiene id, es un optgroup label o separador
        if (!data.id) {
            if (!params.term || !params.term.trim()) {
                return data;
            }
            return null;
        }
        
        // Si no tiene text, no mostrar
        if (!data.text) return null;
        
        // Si no hay término de búsqueda, mostrar todas las opciones
        if (!params.term || !params.term.trim()) {
            data.matchScore = 0;
            return data;
        }

        const term = window.normalize(params.term);
        if (!term) {
            data.matchScore = 0;
            return data;
        }

        const text = window.normalize(data.text);
        
        // PRIMERO: Verificar coincidencia exacta
        if (text === term) {
            data.matchScore = 10000;
            return data;
        }
        
        // SEGUNDO: Verificar si el texto comienza exactamente con el término
        if (text.indexOf(term) === 0) {
            data.matchScore = 9500;
            return data;
        }
        
        // Palabras comunes a ignorar
        const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
        
        const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
            return word.length > 2 && !stopWords.includes(word);
        });
        
        // Si después de filtrar no quedan palabras, buscar el término completo
        if (searchWords.length === 0) {
            if (text.indexOf(term) !== -1) {
                data.matchScore = 7000;
                return data;
            }
            return null;
        }
        
        // Verificar que al menos una palabra significativa esté presente
        const significantMatches = searchWords.filter(word => {
            if (word.length <= 2) {
                return text.split(/\s+/).some(textWord => textWord.indexOf(word) === 0);
            }
            return text.indexOf(word) !== -1;
        });
        
        if (significantMatches.length === 0) return null;
        
        const allSignificantMatch = searchWords.length === significantMatches.length;
        
        // Sistema de puntuación
        let score = 0;
        
        const textWithoutStopWords = text.split(/\s+/).filter(w => !stopWords.includes(w)).join(' ');
        const termWithoutStopWords = searchWords.join(' ');
        
        // Coincidencia exacta sin stop words
        if (textWithoutStopWords === termWithoutStopWords) {
            score = 10000;
        } 
        // El término completo (sin stop words) está al inicio
        else if (textWithoutStopWords.indexOf(termWithoutStopWords) === 0) {
            score = 9000;
        } 
        // El término completo (sin stop words) está en cualquier parte
        else if (textWithoutStopWords.indexOf(termWithoutStopWords) !== -1) {
            score = 8000;
        } 
        // Al menos una palabra significativa al inicio
        else if (searchWords.some(word => text.indexOf(word) === 0)) {
            score = 7000;
        } 
        // El término completo está en cualquier parte
        else if (text.indexOf(term) !== -1) {
            score = 6000;
        } 
        // Coincidencias parciales
        else {
            score = allSignificantMatch ? 5000 : 3000;
            
            let lastIndex = -1;
            let wordsInOrder = true;
            searchWords.forEach(word => {
                const wordIndex = text.indexOf(word, lastIndex + 1);
                if (wordIndex === -1) {
                    wordsInOrder = false;
                } else {
                    if (wordIndex < lastIndex) wordsInOrder = false;
                    lastIndex = wordIndex;
                    if (text.indexOf(word) === 0) score += 500;
                }
            });
            
            if (wordsInOrder) score += 1000;
        }
        
        data.matchScore = score;
        return data;
    };
    
    // Función para inicializar Select2 en los selects de barrios
    function initSelect2Barrios(selectElement) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        
        // Evitar inicializar dos veces
        if (jQuery(selectElement).data('select2')) {
            return;
        }
        
        jQuery(selectElement).select2({
            placeholder: "🔍 Escribe para buscar barrio...",
            width: '100%',
            dropdownParent: jQuery('#modal-editar-destinos'),
            allowClear: true,
            minimumResultsForSearch: 0,
            matcher: window.matcherDestinos,
            sorter: function(results) {
                return results.sort(function(a, b) {
                    return (b.matchScore || 0) - (a.matchScore || 0);
                });
            },
            templateResult: function(data, container) {
                if (!data || !data.text) {
                    return data ? data.text : '';
                }
                
                if (!data.id) return data.text;
                
                let originalText = data.text;
                
                // Obtener el término de búsqueda
                let searchTerm = "";
                const $activeField = jQuery('.select2-container--open .select2-search__field');
                if ($activeField.length) {
                    searchTerm = $activeField.val() || "";
                }
                
                if (!searchTerm || !searchTerm.trim()) {
                    const $result = jQuery('<span>').text(originalText);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
                
                // Normalizar para búsqueda
                const normalizedSearch = window.normalize(searchTerm);
                const normalizedText = window.normalize(originalText);
                
                // Dividir término en palabras significativas
                const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                    return word.length > 2 && !stopWords.includes(word);
                });
                
                const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                
                // Encontrar coincidencias y mapear a texto original
                const highlightRanges = [];
                
                wordsToHighlight.forEach(function(word) {
                    let searchPos = 0;
                    while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                        const endPos = searchPos + word.length;
                        
                        // Mapear posiciones normalizadas a originales
                        let origStart = -1;
                        let origEnd = -1;
                        let normPos = 0;
                        
                        // Encontrar inicio
                        for (let i = 0; i < originalText.length && origStart === -1; i++) {
                            const charNorm = window.normalize(originalText[i]);
                            if (normPos === searchPos) {
                                origStart = i;
                            }
                            normPos += charNorm.length;
                        }
                        
                        // Encontrar fin
                        if (origStart >= 0) {
                            normPos = searchPos;
                            for (let i = origStart; i < originalText.length; i++) {
                                const charNorm = window.normalize(originalText[i]);
                                normPos += charNorm.length;
                                if (normPos >= endPos) {
                                    origEnd = i + 1;
                                    break;
                                }
                            }
                            
                            if (origStart >= 0 && origEnd > origStart) {
                                highlightRanges.push({ start: origStart, end: origEnd });
                            }
                        }
                        
                        searchPos = endPos;
                    }
                });
                
                // Fusionar rangos solapados
                if (highlightRanges.length > 0) {
                    highlightRanges.sort((a, b) => a.start - b.start);
                    const mergedRanges = [highlightRanges[0]];
                    
                    for (let i = 1; i < highlightRanges.length; i++) {
                        const current = highlightRanges[i];
                        const last = mergedRanges[mergedRanges.length - 1];
                        
                        if (current.start <= last.end) {
                            last.end = Math.max(last.end, current.end);
                        } else {
                            mergedRanges.push(current);
                        }
                    }
                    
                    // Construir resultado con resaltados
                    const parts = [];
                    let lastIndex = 0;
                    
                    mergedRanges.forEach(function(range) {
                        if (range.start > lastIndex) {
                            parts.push(originalText.substring(lastIndex, range.start));
                        }
                        
                        const matchText = originalText.substring(range.start, range.end);
                        parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                   matchText + '</span>');
                        
                        lastIndex = range.end;
                    });
                    
                    if (lastIndex < originalText.length) {
                        parts.push(originalText.substring(lastIndex));
                    }
                    
                    const result = parts.join('');
                    const $result = jQuery('<span>').html(result);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
                
                // Si no hay coincidencias, retornar texto sin resaltar
                const $result = jQuery('<span>').text(originalText);
                if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                }
                return $result;
            }
        }).on('select2:open', function(e) {
            // Asegurar que el campo de búsqueda sea visible
            setTimeout(function() {
                const $dropdown = jQuery('.select2-dropdown');
                const $searchContainer = $dropdown.find('.select2-search--dropdown');
                const $searchField = $searchContainer.find('.select2-search__field');
                
                if ($searchContainer.length) {
                    $searchContainer.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                }
                
                if ($searchField.length) {
                    $searchField.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                    
                    setTimeout(function() {
                        $searchField.focus();
                    }, 100);
                }
            }, 50);
        });
    }
    
    // Abrir modal de editar destinos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('gofast-btn-editar-destinos') || e.target.closest('.gofast-btn-editar-destinos')) {
            const btn = e.target.classList.contains('gofast-btn-editar-destinos') ? e.target : e.target.closest('.gofast-btn-editar-destinos');
            const servicioId = btn.getAttribute('data-servicio-id');
            const destinosJson = btn.getAttribute('data-destinos');
            const cliente = btn.getAttribute('data-cliente') || '—';
            const fecha = btn.getAttribute('data-fecha') || '—';
            const origen = btn.getAttribute('data-origen') || '—';
            const total = btn.getAttribute('data-total') || '0';
            
            abrirModalEditar(servicioId, destinosJson, cliente, fecha, origen, total);
        }
    });
    
    function abrirModalEditar(servicioId, destinosJson, cliente, fecha, origen, total) {
        document.getElementById('editar-destinos-id').value = servicioId;
        
        // Mostrar información del servicio
        document.getElementById('info-servicio-id').textContent = '#' + servicioId;
        document.getElementById('info-cliente').textContent = cliente;
        document.getElementById('info-fecha').textContent = fecha;
        document.getElementById('info-origen').textContent = origen;
        document.getElementById('info-total').textContent = '$' + total;
        document.getElementById('info-origen-text').textContent = origen;
        
        const destinos = JSON.parse(destinosJson);
        const container = document.getElementById('destinos-container');
        container.innerHTML = '';
        
        // Actualizar contador de destinos
        const destinosCount = destinos.destinos && Array.isArray(destinos.destinos) ? destinos.destinos.length : 0;
        document.getElementById('info-destinos-count').textContent = destinosCount;
        
        if (destinos.destinos && Array.isArray(destinos.destinos)) {
            destinos.destinos.forEach(function(destino, index) {
                const div = document.createElement('div');
                div.style.cssText = 'margin-bottom:12px;padding:12px;border:1px solid #ddd;border-radius:6px;';
                div.innerHTML = `
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:end;">
                        <div>
                            <label style="display:block;margin-bottom:4px;font-weight:bold;font-size:12px;">Destino ${index + 1}</label>
                            <select name="destino_barrio_${index}" class="gofast-select-destino" required style="width:100%;padding:6px 8px;font-size:13px;">
                                <option value="">Seleccionar barrio...</option>
                                ${barrios.map(b => `<option value="${b.id}" ${destino.barrio_id == b.id ? 'selected' : ''}>${b.nombre}</option>`).join('')}
                            </select>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:4px;font-size:12px;">Dirección específica <span style="color:#999;font-weight:normal;">(direccion)</span></label>
                            <input type="text" name="destino_dir_${index}" value="${destino.direccion || ''}" placeholder="Ej: Calle 25 # 10-20" style="width:100%;padding:6px 8px;font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:4px;font-size:12px;">Monto adicional <span style="color:#999;font-weight:normal;">(monto a pagar)</span></label>
                            <input type="number" name="destino_monto_${index}" value="${destino.monto || 0}" min="0" placeholder="0" style="width:100%;padding:6px 8px;font-size:13px;">
                        </div>
                        <div>
                            <button type="button" class="gofast-btn-mini" onclick="eliminarDestino(this)" style="background:#dc3545;color:#fff;padding:6px 12px;font-size:12px;">Eliminar</button>
                        </div>
                    </div>
                    <input type="hidden" name="destino_barrio_nombre_${index}" class="destino-barrio-nombre">
                `;
                container.appendChild(div);
                
                // Inicializar Select2 en el select de barrio
                const select = div.querySelector('.gofast-select-destino');
                if (select) {
                    initSelect2Barrios(select);
                    
                    // Actualizar nombre del barrio cuando se selecciona
                    jQuery(select).on('change', function() {
                        const barrioId = this.value;
                        const barrio = barrios.find(b => b.id == barrioId);
                        const hiddenInput = div.querySelector('.destino-barrio-nombre');
                        if (hiddenInput) {
                            hiddenInput.value = barrio ? barrio.nombre : '';
                        }
                    });
                    
                    // Trigger inicial si hay valor
                    if (select.value) {
                        jQuery(select).trigger('change');
                    }
                }
            });
        }
        
        // Botón para agregar nuevo destino
        const btnAgregar = document.createElement('button');
        btnAgregar.type = 'button';
        btnAgregar.className = 'gofast-btn-mini';
        btnAgregar.textContent = '➕ Agregar Destino';
        btnAgregar.style.cssText = 'margin-top:12px;padding:8px 16px;font-size:13px;';
        btnAgregar.setAttribute('data-btn-agregar', 'true'); // Marcar para identificarlo fácilmente
        btnAgregar.onclick = function() {
            agregarDestino();
            // Actualizar contador
            actualizarContadorDestinos();
        };
        container.appendChild(btnAgregar);
        
        document.getElementById('modal-editar-destinos').style.display = 'block';
    }
    
    function actualizarContadorDestinos() {
        const container = document.getElementById('destinos-container');
        const count = container.querySelectorAll('.gofast-select-destino').length;
        document.getElementById('info-destinos-count').textContent = count;
    }
    
    function agregarDestino() {
        const container = document.getElementById('destinos-container');
        if (!container) return;
        
        const index = container.querySelectorAll('.gofast-select-destino').length;
        const div = document.createElement('div');
        div.style.cssText = 'margin-bottom:12px;padding:12px;border:1px solid #ddd;border-radius:6px;';
        div.innerHTML = `
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:end;">
                <div>
                    <label style="display:block;margin-bottom:4px;font-weight:bold;font-size:12px;">Destino ${index + 1}</label>
                    <select name="destino_barrio_${index}" class="gofast-select-destino" required style="width:100%;padding:6px 8px;font-size:13px;">
                        <option value="">Seleccionar barrio...</option>
                        ${barrios.map(b => `<option value="${b.id}">${b.nombre}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:12px;">Dirección específica <span style="color:#999;font-weight:normal;">(opcional)</span></label>
                    <input type="text" name="destino_dir_${index}" placeholder="Ej: Calle 25 # 10-20" style="width:100%;padding:6px 8px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:12px;">Monto adicional <span style="color:#999;font-weight:normal;">(extra)</span></label>
                    <input type="number" name="destino_monto_${index}" value="0" min="0" placeholder="0" style="width:100%;padding:6px 8px;font-size:13px;">
                </div>
                <div>
                    <button type="button" class="gofast-btn-mini" onclick="eliminarDestino(this)" style="background:#dc3545;color:#fff;padding:6px 12px;font-size:12px;">Eliminar</button>
                </div>
            </div>
            <input type="hidden" name="destino_barrio_nombre_${index}" class="destino-barrio-nombre">
        `;
        
        // Buscar el botón "Agregar Destino" específicamente usando el atributo data
        const btnAgregar = container.querySelector('button[data-btn-agregar="true"]');
        
        if (btnAgregar && btnAgregar.parentNode === container) {
            container.insertBefore(div, btnAgregar);
        } else {
            // Si no se encuentra el botón o no es hijo directo, simplemente agregar al final
            container.appendChild(div);
        }
        
        // Inicializar Select2 en el nuevo select
        const select = div.querySelector('.gofast-select-destino');
        if (select) {
            initSelect2Barrios(select);
            
            // Event listener para actualizar nombre
            jQuery(select).on('change', function() {
                const barrioId = this.value;
                const barrio = barrios.find(b => b.id == barrioId);
                const hiddenInput = div.querySelector('.destino-barrio-nombre');
                if (hiddenInput) {
                    hiddenInput.value = barrio ? barrio.nombre : '';
                }
                actualizarContadorDestinos();
            });
        }
        
        actualizarContadorDestinos();
    }
    
    window.eliminarDestino = function(btn) {
        if (confirm('¿Eliminar este destino?')) {
            // Buscar el contenedor padre del destino (el div con padding y border)
            const destinoDiv = btn.closest('div[style*="padding:12px"]') || btn.closest('div[style*="padding"]');
            if (destinoDiv) {
                destinoDiv.remove();
                actualizarContadorDestinos();
            } else {
                // Fallback: buscar cualquier div padre que contenga el select
                const select = btn.closest('div').querySelector('.gofast-select-destino');
                if (select) {
                    select.closest('div[style*="border"]')?.remove();
                    actualizarContadorDestinos();
                }
            }
        }
    };
    
    window.cerrarModalEditar = function() {
        document.getElementById('modal-editar-destinos').style.display = 'none';
    };
    
    // Cerrar modal al hacer clic fuera
    document.getElementById('modal-editar-destinos').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalEditar();
        }
    });
    
    // Procesar formulario antes de enviar
    document.getElementById('form-editar-destinos').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevenir envío normal para procesar datos primero
        
        const destinos = [];
        const container = document.getElementById('destinos-container');
        const selects = container.querySelectorAll('.gofast-select-destino');
        
        selects.forEach(function(select) {
            const barrioId = select.value;
            if (barrioId) {
                // Buscar el contenedor padre del destino (div con padding:12px)
                const parentDiv = select.closest('div[style*="padding:12px"]') || 
                                 select.closest('div[style*="padding"]') ||
                                 select.closest('div');
                
                if (parentDiv) {
                    // Obtener el nombre del barrio desde el input hidden
                    const barrioNombreInput = parentDiv.querySelector('.destino-barrio-nombre');
                    let barrioNombre = '';
                    if (barrioNombreInput) {
                        barrioNombre = barrioNombreInput.value;
                    } else {
                        // Si no existe, buscar en el select el texto seleccionado
                        const selectedOption = select.options[select.selectedIndex];
                        barrioNombre = selectedOption ? selectedOption.text : '';
                    }
                    
                    // Obtener el índice del nombre del campo
                    const nameAttr = select.getAttribute('name');
                    const indexMatch = nameAttr ? nameAttr.match(/\d+/) : null;
                    const index = indexMatch ? indexMatch[0] : selects.length;
                    
                    // Obtener dirección y monto
                    const direccionInput = parentDiv.querySelector(`input[name="destino_dir_${index}"]`);
                    const montoInput = parentDiv.querySelector(`input[name="destino_monto_${index}"]`);
                    
                    const direccion = direccionInput ? direccionInput.value.trim() : '';
                    const monto = montoInput ? (parseInt(montoInput.value) || 0) : 0;
                    
                    destinos.push({
                        barrio_id: parseInt(barrioId),
                        barrio_nombre: barrioNombre,
                        direccion: direccion,
                        monto: monto
                    });
                }
            }
        });
        
        if (destinos.length === 0) {
            alert('Debe haber al menos un destino.');
            return false;
        }
        
        // Guardar en el campo hidden
        const jsonData = JSON.stringify({ destinos: destinos });
        document.getElementById('destinos-editados-json').value = jsonData;
        
        // Debug (remover en producción)
        console.log('Destinos a guardar:', jsonData);
        console.log('ID del servicio:', document.getElementById('editar-destinos-id').value);
        
        // Enviar el formulario
        this.submit();
    });
    
    // Inicializar Select2 para filtros de barrios con buscador mejorado
    // Las funciones ya están disponibles globalmente (window.normalize y window.matcherDestinos)
    if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
        jQuery('.gofast-select-filtro').each(function() {
            // Evitar inicializar dos veces
            if (jQuery(this).data('select2')) {
                return;
            }
            
            jQuery(this).select2({
            placeholder: function() {
                return jQuery(this).data('placeholder') || '🔍 Escribe para buscar...';
            },
            width: '100%',
            allowClear: true,
            minimumResultsForSearch: 0,
            matcher: matcherDestinos,
            sorter: function(results) {
                return results.sort(function(a, b) {
                    return (b.matchScore || 0) - (a.matchScore || 0);
                });
            },
            templateResult: function(data, container) {
                if (!data || !data.text) {
                    return data ? data.text : '';
                }
                
                if (!data.id) return data.text;
                
                let originalText = data.text;
                
                // Obtener el término de búsqueda
                let searchTerm = "";
                const $activeField = jQuery('.select2-container--open .select2-search__field');
                if ($activeField.length) {
                    searchTerm = $activeField.val() || "";
                }
                
                if (!searchTerm || !searchTerm.trim()) {
                    const $result = jQuery('<span>').text(originalText);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
                
                // Normalizar para búsqueda
                const normalizedSearch = window.normalize(searchTerm);
                const normalizedText = window.normalize(originalText);
                
                // Dividir término en palabras significativas
                const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                    return word.length > 2 && !stopWords.includes(word);
                });
                
                const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                
                // Encontrar coincidencias y mapear a texto original
                const highlightRanges = [];
                
                wordsToHighlight.forEach(function(word) {
                    let searchPos = 0;
                    while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                        const endPos = searchPos + word.length;
                        
                        // Mapear posiciones normalizadas a originales
                        let origStart = -1;
                        let origEnd = -1;
                        let normPos = 0;
                        
                        // Encontrar inicio
                        for (let i = 0; i < originalText.length && origStart === -1; i++) {
                            const charNorm = window.normalize(originalText[i]);
                            if (normPos === searchPos) {
                                origStart = i;
                            }
                            normPos += charNorm.length;
                        }
                        
                        // Encontrar fin
                        if (origStart >= 0) {
                            normPos = searchPos;
                            for (let i = origStart; i < originalText.length; i++) {
                                const charNorm = window.normalize(originalText[i]);
                                normPos += charNorm.length;
                                if (normPos >= endPos) {
                                    origEnd = i + 1;
                                    break;
                                }
                            }
                            
                            if (origStart >= 0 && origEnd > origStart) {
                                highlightRanges.push({ start: origStart, end: origEnd });
                            }
                        }
                        
                        searchPos = endPos;
                    }
                });
                
                // Fusionar rangos solapados
                if (highlightRanges.length > 0) {
                    highlightRanges.sort((a, b) => a.start - b.start);
                    const mergedRanges = [highlightRanges[0]];
                    
                    for (let i = 1; i < highlightRanges.length; i++) {
                        const current = highlightRanges[i];
                        const last = mergedRanges[mergedRanges.length - 1];
                        
                        if (current.start <= last.end) {
                            last.end = Math.max(last.end, current.end);
                        } else {
                            mergedRanges.push(current);
                        }
                    }
                    
                    // Construir resultado con resaltados
                    const parts = [];
                    let lastIndex = 0;
                    
                    mergedRanges.forEach(function(range) {
                        if (range.start > lastIndex) {
                            parts.push(originalText.substring(lastIndex, range.start));
                        }
                        
                        const matchText = originalText.substring(range.start, range.end);
                        parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                   matchText + '</span>');
                        
                        lastIndex = range.end;
                    });
                    
                    if (lastIndex < originalText.length) {
                        parts.push(originalText.substring(lastIndex));
                    }
                    
                    const result = parts.join('');
                    const $result = jQuery('<span>').html(result);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
                
                // Si no hay coincidencias, retornar texto sin resaltar
                const $result = jQuery('<span>').text(originalText);
                if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                }
                return $result;
            }
        }).on('select2:open', function(e) {
            // Asegurar que el campo de búsqueda sea visible
            setTimeout(function() {
                const $dropdown = jQuery('.select2-dropdown');
                const $searchContainer = $dropdown.find('.select2-search--dropdown');
                const $searchField = $searchContainer.find('.select2-search__field');
                
                if ($searchContainer.length) {
                    $searchContainer.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                }
                
                if ($searchField.length) {
                    $searchField.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                    
                    setTimeout(function() {
                        $searchField.focus();
                    }, 100);
                }
            }, 50);
        });
        });
    } else {
        // Si las funciones no están disponibles, reintentar después de un breve delay
        setTimeout(function() {
            if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
                jQuery('.gofast-select-filtro').each(function() {
                    if (jQuery(this).data('select2')) {
                        return;
                    }
                    
                    jQuery(this).select2({
                        placeholder: function() {
                            return jQuery(this).data('placeholder') || '🔍 Escribe para buscar...';
                        },
                        width: '100%',
                        allowClear: true,
                        minimumResultsForSearch: 0,
                        matcher: window.matcherDestinos,
                        sorter: function(results) {
                            return results.sort(function(a, b) {
                                return (b.matchScore || 0) - (a.matchScore || 0);
                            });
                        },
                        templateResult: function(data, container) {
                            if (!data || !data.text) {
                                return data ? data.text : '';
                            }
                            
                            if (!data.id) return data.text;
                            
                            let originalText = data.text;
                            let searchTerm = "";
                            const $activeField = jQuery('.select2-container--open .select2-search__field');
                            if ($activeField.length) {
                                searchTerm = $activeField.val() || "";
                            }
                            
                            if (!searchTerm || !searchTerm.trim()) {
                                const $result = jQuery('<span>').text(originalText);
                                if (data.matchScore !== undefined) {
                                    $result.attr('data-match-score', data.matchScore);
                                }
                                return $result;
                            }
                            
                            const normalizedSearch = window.normalize(searchTerm);
                            const normalizedText = window.normalize(originalText);
                            const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                            const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                                return word.length > 2 && !stopWords.includes(word);
                            });
                            const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                            const highlightRanges = [];
                            
                            wordsToHighlight.forEach(function(word) {
                                let searchPos = 0;
                                while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                                    const endPos = searchPos + word.length;
                                    let origStart = -1;
                                    let origEnd = -1;
                                    let normPos = 0;
                                    
                                    for (let i = 0; i < originalText.length && origStart === -1; i++) {
                                        const charNorm = window.normalize(originalText[i]);
                                        if (normPos === searchPos) {
                                            origStart = i;
                                        }
                                        normPos += charNorm.length;
                                    }
                                    
                                    if (origStart >= 0) {
                                        normPos = searchPos;
                                        for (let i = origStart; i < originalText.length; i++) {
                                            const charNorm = window.normalize(originalText[i]);
                                            normPos += charNorm.length;
                                            if (normPos >= endPos) {
                                                origEnd = i + 1;
                                                break;
                                            }
                                        }
                                        
                                        if (origStart >= 0 && origEnd > origStart) {
                                            highlightRanges.push({ start: origStart, end: origEnd });
                                        }
                                    }
                                    
                                    searchPos = endPos;
                                }
                            });
                            
                            if (highlightRanges.length > 0) {
                                highlightRanges.sort((a, b) => a.start - b.start);
                                const mergedRanges = [highlightRanges[0]];
                                
                                for (let i = 1; i < highlightRanges.length; i++) {
                                    const current = highlightRanges[i];
                                    const last = mergedRanges[mergedRanges.length - 1];
                                    
                                    if (current.start <= last.end) {
                                        last.end = Math.max(last.end, current.end);
                                    } else {
                                        mergedRanges.push(current);
                                    }
                                }
                                
                                const parts = [];
                                let lastIndex = 0;
                                
                                mergedRanges.forEach(function(range) {
                                    if (range.start > lastIndex) {
                                        parts.push(originalText.substring(lastIndex, range.start));
                                    }
                                    
                                    const matchText = originalText.substring(range.start, range.end);
                                    parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                               matchText + '</span>');
                                    
                                    lastIndex = range.end;
                                });
                                
                                if (lastIndex < originalText.length) {
                                    parts.push(originalText.substring(lastIndex));
                                }
                                
                                const result = parts.join('');
                                const $result = jQuery('<span>').html(result);
                                if (data.matchScore !== undefined) {
                                    $result.attr('data-match-score', data.matchScore);
                                }
                                return $result;
                            }
                            
                            const $result = jQuery('<span>').text(originalText);
                            if (data.matchScore !== undefined) {
                                $result.attr('data-match-score', data.matchScore);
                            }
                            return $result;
                        }
                    }).on('select2:open', function(e) {
                        setTimeout(function() {
                            const $dropdown = jQuery('.select2-dropdown');
                            const $searchContainer = $dropdown.find('.select2-search--dropdown');
                            const $searchField = $searchContainer.find('.select2-search__field');
                            
                            if ($searchContainer.length) {
                                $searchContainer.css({
                                    'display': 'block',
                                    'visibility': 'visible',
                                    'opacity': '1'
                                });
                            }
                            
                            if ($searchField.length) {
                                $searchField.css({
                                    'display': 'block',
                                    'visibility': 'visible',
                                    'opacity': '1'
                                });
                                
                                setTimeout(function() {
                                    $searchField.focus();
                                }, 100);
                            }
                        }, 50);
                    });
                });
            }
        }, 500);
    }
})();
</script>
<?php endif; ?>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_pedidos', 'gofast_pedidos_shortcode');

