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
    
    // Aplicar filtro de negocio si existe
    if ($es_admin && $negocio_id > 0) {
        $where_pedidos_dia .= " AND JSON_EXTRACT(destinos, '$.origen.negocio_id') = %d";
        $params_pedidos_dia[] = $negocio_id;
    }
    
    $where_pedidos_dia .= " AND fecha >= %s AND fecha <= %s";
    $params_pedidos_dia[] = $fecha_desde_30dias . ' 00:00:00';
    $params_pedidos_dia[] = $fecha_hasta_hoy . ' 23:59:59';
    
    // Construir WHERE para compras por día (solo si NO se filtra por negocio)
    $where_compras_dia = "1=1";
    $params_compras_dia = [];
    
    if (!($es_admin && $negocio_id > 0)) {
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
    }
    
    // Contar total de días únicos para paginación
    if (!empty($params_pedidos_dia)) {
        $sql_count_dias = $wpdb->prepare(
            "SELECT COUNT(DISTINCT DATE(fecha))
             FROM $tabla
             WHERE $where_pedidos_dia AND tracking_estado != 'cancelado'",
            $params_pedidos_dia
        );
    } else {
        $sql_count_dias = "SELECT COUNT(DISTINCT DATE(fecha))
             FROM $tabla
             WHERE $where_pedidos_dia AND tracking_estado != 'cancelado'";
    }
    $total_dias = (int) ($wpdb->get_var($sql_count_dias) ?? 0);
    $total_paginas_dias = max(1, (int) ceil($total_dias / $por_pagina));
    $offset_dias = ($pg_dias - 1) * $por_pagina;
    
    if (!empty($params_pedidos_dia)) {
        // Consulta de servicios con destinos y ingresos (con paginación)
        $params_pedidos_dia_limit = $params_pedidos_dia;
        $params_pedidos_dia_limit[] = $por_pagina;
        $params_pedidos_dia_limit[] = $offset_dias;
        
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
                 LIMIT %d OFFSET %d",
                $params_pedidos_dia_limit
            )
        );
        
        // Consulta de compras por día (cantidad e ingresos) - solo si NO se filtra por negocio
        $compras_por_dia = [];
        if (!($es_admin && $negocio_id > 0) && !empty($params_compras_dia)) {
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
        $total_dias = 0;
        $total_paginas_dias = 0;
    }

    /* ==========================================================
       3. Lista de mensajeros y negocios para filtro (solo admin)
    ========================================================== */
    $mensajeros = [];
    $negocios = [];
    if ($es_admin) {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre 
             FROM usuarios_gofast
             WHERE rol = 'mensajero' AND activo = 1
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

