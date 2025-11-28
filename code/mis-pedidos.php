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
    if ($rol === 'admin') {
        $mensajeros = $wpdb->get_results("
            SELECT id, nombre 
            FROM usuarios_gofast
            WHERE rol = 'mensajero' AND activo = 1
            ORDER BY nombre ASC
        ");
    }

    /****************************************
     * 0. GESTIÓN DE CAMBIO DE ESTADO / MENSAJERO (POST)
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

            if ($rol === 'admin') {
                // Admin puede asignar cualquier mensajero
                if (isset($_POST['gofast_mensajero_id'])) {
                    $tmp_m = (int) $_POST['gofast_mensajero_id'];
                    $nuevo_mensajero_id = $tmp_m > 0 ? $tmp_m : null;
                }
            } elseif ($rol === 'mensajero') {
                // Mensajero se auto-asigna si el pedido no tiene mensajero y está tocando el estado
                if (empty($pedido->mensajero_id) && !empty($_POST['gofast_estado_nuevo'])) {
                    $nuevo_mensajero_id = $user_id;
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

                <div class="gofast-pedidos-filtros-actions">
                    <button type="submit" class="gofast-btn-mini">Filtrar</button>
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
                </div>
            </div>
        </form>

        <?php if ($total_registros === 0): ?>
            <p style="margin-top:20px;text-align:center;color:#666;">No se encontraron pedidos con los filtros seleccionados.</p>
        <?php else: ?>

            <div class="gofast-table-wrap" style="margin-top:20px;">
                <table class="gofast-table">
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

                        // Mensajero (solo texto por defecto)
                        $mensajero_nombre = '—';
                        if (!empty($p->mensajero_id)) {
                            $mensajero_nombre = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                    $p->mensajero_id
                                )
                            ) ?: '—';
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
                                    </form>
                                <?php else: ?>
                                    <?php echo esc_html($mensajero_nombre); ?>
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

<?php
    return ob_get_clean();
}
add_shortcode('gofast_pedidos', 'gofast_pedidos_shortcode');

