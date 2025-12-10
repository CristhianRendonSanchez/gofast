/***************************************************
 * GOFAST – REPORTES DE PEDIDOS (ADMIN Y MENSAJERO)
 * Shortcode: [gofast_reportes_admin]
 * URL: /admin-reportes
 ***************************************************/
function gofast_reportes_admin_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    $tabla = 'servicios_gofast';

    /* ==========================================================
       0. Validar usuario (admin o mensajero)
    ========================================================== */
    $usuario = null;
    if (!empty($_SESSION['gofast_user_id'])) {
        $uid = (int) $_SESSION['gofast_user_id'];
        $usuario = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, nombre, rol, activo 
                 FROM usuarios_gofast 
                 WHERE id = %d AND activo = 1",
                $uid
            )
        );
    }

    if (!$usuario || !in_array(strtolower($usuario->rol), ['admin', 'mensajero'])) {
        return "<div class='gofast-box'>
                    ⚠️ Solo los administradores y mensajeros pueden ver reportes.
                </div>";
    }

    $rol = strtolower($usuario->rol);
    $es_admin = ($rol === 'admin');

    /* ==========================================================
       1. Filtros (GET)
    ========================================================== */
    $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $desde = isset($_GET['desde']) ? sanitize_text_field($_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? sanitize_text_field($_GET['hasta']) : '';
    $mensajero_id = isset($_GET['mensajero_id']) ? (int) $_GET['mensajero_id'] : 0;
    $buscar = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $tipo_servicio = isset($_GET['tipo_servicio']) ? sanitize_text_field($_GET['tipo_servicio']) : 'todos';

    if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
    if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

    // Si no hay fechas, usar día actual por defecto (fecha Colombia)
    if (!$desde && !$hasta) {
        $desde = gofast_current_time('Y-m-d');
        $hasta = gofast_current_time('Y-m-d');
    }

    $where = "1=1";
    $params = [];

    // Si es mensajero, filtrar automáticamente por su ID
    if (!$es_admin) {
        $where .= " AND mensajero_id = %d";
        $params[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        // Solo admin puede filtrar por mensajero
        $where .= " AND mensajero_id = %d";
        $params[] = $mensajero_id;
    }

    if ($estado !== '' && $estado !== 'todos') {
        $where .= " AND tracking_estado = %s";
        $params[] = $estado;
    }

    if ($desde !== '') {
        $where .= " AND fecha >= %s";
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where .= " AND fecha <= %s";
        $params[] = $hasta . ' 23:59:59';
    }

    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where .= " AND (nombre_cliente LIKE %s OR telefono_cliente LIKE %s)";
        $params[] = $like;
        $params[] = $like;
    }

    // Filtro por tipo de servicio (normal o intermunicipal)
    if ($tipo_servicio === 'intermunicipal') {
        // Filtrar envíos intermunicipales: tienen "tipo_servicio": "intermunicipal" en JSON o "(Intermunicipal)" en direccion_origen
        $where .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') = %s OR direccion_origen LIKE %s)";
        $params[] = '"intermunicipal"';
        $params[] = '%(Intermunicipal)%';
    } elseif ($tipo_servicio === 'normal') {
        // Filtrar servicios normales: NO tienen tipo_servicio intermunicipal y NO tienen "(Intermunicipal)" en direccion_origen
        $where .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') IS NULL OR JSON_EXTRACT(destinos, '$.tipo_servicio') != %s) AND direccion_origen NOT LIKE %s";
        $params[] = '"intermunicipal"';
        $params[] = '%(Intermunicipal)%';
    }
    // Si es 'todos', no se aplica filtro

    /* ==========================================================
       2. Estadísticas de Servicios
    ========================================================== */
    // Construir WHERE para servicios (excluyendo cancelados para ingresos)
    $where_servicios = $where;
    $where_servicios_ingresos = $where . " AND tracking_estado != 'cancelado'";
    
    // Contar total de destinos (usando JSON_LENGTH)
    if (!empty($params)) {
        $sql_total_destinos = $wpdb->prepare(
            "SELECT 
                SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) as total_destinos
             FROM $tabla 
             WHERE $where_servicios",
            $params
        );
    } else {
        $sql_total_destinos = "SELECT 
                SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) as total_destinos
             FROM $tabla 
             WHERE $where_servicios";
    }
    
    $total_destinos = (int) ($wpdb->get_var($sql_total_destinos) ?? 0);
    
    // Ingresos de servicios (excluyendo cancelados)
    $params_ingresos = $params;
    if (!empty($params)) {
        $sql_ingresos_servicios = $wpdb->prepare(
            "SELECT 
                SUM(total) as total_ingresos_servicios
             FROM $tabla 
             WHERE $where_servicios_ingresos",
            $params_ingresos
        );
    } else {
        $sql_ingresos_servicios = "SELECT 
                SUM(total) as total_ingresos_servicios
             FROM $tabla 
             WHERE $where_servicios_ingresos";
    }
    
    $ingresos_servicios = (float) ($wpdb->get_var($sql_ingresos_servicios) ?? 0);
    
    // Contar pedidos sin asignar (sin mensajero)
    // Construir WHERE sin el filtro de mensajero, pero con los demás filtros
    $where_sin_asignar = "1=1";
    $params_sin_asignar = [];
    
    // NO incluir filtro de mensajero para pedidos sin asignar
    // Solo aplicar filtros de estado, fecha y búsqueda
    
    if ($estado !== '' && $estado !== 'todos') {
        $where_sin_asignar .= " AND tracking_estado = %s";
        $params_sin_asignar[] = $estado;
    }
    
    if ($desde !== '') {
        $where_sin_asignar .= " AND fecha >= %s";
        $params_sin_asignar[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where_sin_asignar .= " AND fecha <= %s";
        $params_sin_asignar[] = $hasta . ' 23:59:59';
    }
    
    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where_sin_asignar .= " AND (nombre_cliente LIKE %s OR telefono_cliente LIKE %s)";
        $params_sin_asignar[] = $like;
        $params_sin_asignar[] = $like;
    }

    // Aplicar filtro de tipo de servicio también a pedidos sin asignar
    if ($tipo_servicio === 'intermunicipal') {
        $where_sin_asignar .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') = %s OR direccion_origen LIKE %s)";
        $params_sin_asignar[] = '"intermunicipal"';
        $params_sin_asignar[] = '%(Intermunicipal)%';
    } elseif ($tipo_servicio === 'normal') {
        $where_sin_asignar .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') IS NULL OR JSON_EXTRACT(destinos, '$.tipo_servicio') != %s) AND direccion_origen NOT LIKE %s";
        $params_sin_asignar[] = '"intermunicipal"';
        $params_sin_asignar[] = '%(Intermunicipal)%';
    }
    
    // Condición principal: sin mensajero asignado
    $where_sin_asignar .= " AND mensajero_id IS NULL";
    
    if (!empty($params_sin_asignar)) {
        $sql_sin_asignar = $wpdb->prepare(
            "SELECT COUNT(*) as pedidos_sin_asignar
             FROM $tabla 
             WHERE $where_sin_asignar",
            $params_sin_asignar
        );
    } else {
        $sql_sin_asignar = "SELECT COUNT(*) as pedidos_sin_asignar
             FROM $tabla 
             WHERE $where_sin_asignar";
    }
    
    $pedidos_sin_asignar = (int) ($wpdb->get_var($sql_sin_asignar) ?? 0);
    
    /* ==========================================================
       3. Estadísticas de Compras
    ========================================================== */
    $tabla_compras = 'compras_gofast';
    $where_compras = "1=1";
    $params_compras = [];
    
    // Aplicar filtros de mensajero
    if (!$es_admin) {
        $where_compras .= " AND mensajero_id = %d";
        $params_compras[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_compras .= " AND mensajero_id = %d";
        $params_compras[] = $mensajero_id;
    }
    
    // Aplicar filtros de fecha
    if ($desde !== '') {
        $where_compras .= " AND fecha_creacion >= %s";
        $params_compras[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where_compras .= " AND fecha_creacion <= %s";
        $params_compras[] = $hasta . ' 23:59:59';
    }
    
    // Excluir canceladas
    $where_compras .= " AND estado != 'cancelada'";
    
    // Contar total de compras
    if (!empty($params_compras)) {
        $sql_total_compras = $wpdb->prepare(
            "SELECT COUNT(*) as total_compras
             FROM $tabla_compras 
             WHERE $where_compras",
            $params_compras
        );
    } else {
        $sql_total_compras = "SELECT COUNT(*) as total_compras
             FROM $tabla_compras 
             WHERE $where_compras";
    }
    
    $total_compras = (int) ($wpdb->get_var($sql_total_compras) ?? 0);
    
    // Ingresos de compras (excluyendo canceladas)
    if (!empty($params_compras)) {
        $sql_ingresos_compras = $wpdb->prepare(
            "SELECT SUM(valor) as total_ingresos_compras
             FROM $tabla_compras 
             WHERE $where_compras",
            $params_compras
        );
    } else {
        $sql_ingresos_compras = "SELECT SUM(valor) as total_ingresos_compras
             FROM $tabla_compras 
             WHERE $where_compras";
    }
    
    $ingresos_compras = (float) ($wpdb->get_var($sql_ingresos_compras) ?? 0);
    
    // Ingresos totales (servicios + compras, excluyendo cancelados)
    $total_ingresos = $ingresos_servicios + $ingresos_compras;
    
    /* ==========================================================
       4. Cálculos de Comisión y Utilidad
    ========================================================== */
    // Comisión generada: 20% de los ingresos totales
    $comision_generada = $total_ingresos * 0.20;
    
    // Utilidad neta: ingresos totales - comisión
    $utilidad_neta = $total_ingresos - $comision_generada;
    
    /* ==========================================================
       5. Estadísticas de Transferencias
    ========================================================== */
    $tabla_transferencias = 'transferencias_gofast';
    $where_transferencias = "1=1";
    $params_transferencias = [];
    
    // Aplicar filtros de mensajero
    if (!$es_admin) {
        $where_transferencias .= " AND mensajero_id = %d";
        $params_transferencias[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_transferencias .= " AND mensajero_id = %d";
        $params_transferencias[] = $mensajero_id;
    }
    
    // Aplicar filtros de fecha
    if ($desde !== '') {
        $where_transferencias .= " AND fecha_creacion >= %s";
        $params_transferencias[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where_transferencias .= " AND fecha_creacion <= %s";
        $params_transferencias[] = $hasta . ' 23:59:59';
    }
    
    // Solo transferencias aprobadas
    $where_transferencias .= " AND estado = 'aprobada'";
    
    // Sumar valor de transferencias aprobadas
    if (!empty($params_transferencias)) {
        $sql_transferencias_aprobadas = $wpdb->prepare(
            "SELECT SUM(valor) as total_transferencias_aprobadas
             FROM $tabla_transferencias 
             WHERE $where_transferencias",
            $params_transferencias
        );
    } else {
        $sql_transferencias_aprobadas = "SELECT SUM(valor) as total_transferencias_aprobadas
             FROM $tabla_transferencias 
             WHERE $where_transferencias";
    }
    
    $transferencias_aprobadas = (float) ($wpdb->get_var($sql_transferencias_aprobadas) ?? 0);
    
    // Total a pagar: comisión - transferencias aprobadas
    $total_a_pagar = $comision_generada - $transferencias_aprobadas;

    /* ==========================================================
       6. Pedidos del Día Actual
    ========================================================== */
    $fecha_hoy = gofast_current_time('Y-m-d');
    $where_pedidos_hoy = "1=1";
    $params_pedidos_hoy = [];
    
    // Aplicar filtros de mensajero
    if (!$es_admin) {
        $where_pedidos_hoy .= " AND mensajero_id = %d";
        $params_pedidos_hoy[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_pedidos_hoy .= " AND mensajero_id = %d";
        $params_pedidos_hoy[] = $mensajero_id;
    }
    
    // Solo pedidos del día actual
    $where_pedidos_hoy .= " AND DATE(fecha) = %s";
    $params_pedidos_hoy[] = $fecha_hoy;
    
    // Contar total de pedidos del día
    if (!empty($params_pedidos_hoy)) {
        $sql_count_hoy = $wpdb->prepare(
            "SELECT COUNT(*) as total
             FROM $tabla
             WHERE $where_pedidos_hoy",
            $params_pedidos_hoy
        );
    } else {
        $sql_count_hoy = "SELECT COUNT(*) as total
             FROM $tabla
             WHERE $where_pedidos_hoy";
    }
    
    $total_pedidos_hoy = (int) ($wpdb->get_var($sql_count_hoy) ?? 0);
    $limite_pedidos_hoy = 500; // Límite de registros a mostrar
    
    // Obtener pedidos del día actual (con límite)
    $params_pedidos_hoy_limit = $params_pedidos_hoy;
    $params_pedidos_hoy_limit[] = $limite_pedidos_hoy;
    
    if (!empty($params_pedidos_hoy)) {
        $sql_pedidos_hoy = $wpdb->prepare(
            "SELECT 
                id,
                fecha,
                direccion_origen,
                destinos,
                total,
                mensajero_id
             FROM $tabla
             WHERE $where_pedidos_hoy
             ORDER BY fecha DESC
             LIMIT %d",
            $params_pedidos_hoy_limit
        );
    } else {
        $sql_pedidos_hoy = $wpdb->prepare(
            "SELECT 
                id,
                fecha,
                direccion_origen,
                destinos,
                total,
                mensajero_id
             FROM $tabla
             WHERE $where_pedidos_hoy
             ORDER BY fecha DESC
             LIMIT %d",
            $limite_pedidos_hoy
        );
    }
    
    $pedidos_hoy = $wpdb->get_results($sql_pedidos_hoy);

    // Top mensajeros (solo para admin)
    $top_mensajeros = [];
    if ($es_admin) {
        $top_mensajeros = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    u.id,
                    u.nombre,
                    COUNT(s.id) as total_entregados,
                    SUM(s.total) as total_ingresos
                 FROM $tabla s
                 INNER JOIN usuarios_gofast u ON s.mensajero_id = u.id
                 WHERE $where AND s.tracking_estado = 'entregado'
                 GROUP BY u.id, u.nombre
                 ORDER BY total_entregados DESC
                 LIMIT 10",
                $params
            )
        );
    }

    // Pedidos por día (últimos 30 días) - respeta filtros aplicados (zona horaria Colombia)
    $timezone = new DateTimeZone('America/Bogota');
    $datetime = new DateTime('now', $timezone);
    $datetime->modify('-30 days');
    $fecha_desde_30dias = $datetime->format('Y-m-d');
    $fecha_hasta_hoy = gofast_current_time('Y-m-d');
    
    // Construir WHERE para pedidos por día respetando filtros
    $where_pedidos_dia = "1=1";
    $params_pedidos_dia = [];
    
    if (!$es_admin) {
        $where_pedidos_dia .= " AND mensajero_id = %d";
        $params_pedidos_dia[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_pedidos_dia .= " AND mensajero_id = %d";
        $params_pedidos_dia[] = $mensajero_id;
    }
    
    $where_pedidos_dia .= " AND fecha >= %s AND fecha <= %s";
    $params_pedidos_dia[] = $fecha_desde_30dias . ' 00:00:00';
    $params_pedidos_dia[] = $fecha_hasta_hoy . ' 23:59:59';
    
    // Construir WHERE para compras por día
    $where_compras_dia = "1=1";
    $params_compras_dia = [];
    
    if (!$es_admin) {
        $where_compras_dia .= " AND mensajero_id = %d";
        $params_compras_dia[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_compras_dia .= " AND mensajero_id = %d";
        $params_compras_dia[] = $mensajero_id;
    }
    
    $where_compras_dia .= " AND fecha_creacion >= %s AND fecha_creacion <= %s AND estado != 'cancelada'";
    $params_compras_dia[] = $fecha_desde_30dias . ' 00:00:00';
    $params_compras_dia[] = $fecha_hasta_hoy . ' 23:59:59';
    
    if (!empty($params_pedidos_dia)) {
        // Consulta de servicios con destinos y ingresos
        $pedidos_por_dia = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(fecha) as dia,
                    SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) as cantidad_destinos,
                    SUM(CASE WHEN tracking_estado != 'cancelado' THEN total ELSE 0 END) as ingresos
                 FROM $tabla
                 WHERE $where_pedidos_dia AND tracking_estado != 'cancelado'
                 GROUP BY DATE(fecha)
                 ORDER BY dia DESC
                 LIMIT 30",
                $params_pedidos_dia
            )
        );
        
        // Consulta de compras por día (cantidad e ingresos)
        if (!empty($params_compras_dia)) {
            $compras_por_dia = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        DATE(fecha_creacion) as dia,
                        COUNT(*) as cantidad_compras,
                        SUM(valor) as ingresos_compras
                     FROM $tabla_compras
                     WHERE $where_compras_dia
                     GROUP BY DATE(fecha_creacion)
                     ORDER BY dia DESC
                     LIMIT 30",
                    $params_compras_dia
                )
            );
        } else {
            $compras_por_dia = [];
        }
        
        // Combinar datos de servicios y compras por día
        $pedidos_por_dia_completo = [];
        
        // Agregar servicios
        foreach ($pedidos_por_dia as $servicio) {
            $dia_key = $servicio->dia;
            $pedidos_por_dia_completo[$dia_key] = [
                'dia' => $dia_key,
                'cantidad_destinos' => (int) ($servicio->cantidad_destinos ?? 0),
                'cantidad_compras' => 0,
                'ingresos' => (float) ($servicio->ingresos ?? 0),
                'comision' => 0
            ];
        }
        
        // Agregar compras y sumar ingresos
        foreach ($compras_por_dia as $compra) {
            $dia_key = $compra->dia;
            if (!isset($pedidos_por_dia_completo[$dia_key])) {
                $pedidos_por_dia_completo[$dia_key] = [
                    'dia' => $dia_key,
                    'cantidad_destinos' => 0,
                    'cantidad_compras' => 0,
                    'ingresos' => 0,
                    'comision' => 0
                ];
            }
            $pedidos_por_dia_completo[$dia_key]['cantidad_compras'] = (int) $compra->cantidad_compras;
            $pedidos_por_dia_completo[$dia_key]['ingresos'] += (float) ($compra->ingresos_compras ?? 0);
        }
        
        // Calcular comisión (20% de ingresos totales)
        foreach ($pedidos_por_dia_completo as $key => $dia_data) {
            $pedidos_por_dia_completo[$key]['comision'] = $dia_data['ingresos'] * 0.20;
        }
        
        // Convertir a array indexado y ordenar
        $pedidos_por_dia = array_values($pedidos_por_dia_completo);
    } else {
        $pedidos_por_dia = [];
    }

    /* ==========================================================
       3. Lista de mensajeros para filtro (solo admin)
    ========================================================== */
    $mensajeros = [];
    if ($es_admin) {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre 
             FROM usuarios_gofast
             WHERE rol = 'mensajero' AND activo = 1
             ORDER BY nombre ASC
            "
        );
    }

    /* ==========================================================
       4. Exportar a CSV
    ========================================================== */
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_pedidos_' . gofast_date_today() . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, ['ID', 'Fecha', 'Cliente', 'Teléfono', 'Origen', 'Total', 'Estado', 'Mensajero']);
        
        // Datos
        $params_export = $params;
        $params_export[] = 10000; // Límite alto para exportación
        
        $sql_export = $wpdb->prepare(
            "SELECT s.*, u.nombre as mensajero_nombre
             FROM $tabla s
             LEFT JOIN usuarios_gofast u ON s.mensajero_id = u.id
             WHERE $where
             ORDER BY s.fecha DESC
             LIMIT %d",
            $params_export
        );
        
        $pedidos_export = $wpdb->get_results($sql_export);
        
        foreach ($pedidos_export as $p) {
            fputcsv($output, [
                $p->id,
                $p->fecha,
                $p->nombre_cliente,
                $p->telefono_cliente,
                $p->direccion_origen,
                $p->total,
                $p->tracking_estado,
                $p->mensajero_nombre ?: 'Sin asignar'
            ]);
        }
        
        fclose($output);
        exit;
    }

    /* ==========================================================
       5. HTML
    ========================================================== */
    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h1 style="margin-bottom:8px;">📊 Reportes y Estadísticas</h1>
            <p class="gofast-home-text">
                <?php if ($es_admin): ?>
                    Analiza el rendimiento de los pedidos y genera reportes detallados.
                <?php else: ?>
                    Visualiza tus pedidos y estadísticas de rendimiento.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($es_admin): ?>
            <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;">
                ← Volver al Dashboard
            </a>
        <?php else: ?>
            <a href="<?php echo esc_url( home_url('/') ); ?>" class="gofast-btn-request" style="text-decoration:none;">
                ← Volver al Inicio
            </a>
        <?php endif; ?>
    </div>

    <!-- =====================================================
         A) FILTROS
    ====================================================== -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <form method="get" class="gofast-pedidos-filtros">
            <div class="gofast-pedidos-filtros-row">
                <div>
                    <label>Estado</label>
                    <select name="estado">
                        <option value="todos"<?php selected($estado, 'todos'); ?>>Todos</option>
                        <option value="pendiente"<?php selected($estado, 'pendiente'); ?>>Pendiente</option>
                        <option value="asignado"<?php selected($estado, 'asignado'); ?>>Asignado</option>
                        <option value="en_ruta"<?php selected($estado, 'en_ruta'); ?>>En Ruta</option>
                        <option value="entregado"<?php selected($estado, 'entregado'); ?>>Entregado</option>
                        <option value="cancelado"<?php selected($estado, 'cancelado'); ?>>Cancelado</option>
                    </select>
                </div>

                <div>
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?php echo esc_attr($desde); ?>">
                </div>

                <div>
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?php echo esc_attr($hasta); ?>">
                </div>

                <?php if ($es_admin): ?>
                    <div>
                        <label>Mensajero</label>
                        <select name="mensajero_id">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($mensajero_id, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <label>Tipo de Servicio</label>
                    <select name="tipo_servicio">
                        <option value="todos"<?php selected($tipo_servicio, 'todos'); ?>>Todos</option>
                        <option value="normal"<?php selected($tipo_servicio, 'normal'); ?>>Normal</option>
                        <option value="intermunicipal"<?php selected($tipo_servicio, 'intermunicipal'); ?>>Intermunicipal</option>
                    </select>
                </div>

                <div>
                    <label>Buscar</label>
                    <input type="text" name="q" placeholder="Cliente o teléfono" value="<?php echo esc_attr($buscar); ?>">
                </div>

                <div class="gofast-pedidos-filtros-actions">
                    <button type="submit" class="gofast-btn-mini">Filtrar</button>
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
                    <a href="<?php echo esc_url( add_query_arg(['export' => 'csv'], get_permalink()) ); ?>" class="gofast-btn-mini" style="background:#4CAF50;color:#fff;">
                        📥 Exportar CSV
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- =====================================================
         B) ESTADÍSTICAS PRINCIPALES
    ====================================================== -->
    <div class="gofast-dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin:24px 0;">
        
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📍</div>
            <div style="font-size:28px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($total_destinos); ?></div>
            <div style="font-size:13px;color:#666;">Total Destinos</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🛒</div>
            <div style="font-size:28px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($total_compras); ?></div>
            <div style="font-size:13px;color:#666;">Total Compras</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💰</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;">$<?= number_format($total_ingresos, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Ingresos Totales</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💵</div>
            <div style="font-size:28px;font-weight:700;color:#9C27B0;margin-bottom:4px;">$<?= number_format($comision_generada, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Comisión Generada (20%)</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📈</div>
            <div style="font-size:28px;font-weight:700;color:#00BCD4;margin-bottom:4px;">$<?= number_format($utilidad_neta, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Utilidad Neta</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📋</div>
            <div style="font-size:28px;font-weight:700;color:#FF5722;margin-bottom:4px;"><?= number_format($pedidos_sin_asignar); ?></div>
            <div style="font-size:13px;color:#666;">Pedidos sin Asignar</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💸</div>
            <div style="font-size:28px;font-weight:700;color:#FF9800;margin-bottom:4px;">$<?= number_format($transferencias_aprobadas, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Transferencias Aprobadas</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💳</div>
            <div style="font-size:28px;font-weight:700;color:<?= $total_a_pagar >= 0 ? '#4CAF50' : '#f44336'; ?>;margin-bottom:4px;">$<?= number_format($total_a_pagar, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Total a Pagar</div>
        </div>

    </div>

    <!-- =====================================================
         B2) PEDIDOS DEL DÍA ACTUAL
    ====================================================== -->
    <?php if (!empty($pedidos_hoy) || $total_pedidos_hoy > 0): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">
                📅 Pedidos del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)
                <?php if ($total_pedidos_hoy > $limite_pedidos_hoy): ?>
                    <span style="font-size:14px;color:#ff9800;font-weight:normal;">
                        (Mostrando <?= number_format($limite_pedidos_hoy); ?> de <?= number_format($total_pedidos_hoy); ?>)
                    </span>
                <?php else: ?>
                    <span style="font-size:14px;color:#666;font-weight:normal;">
                        (<?= number_format($total_pedidos_hoy); ?> registros)
                    </span>
                <?php endif; ?>
            </h3>
            <div style="margin-bottom:10px;padding:8px;background:#f0f7ff;border-left:3px solid #2196F3;border-radius:4px;font-size:12px;color:#1976D2;">
                💡 <strong>En móvil:</strong> Desliza horizontalmente para ver todas las columnas
            </div>
            <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                <table class="gofast-table" style="min-width:800px;width:100%;">
                    <thead>
                        <tr>
                            <th># Servicio</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <?php if ($es_admin): ?>
                                <th>Mensajero</th>
                            <?php endif; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_hoy as $pedido): 
                            $json_destinos = json_decode($pedido->destinos, true);
                            $destinos_array = $json_destinos['destinos'] ?? [];
                            $primer_destino = !empty($destinos_array) ? $destinos_array[0] : null;
                            $destino_texto = '';
                            if ($primer_destino) {
                                $destino_texto = !empty($primer_destino['barrio_nombre']) 
                                    ? $primer_destino['barrio_nombre'] 
                                    : (!empty($primer_destino['direccion']) ? $primer_destino['direccion'] : 'N/A');
                                if (count($destinos_array) > 1) {
                                    $destino_texto .= ' +' . (count($destinos_array) - 1) . ' más';
                                }
                            }
                            
                            // Obtener nombre del mensajero si existe
                            $mensajero_nombre = '';
                            if ($pedido->mensajero_id) {
                                $mensajero = $wpdb->get_row($wpdb->prepare(
                                    "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                    $pedido->mensajero_id
                                ));
                                $mensajero_nombre = $mensajero ? $mensajero->nombre : 'N/A';
                            }
                        ?>
                            <tr>
                                <td>#<?= (int) $pedido->id; ?></td>
                                <td><?= esc_html( gofast_date_format($pedido->fecha, 'H:i') ); ?></td>
                                <td><?= esc_html($pedido->direccion_origen); ?></td>
                                <td><?= esc_html($destino_texto ?: 'N/A'); ?></td>
                                <?php if ($es_admin): ?>
                                    <td><?= esc_html($mensajero_nombre ?: 'Sin asignar'); ?></td>
                                <?php endif; ?>
                                <td>$<?= number_format($pedido->total, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">📅 Pedidos del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)</h3>
            <p>No hay pedidos registrados para el día de hoy.</p>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         C) TOP MENSAJEROS (Solo Admin)
    ====================================================== -->
    <?php if ($es_admin && !empty($top_mensajeros)): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">🏆 Top Mensajeros</h3>
            <div style="margin-bottom:10px;padding:8px;background:#f0f7ff;border-left:3px solid #2196F3;border-radius:4px;font-size:12px;color:#1976D2;">
                💡 <strong>En móvil:</strong> Desliza horizontalmente para ver todas las columnas
            </div>
            <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                <table class="gofast-table" style="min-width:600px;width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Mensajero</th>
                            <th>Pedidos Entregados</th>
                            <th>Total Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_mensajeros as $idx => $m): ?>
                            <tr>
                                <td><?= $idx + 1; ?></td>
                                <td><?= esc_html($m->nombre); ?></td>
                                <td><?= number_format($m->total_entregados); ?></td>
                                <td>$<?= number_format($m->total_ingresos, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         D) PEDIDOS POR DÍA (ÚLTIMOS 30 DÍAS)
    ====================================================== -->
    <?php if (!empty($pedidos_por_dia)): ?>
        <div class="gofast-box">
            <h3 style="margin-top:0;">📈 Pedidos por Día (Últimos 30 días)</h3>
            <div style="margin-bottom:10px;padding:8px;background:#f0f7ff;border-left:3px solid #2196F3;border-radius:4px;font-size:12px;color:#1976D2;">
                💡 <strong>En móvil:</strong> Desliza horizontalmente para ver todas las columnas
            </div>
            <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                <table class="gofast-table" style="min-width:700px;width:100%;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cantidad de Destinos</th>
                            <th>Cantidad de Compras</th>
                            <th>Ingresos</th>
                            <th>Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_por_dia as $dia): ?>
                            <tr>
                                <td><?= esc_html( gofast_date_format($dia['dia'], 'd/m/Y') ); ?></td>
                                <td><?= number_format($dia['cantidad_destinos']); ?></td>
                                <td><?= number_format($dia['cantidad_compras']); ?></td>
                                <td>$<?= number_format($dia['ingresos'], 0, ',', '.'); ?></td>
                                <td>$<?= number_format($dia['comision'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_reportes_admin', 'gofast_reportes_admin_shortcode');
?>

<style>
/* Responsive para móvil - tablas de reportes */
@media (max-width: 768px) {
    
    /* Asegurar que las tablas sean visibles y scrollables en móvil */
    .gofast-table-wrap {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: thin !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        visibility: visible !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative !important;
    }
    
    .gofast-table-wrap::-webkit-scrollbar {
        height: 12px !important;
    }
    
    .gofast-table-wrap::-webkit-scrollbar-track {
        background: #e0e0e0 !important;
        border-radius: 6px !important;
    }
    
    .gofast-table-wrap::-webkit-scrollbar-thumb {
        background: #2196F3 !important;
        border-radius: 6px !important;
        border: 2px solid #e0e0e0 !important;
    }
    
    .gofast-table-wrap::-webkit-scrollbar-thumb:hover {
        background: #1976D2 !important;
    }
    
    .gofast-table {
        font-size: 13px !important;
        display: table !important;
        visibility: visible !important;
        width: 100% !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
        border-spacing: 0 !important;
    }
    
    .gofast-table th,
    .gofast-table td {
        padding: 12px 10px !important;
        font-size: 13px !important;
        white-space: nowrap !important;
        display: table-cell !important;
        visibility: visible !important;
        text-align: left !important;
        border-bottom: 1px solid #e0e0e0 !important;
        vertical-align: middle !important;
    }
    
    .gofast-table th {
        background: #f5f5f5 !important;
        font-weight: 600 !important;
        color: #333 !important;
        border-bottom: 2px solid #ddd !important;
    }
    
    .gofast-table tbody tr {
        background: #fff !important;
    }
    
    .gofast-table tbody tr:hover {
        background: #f9f9f9 !important;
    }
    
    .gofast-table tbody tr:last-child td {
        border-bottom: none !important;
    }
    
    /* Mejorar visibilidad del scroll en móvil */
    .gofast-box .gofast-table-wrap {
        border: 1px solid #e0e0e0 !important;
        border-radius: 8px !important;
        background: #fff !important;
        position: relative !important;
    }
    
    
    /* Asegurar que las tablas tengan el ancho mínimo correcto */
    .gofast-table[style*="min-width:800px"] {
        min-width: 800px !important;
    }
    
    .gofast-table[style*="min-width:700px"] {
        min-width: 700px !important;
    }
    
    .gofast-table[style*="min-width:600px"] {
        min-width: 600px !important;
    }
    
    /* Ajustar tarjetas de estadísticas en móvil - scroll horizontal */
    .gofast-dashboard-stats {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) !important;
        gap: 12px !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        padding-bottom: 8px !important;
    }
    
    .gofast-dashboard-stats::-webkit-scrollbar {
        height: 6px;
    }
    
    .gofast-dashboard-stats::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .gofast-dashboard-stats::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .gofast-dashboard-stats .gofast-box {
        min-width: 160px !important;
        flex-shrink: 0 !important;
    }
    
    .gofast-dashboard-stats .gofast-box {
        padding: 16px !important;
    }
    
    .gofast-dashboard-stats .gofast-box > div:first-child {
        font-size: 24px !important;
    }
    
    .gofast-dashboard-stats .gofast-box > div:nth-child(2) {
        font-size: 22px !important;
    }
    
    /* Ajustar filtros en móvil */
    .gofast-pedidos-filtros-row {
        flex-direction: column !important;
        gap: 12px !important;
    }
    
    .gofast-pedidos-filtros-row > div {
        width: 100% !important;
    }
    
    .gofast-pedidos-filtros-actions {
        flex-direction: column !important;
        width: 100% !important;
    }
    
    .gofast-pedidos-filtros-actions button,
    .gofast-pedidos-filtros-actions a {
        width: 100% !important;
        text-align: center !important;
    }
    
    /* Ajustar encabezado en móvil */
    .gofast-home > div:first-child {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
    .gofast-home > div:first-child > div:first-child h1 {
        font-size: 24px !important;
    }
    
    .gofast-home > div:first-child > a {
        width: 100% !important;
        text-align: center !important;
    }
}
</style>

