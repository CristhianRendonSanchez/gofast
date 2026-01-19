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
    $negocio_id = isset($_GET['negocio_id']) ? (int) $_GET['negocio_id'] : 0;
    $buscar = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $tipo_servicio = isset($_GET['tipo_servicio']) ? sanitize_text_field($_GET['tipo_servicio']) : 'todos';
    
    // Paginación para tablas
    $por_pagina = 15;
    $pg_pedidos = isset($_GET['pg_pedidos']) ? max(1, (int) $_GET['pg_pedidos']) : 1;
    $pg_mensajeros = isset($_GET['pg_mensajeros']) ? max(1, (int) $_GET['pg_mensajeros']) : 1;
    $pg_dias = isset($_GET['pg_dias']) ? max(1, (int) $_GET['pg_dias']) : 1;

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

    // Filtro por negocio (solo admin)
    if ($es_admin && $negocio_id > 0) {
        $where .= " AND JSON_EXTRACT(destinos, '$.origen.negocio_id') = %d";
        $params[] = $negocio_id;
    }

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
    
    // Aplicar filtro de negocio también a pedidos sin asignar (solo admin)
    if ($es_admin && $negocio_id > 0) {
        $where_sin_asignar .= " AND JSON_EXTRACT(destinos, '$.origen.negocio_id') = %d";
        $params_sin_asignar[] = $negocio_id;
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
    // Si se filtra por negocio, NO incluir compras (los negocios no tienen compras)
    $total_compras = 0;
    $ingresos_compras = 0;
    
    if (!($es_admin && $negocio_id > 0)) {
        // Solo calcular compras si NO se está filtrando por negocio
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
    }
    
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
    
    // Solo transferencias aprobadas y de tipo normal (excluir tipo pago)
    $where_transferencias .= " AND estado = 'aprobada' AND (tipo = 'normal' OR tipo IS NULL)";
    
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
       5.1. Estadísticas de Pagos Registrados
    ========================================================== */
    $tabla_pagos = 'pagos_mensajeros_gofast';
    $where_pagos = "1=1";
    $params_pagos = [];
    
    // Aplicar filtros de mensajero
    if (!$es_admin) {
        $where_pagos .= " AND mensajero_id = %d";
        $params_pagos[] = $usuario->id;
    } elseif ($mensajero_id > 0) {
        $where_pagos .= " AND mensajero_id = %d";
        $params_pagos[] = $mensajero_id;
    }
    
    // Aplicar filtros de fecha
    if ($desde !== '') {
        $where_pagos .= " AND fecha >= %s";
        $params_pagos[] = $desde;
    }
    if ($hasta !== '') {
        $where_pagos .= " AND fecha <= %s";
        $params_pagos[] = $hasta;
    }
    
    // Pagos en efectivo
    $where_pagos_efectivo = $where_pagos . " AND tipo_pago = 'efectivo'";
    if (!empty($params_pagos)) {
        $sql_pagos_efectivo = $wpdb->prepare(
            "SELECT SUM(total_a_pagar) as total_pagos_efectivo
             FROM $tabla_pagos 
             WHERE $where_pagos_efectivo",
            $params_pagos
        );
    } else {
        $sql_pagos_efectivo = "SELECT SUM(total_a_pagar) as total_pagos_efectivo
             FROM $tabla_pagos 
             WHERE $where_pagos_efectivo";
    }
    $pagos_efectivo = (float) ($wpdb->get_var($sql_pagos_efectivo) ?? 0);
    
    // Pagos por transferencia
    $where_pagos_transferencia = $where_pagos . " AND tipo_pago = 'transferencia'";
    if (!empty($params_pagos)) {
        $sql_pagos_transferencia = $wpdb->prepare(
            "SELECT SUM(total_a_pagar) as total_pagos_transferencia
             FROM $tabla_pagos 
             WHERE $where_pagos_transferencia",
            $params_pagos
        );
    } else {
        $sql_pagos_transferencia = "SELECT SUM(total_a_pagar) as total_pagos_transferencia
             FROM $tabla_pagos 
             WHERE $where_pagos_transferencia";
    }
    $pagos_transferencia = (float) ($wpdb->get_var($sql_pagos_transferencia) ?? 0);
    
    // Total de pagos registrados
    $total_pagos_registrados = $pagos_efectivo + $pagos_transferencia;

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
    
    // Aplicar filtro de negocio si existe
    if ($es_admin && $negocio_id > 0) {
        $where_pedidos_hoy .= " AND JSON_EXTRACT(destinos, '$.origen.negocio_id') = %d";
        $params_pedidos_hoy[] = $negocio_id;
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
    $total_paginas_pedidos = max(1, (int) ceil($total_pedidos_hoy / $por_pagina));
    $offset_pedidos = ($pg_pedidos - 1) * $por_pagina;
    
    // Obtener pedidos del día actual (con paginación)
    $params_pedidos_hoy_limit = $params_pedidos_hoy;
    $params_pedidos_hoy_limit[] = $por_pagina;
    $params_pedidos_hoy_limit[] = $offset_pedidos;
    
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
             LIMIT %d OFFSET %d",
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
             LIMIT %d OFFSET %d",
            $por_pagina,
            $offset_pedidos
        );
    }
    
    $pedidos_hoy = $wpdb->get_results($sql_pedidos_hoy);

    /* ==========================================================
       6.1. Compras del Día Actual
    ========================================================== */
    // Si se filtra por negocio, NO mostrar compras (los negocios no tienen compras)
    $total_compras_hoy = 0;
    $compras_hoy = [];
    $total_paginas_compras_hoy = 0;
    
    if (!($es_admin && $negocio_id > 0)) {
        $where_compras_hoy = "1=1";
        $params_compras_hoy = [];
        
        // Aplicar filtros de mensajero
        if (!$es_admin) {
            $where_compras_hoy .= " AND mensajero_id = %d";
            $params_compras_hoy[] = $usuario->id;
        } elseif ($mensajero_id > 0) {
            $where_compras_hoy .= " AND mensajero_id = %d";
            $params_compras_hoy[] = $mensajero_id;
        }
        
        // Solo compras del día actual
        $where_compras_hoy .= " AND DATE(fecha_creacion) = %s";
        $params_compras_hoy[] = $fecha_hoy;
        
        // Excluir canceladas
        $where_compras_hoy .= " AND estado != 'cancelada'";
        
        // Contar total de compras del día
        if (!empty($params_compras_hoy)) {
            $sql_count_compras_hoy = $wpdb->prepare(
                "SELECT COUNT(*) as total
                 FROM $tabla_compras
                 WHERE $where_compras_hoy",
                $params_compras_hoy
            );
        } else {
            $sql_count_compras_hoy = "SELECT COUNT(*) as total
                 FROM $tabla_compras
                 WHERE $where_compras_hoy";
        }
        
        $total_compras_hoy = (int) ($wpdb->get_var($sql_count_compras_hoy) ?? 0);
        $pg_compras_hoy = isset($_GET['pg_compras_hoy']) ? max(1, (int) $_GET['pg_compras_hoy']) : 1;
        $total_paginas_compras_hoy = max(1, (int) ceil($total_compras_hoy / $por_pagina));
        $offset_compras_hoy = ($pg_compras_hoy - 1) * $por_pagina;
        
        // Obtener compras del día actual (con paginación)
        $params_compras_hoy_limit = $params_compras_hoy;
        $params_compras_hoy_limit[] = $por_pagina;
        $params_compras_hoy_limit[] = $offset_compras_hoy;
        
        if (!empty($params_compras_hoy)) {
            $sql_compras_hoy = $wpdb->prepare(
                "SELECT c.*, 
                        m.nombre as mensajero_nombre,
                        m.telefono as mensajero_telefono,
                        u.nombre as creador_nombre,
                        b.nombre as barrio_nombre
                 FROM $tabla_compras c
                 LEFT JOIN usuarios_gofast m ON c.mensajero_id = m.id
                 LEFT JOIN usuarios_gofast u ON c.creado_por = u.id
                 LEFT JOIN barrios b ON c.barrio_id = b.id
                 WHERE $where_compras_hoy
                 ORDER BY c.fecha_creacion DESC
                 LIMIT %d OFFSET %d",
                $params_compras_hoy_limit
            );
        } else {
            $sql_compras_hoy = $wpdb->prepare(
                "SELECT c.*, 
                        m.nombre as mensajero_nombre,
                        m.telefono as mensajero_telefono,
                        u.nombre as creador_nombre,
                        b.nombre as barrio_nombre
                 FROM $tabla_compras c
                 LEFT JOIN usuarios_gofast m ON c.mensajero_id = m.id
                 LEFT JOIN usuarios_gofast u ON c.creado_por = u.id
                 LEFT JOIN barrios b ON c.barrio_id = b.id
                 WHERE $where_compras_hoy
                 ORDER BY c.fecha_creacion DESC
                 LIMIT %d OFFSET %d",
                $por_pagina,
                $offset_compras_hoy
            );
        }
        
        $compras_hoy = $wpdb->get_results($sql_compras_hoy);
    }

    // Top mensajeros (solo para admin) - con paginación
    $top_mensajeros = [];
    $total_mensajeros = 0;
    $total_paginas_mensajeros = 0;
    if ($es_admin) {
        // Contar total de mensajeros
        if (!empty($params)) {
            $sql_count_mensajeros = $wpdb->prepare(
                "SELECT COUNT(DISTINCT u.id)
                 FROM $tabla s
                 INNER JOIN usuarios_gofast u ON s.mensajero_id = u.id
                 WHERE $where AND s.tracking_estado = 'entregado'",
                $params
            );
        } else {
            $sql_count_mensajeros = "SELECT COUNT(DISTINCT u.id)
                 FROM $tabla s
                 INNER JOIN usuarios_gofast u ON s.mensajero_id = u.id
                 WHERE $where AND s.tracking_estado = 'entregado'";
        }
        $total_mensajeros = (int) ($wpdb->get_var($sql_count_mensajeros) ?? 0);
        $total_paginas_mensajeros = max(1, (int) ceil($total_mensajeros / $por_pagina));
        $offset_mensajeros = ($pg_mensajeros - 1) * $por_pagina;
        
        $params_mensajeros = $params;
        $params_mensajeros[] = $por_pagina;
        $params_mensajeros[] = $offset_mensajeros;
        
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
                 LIMIT %d OFFSET %d",
                $params_mensajeros
            )
        );
    }

    // Pedidos por día (últimos 30 días) - NO se afecta por filtros, siempre últimos 30 días (zona horaria Colombia)
    $timezone = new DateTimeZone('America/Bogota');
    $fecha_hasta_hoy = gofast_current_time('Y-m-d');
    $datetime = new DateTime($fecha_hasta_hoy, $timezone);
    $datetime->modify('-29 days'); // -29 días para tener exactamente 30 días (incluyendo hoy)
    $fecha_desde_30dias = $datetime->format('Y-m-d');
    
    // Construir WHERE para pedidos por día - SOLO filtro de mensajero si es mensajero (NO admin)
    // NO aplicar filtros de fecha, negocio, estado, etc. - siempre últimos 30 días
    $where_pedidos_dia = "1=1";
    $params_pedidos_dia = [];
    
    // Solo filtrar por mensajero si es mensajero (no admin)
    if (!$es_admin) {
        $where_pedidos_dia .= " AND mensajero_id = %d";
        $params_pedidos_dia[] = $usuario->id;
    }
    // NO aplicar filtro de mensajero si es admin (mostrar todos)
    // NO aplicar filtro de negocio
    // NO aplicar filtros de fecha (usar siempre últimos 30 días)
    
    $where_pedidos_dia .= " AND fecha >= %s AND fecha <= %s";
    $params_pedidos_dia[] = $fecha_desde_30dias . ' 00:00:00';
    $params_pedidos_dia[] = $fecha_hasta_hoy . ' 23:59:59';
    
    // Construir WHERE para compras por día - SOLO filtro de mensajero si es mensajero
    $where_compras_dia = "1=1";
    $params_compras_dia = [];
    
    // Solo filtrar por mensajero si es mensajero (no admin)
    if (!$es_admin) {
        $where_compras_dia .= " AND mensajero_id = %d";
        $params_compras_dia[] = $usuario->id;
    }
    // NO aplicar filtro de mensajero si es admin (mostrar todos)
    // NO aplicar filtro de negocio
    
    $where_compras_dia .= " AND fecha_creacion >= %s AND fecha_creacion <= %s AND estado != 'cancelada'";
    $params_compras_dia[] = $fecha_desde_30dias . ' 00:00:00';
    $params_compras_dia[] = $fecha_hasta_hoy . ' 23:59:59';
    
    // Consulta de servicios con destinos y ingresos - SIN paginación, siempre últimos 30 días
    $pedidos_por_dia = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                DATE(fecha) as dia,
                SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) as cantidad_destinos,
                SUM(CASE WHEN tracking_estado != 'cancelado' THEN total ELSE 0 END) as ingresos
             FROM $tabla
             WHERE $where_pedidos_dia AND tracking_estado != 'cancelado'
             GROUP BY DATE(fecha)
             ORDER BY dia DESC",
            $params_pedidos_dia
        )
    );
    
    // Consulta de compras por día (cantidad e ingresos) - SIN paginación, siempre últimos 30 días
    // Solo mostrar compras si NO es admin filtrando por negocio (los negocios no tienen compras)
    $compras_por_dia = [];
    if (!($es_admin && $negocio_id > 0)) {
        // Si es mensajero, solo sus compras; si es admin, todas las compras
        $compras_por_dia = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(fecha_creacion) as dia,
                    COUNT(*) as cantidad_compras,
                    SUM(valor) as ingresos_compras
                 FROM $tabla_compras
                 WHERE $where_compras_dia
                 GROUP BY DATE(fecha_creacion)
                 ORDER BY dia DESC",
                $params_compras_dia
            )
        );
    }
    
    // Generar array completo de los últimos 30 días (incluso días sin actividad)
    $pedidos_por_dia_completo = [];
    
    // Crear array con todos los días del rango (exactamente 30 días: desde fecha_desde_30dias hasta fecha_hasta_hoy inclusive)
    $fecha_inicio = new DateTime($fecha_desde_30dias);
    $fecha_fin = new DateTime($fecha_hasta_hoy);
    $fecha_fin->modify('+1 day'); // +1 para incluir el último día en DatePeriod
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($fecha_inicio, $intervalo, $fecha_fin);
    
    foreach ($periodo as $dia) {
        $dia_key = $dia->format('Y-m-d');
        $pedidos_por_dia_completo[$dia_key] = [
            'dia' => $dia_key,
            'cantidad_destinos' => 0,
            'cantidad_compras' => 0,
            'ingresos' => 0,
            'comision' => 0
        ];
    }
    
    // Agregar datos de servicios
    foreach ($pedidos_por_dia as $servicio) {
        $dia_key = $servicio->dia;
        if (isset($pedidos_por_dia_completo[$dia_key])) {
            $pedidos_por_dia_completo[$dia_key]['cantidad_destinos'] = (int) ($servicio->cantidad_destinos ?? 0);
            $pedidos_por_dia_completo[$dia_key]['ingresos'] = (float) ($servicio->ingresos ?? 0);
        }
    }
    
    // Agregar compras y sumar ingresos
    foreach ($compras_por_dia as $compra) {
        $dia_key = $compra->dia;
        if (isset($pedidos_por_dia_completo[$dia_key])) {
            $pedidos_por_dia_completo[$dia_key]['cantidad_compras'] = (int) ($compra->cantidad_compras ?? 0);
            $pedidos_por_dia_completo[$dia_key]['ingresos'] += (float) ($compra->ingresos_compras ?? 0);
        }
    }
    
    // Calcular comisión (20% de ingresos totales)
    foreach ($pedidos_por_dia_completo as $key => $dia_data) {
        $pedidos_por_dia_completo[$key]['comision'] = $dia_data['ingresos'] * 0.20;
    }
    
    // Convertir a array indexado y ordenar por fecha descendente (más reciente primero)
    $pedidos_por_dia_todos = array_values($pedidos_por_dia_completo);
    usort($pedidos_por_dia_todos, function($a, $b) {
        return strcmp($b['dia'], $a['dia']); // Orden descendente (más reciente primero)
    });
    
    // Aplicar paginación: 15 días por página
    $por_pagina_dias = 15;
    $total_dias = count($pedidos_por_dia_todos);
    $total_paginas_dias = max(1, (int) ceil($total_dias / $por_pagina_dias));
    $offset_dias = ($pg_dias - 1) * $por_pagina_dias;
    
    // Obtener solo los días de la página actual
    $pedidos_por_dia = array_slice($pedidos_por_dia_todos, $offset_dias, $por_pagina_dias);

    /* ==========================================================
       3. Lista de mensajeros y negocios para filtro (solo admin)
    ========================================================== */
    $mensajeros = [];
    $negocios = [];
    if ($es_admin) {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre 
             FROM usuarios_gofast
             WHERE rol = 'mensajero'
             ORDER BY nombre ASC
            "
        );
        
        $negocios = $wpdb->get_results(
            "SELECT id, nombre, user_id
             FROM negocios_gofast
             WHERE activo = 1
             ORDER BY nombre ASC
            "
        );
    }
    
    /* ==========================================================
       7. SALDOS PENDIENTES POR MENSAJERO
       (Cálculos independientes: solo usa filtro hasta y mensajero)
    ========================================================== */
    $saldos_mensajeros = [];
    $total_saldos_pendientes_acumulado = 0;
    
    // Obtener filtros específicos para saldos pendientes
    // Calcular quincena actual automáticamente (igual que finanzas_admin)
    $fecha_actual = gofast_current_time('Y-m-d');
    $timezone = new DateTimeZone('America/Bogota');
    $datetime = new DateTime($fecha_actual, $timezone);
    $mes_actual = $datetime->format('Y-m');
    $dia_actual = (int) $datetime->format('d');
    $ultimo_dia_mes = (int) $datetime->format('t');
    
    // Determinar quincena actual
    if ($dia_actual <= 15) {
        // Primera quincena: del 1 al 15
        $fecha_desde_saldos = $mes_actual . '-01';
        $fecha_hasta_saldos = $mes_actual . '-15';
    } else {
        // Segunda quincena: del 16 al fin de mes
        $fecha_desde_saldos = $mes_actual . '-16';
        $fecha_hasta_saldos = $mes_actual . '-' . str_pad($ultimo_dia_mes, 2, '0', STR_PAD_LEFT);
    }
    
    // Calcular total de saldos pendientes GLOBAL (igual que finanzas_admin)
    // Fórmula: Comisión(20% de ingresos) - Transferencias Ingresos - Descuentos - Pagos en Efectivo realizados
    // Esto representa lo que se debe a los mensajeros en total (solo considera pagos en efectivo, no transferencias)
    
    // Total Ingresos (servicios + compras) de la quincena
    $total_ingresos_servicios_saldos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
             WHERE tracking_estado != 'cancelado' 
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
        )
    ) ?? 0);
    
    $total_ingresos_compras_saldos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
             WHERE estado != 'cancelada' 
             AND fecha_creacion >= %s AND fecha_creacion <= %s",
            $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
        )
    ) ?? 0);
    
    $total_ingresos_saldos = $total_ingresos_servicios_saldos + $total_ingresos_compras_saldos;
    
    // Total Comisiones (20% de ingresos totales)
    $total_comisiones_saldos = $total_ingresos_saldos * 0.20;
    
    // Total Transferencias Ingresos de la quincena (igual que finanzas_admin)
    // Solo incluir: transferencias tipo "normal" y transferencias tipo "pago" asociadas a pagos registrados
    // IMPORTANTE: Usar exactamente la misma lógica que finanzas_admin
    $where_transf_entradas_saldos = ["estado = 'aprobada'"];
    $params_transf_entradas_saldos = [];
    
    if (!empty($fecha_desde_saldos)) {
        $where_transf_entradas_saldos[] = "fecha_creacion >= %s";
        $params_transf_entradas_saldos[] = $fecha_desde_saldos . ' 00:00:00';
    }
    if (!empty($fecha_hasta_saldos)) {
        $where_transf_entradas_saldos[] = "fecha_creacion <= %s";
        $params_transf_entradas_saldos[] = $fecha_hasta_saldos . ' 23:59:59';
    }
    
    // Construir la consulta: solo transferencias tipo "normal" y tipo "pago" asociadas a pagos registrados
    // Las transferencias tipo "pago" solo se crean cuando se registra un pago por transferencia
    // Verificamos que exista un pago registrado (efectivo o transferencia) que coincida
    $sql_transf_entradas_saldos = "
        SELECT COALESCE(SUM(t.valor), 0) 
        FROM transferencias_gofast t
        WHERE " . implode(' AND ', $where_transf_entradas_saldos) . "
        AND (
            (t.tipo = 'normal' OR t.tipo IS NULL)
            OR (
                t.tipo = 'pago' 
                AND EXISTS (
                    SELECT 1 FROM pagos_mensajeros_gofast p
                    WHERE p.mensajero_id = t.mensajero_id
                    AND p.total_a_pagar = t.valor
                    AND p.tipo_pago IN ('efectivo', 'transferencia')
                )
            )
        )
    ";
    
    $total_transferencias_ingresos_saldos = (float) ($wpdb->get_var(
        !empty($params_transf_entradas_saldos)
            ? $wpdb->prepare($sql_transf_entradas_saldos, $params_transf_entradas_saldos)
            : str_replace(implode(' AND ', $where_transf_entradas_saldos), "1=1", $sql_transf_entradas_saldos)
    ) ?? 0);
    
    // Total Descuentos de la quincena
    $total_descuentos_saldos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde_saldos, $fecha_hasta_saldos
        )
    ) ?? 0);
    
    // Saldos Pendientes Totales - Se calculará después de procesar todos los mensajeros
    // NOTA: No calcular aquí porque aún no tenemos todos los datos procesados
    $total_saldos_pendientes_acumulado = 0;
    
    // Obtener lista de mensajeros para calcular saldos
    // Si es admin y hay mensajero_id seleccionado, solo ese mensajero
    // Si es admin sin mensajero_id, todos los mensajeros (incluye deshabilitados)
    // Si es mensajero, solo él mismo
    if ($es_admin) {
        if ($mensajero_id > 0) {
            // Admin con mensajero seleccionado: solo ese mensajero
            $mensajeros_saldos = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero'",
                    $mensajero_id
                )
            );
        } else {
            // Admin sin mensajero seleccionado: todos los mensajeros (incluye deshabilitados)
            $mensajeros_saldos = $wpdb->get_results(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE rol = 'mensajero' ORDER BY nombre ASC"
            );
        }
    } else {
        // Mensajero: solo él mismo
        $mensajeros_saldos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d",
                $usuario->id
            )
        );
    }
    
    // Calcular saldos para cada mensajero
    foreach ($mensajeros_saldos as $mensajero) {
        $mensajero_id_saldo = (int) $mensajero->id;
        
        // Calcular comisión de la quincena actual (rango fecha_desde_saldos a fecha_hasta_saldos)
        // Usar formato con hora completa igual que finanzas_admin
        $comision_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) * 0.20 FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado' 
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id_saldo, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        $comision_compras_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) * 0.20 FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada' 
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $mensajero_id_saldo, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        // Transferencias de la quincena: solo tipo "normal" (excluir tipo "pago") (igual que finanzas_admin)
        // Las transferencias tipo "pago" se contabilizan en los pagos
        $transferencias_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) 
                 FROM transferencias_gofast
                 WHERE mensajero_id = %d 
                 AND estado = 'aprobada' 
                 AND (tipo = 'normal' OR tipo IS NULL)
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $mensajero_id_saldo, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        $descuentos_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND fecha >= %s AND fecha <= %s",
                $mensajero_id_saldo, $fecha_desde_saldos, $fecha_hasta_saldos
            )
        ) ?? 0);
        
        // Pagos de la quincena: SOLO pagos en efectivo (igual que finanzas_admin)
        // NOTA: Los pagos por transferencia NO se restan aquí porque ya están contabilizados como transferencias salidas
        $pagos_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago = 'efectivo'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id_saldo, $fecha_desde_saldos, $fecha_hasta_saldos
            )
        ) ?? 0);
        
        // Total pendiente de la quincena actual
        // Usar variable diferente para no afectar el $total_a_pagar de las estadísticas principales
        // Fórmula: Comisión - Transferencias - Descuentos - Pagos en Efectivo
        $total_a_pagar_saldo = ($comision_quincena + $comision_compras_quincena) - $transferencias_quincena - $descuentos_quincena - $pagos_quincena;
        if ($total_a_pagar_saldo < 0) {
            $total_a_pagar_saldo = 0;
        }
        
        // Calcular desglose por día SOLO dentro del rango de fechas seleccionado
        // Esto asegura que el desglose coincida con el "Total a Pagar" del resumen
        $desglose_dias = [];
        
        if (!empty($fecha_desde_saldos) && !empty($fecha_hasta_saldos)) {
            // Calcular solo días dentro del rango seleccionado
            $fecha_hoy = gofast_current_time('Y-m-d');
            $fecha_inicio = new DateTime($fecha_desde_saldos);
            
            // Determinar la fecha final del periodo
            // Si fecha_hasta >= hoy, incluir hasta hoy
            $fecha_fin_periodo = $fecha_hasta_saldos;
            if ($fecha_hasta_saldos >= $fecha_hoy) {
                $fecha_fin_periodo = $fecha_hoy;
            }
            
            $fecha_fin = new DateTime($fecha_fin_periodo);
            $fecha_fin->modify('+1 day'); // +1 day para incluir el último día en el DatePeriod
            $intervalo = new DateInterval('P1D');
            $periodo = new DatePeriod($fecha_inicio, $intervalo, $fecha_fin);
            
            // Obtener pagos SOLO dentro del rango de fechas seleccionado
            // Esto asegura que los pagos aplicados coincidan con el cálculo del resumen
            // IMPORTANTE: Incluir pagos de ambos tipos (efectivo y transferencia) igual que en finanzas_admin
            $todos_pagos = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT fecha, total_a_pagar FROM pagos_mensajeros_gofast 
                     WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia')
                     AND fecha >= %s AND fecha <= %s
                     ORDER BY fecha ASC, fecha_pago ASC",
                    $mensajero_id_saldo, $fecha_desde_saldos, $fecha_hasta_saldos
                )
            );
            
            // Sumar todos los pagos dentro del rango
            $total_pagos_rango = 0;
            foreach ($todos_pagos as $pago) {
                $total_pagos_rango += (float) $pago->total_a_pagar;
            }
            
            // Calcular todos los días con sus valores
            $dias_historico = [];
            foreach ($periodo as $dia) {
                $fecha_dia = $dia->format('Y-m-d');
                
                // Ingresos del día (servicios + compras)
                $ingresos_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                         WHERE mensajero_id = %d AND tracking_estado != 'cancelado' AND DATE(fecha) = %s",
                        $mensajero_id_saldo, $fecha_dia
                    )
                ) ?? 0);
                
                $ingresos_compras_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                         WHERE mensajero_id = %d AND estado != 'cancelada' AND DATE(fecha_creacion) = %s",
                        $mensajero_id_saldo, $fecha_dia
                    )
                ) ?? 0);
                
                // Transferencias del día: solo tipo "normal" (excluir tipo "pago")
                // Las transferencias tipo "pago" se contabilizan en los pagos del día
                // IMPORTANTE: Usar fecha_creacion con hora para coincidir con reportes_admin.php
                $transferencias_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) 
                         FROM transferencias_gofast
                         WHERE mensajero_id = %d 
                         AND estado = 'aprobada' 
                         AND fecha_creacion >= %s
                         AND fecha_creacion <= %s
                         AND (tipo = 'normal' OR tipo IS NULL)",
                        $mensajero_id_saldo, $fecha_dia . ' 00:00:00', $fecha_dia . ' 23:59:59'
                    )
                ) ?? 0);
                
                // Descuentos del día
                $descuentos_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                         WHERE mensajero_id = %d AND fecha = %s",
                        $mensajero_id_saldo, $fecha_dia
                    )
                ) ?? 0);
                
                $ingresos_total_dia = $ingresos_dia + $ingresos_compras_dia;
                $comision_dia = $ingresos_total_dia * 0.20;
                $a_pagar_dia = $comision_dia - $transferencias_dia - $descuentos_dia;
                
                // Solo agregar días con actividad
                // NOTA: El pendiente puede ser negativo si las transferencias superan la comisión
                // o si hay excedentes de pago que ajustan días anteriores
                if ($ingresos_total_dia > 0) {
                    $dias_historico[] = [
                        'fecha' => $fecha_dia,
                        'ingresos' => $ingresos_total_dia,
                        'ingresos_servicios' => $ingresos_dia,
                        'ingresos_compras' => $ingresos_compras_dia,
                        'comision' => $comision_dia,
                        'comision_servicios' => $ingresos_dia * 0.20,
                        'comision_compras' => $ingresos_compras_dia * 0.20,
                        'transferencias' => $transferencias_dia,
                        'descuentos' => $descuentos_dia,
                        'a_pagar' => $a_pagar_dia, // Permitir valores negativos (sin max(0, ...))
                        'pagado' => 0,
                        'pendiente' => $a_pagar_dia // Permitir valores negativos (sin max(0, ...))
                    ];
                }
            }
            
            // Aplicar pagos a cada día según su fecha específica
            // Si un pago excede el pendiente del día, aplicar el excedente a días anteriores
            // IMPORTANTE: Si un pago tiene fecha sin actividad, crear un día virtual para ese pago
            foreach ($todos_pagos as $pago) {
                $pago_restante = (float) $pago->total_a_pagar;
                $fecha_pago = $pago->fecha;
                
                // Buscar el día correspondiente a la fecha del pago
                $dia_pago_index = null;
                foreach ($dias_historico as $index => $dia) {
                    if ($dia['fecha'] === $fecha_pago) {
                        $dia_pago_index = $index;
                        break;
                    }
                }
                
                // Si NO existe el día del pago (fecha sin actividad), crear un día virtual para aplicar el pago
                if ($dia_pago_index === null && $fecha_pago >= $fecha_desde_saldos && $fecha_pago <= $fecha_hasta_saldos) {
                    // Crear un día virtual con valores en 0 excepto el pago
                    $dias_historico[] = [
                        'fecha' => $fecha_pago,
                        'ingresos' => 0,
                        'ingresos_servicios' => 0,
                        'ingresos_compras' => 0,
                        'comision' => 0,
                        'comision_servicios' => 0,
                        'comision_compras' => 0,
                        'transferencias' => 0,
                        'descuentos' => 0,
                        'a_pagar' => 0, // No hay nada a pagar (sin actividad)
                        'pagado' => 0, // Se aplicará el pago a continuación
                        'pendiente' => 0 // Sin pendiente inicial
                    ];
                    // Actualizar el índice
                    $dia_pago_index = count($dias_historico) - 1;
                }
                
                // Si existe el día del pago (original o virtual), aplicar el pago
                if ($dia_pago_index !== null && $pago_restante > 0) {
                    $pendiente_dia_pago = $dias_historico[$dia_pago_index]['pendiente'];
                    
                    // Si el pendiente es positivo, aplicar el pago hasta ese monto
                    // Si el pendiente es negativo o cero, el pago completo puede aplicarse (creará o aumentará el excedente)
                    if ($pendiente_dia_pago > 0) {
                        $aplicar_al_dia_pago = min($pago_restante, $pendiente_dia_pago);
                    } else {
                        // Si el pendiente ya es negativo o cero, aplicar el pago completo
                        // Esto aumentará el excedente (pendiente negativo)
                        $aplicar_al_dia_pago = $pago_restante;
                    }
                    
                    $dias_historico[$dia_pago_index]['pagado'] += $aplicar_al_dia_pago;
                    $dias_historico[$dia_pago_index]['pendiente'] -= $aplicar_al_dia_pago;
                    $pago_restante -= $aplicar_al_dia_pago;
                    
                    // Si quedó excedente del pago después de aplicar al día correspondiente,
                    // aplicar a días anteriores (hacia atrás)
                    if ($pago_restante > 0) {
                        // Ordenar días por fecha (más antiguo primero) para aplicar excedente hacia atrás
                        // Solo considerar días con pendiente positivo (que aún deben pagos)
                        $indices_ordenados = [];
                        foreach ($dias_historico as $idx => $d) {
                            if ($d['fecha'] < $fecha_pago && $d['pendiente'] > 0) {
                                $indices_ordenados[] = $idx;
                            }
                        }
                        // Ordenar índices por fecha descendente (días más cercanos al pago primero)
                        usort($indices_ordenados, function($a, $b) use ($dias_historico) {
                            return strcmp($dias_historico[$b]['fecha'], $dias_historico[$a]['fecha']);
                        });
                        
                        // Aplicar excedente a días anteriores
                        foreach ($indices_ordenados as $idx_anterior) {
                            if ($pago_restante <= 0) break;
                            
                            $pendiente_anterior = $dias_historico[$idx_anterior]['pendiente'];
                            if ($pendiente_anterior > 0) {
                                $aplicar_anterior = min($pago_restante, $pendiente_anterior);
                                
                                $dias_historico[$idx_anterior]['pagado'] += $aplicar_anterior;
                                $dias_historico[$idx_anterior]['pendiente'] -= $aplicar_anterior;
                                $pago_restante -= $aplicar_anterior;
                            }
                        }
                        
                        // Si aún queda excedente después de aplicar a días anteriores,
                        // crear un saldo negativo en el día del pago (para ajustar días pasados)
                        if ($pago_restante > 0) {
                            $dias_historico[$dia_pago_index]['pagado'] += $pago_restante;
                            $dias_historico[$dia_pago_index]['pendiente'] -= $pago_restante;
                            // Permitir pendiente negativo (saldo a favor) para ajustar días anteriores
                        }
                    }
                }
            }
            
            // Agregar días dentro del rango fecha_desde a fecha_hasta
            // Solo mostrar días con pendiente != 0 (excluir días completamente pagados)
            // Incluye días con pendiente > 0 y pendiente < 0
            foreach ($dias_historico as $dia) {
                // Solo mostrar días dentro del rango seleccionado
                if ($dia['fecha'] >= $fecha_desde_saldos && $dia['fecha'] <= $fecha_hasta_saldos) {
                    // Calcular pendiente del día
                    $pendiente_dia = isset($dia['pendiente']) ? $dia['pendiente'] : ($dia['a_pagar'] - (isset($dia['pagado']) ? $dia['pagado'] : 0));
                    
                    // Mostrar días con actividad (ingresos > 0) O con pagos aplicados, pero solo si pendiente != 0
                    // Excluir días con pendiente = 0 (completamente pagados)
                    if (($dia['ingresos'] > 0 || (isset($dia['pagado']) && $dia['pagado'] > 0)) && abs($pendiente_dia) > 0.01) {
                        $desglose_dias[] = (object) $dia;
                    }
                }
            }
            
            // Asegurar que el día de hoy esté en el desglose si está dentro del rango y tiene actividad
            // Solo si fecha_hasta >= hoy Y fecha_desde <= hoy (hoy está dentro del rango)
            if ($fecha_hoy >= $fecha_desde_saldos && $fecha_hoy <= $fecha_hasta_saldos) {
                // Verificar si hoy ya está en el desglose
                $hoy_en_desglose = false;
                foreach ($desglose_dias as $dia) {
                    if ($dia->fecha === $fecha_hoy) {
                        $hoy_en_desglose = true;
                        break;
                    }
                }
                
                // Si hoy no está en el desglose pero tiene actividad, calcularlo y agregarlo
                if (!$hoy_en_desglose) {
                    $ingresos_hoy = (float) ($wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                             WHERE mensajero_id = %d AND tracking_estado != 'cancelado' AND DATE(fecha) = %s",
                            $mensajero_id_saldo, $fecha_hoy
                        )
                    ) ?? 0);
                    
                    $ingresos_compras_hoy = (float) ($wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                             WHERE mensajero_id = %d AND estado != 'cancelada' AND DATE(fecha_creacion) = %s",
                            $mensajero_id_saldo, $fecha_hoy
                        )
                    ) ?? 0);
                    
                    if ($ingresos_hoy > 0 || $ingresos_compras_hoy > 0) {
                        $ingresos_total_hoy = $ingresos_hoy + $ingresos_compras_hoy;
                        $comision_hoy = $ingresos_total_hoy * 0.20;
                        
                        $transferencias_hoy = (float) ($wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COALESCE(SUM(valor), 0) 
                                 FROM transferencias_gofast
                                 WHERE mensajero_id = %d 
                                 AND estado = 'aprobada' 
                                 AND fecha_creacion >= %s
                                 AND fecha_creacion <= %s
                                 AND (tipo = 'normal' OR tipo IS NULL)",
                                $mensajero_id_saldo, $fecha_hoy . ' 00:00:00', $fecha_hoy . ' 23:59:59'
                            )
                        ) ?? 0);
                        
                        $descuentos_hoy = (float) ($wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                                 WHERE mensajero_id = %d AND fecha = %s",
                                $mensajero_id_saldo, $fecha_hoy
                            )
                        ) ?? 0);
                        
                        $a_pagar_hoy = $comision_hoy - $transferencias_hoy - $descuentos_hoy;
                        $pendiente_hoy = $a_pagar_hoy; // Permitir valores negativos (sin max(0, ...))
                        
                        // Aplicar pagos del día de hoy si existen (solo dentro del rango)
                        $pagos_hoy = 0;
                        foreach ($todos_pagos as $pago) {
                            if ($pago->fecha === $fecha_hoy) {
                                // Permitir aplicar pagos incluso si el pendiente es negativo
                                // El pendiente puede volverse más negativo si hay excedentes
                                $aplicar_hoy = (float) $pago->total_a_pagar;
                                $pagos_hoy += $aplicar_hoy;
                                $pendiente_hoy -= $aplicar_hoy;
                                // No forzar a 0, permitir valores negativos
                            }
                        }
                        
                        // Agregar hoy al desglose si tiene actividad
                        if ($ingresos_total_hoy > 0) {
                            $desglose_dias[] = (object) [
                                'fecha' => $fecha_hoy,
                                'ingresos' => $ingresos_total_hoy,
                                'ingresos_servicios' => $ingresos_hoy,
                                'ingresos_compras' => $ingresos_compras_hoy,
                                'comision' => $comision_hoy,
                                'comision_servicios' => $ingresos_hoy * 0.20,
                                'comision_compras' => $ingresos_compras_hoy * 0.20,
                                'transferencias' => $transferencias_hoy,
                                'descuentos' => $descuentos_hoy,
                                'a_pagar' => $a_pagar_hoy, // Permitir valores negativos (sin max(0, ...))
                                'pagado' => $pagos_hoy,
                                'pendiente' => $pendiente_hoy // Permitir valores negativos (sin max(0, ...))
                            ];
                        }
                    }
                }
            }
            
            // Ordenar desglose_dias por fecha (más antiguo primero)
            usort($desglose_dias, function($a, $b) {
                return strcmp($a->fecha, $b->fecha);
            });
        }

        // Calcular total pendiente desde el desglose por días (suma de pendientes de días mostrados)
        // Esto se usa para verificar que coincida con el cálculo del rango
        // IMPORTANTE: Recalcular el pendiente correctamente para cada día: Total a Pagar - Pagado
        $total_pendiente_desglose = 0;
        if (!empty($desglose_dias)) {
            foreach ($desglose_dias as $dia_obj) {
                // Recalcular pendiente correctamente: Comisión - Transferencias - Descuentos - Pagado
                $total_a_pagar_dia_recalc = $dia_obj->comision - $dia_obj->transferencias - $dia_obj->descuentos;
                $pendiente_recalc = $total_a_pagar_dia_recalc - (isset($dia_obj->pagado) ? $dia_obj->pagado : 0);
                
                // Actualizar el pendiente en el objeto para que coincida con el cálculo correcto
                $dia_obj->pendiente = $pendiente_recalc;
                
                // Sumar al total
                $total_pendiente_desglose += $pendiente_recalc;
            }
        }
        
        // Solo agregar mensajeros con saldo pendiente o con días en el desglose
        // Usar el total del desglose si hay días, de lo contrario usar el cálculo directo
        $total_final_a_pagar = !empty($desglose_dias) ? $total_pendiente_desglose : $total_a_pagar_saldo;
        
        if ($total_final_a_pagar != 0 || !empty($desglose_dias)) {
            $saldos_mensajeros[] = (object) [
                'mensajero_id' => $mensajero_id_saldo,
                'mensajero_nombre' => $mensajero->nombre,
                'mensajero_telefono' => $mensajero->telefono,
                'total_a_pagar' => $total_final_a_pagar,
                'desglose_dias' => $desglose_dias
            ];
        }
    }
    
    // Para admin: calcular desglose diario agregado de todos los mensajeros (o del seleccionado)
    // IMPORTANTE: Solo incluir días dentro de la quincena activa
    $desglose_dias_admin = [];
    $total_pendiente_desglose_admin = 0;
    
    if ($es_admin && !empty($saldos_mensajeros)) {
        // Agrupar todos los días de todos los mensajeros SOLO dentro de la quincena activa
        $dias_agrupados = [];
        
        foreach ($saldos_mensajeros as $saldo) {
            foreach ($saldo->desglose_dias as $dia) {
                // Solo incluir días dentro de la quincena activa
                if ($dia->fecha >= $fecha_desde_saldos && $dia->fecha <= $fecha_hasta_saldos) {
                    $fecha = $dia->fecha;
                    
                    if (!isset($dias_agrupados[$fecha])) {
                        $dias_agrupados[$fecha] = [
                            'fecha' => $fecha,
                            'comision' => 0,
                            'transferencias' => 0,
                            'descuentos' => 0,
                            'pagado' => 0,
                            'pendiente' => 0
                        ];
                    }
                    
                    $dias_agrupados[$fecha]['comision'] += $dia->comision;
                    $dias_agrupados[$fecha]['transferencias'] += $dia->transferencias;
                    $dias_agrupados[$fecha]['descuentos'] += $dia->descuentos;
                    $dias_agrupados[$fecha]['pagado'] += (isset($dia->pagado) ? $dia->pagado : 0);
                }
            }
        }
        
        // IMPORTANTE: Actualizar descuentos de todos los días usando los totales reales de la BD por fecha
        // Esto asegura que todos los descuentos de la quincena se incluyan correctamente
        // Obtener todos los descuentos de la quincena agrupados por fecha
        $descuentos_totales_por_fecha = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT fecha, COALESCE(SUM(valor), 0) as total_descuentos 
                 FROM descuentos_mensajeros_gofast 
                 WHERE fecha >= %s AND fecha <= %s
                 GROUP BY fecha",
                $fecha_desde_saldos, $fecha_hasta_saldos
            )
        );
        
        // Crear un array con los descuentos totales por fecha desde BD
        $descuentos_bd_por_fecha = [];
        foreach ($descuentos_totales_por_fecha as $row) {
            $descuentos_bd_por_fecha[$row->fecha] = (float) $row->total_descuentos;
        }
        
        // Actualizar descuentos de días existentes con los totales reales de la BD para cada fecha
        // Esto asegura que se incluyan todos los descuentos de todos los mensajeros para esa fecha
        foreach ($dias_agrupados as $fecha_dia => &$dia) {
            if (isset($descuentos_bd_por_fecha[$fecha_dia])) {
                // Actualizar descuentos con el total real de la BD para esa fecha
                $dia['descuentos'] = $descuentos_bd_por_fecha[$fecha_dia];
            }
        }
        unset($dia); // Liberar referencia
        
        // Agregar días con descuentos pero sin ingresos (que no aparecen en el desglose individual)
        foreach ($descuentos_bd_por_fecha as $fecha_descuento => $total_descuentos_fecha) {
            if (!isset($dias_agrupados[$fecha_descuento]) && abs($total_descuentos_fecha) > 0.01) {
                // Crear un día solo con descuentos (sin ingresos)
                $dias_agrupados[$fecha_descuento] = [
                    'fecha' => $fecha_descuento,
                    'comision' => 0,
                    'transferencias' => 0,
                    'descuentos' => $total_descuentos_fecha,
                    'pagado' => 0,
                    'pendiente' => 0
                ];
            }
        }
        
        // Recalcular pendientes correctamente para cada día agrupado
        foreach ($dias_agrupados as &$dia) {
            // Recalcular pendiente correctamente: Comisión - Transferencias - Descuentos - Pagado
            $total_a_pagar_dia_recalc = $dia['comision'] - $dia['transferencias'] - $dia['descuentos'];
            $pendiente_recalc = $total_a_pagar_dia_recalc - $dia['pagado'];
            $dia['pendiente'] = $pendiente_recalc;
        }
        unset($dia); // Liberar referencia
        
        // Filtrar días: mostrar días con actividad (comisión > 0) O con descuentos != 0 O con pagado > 0
        // Pero solo si pendiente != 0 (excluir días completamente pagados)
        foreach ($dias_agrupados as $fecha_dia => $dia) {
            // Solo mostrar días dentro de la quincena activa
            if ($dia['fecha'] >= $fecha_desde_saldos && $dia['fecha'] <= $fecha_hasta_saldos) {
                // Calcular pendiente del día
                $pendiente_dia = isset($dia['pendiente']) ? $dia['pendiente'] : ($dia['comision'] - $dia['transferencias'] - $dia['descuentos'] - $dia['pagado']);
                
                // Mostrar días con actividad (comisión > 0) O con descuentos != 0 O con pagos aplicados, pero solo si pendiente != 0
                // Excluir días con pendiente = 0 (completamente pagados)
                if (($dia['comision'] > 0 || abs($dia['descuentos']) > 0.01 || $dia['pagado'] > 0) && abs($pendiente_dia) > 0.01) {
                    $desglose_dias_admin[] = (object) $dia;
                }
            }
        }
        
        // Ordenar por fecha (más antiguo primero)
        usort($desglose_dias_admin, function($a, $b) {
            return strcmp($a->fecha, $b->fecha);
        });
    }
    
    // Calcular el total de saldos pendientes acumulado
    // IMPORTANTE: Usar la misma fórmula que finanzas_admin
    // Fórmula: Comisión - Transferencias Ingresos - Descuentos - Pagos en Efectivo (solo efectivo, no transferencias)
    // Esto representa lo que se debe a los mensajeros (solo considerando pagos en efectivo, no transferencias)
    // IMPORTANTE: Usar SIEMPRE el rango de fechas (fecha_desde_saldos y fecha_hasta_saldos), no acumulados históricos
    
    // Total de pagos realizados a mensajeros SOLO EN EFECTIVO en el rango de fechas
    // NOTA: Los pagos por transferencia NO se restan aquí porque ya están contabilizados en las transferencias salidas
    $total_pagos_mensajeros_efectivo_final = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
             WHERE tipo_pago = 'efectivo'
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde_saldos, $fecha_hasta_saldos
        )
    ) ?? 0);
    
    // Saldos Pendientes = Comisión - Transferencias Ingresos - Descuentos - Pagos en Efectivo (en rango de fecha)
    // NOTA: Se permiten saldos negativos y saldos 0 para permitir bonificaciones mediante descuentos negativos
    // NOTA: Los pagos por transferencia NO se restan aquí porque ya están contabilizados como transferencias salidas
    $total_saldos_pendientes_acumulado = $total_comisiones_saldos - $total_transferencias_ingresos_saldos - $total_descuentos_saldos - $total_pagos_mensajeros_efectivo_final;
    
    // No forzar a 0 si es negativo, pero sí asegurar que no sea menor que 0 para mostrar
    if ($total_saldos_pendientes_acumulado < 0) {
        $total_saldos_pendientes_acumulado = 0;
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
                        <select name="mensajero_id" class="gofast-select-filtro" data-placeholder="Todos los mensajeros">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($mensajero_id, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Negocio</label>
                        <select name="negocio_id" class="gofast-select-filtro" data-placeholder="Todos los negocios">
                            <option value="0">Todos</option>
                            <?php foreach ($negocios as $n): ?>
                                <option value="<?= (int) $n->id; ?>"<?php selected($negocio_id, $n->id); ?>>
                                    <?= esc_html($n->nombre); ?>
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

                <div class="gofast-pedidos-filtros-actions">
                    <button type="submit" class="gofast-btn-mini">Filtrar</button>
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
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

        <?php if (!($es_admin && $negocio_id > 0)): ?>
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🛒</div>
            <div style="font-size:28px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($total_compras); ?></div>
            <div style="font-size:13px;color:#666;">Total Compras</div>
        </div>
        <?php endif; ?>

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
            <div style="font-size:11px;color:#999;margin-top:4px;">(Solo tipo normal)</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <div style="font-size:28px;font-weight:700;color:#28a745;margin-bottom:4px;">$<?= number_format($total_pagos_registrados, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Pagos Registrados</div>
            <div style="font-size:11px;color:#999;margin-top:4px;border-top:1px solid #e0e0e0;padding-top:6px;margin-top:6px;">
                <div style="margin-bottom:2px;">💵 Efectivo: $<?= number_format($pagos_efectivo, 0, ',', '.'); ?></div>
                <div>💸 Transferencia: $<?= number_format($pagos_transferencia, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💳</div>
            <div style="font-size:28px;font-weight:700;color:<?= $total_a_pagar >= 0 ? '#4CAF50' : '#f44336'; ?>;margin-bottom:4px;">$<?= number_format($total_a_pagar, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Total a Pagar</div>
        </div>

    </div>

    <!-- =====================================================
         B1) SALDOS PENDIENTES POR MENSAJERO
    ====================================================== -->
    <?php if ($es_admin): ?>
        <!-- Para admin: mostrar total acumulado y desglose por día de todos los mensajeros (o del seleccionado) -->
        <?php if ($total_saldos_pendientes_acumulado > 0 || !empty($desglose_dias_admin)): ?>
            <div class="gofast-box" style="margin-bottom:20px;">
                <h3 style="margin-top:0;">
                    💵 Saldos Pendientes
                    <?php if ($total_saldos_pendientes_acumulado > 0): ?>
                        <span style="font-size:14px;color:#666;font-weight:normal;">
                            (Total: $<?= number_format($total_saldos_pendientes_acumulado, 0, ',', '.'); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                
                <?php if ($mensajero_id > 0): ?>
                    <p style="color:#666;font-size:13px;margin-top:8px;">
                        Mostrando saldos pendientes para el mensajero seleccionado de la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>)
                    </p>
                <?php else: ?>
                    <p style="color:#666;font-size:13px;margin-top:8px;">
                        Mostrando saldos pendientes de todos los mensajeros de la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>)
                    </p>
                <?php endif; ?>
                
                <!-- Desglose por día agregado de todos los mensajeros (o del seleccionado) -->
                <?php if (!empty($desglose_dias_admin)): ?>
                    <div style="margin-top:16px;">
                        <h5 style="margin:0 0 12px 0;font-size:14px;color:#666;font-weight:600;">
                            📅 Desglose por Día (<?= count($desglose_dias_admin); ?> días con saldo pendiente)
                        </h5>
                        <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                            <table class="gofast-table" style="min-width:600px;width:100%;font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Comisión</th>
                                        <th>Transferencias</th>
                                        <th>Descuentos</th>
                                        <th>Pendiente</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                        <?php foreach ($desglose_dias_admin as $dia): ?>
                                        <?php 
                                        // Calcular total a pagar del día (antes de pagos): Comisión - Transferencias - Descuentos
                                        $total_a_pagar_dia = $dia->comision - $dia->transferencias - $dia->descuentos;
                                        
                                        // Calcular pendiente correctamente: Total a Pagar - Pagado
                                        // El pendiente del objeto ya debería estar calculado correctamente, pero lo verificamos
                                        $pendiente_calculado = $total_a_pagar_dia - (isset($dia->pagado) ? $dia->pagado : 0);
                                        
                                        // Usar el pendiente calculado si el del objeto está en 0 pero debería ser negativo
                                        // (para corregir casos donde se haya forzado a 0 incorrectamente)
                                        $pendiente_mostrar = ($dia->pendiente == 0 && $pendiente_calculado < 0) ? $pendiente_calculado : $dia->pendiente;
                                        ?>
                                        <tr>
                                            <td><?= esc_html(gofast_date_format($dia->fecha, 'd/m/Y')); ?></td>
                                            <td>$<?= number_format($dia->comision, 0, ',', '.'); ?></td>
                                            <td>$<?= number_format($dia->transferencias, 0, ',', '.'); ?></td>
                                            <td style="color:<?= $dia->descuentos < 0 ? '#4CAF50' : ($dia->descuentos > 0 ? '#ff9800' : '#666'); ?>;">
                                                <?php if ($dia->descuentos != 0): ?>
                                                    <?= $dia->descuentos < 0 ? '+' : '-' ?>$<?= number_format(abs($dia->descuentos), 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    $<?= number_format(0, 0, ',', '.'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight:600;color:<?= $pendiente_mostrar < 0 ? '#4CAF50' : ($pendiente_mostrar > 0 ? '#f44336' : '#666'); ?>;">
                                                <?php if ($pendiente_mostrar < 0): ?>
                                                    -$<?= number_format(abs($pendiente_mostrar), 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    $<?= number_format($pendiente_mostrar, 0, ',', '.'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                <tfoot>
                                    <?php 
                                    // Calcular totales desde el desglose mostrado para comisión y transferencias
                                    $total_comision_desglose = !empty($desglose_dias_admin) ? array_sum(array_map(function($d) { return $d->comision; }, $desglose_dias_admin)) : 0;
                                    $total_transferencias_desglose = !empty($desglose_dias_admin) ? array_sum(array_map(function($d) { return $d->transferencias; }, $desglose_dias_admin)) : 0;
                                    
                                    // IMPORTANTE: Usar el total global de descuentos (de la quincena) para que coincida con finanzas_admin
                                    // No solo la suma del desglose, porque puede haber descuentos en días sin ingresos
                                    $total_descuentos_desglose = $total_descuentos_saldos;
                                    
                                    // IMPORTANTE: Usar el mismo total calculado que el título para que coincidan
                                    // No usar la suma del desglose porque puede faltar días sin actividad pero con descuentos
                                    $total_pendiente_desglose_tfoot = $total_saldos_pendientes_acumulado;
                                    ?>
                                    <tr style="background:#f5f5f5;font-weight:700;">
                                        <td>Total</td>
                                        <td>$<?= number_format($total_comision_desglose, 0, ',', '.'); ?></td>
                                        <td>$<?= number_format($total_transferencias_desglose, 0, ',', '.'); ?></td>
                                        <td style="color:<?= $total_descuentos_desglose < 0 ? '#4CAF50' : ($total_descuentos_desglose > 0 ? '#ff9800' : '#666'); ?>;">
                                            <?php if (abs($total_descuentos_desglose) > 0.01): ?>
                                                <?= $total_descuentos_desglose < 0 ? '+' : '-' ?>$<?= number_format(abs($total_descuentos_desglose), 0, ',', '.'); ?>
                                            <?php else: ?>
                                                $<?= number_format(0, 0, ',', '.'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:<?= $total_pendiente_desglose_tfoot < 0 ? '#4CAF50' : ($total_pendiente_desglose_tfoot > 0 ? '#f44336' : '#666'); ?>;">
                                            <?php if ($total_pendiente_desglose_tfoot < 0): ?>
                                                -$<?= number_format(abs($total_pendiente_desglose_tfoot), 0, ',', '.'); ?>
                                            <?php else: ?>
                                                $<?= number_format($total_pendiente_desglose_tfoot, 0, ',', '.'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404;font-size:13px;margin-top:16px;">
                        ℹ️ No hay días con saldo pendiente en la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>).
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="gofast-box" style="margin-bottom:20px;">
                <h3 style="margin-top:0;">💵 Saldos Pendientes</h3>
                <p style="color:#666;">No hay mensajeros con saldos pendientes en la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>).</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Para mensajero: mostrar su historial individual -->
        <?php if (!empty($saldos_mensajeros)): ?>
            <?php foreach ($saldos_mensajeros as $saldo): ?>
                <div class="gofast-box" style="margin-bottom:20px;">
                    <h3 style="margin-top:0;">
                        💵 Saldos Pendientes
                        <?php if ($saldo->total_a_pagar > 0): ?>
                            <span style="font-size:14px;color:#666;font-weight:normal;">
                                (Total: $<?= number_format($saldo->total_a_pagar, 0, ',', '.'); ?>)
                            </span>
                        <?php endif; ?>
                    </h3>
                    
                    <p style="color:#666;font-size:13px;margin-top:8px;">
                        Mostrando tus saldos pendientes de la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>)
                    </p>
                    
                    <!-- Desglose por día -->
                    <?php if (!empty($saldo->desglose_dias)): ?>
                        <div style="margin-top:16px;">
                            <h5 style="margin:0 0 12px 0;font-size:14px;color:#666;font-weight:600;">
                                📅 Desglose por Día (<?= count($saldo->desglose_dias); ?> días con saldo pendiente)
                            </h5>
                            <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                                <table class="gofast-table" style="min-width:600px;width:100%;font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Comisión</th>
                                            <th>Transferencias</th>
                                            <th>Descuentos</th>
                                            <th>Pendiente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($saldo->desglose_dias as $dia): ?>
                                        <?php 
                                        // Calcular total a pagar del día (antes de pagos): Comisión - Transferencias - Descuentos
                                        $total_a_pagar_dia = $dia->comision - $dia->transferencias - $dia->descuentos;
                                        
                                        // Calcular pendiente correctamente: Total a Pagar - Pagado
                                        // El pendiente del objeto ya debería estar calculado correctamente, pero lo verificamos
                                        $pendiente_calculado = $total_a_pagar_dia - (isset($dia->pagado) ? $dia->pagado : 0);
                                        
                                        // Usar el pendiente calculado si el del objeto está en 0 pero debería ser negativo
                                        // (para corregir casos donde se haya forzado a 0 incorrectamente)
                                        $pendiente_mostrar = ($dia->pendiente == 0 && $pendiente_calculado < 0) ? $pendiente_calculado : $dia->pendiente;
                                        ?>
                                            <tr>
                                                <td><?= esc_html(gofast_date_format($dia->fecha, 'd/m/Y')); ?></td>
                                                <td>$<?= number_format($dia->comision, 0, ',', '.'); ?></td>
                                                <td>$<?= number_format($dia->transferencias, 0, ',', '.'); ?></td>
                                                <td style="color:<?= $dia->descuentos < 0 ? '#4CAF50' : ($dia->descuentos > 0 ? '#ff9800' : '#666'); ?>;">
                                                    <?php if ($dia->descuentos != 0): ?>
                                                        <?= $dia->descuentos < 0 ? '+' : '-' ?>$<?= number_format(abs($dia->descuentos), 0, ',', '.'); ?>
                                                    <?php else: ?>
                                                        $<?= number_format(0, 0, ',', '.'); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-weight:600;color:<?= $pendiente_mostrar < 0 ? '#4CAF50' : ($pendiente_mostrar > 0 ? '#f44336' : '#666'); ?>;">
                                                    <?php if ($pendiente_mostrar < 0): ?>
                                                        -$<?= number_format(abs($pendiente_mostrar), 0, ',', '.'); ?>
                                                    <?php else: ?>
                                                        $<?= number_format($pendiente_mostrar, 0, ',', '.'); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404;font-size:13px;margin-top:16px;">
                            ℹ️ No hay días con saldo pendiente en la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>).
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="gofast-box" style="margin-bottom:20px;">
                <h3 style="margin-top:0;">💵 Saldos Pendientes</h3>
                <p style="color:#666;">No tienes saldos pendientes en la quincena actual (<?= esc_html(gofast_date_format($fecha_desde_saldos, 'd/m/Y')); ?> al <?= esc_html(gofast_date_format($fecha_hasta_saldos, 'd/m/Y')); ?>).</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- =====================================================
         B2) PEDIDOS DEL DÍA ACTUAL
    ====================================================== -->
    <?php if (!empty($pedidos_hoy) || $total_pedidos_hoy > 0): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">
                📅 Pedidos del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)
                <span style="font-size:14px;color:#666;font-weight:normal;">
                    (<?= number_format($total_pedidos_hoy); ?> registro(s) total(es))
                </span>
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
            
            <?php if ($total_paginas_pedidos > 1): ?>
                <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <?php
                    $base_url_pedidos = get_permalink();
                    $query_args_pedidos = $_GET;
                    for ($i = 1; $i <= $total_paginas_pedidos; $i++):
                        $query_args_pedidos['pg_pedidos'] = $i;
                        $url_pedidos = esc_url( add_query_arg($query_args_pedidos, $base_url_pedidos) );
                        $active_pedidos = ($i === $pg_pedidos) ? 'gofast-page-current' : '';
                        ?>
                        <a href="<?php echo $url_pedidos; ?>" class="gofast-page-link <?php echo $active_pedidos; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_pedidos ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">📅 Pedidos del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)</h3>
            <p>No hay pedidos registrados para el día de hoy.</p>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         B3) COMPRAS DEL DÍA ACTUAL
    ====================================================== -->
    <?php if (!empty($compras_hoy) || $total_compras_hoy > 0): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">
                🛒 Compras del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)
                <span style="font-size:14px;color:#666;font-weight:normal;">
                    (<?= number_format($total_compras_hoy); ?> compra(s) total(es))
                </span>
            </h3>
            <div style="margin-bottom:10px;padding:8px;background:#f0f7ff;border-left:3px solid #2196F3;border-radius:4px;font-size:12px;color:#1976D2;">
                💡 <strong>En móvil:</strong> Desliza horizontalmente para ver todas las columnas
            </div>
            <div class="gofast-table-wrap" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;display:block;">
                <table class="gofast-table" style="min-width:800px;width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hora</th>
                            <?php if ($es_admin): ?>
                                <th>Mensajero</th>
                                <th>Creado por</th>
                            <?php endif; ?>
                            <th>Valor</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras_hoy as $compra): ?>
                            <tr>
                                <td>#<?= (int) $compra->id; ?></td>
                                <td><?= esc_html( gofast_date_format($compra->fecha_creacion, 'H:i') ); ?></td>
                                <?php if ($es_admin): ?>
                                    <td>
                                        <?= esc_html($compra->mensajero_nombre ?: 'N/A'); ?>
                                        <?php if ($compra->mensajero_telefono): ?>
                                            <br><small style="color:#666;"><?= esc_html($compra->mensajero_telefono); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc_html($compra->creador_nombre ?: 'N/A'); ?></td>
                                <?php endif; ?>
                                <td><strong>$<?= number_format($compra->valor, 0, ',', '.'); ?></strong></td>
                                <td><?= esc_html($compra->barrio_nombre ?: 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $estado_compra = $compra->estado;
                                    $estado_colors = [
                                        'pendiente' => '#fff3cd',
                                        'en_proceso' => '#cfe2ff',
                                        'completada' => '#d4edda',
                                        'cancelada' => '#f8d7da'
                                    ];
                                    $estado_labels = [
                                        'pendiente' => 'Pendiente',
                                        'en_proceso' => 'En Proceso',
                                        'completada' => 'Completada',
                                        'cancelada' => 'Cancelada'
                                    ];
                                    $color = $estado_colors[$estado_compra] ?? '#f8f9fa';
                                    $label = $estado_labels[$estado_compra] ?? $estado_compra;
                                    ?>
                                    <span style="display:inline-block;padding:4px 10px;border-radius:4px;background:<?= $color; ?>;font-size:12px;font-weight:600;">
                                        <?= esc_html($label); ?>
                                    </span>
                                </td>
                                <td><?= esc_html($compra->observaciones ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_paginas_compras_hoy > 1): ?>
                <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <?php
                    $base_url_compras_hoy = get_permalink();
                    $query_args_compras_hoy = $_GET;
                    for ($i = 1; $i <= $total_paginas_compras_hoy; $i++):
                        $query_args_compras_hoy['pg_compras_hoy'] = $i;
                        $url_compras_hoy = esc_url( add_query_arg($query_args_compras_hoy, $base_url_compras_hoy) );
                        $active_compras_hoy = ($i === $pg_compras_hoy) ? 'gofast-page-current' : '';
                        ?>
                        <a href="<?php echo $url_compras_hoy; ?>" class="gofast-page-link <?php echo $active_compras_hoy; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_compras_hoy ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">🛒 Compras del Día Actual (<?= gofast_date_format($fecha_hoy, 'd/m/Y'); ?>)</h3>
            <p>No hay compras registradas para el día de hoy.</p>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         C) PEDIDOS POR DÍA (ÚLTIMOS 30 DÍAS)
    ====================================================== -->
    <?php if (!empty($pedidos_por_dia)): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
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
                            <?php if (!($es_admin && $negocio_id > 0)): ?>
                            <th>Cantidad de Compras</th>
                            <?php endif; ?>
                            <th>Ingresos</th>
                            <th>Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_por_dia as $dia): ?>
                            <tr>
                                <td><?= esc_html( gofast_date_format($dia['dia'], 'd/m/Y') ); ?></td>
                                <td><?= number_format($dia['cantidad_destinos']); ?></td>
                                <?php if (!($es_admin && $negocio_id > 0)): ?>
                                <td><?= number_format($dia['cantidad_compras']); ?></td>
                                <?php endif; ?>
                                <td>$<?= number_format($dia['ingresos'], 0, ',', '.'); ?></td>
                                <td>$<?= number_format($dia['comision'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_paginas_dias > 1): ?>
                <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <?php
                    $base_url_dias = get_permalink();
                    $query_args_dias = $_GET;
                    for ($i = 1; $i <= $total_paginas_dias; $i++):
                        $query_args_dias['pg_dias'] = $i;
                        $url_dias = esc_url( add_query_arg($query_args_dias, $base_url_dias) );
                        $active_dias = ($i === $pg_dias) ? 'gofast-page-current' : '';
                        ?>
                        <a href="<?php echo $url_dias; ?>" class="gofast-page-link <?php echo $active_dias; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_dias ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
(function() {
    // Asegurar que las funciones normalize y matcherDestinos estén disponibles
    if (typeof window.normalize === 'undefined') {
        window.normalize = function(s) {
            return (s || "")
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        };
    }
    
    if (typeof window.matcherDestinos === 'undefined') {
        window.matcherDestinos = function(params, data) {
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

            const term = window.normalize(params.term);
            if (!term) {
                data.matchScore = 0;
                return data;
            }

            const text = window.normalize(data.text);
            
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
            
            let score = 0;
            
            const textWithoutStopWords = text.split(/\s+/).filter(w => !stopWords.includes(w)).join(' ');
            const termWithoutStopWords = searchWords.join(' ');
            
            if (textWithoutStopWords === termWithoutStopWords) {
                score = 10000;
            } else if (textWithoutStopWords.indexOf(termWithoutStopWords) === 0) {
                score = 9000;
            } else if (textWithoutStopWords.indexOf(termWithoutStopWords) !== -1) {
                score = 8000;
            } else if (searchWords.some(word => text.indexOf(word) === 0)) {
                score = 7000;
            } else if (text.indexOf(term) !== -1) {
                score = 6000;
            } else {
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
    }
    
    // Inicializar Select2 para filtro de mensajero
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
                allowClear: false,
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
        
        // Ocultar select original cuando Select2 está activo
        jQuery('.gofast-select-filtro').each(function() {
            if (jQuery(this).data('select2')) {
                jQuery(this).css({
                    'visibility': 'hidden',
                    'position': 'absolute',
                    'width': '1px',
                    'height': '1px',
                    'opacity': '0',
                    'pointer-events': 'none'
                });
            }
        });
    } else {
        // Reintentar después de un breve delay
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
                        allowClear: false,
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
                
                // Ocultar select original
                jQuery('.gofast-select-filtro').each(function() {
                    if (jQuery(this).data('select2')) {
                        jQuery(this).css({
                            'visibility': 'hidden',
                            'position': 'absolute',
                            'width': '1px',
                            'height': '1px',
                            'opacity': '0',
                            'pointer-events': 'none'
                        });
                    }
                });
            }
        }, 500);
    }
})();
</script>

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
        align-items: stretch !important;
    }
    
    .gofast-pedidos-filtros-row > div {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
    }
    
    .gofast-pedidos-filtros label {
        margin-bottom: 4px !important;
    }
    
    .gofast-pedidos-filtros input,
    .gofast-pedidos-filtros select {
        height: 46px !important;
        font-size: 16px !important;
    }
    
    .gofast-pedidos-filtros .gofast-select-filtro + .select2-container .select2-selection--single {
        height: 46px !important;
    }
    
    .gofast-pedidos-filtros .gofast-select-filtro + .select2-container .select2-selection__rendered {
        line-height: 46px !important;
    }
    
    .gofast-pedidos-filtros-actions {
        flex-direction: column !important;
        width: 100% !important;
        gap: 8px !important;
    }
    
    .gofast-pedidos-filtros-actions button,
    .gofast-pedidos-filtros-actions a {
        width: 100% !important;
        text-align: center !important;
        height: 46px !important;
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
    
    /* Estilos para paginación */
    .gofast-pagination {
        margin-top: 20px !important;
        display: flex !important;
        gap: 8px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    
    .gofast-page-link {
        padding: 8px 12px !important;
        border: 1px solid #ddd !important;
        border-radius: 6px !important;
        text-decoration: none !important;
        color: #333 !important;
        background: #fff !important;
        font-size: 14px !important;
        min-width: 40px !important;
        text-align: center !important;
        transition: all 0.2s ease !important;
    }
    
    .gofast-page-link:hover {
        background: #f5f5f5 !important;
        border-color: #bbb !important;
    }
    
    .gofast-page-current {
        background: var(--gofast-yellow, #F4C524) !important;
        font-weight: 700 !important;
        border-color: var(--gofast-yellow, #F4C524) !important;
        color: #000 !important;
    }
    
    @media (max-width: 768px) {
        .gofast-pagination {
            gap: 6px !important;
        }
        
        .gofast-page-link {
            padding: 6px 10px !important;
            font-size: 13px !important;
            min-width: 36px !important;
        }
    }
}
</style>

