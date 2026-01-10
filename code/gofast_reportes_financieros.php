<?php
/***************************************************
 * GOFAST – REPORTES FINANCIEROS
 * Shortcode: [gofast_reportes_financieros]
 * URL: /reportes-financieros
 * 
 * Genera reportes financieros en formato PDF/HTML
 * - Resumen General
 * - Saldos Mensajeros
 * - Historial de Pagos
 ***************************************************/

function gofast_reportes_financieros_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar usuario admin
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a esta sección.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    if ($rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo los administradores pueden acceder a esta sección.</div>";
    }

    // Obtener filtros de quincena (solo quincenas, no rangos de fechas)
    $quincena_mes = isset($_GET['quincena_mes']) ? sanitize_text_field($_GET['quincena_mes']) : '';
    $quincena_numero = isset($_GET['quincena_numero']) ? (int) $_GET['quincena_numero'] : 0;
    
    // Calcular quincena actual automáticamente si no se especifica
    $fecha_actual = gofast_current_time('Y-m-d');
    $timezone = new DateTimeZone('America/Bogota');
    $datetime = new DateTime($fecha_actual, $timezone);
    $mes_actual = $datetime->format('Y-m');
    $dia_actual = (int) $datetime->format('d');
    
    // Si no se especifica quincena, usar la quincena actual
    if (empty($quincena_mes)) {
        $quincena_mes = $mes_actual;
    }
    if ($quincena_numero === 0) {
        $quincena_numero = ($dia_actual <= 15) ? 1 : 2;
    }
    
    // Calcular fechas de la quincena seleccionada
    $datetime_quincena = new DateTime($quincena_mes . '-01', $timezone);
    $ultimo_dia_mes = (int) $datetime_quincena->format('t');
    
    if ($quincena_numero == 1) {
        // Primera quincena: del 1 al 15
        $fecha_desde = $quincena_mes . '-01';
        $fecha_hasta = $quincena_mes . '-15';
    } else {
        // Segunda quincena: del 16 al fin de mes
        $fecha_desde = $quincena_mes . '-16';
        $fecha_hasta = $quincena_mes . '-' . str_pad($ultimo_dia_mes, 2, '0', STR_PAD_LEFT);
    }
    
    // Usar las mismas fechas para todos los cálculos
    $fecha_desde_saldos = $fecha_desde;
    $fecha_hasta_saldos = $fecha_hasta;

    $mensaje = '';
    $mensaje_tipo = '';

    // ========================================
    // CALCULAR DATOS FINANCIEROS - SOLO DE LA QUINCENA SELECCIONADA
    // ========================================
    
    // Total Ingresos (servicios + compras) de la quincena
    $total_ingresos_servicios = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
             WHERE tracking_estado != 'cancelado' 
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $total_ingresos_compras = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
             WHERE estado != 'cancelada' 
             AND fecha_creacion >= %s AND fecha_creacion <= %s",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $total_ingresos = $total_ingresos_servicios + $total_ingresos_compras;
    
    // Total Egresos de la quincena
    $total_egresos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde, $fecha_hasta
        )
    ) ?? 0);
    
    // Total Vales Empresa de la quincena
    $total_vales_empresa = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde, $fecha_hasta
        )
    ) ?? 0);
    
    // Total Transferencias Ingresos de la quincena (aprobadas)
    // Solo incluir: transferencias tipo "normal" y transferencias tipo "pago" asociadas a pagos registrados
    $where_transf_entradas = ["estado = 'aprobada'"];
    $where_transf_entradas[] = "fecha_creacion >= %s";
    $where_transf_entradas[] = "fecha_creacion <= %s";
    
    $sql_transf_entradas = "
        SELECT COALESCE(SUM(t.valor), 0) 
        FROM transferencias_gofast t
        WHERE " . implode(' AND ', $where_transf_entradas) . "
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
    
    $total_transferencias_ingresos = (float) ($wpdb->get_var(
        $wpdb->prepare($sql_transf_entradas, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59')
    ) ?? 0);
    
    // Total Transferencias Salidas de la quincena
    $total_transferencias_salidas = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde, $fecha_hasta
        )
    ) ?? 0);
    
    // Total Descuentos de la quincena
    $total_descuentos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde, $fecha_hasta
        )
    ) ?? 0);
    
    // Saldo Transferencias
    $saldo_transferencias = $total_transferencias_ingresos - $total_transferencias_salidas;
    
    // Calcular comisiones (20% de los ingresos totales)
    $total_comisiones = $total_ingresos * 0.20;

    // Saldos Mensajeros - Calculado solo para la quincena actual (igual que reportes_admin)
    // NOTA: No filtramos por activo=1 para incluir mensajeros deshabilitados en reportes financieros
    $mensajeros = $wpdb->get_results("SELECT id, nombre, telefono, email FROM usuarios_gofast WHERE rol = 'mensajero' ORDER BY nombre ASC");
    
    $saldos_mensajeros = [];
    foreach ($mensajeros as $mensajero) {
        $mensajero_id = (int) $mensajero->id;
        
        // Ingresos de la quincena (para mostrar en reporte)
        $ingresos_servicios = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        );
        
        $ingresos_compras = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada'
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $mensajero_id, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        );
        
        $ingresos_totales = $ingresos_servicios + $ingresos_compras;
        $comision_generada = $ingresos_totales * 0.20;
        
        // Transferencias aprobadas de la quincena
        // Solo incluir: transferencias tipo "normal" (excluir tipo "pago")
        // Las transferencias tipo "pago" se contabilizan en los pagos
        $transferencias_aprobadas = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE mensajero_id = %d AND estado = 'aprobada'
                 AND (tipo = 'normal' OR tipo IS NULL)
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $mensajero_id, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        );
        
        // Descuentos de la quincena
        $descuentos = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        // Pagos de la quincena
        $pagos_rango = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia')
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        // TOTAL A PAGAR: Solo de la quincena actual (igual que reportes_admin)
        // Calcular comisión de la quincena actual
        $comision_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) * 0.20 FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado' 
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        $comision_compras_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) * 0.20 FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada' 
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $mensajero_id, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        // Transferencias de la quincena: incluir tipo 'normal' y tipo 'pago' asociadas a pagos (igual que reportes_admin)
        $transferencias_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(t.valor), 0)
                 FROM transferencias_gofast t
                 WHERE t.mensajero_id = %d
                 AND t.estado = 'aprobada'
                 AND t.fecha_creacion >= %s AND t.fecha_creacion <= %s
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
                 )",
                $mensajero_id, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59'
            )
        ) ?? 0);
        
        // Descuentos de la quincena
        $descuentos_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde_saldos, $fecha_hasta_saldos
            )
        ) ?? 0);
        
        // Pagos de la quincena: solo pagos en efectivo (igual que reportes_admin)
        // NOTA: Los pagos por transferencia se contabilizan como transferencias ingresos tipo 'pago'
        $pagos_quincena = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago = 'efectivo'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id, $fecha_desde_saldos, $fecha_hasta_saldos
            )
        ) ?? 0);
        
        // Total pendiente de la quincena actual
        $total_a_pagar = ($comision_quincena + $comision_compras_quincena) - $transferencias_quincena - $descuentos_quincena - $pagos_quincena;
        
        if ($comision_generada > 0 || $total_a_pagar > 0) {
            $saldos_mensajeros[] = (object) [
                'mensajero_id' => $mensajero_id,
                'mensajero_nombre' => $mensajero->nombre,
                'comision_generada' => $comision_generada,
                'transferencias_aprobadas' => $transferencias_aprobadas,
                'total_descuentos' => $descuentos,
                'total_pagos_rango' => $pagos_rango,
                'total_a_pagar' => max(0, $total_a_pagar)
            ];
        }
    }
    
    // Total saldos pendientes - Solo de la quincena actual (igual que reportes_admin)
    // Fórmula: Comisión(20% de ingresos) - Transferencias Ingresos - Descuentos - Pagos
    // Calcular usando la quincena actual, no el rango de fechas seleccionado
    
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
    $total_comisiones_saldos = $total_ingresos_saldos * 0.20;
    
    // Total Transferencias Ingresos de la quincena (igual que reportes_admin)
    $where_transf_entradas_saldos = ["estado = 'aprobada'"];
    $where_transf_entradas_saldos[] = "fecha_creacion >= %s";
    $where_transf_entradas_saldos[] = "fecha_creacion <= %s";
    
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
        $wpdb->prepare($sql_transf_entradas_saldos, $fecha_desde_saldos . ' 00:00:00', $fecha_hasta_saldos . ' 23:59:59')
    ) ?? 0);
    
    // Total Descuentos de la quincena
    $total_descuentos_saldos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde_saldos, $fecha_hasta_saldos
        )
    ) ?? 0);
    
    // Total Pagos en Efectivo de la quincena
    $total_pagos_efectivo_saldos = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
             WHERE tipo_pago = 'efectivo'
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde_saldos, $fecha_hasta_saldos
        )
    ) ?? 0);
    
    // Saldos Pendientes = Comisión - Transferencias Ingresos - Descuentos - Pagos en Efectivo
    $total_saldos_pendientes = $total_comisiones_saldos - $total_transferencias_ingresos_saldos - $total_descuentos_saldos - $total_pagos_efectivo_saldos;
    if ($total_saldos_pendientes < 0) {
        $total_saldos_pendientes = 0;
    }

    // Historial de Pagos de la quincena
    $pagos_historial = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.*, m.nombre as mensajero_nombre, u.nombre as creador_nombre
             FROM pagos_mensajeros_gofast p
             LEFT JOIN usuarios_gofast m ON p.mensajero_id = m.id
             LEFT JOIN usuarios_gofast u ON p.creado_por = u.id
             WHERE p.tipo_pago IN ('efectivo', 'transferencia')
             AND p.fecha >= %s AND p.fecha <= %s
             ORDER BY p.fecha DESC, p.fecha_pago DESC",
            $fecha_desde, $fecha_hasta
        )
    );

    // Cálculos finales
    $subtotal = $total_comisiones - $total_egresos - $total_vales_empresa - $total_descuentos;
    $efectivo = $subtotal - $saldo_transferencias - $total_saldos_pendientes;
    $utilidad_total = $subtotal;
    $utilidad_individual = $utilidad_total > 0 ? ($utilidad_total / 2) : 0;

    // ========================================
    // CÁLCULOS ADICIONALES PARA NUEVOS REPORTES
    // ========================================
    
    // Calcular quincena anterior para comparaciones
    $datetime_anterior = new DateTime($quincena_mes . '-01', $timezone);
    if ($quincena_numero == 1) {
        // Si es primera quincena, la anterior es la segunda del mes anterior
        $datetime_anterior->modify('-1 month');
        $ultimo_dia_mes_anterior = (int) $datetime_anterior->format('t');
        $fecha_desde_anterior = $datetime_anterior->format('Y-m') . '-16';
        $fecha_hasta_anterior = $datetime_anterior->format('Y-m') . '-' . str_pad($ultimo_dia_mes_anterior, 2, '0', STR_PAD_LEFT);
    } else {
        // Si es segunda quincena, la anterior es la primera del mismo mes
        $fecha_desde_anterior = $quincena_mes . '-01';
        $fecha_hasta_anterior = $quincena_mes . '-15';
    }
    
    // Ingresos de quincena anterior
    $total_ingresos_anterior = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) + COALESCE((SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada' AND fecha_creacion >= %s AND fecha_creacion <= %s), 0) 
             FROM servicios_gofast 
             WHERE tracking_estado != 'cancelado' AND fecha >= %s AND fecha <= %s",
            $fecha_desde_anterior . ' 00:00:00', $fecha_hasta_anterior . ' 23:59:59',
            $fecha_desde_anterior . ' 00:00:00', $fecha_hasta_anterior . ' 23:59:59'
        )
    ) ?? 0);
    $total_comisiones_anterior = $total_ingresos_anterior * 0.20;
    
    // Desglose diario de la quincena
    $ingresos_diarios = [];
    $fecha_inicio = new DateTime($fecha_desde);
    $fecha_fin = new DateTime($fecha_hasta);
    $fecha_fin->modify('+1 day');
    $intervalo = new DateInterval('P1D');
    $periodo_dias = new DatePeriod($fecha_inicio, $intervalo, $fecha_fin);
    
    foreach ($periodo_dias as $fecha_dia) {
        $fecha_dia_str = $fecha_dia->format('Y-m-d');
        $ingresos_servicios_dia = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                 WHERE tracking_estado != 'cancelado' AND DATE(fecha) = %s",
                $fecha_dia_str
            )
        ) ?? 0);
        $ingresos_compras_dia = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                 WHERE estado != 'cancelada' AND DATE(fecha_creacion) = %s",
                $fecha_dia_str
            )
        ) ?? 0);
        $ingresos_diarios[] = [
            'fecha' => $fecha_dia_str,
            'servicios' => $ingresos_servicios_dia,
            'compras' => $ingresos_compras_dia,
            'total' => $ingresos_servicios_dia + $ingresos_compras_dia,
            'comision' => ($ingresos_servicios_dia + $ingresos_compras_dia) * 0.20
        ];
    }
    
    // KPIs
    $dias_quincena = count($ingresos_diarios);
    $promedio_diario = $dias_quincena > 0 ? ($total_ingresos / $dias_quincena) : 0;
    $margen_ganancia = $total_ingresos > 0 ? (($utilidad_total / $total_ingresos) * 100) : 0;
    $porcentaje_comisiones = $total_ingresos > 0 ? (($total_comisiones / $total_ingresos) * 100) : 0;
    $variacion_anterior = $total_comisiones_anterior > 0 ? ((($total_comisiones - $total_comisiones_anterior) / $total_comisiones_anterior) * 100) : 0;
    
    // Rendimiento de mensajeros
    $rendimiento_mensajeros = [];
    foreach ($mensajeros as $mensajero) {
        $mensajero_id_rend = (int) $mensajero->id;
        
        // Pedidos entregados
        $pedidos_entregados = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado = 'entregado'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id_rend, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        ) ?? 0);
        
        // Pedidos cancelados
        $pedidos_cancelados = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado = 'cancelado'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id_rend, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        ) ?? 0);
        
        // Total pedidos
        $total_pedidos = $pedidos_entregados + $pedidos_cancelados;
        $tasa_entrega = $total_pedidos > 0 ? (($pedidos_entregados / $total_pedidos) * 100) : 0;
        
        // Comisiones generadas
        $comision_rend = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) * 0.20 + COALESCE((SELECT SUM(valor) * 0.20 FROM compras_gofast WHERE mensajero_id = %d AND estado != 'cancelada' AND fecha_creacion >= %s AND fecha_creacion <= %s), 0)
                 FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado'
                 AND fecha >= %s AND fecha <= %s",
                $mensajero_id_rend, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59',
                $mensajero_id_rend, $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        ) ?? 0);
        
        if ($total_pedidos > 0 || $comision_rend > 0) {
            $rendimiento_mensajeros[] = (object) [
                'mensajero_id' => $mensajero_id_rend,
                'mensajero_nombre' => $mensajero->nombre,
                'pedidos_entregados' => $pedidos_entregados,
                'pedidos_cancelados' => $pedidos_cancelados,
                'total_pedidos' => $total_pedidos,
                'tasa_entrega' => $tasa_entrega,
                'comision_generada' => $comision_rend
            ];
        }
    }
    
    // Ordenar por comisión generada
    usort($rendimiento_mensajeros, function($a, $b) {
        return $b->comision_generada <=> $a->comision_generada;
    });
    
    // Descuentos por mensajero
    $descuentos_por_mensajero = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT d.mensajero_id, m.nombre as mensajero_nombre, 
                    COUNT(*) as cantidad, SUM(d.valor) as total,
                    GROUP_CONCAT(DISTINCT d.motivo SEPARATOR ', ') as motivos
             FROM descuentos_mensajeros_gofast d
             LEFT JOIN usuarios_gofast m ON d.mensajero_id = m.id
             WHERE d.fecha >= %s AND d.fecha <= %s
             GROUP BY d.mensajero_id, m.nombre
             ORDER BY total DESC",
            $fecha_desde, $fecha_hasta
        )
    );
    
    // Egresos por categoría (usando descripción como categoría aproximada)
    $egresos_por_categoria = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT descripcion, COUNT(*) as cantidad, SUM(valor) as total
             FROM egresos_gofast
             WHERE fecha >= %s AND fecha <= %s
             GROUP BY descripcion
             ORDER BY total DESC",
            $fecha_desde, $fecha_hasta
        )
    );
    
    // Vales empresa vs personal
    $total_vales_personal = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM vales_personal_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde, $fecha_hasta
        )
    ) ?? 0);
    
    // Servicios normal vs intermunicipal
    $servicios_normal = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
             WHERE tracking_estado != 'cancelado' 
             AND fecha >= %s AND fecha <= %s
             AND (JSON_EXTRACT(destinos, '$.tipo_servicio') IS NULL OR JSON_EXTRACT(destinos, '$.tipo_servicio') != 'intermunicipal')
             AND direccion_origen NOT LIKE '%%(Intermunicipal)%%'",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $servicios_intermunicipal = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
             WHERE tracking_estado != 'cancelado' 
             AND fecha >= %s AND fecha <= %s
             AND (JSON_EXTRACT(destinos, '$.tipo_servicio') = 'intermunicipal' OR direccion_origen LIKE '%%(Intermunicipal)%%')",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    // Pedidos por negocio
    $pedidos_por_negocio = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT JSON_EXTRACT(destinos, '$.origen.negocio_id') as negocio_id,
                    n.nombre as negocio_nombre,
                    COUNT(*) as cantidad_pedidos,
                    SUM(s.total) as total_ingresos
             FROM servicios_gofast s
             LEFT JOIN negocios_gofast n ON JSON_EXTRACT(s.destinos, '$.origen.negocio_id') = n.id
             WHERE s.tracking_estado != 'cancelado'
             AND s.fecha >= %s AND s.fecha <= %s
             AND JSON_EXTRACT(s.destinos, '$.origen.negocio_id') IS NOT NULL
             GROUP BY negocio_id, n.nombre
             ORDER BY total_ingresos DESC",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    );
    
    // Cancelaciones
    $total_cancelaciones = (int) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM servicios_gofast 
             WHERE tracking_estado = 'cancelado'
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $valor_cancelaciones = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
             WHERE tracking_estado = 'cancelado'
             AND fecha >= %s AND fecha <= %s",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $total_pedidos = (int) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM servicios_gofast 
             WHERE fecha >= %s AND fecha <= %s",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    ) ?? 0);
    
    $tasa_cancelacion = $total_pedidos > 0 ? (($total_cancelaciones / $total_pedidos) * 100) : 0;
    
    // Cancelaciones por mensajero
    $cancelaciones_por_mensajero = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.mensajero_id, m.nombre as mensajero_nombre,
                    COUNT(*) as cantidad_cancelaciones,
                    SUM(s.total) as valor_perdido
             FROM servicios_gofast s
             LEFT JOIN usuarios_gofast m ON s.mensajero_id = m.id
             WHERE s.tracking_estado = 'cancelado'
             AND s.fecha >= %s AND s.fecha <= %s
             GROUP BY s.mensajero_id, m.nombre
             ORDER BY cantidad_cancelaciones DESC",
            $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
        )
    );

    // ========================================
    // GENERAR REPORTE
    // ========================================
    
    $tipo_reporte = isset($_GET['tipo']) ? sanitize_text_field($_GET['tipo']) : '';
    $accion = isset($_GET['accion']) ? sanitize_text_field($_GET['accion']) : '';
    
    if (!empty($tipo_reporte) && in_array($accion, ['ver', 'descargar'])) {
        $logo_url = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
        
        $periodo = 'Quincena ' . $quincena_numero . ' de ' . gofast_date_format($quincena_mes . '-01', 'F Y') . ' (' . gofast_date_format($fecha_desde, 'd/m/Y') . ' - ' . gofast_date_format($fecha_hasta, 'd/m/Y') . ')';
        
        // Generar HTML del reporte
        $reporte_html = '
<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
    .container { max-width: 800px; margin: 0 auto; }
    .header { background: #F4C524; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
    .header img { max-height: 50px; }
    .periodo { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
    .section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #F4C524; padding-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #f8f9fa; font-weight: 600; }
    .text-right { text-align: right; }
    .total-row { background: #e8f5e9; font-weight: bold; }
    .negative { color: #dc3545; }
    .positive { color: #28a745; }
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
    .summary-box { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; }
    .summary-box .value { font-size: 24px; font-weight: bold; color: #333; }
    .summary-box .label { font-size: 12px; color: #666; }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 20px 0; }
    .kpi-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; text-align: center; }
    .kpi-box.positive { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .kpi-box.negative { background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); }
    .kpi-box .kpi-value { font-size: 32px; font-weight: bold; margin-bottom: 8px; }
    .kpi-box .kpi-label { font-size: 14px; opacity: 0.9; }
    .comparison { background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #F4C524; }
    .comparison .comparison-label { font-size: 12px; color: #666; }
    .comparison .comparison-value { font-size: 18px; font-weight: bold; color: #333; }
    .chart-container { margin: 20px 0; padding: 16px; background: #f8f9fa; border-radius: 8px; }
    .bar-chart { display: flex; align-items: flex-end; height: 200px; gap: 8px; }
    .bar-item { flex: 1; background: #F4C524; border-radius: 4px 4px 0 0; position: relative; min-height: 20px; }
    .bar-item:hover { opacity: 0.8; }
    .bar-label { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 11px; color: #666; white-space: nowrap; }
    .bar-value { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: bold; color: #333; }
    .table-stats { font-size: 13px; }
    .table-stats td { padding: 8px; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .badge-success { background: #28a745; color: #fff; }
    .badge-danger { background: #dc3545; color: #fff; }
    .badge-warning { background: #ffc107; color: #000; }
    .badge-info { background: #17a2b8; color: #fff; }
    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; padding-top: 20px; border-top: 1px solid #ddd; }
    @media print { body { padding: 10px; } .container { max-width: 100%; } .chart-container { page-break-inside: avoid; } }
</style>
<div class="container">
    <div class="header">
        <img src="' . $logo_url . '" alt="Go Fast">
        <p style="margin: 8px 0 0; font-size: 14px;">Reporte Financiero</p>
    </div>
    <div class="periodo">
        <strong>📅 Período:</strong> ' . esc_html($periodo) . '
        <br><small>Generado: ' . gofast_date_format(gofast_date_mysql(), 'd/m/Y H:i') . '</small>
    </div>';

        if ($tipo_reporte === 'resumen') {
            // Comparación con quincena anterior
            $variacion_class = $variacion_anterior >= 0 ? 'positive' : 'negative';
            $variacion_icon = $variacion_anterior >= 0 ? '📈' : '📉';
            
            $reporte_html .= '
    <div class="summary-grid">
        <div class="summary-box">
            <div class="value">$' . number_format($total_comisiones, 0, ',', '.') . '</div>
            <div class="label">Ingresos (Comisiones)</div>
        </div>
        <div class="summary-box">
            <div class="value">$' . number_format($total_egresos, 0, ',', '.') . '</div>
            <div class="label">Total Egresos</div>
        </div>
        <div class="summary-box">
            <div class="value">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</div>
            <div class="label">Saldos Pendientes</div>
        </div>
    </div>
    
    <div class="comparison">
        <div class="comparison-label">' . $variacion_icon . ' Comparación con Quincena Anterior</div>
        <div class="comparison-value">
            Actual: $' . number_format($total_comisiones, 0, ',', '.') . ' | 
            Anterior: $' . number_format($total_comisiones_anterior, 0, ',', '.') . ' | 
            Variación: <span class="' . $variacion_class . '">' . number_format($variacion_anterior, 2) . '%</span>
        </div>
    </div>
    
    <div class="kpi-grid">
        <div class="kpi-box positive">
            <div class="kpi-value">' . number_format($margen_ganancia, 1) . '%</div>
            <div class="kpi-label">Margen de Ganancia</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-value">$' . number_format($promedio_diario, 0, ',', '.') . '</div>
            <div class="kpi-label">Promedio Diario</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-value">' . number_format($porcentaje_comisiones, 1) . '%</div>
            <div class="kpi-label">% Comisiones</div>
        </div>
        <div class="kpi-box ' . ($variacion_anterior >= 0 ? 'positive' : 'negative') . '">
            <div class="kpi-value">' . number_format($variacion_anterior, 1) . '%</div>
            <div class="kpi-label">Crecimiento</div>
        </div>
    </div>
    
    <div class="section">
        <h3>📊 Resumen General</h3>
        <table>
            <tr><td>💰 Ingresos</td><td class="text-right">$' . number_format($total_comisiones, 0, ',', '.') . '</td></tr>
            <tr><td>📤 Total Egresos</td><td class="text-right negative">-$' . number_format($total_egresos, 0, ',', '.') . '</td></tr>
            <tr><td>🏢 Vales Empresa</td><td class="text-right negative">-$' . number_format($total_vales_empresa, 0, ',', '.') . '</td></tr>
            <tr><td>💸 Saldo Transferencias (Ingresos - Salidas)</td><td class="text-right">$' . number_format($saldo_transferencias, 0, ',', '.') . '</td></tr>
            <tr><td>💵 Saldos Pendientes Mensajeros</td><td class="text-right">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</td></tr>
            <tr><td>➖ Total Descuentos</td><td class="text-right negative">-$' . number_format($total_descuentos, 0, ',', '.') . '</td></tr>
            <tr class="total-row"><td><strong>📊 Subtotal (Comisiones - Egresos - Vales - Descuentos)</strong></td><td class="text-right"><strong>$' . number_format($subtotal, 0, ',', '.') . '</strong></td></tr>
            <tr class="total-row"><td><strong>💵 Efectivo Disponible</strong></td><td class="text-right positive"><strong>$' . number_format($efectivo, 0, ',', '.') . '</strong></td></tr>
            <tr class="total-row"><td><strong>📈 Utilidad Total</strong></td><td class="text-right positive"><strong>$' . number_format($utilidad_total, 0, ',', '.') . '</strong></td></tr>
            <tr><td>👤 Utilidad Individual (÷2)</td><td class="text-right">$' . number_format($utilidad_individual, 0, ',', '.') . '</td></tr>
        </table>
    </div>
    
    <div class="section">
        <h3>📅 Desglose Diario de la Quincena</h3>
        <div class="chart-container">
            <div class="bar-chart">';
            
            $max_ingreso = 0;
            foreach ($ingresos_diarios as $dia) {
                if ($dia['total'] > $max_ingreso) {
                    $max_ingreso = $dia['total'];
                }
            }
            
            foreach ($ingresos_diarios as $dia) {
                $altura = $max_ingreso > 0 ? (($dia['total'] / $max_ingreso) * 100) : 0;
                $reporte_html .= '
                <div class="bar-item" style="height: ' . max(20, $altura) . '%;">
                    <div class="bar-value">$' . number_format($dia['total'], 0, ',', '.') . '</div>
                    <div class="bar-label">' . gofast_date_format($dia['fecha'], 'd/m') . '</div>
                </div>';
            }
            
            $reporte_html .= '
            </div>
        </div>
        <table class="table-stats">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="text-right">Servicios</th>
                    <th class="text-right">Compras</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Comisión (20%)</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($ingresos_diarios as $dia) {
                $reporte_html .= '
                <tr>
                    <td>' . gofast_date_format($dia['fecha'], 'd/m/Y') . '</td>
                    <td class="text-right">$' . number_format($dia['servicios'], 0, ',', '.') . '</td>
                    <td class="text-right">$' . number_format($dia['compras'], 0, ',', '.') . '</td>
                    <td class="text-right"><strong>$' . number_format($dia['total'], 0, ',', '.') . '</strong></td>
                    <td class="text-right">$' . number_format($dia['comision'], 0, ',', '.') . '</td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }

        if ($tipo_reporte === 'saldos') {
            $reporte_html .= '
    <div class="section">
        <h3>💵 Saldos por Mensajero (Quincena Actual: ' . gofast_date_format($fecha_desde_saldos, 'd/m/Y') . ' - ' . gofast_date_format($fecha_hasta_saldos, 'd/m/Y') . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Mensajero</th>
                    <th class="text-right">Comisión</th>
                    <th class="text-right">Transferencias</th>
                    <th class="text-right">Descuentos</th>
                    <th class="text-right">Pagos</th>
                    <th class="text-right">Total a Pagar</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($saldos_mensajeros as $saldo) {
                $reporte_html .= '
                <tr>
                    <td>' . esc_html($saldo->mensajero_nombre) . '</td>
                    <td class="text-right">$' . number_format($saldo->comision_generada, 0, ',', '.') . '</td>
                    <td class="text-right">$' . number_format($saldo->transferencias_aprobadas, 0, ',', '.') . '</td>
                    <td class="text-right negative">$' . number_format($saldo->total_descuentos, 0, ',', '.') . '</td>
                    <td class="text-right">$' . number_format($saldo->total_pagos_rango, 0, ',', '.') . '</td>
                    <td class="text-right ' . ($saldo->total_a_pagar >= 0 ? 'positive' : 'negative') . '"><strong>$' . number_format($saldo->total_a_pagar, 0, ',', '.') . '</strong></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }

        if ($tipo_reporte === 'pagos') {
            $total_pagos = 0;
            $reporte_html .= '
    <div class="section">
        <h3>📋 Historial de Pagos</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Mensajero</th>
                    <th class="text-right">Tipo</th>
                    <th class="text-right">Total Pagado</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($pagos_historial as $pago) {
                $total_pagos += (float)$pago->total_a_pagar;
                $reporte_html .= '
                <tr>
                    <td>' . gofast_date_format($pago->fecha, 'd/m/Y') . '</td>
                    <td>' . esc_html($pago->mensajero_nombre ?? 'N/A') . '</td>
                    <td class="text-right">' . ($pago->tipo_pago === 'efectivo' ? '💵 Efectivo' : '💸 Transferencia') . '</td>
                    <td class="text-right positive">$' . number_format((float)$pago->total_a_pagar, 0, ',', '.') . '</td>
                </tr>';
            }
            
            $reporte_html .= '
                <tr class="total-row">
                    <td colspan="3"><strong>Total Pagos</strong></td>
                    <td class="text-right positive"><strong>$' . number_format($total_pagos, 0, ',', '.') . '</strong></td>
                </tr>
            </tbody>
        </table>
    </div>';
        }
        
        // ========================================
        // NUEVOS REPORTES
        // ========================================
        
        if ($tipo_reporte === 'rendimiento') {
            $reporte_html .= '
    <div class="section">
        <h3>🏆 Rendimiento de Mensajeros</h3>
        <table>
            <thead>
                <tr>
                    <th>Mensajero</th>
                    <th class="text-right">Pedidos Entregados</th>
                    <th class="text-right">Pedidos Cancelados</th>
                    <th class="text-right">Total Pedidos</th>
                    <th class="text-right">Tasa Entrega</th>
                    <th class="text-right">Comisión Generada</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($rendimiento_mensajeros as $rend) {
                $badge_class = $rend->tasa_entrega >= 90 ? 'badge-success' : ($rend->tasa_entrega >= 70 ? 'badge-warning' : 'badge-danger');
                $reporte_html .= '
                <tr>
                    <td><strong>' . esc_html($rend->mensajero_nombre) . '</strong></td>
                    <td class="text-right"><span class="badge badge-success">' . $rend->pedidos_entregados . '</span></td>
                    <td class="text-right"><span class="badge badge-danger">' . $rend->pedidos_cancelados . '</span></td>
                    <td class="text-right">' . $rend->total_pedidos . '</td>
                    <td class="text-right"><span class="badge ' . $badge_class . '">' . number_format($rend->tasa_entrega, 1) . '%</span></td>
                    <td class="text-right"><strong>$' . number_format($rend->comision_generada, 0, ',', '.') . '</strong></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }
        
        if ($tipo_reporte === 'transferencias') {
            $reporte_html .= '
    <div class="section">
        <h3>💸 Reporte de Transferencias</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">$' . number_format($total_transferencias_ingresos, 0, ',', '.') . '</div>
                <div class="label">Transferencias Ingresos</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($total_transferencias_salidas, 0, ',', '.') . '</div>
                <div class="label">Transferencias Salidas</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($saldo_transferencias, 0, ',', '.') . '</div>
                <div class="label">Saldo Neto</div>
            </div>
        </div>
        <p style="text-align: center; margin: 20px 0; padding: 12px; background: #f8f9fa; border-radius: 6px;">
            <strong>Saldo de Transferencias:</strong> ' . ($saldo_transferencias >= 0 ? 'A favor' : 'En contra') . ' por $' . number_format(abs($saldo_transferencias), 0, ',', '.') . '
        </p>
    </div>';
        }
        
        if ($tipo_reporte === 'descuentos') {
            $reporte_html .= '
    <div class="section">
        <h3>➖ Reporte de Descuentos</h3>
        <div class="summary-box" style="margin-bottom: 20px;">
            <div class="value">$' . number_format($total_descuentos, 0, ',', '.') . '</div>
            <div class="label">Total Descuentos de la Quincena</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Mensajero</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Total</th>
                    <th>Motivos</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($descuentos_por_mensajero as $desc) {
                $reporte_html .= '
                <tr>
                    <td>' . esc_html($desc->mensajero_nombre ?? 'N/A') . '</td>
                    <td class="text-right">' . $desc->cantidad . '</td>
                    <td class="text-right negative"><strong>$' . number_format((float)$desc->total, 0, ',', '.') . '</strong></td>
                    <td><small>' . esc_html($desc->motivos ?? 'N/A') . '</small></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }
        
        if ($tipo_reporte === 'egresos') {
            $reporte_html .= '
    <div class="section">
        <h3>💸 Reporte de Egresos y Vales</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">$' . number_format($total_egresos, 0, ',', '.') . '</div>
                <div class="label">Total Egresos</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($total_vales_empresa, 0, ',', '.') . '</div>
                <div class="label">Vales Empresa</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($total_vales_personal, 0, ',', '.') . '</div>
                <div class="label">Vales Personal</div>
            </div>
        </div>
        <h4 style="margin-top: 20px;">Egresos por Categoría</h4>
        <table>
            <thead>
                <tr>
                    <th>Categoría/Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($egresos_por_categoria as $egr) {
                $reporte_html .= '
                <tr>
                    <td>' . esc_html($egr->descripcion) . '</td>
                    <td class="text-right">' . $egr->cantidad . '</td>
                    <td class="text-right negative"><strong>$' . number_format((float)$egr->total, 0, ',', '.') . '</strong></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }
        
        if ($tipo_reporte === 'negocios') {
            $reporte_html .= '
    <div class="section">
        <h3>🏪 Pedidos por Negocio</h3>
        <table>
            <thead>
                <tr>
                    <th>Negocio</th>
                    <th class="text-right">Cantidad Pedidos</th>
                    <th class="text-right">Total Ingresos</th>
                    <th class="text-right">Comisión (20%)</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($pedidos_por_negocio as $neg) {
                $comision_neg = (float)$neg->total_ingresos * 0.20;
                $reporte_html .= '
                <tr>
                    <td><strong>' . esc_html($neg->negocio_nombre ?? 'N/A') . '</strong></td>
                    <td class="text-right">' . $neg->cantidad_pedidos . '</td>
                    <td class="text-right">$' . number_format((float)$neg->total_ingresos, 0, ',', '.') . '</td>
                    <td class="text-right"><strong>$' . number_format($comision_neg, 0, ',', '.') . '</strong></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }
        
        if ($tipo_reporte === 'servicios') {
            $total_servicios = $servicios_normal + $servicios_intermunicipal;
            $porcentaje_normal = $total_servicios > 0 ? (($servicios_normal / $total_servicios) * 100) : 0;
            $porcentaje_inter = $total_servicios > 0 ? (($servicios_intermunicipal / $total_servicios) * 100) : 0;
            
            $reporte_html .= '
    <div class="section">
        <h3>🚚 Servicios Normal vs Intermunicipal</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">$' . number_format($servicios_normal, 0, ',', '.') . '</div>
                <div class="label">Servicios Normales (' . number_format($porcentaje_normal, 1) . '%)</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($servicios_intermunicipal, 0, ',', '.') . '</div>
                <div class="label">Servicios Intermunicipales (' . number_format($porcentaje_inter, 1) . '%)</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($total_servicios, 0, ',', '.') . '</div>
                <div class="label">Total Servicios</div>
            </div>
        </div>
        <div class="chart-container">
            <div class="bar-chart">';
            
            $max_serv = max($servicios_normal, $servicios_intermunicipal);
            $altura_normal = $max_serv > 0 ? (($servicios_normal / $max_serv) * 100) : 0;
            $altura_inter = $max_serv > 0 ? (($servicios_intermunicipal / $max_serv) * 100) : 0;
            
            $reporte_html .= '
                <div class="bar-item" style="height: ' . max(20, $altura_normal) . '%; background: #28a745;">
                    <div class="bar-value">$' . number_format($servicios_normal, 0, ',', '.') . '</div>
                    <div class="bar-label">Normal</div>
                </div>
                <div class="bar-item" style="height: ' . max(20, $altura_inter) . '%; background: #17a2b8;">
                    <div class="bar-value">$' . number_format($servicios_intermunicipal, 0, ',', '.') . '</div>
                    <div class="bar-label">Intermunicipal</div>
                </div>';
            
            $reporte_html .= '
            </div>
        </div>
    </div>';
        }
        
        if ($tipo_reporte === 'compras') {
            $total_compras = $total_ingresos_compras;
            $comision_compras = $total_compras * 0.20;
            
            $reporte_html .= '
    <div class="section">
        <h3>🛒 Reporte de Compras</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">$' . number_format($total_compras, 0, ',', '.') . '</div>
                <div class="label">Total Compras</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($comision_compras, 0, ',', '.') . '</div>
                <div class="label">Comisión (20%)</div>
            </div>
        </div>
        <p style="text-align: center; margin: 20px 0; padding: 12px; background: #f8f9fa; border-radius: 6px;">
            Las compras representan el <strong>' . number_format(($total_compras / max(1, $total_ingresos)) * 100, 1) . '%</strong> del total de ingresos de la quincena.
        </p>
    </div>';
        }
        
        if ($tipo_reporte === 'cancelaciones') {
            $reporte_html .= '
    <div class="section">
        <h3>❌ Reporte de Cancelaciones</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">' . $total_cancelaciones . '</div>
                <div class="label">Total Cancelaciones</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($valor_cancelaciones, 0, ',', '.') . '</div>
                <div class="label">Valor Perdido</div>
            </div>
            <div class="summary-box">
                <div class="value">' . number_format($tasa_cancelacion, 1) . '%</div>
                <div class="label">Tasa de Cancelación</div>
            </div>
        </div>
        <h4 style="margin-top: 20px;">Cancelaciones por Mensajero</h4>
        <table>
            <thead>
                <tr>
                    <th>Mensajero</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Valor Perdido</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($cancelaciones_por_mensajero as $cancel) {
                $reporte_html .= '
                <tr>
                    <td>' . esc_html($cancel->mensajero_nombre ?? 'N/A') . '</td>
                    <td class="text-right"><span class="badge badge-danger">' . $cancel->cantidad_cancelaciones . '</span></td>
                    <td class="text-right negative"><strong>$' . number_format((float)$cancel->valor_perdido, 0, ',', '.') . '</strong></td>
                </tr>';
            }
            
            $reporte_html .= '
            </tbody>
        </table>
    </div>';
        }
        
        if ($tipo_reporte === 'consolidado') {
            // Calcular datos del mes completo
            $mes_completo = $quincena_mes;
            $fecha_inicio_mes = $mes_completo . '-01';
            $datetime_mes = new DateTime($fecha_inicio_mes, $timezone);
            $ultimo_dia_mes_completo = (int) $datetime_mes->format('t');
            $fecha_fin_mes = $mes_completo . '-' . str_pad($ultimo_dia_mes_completo, 2, '0', STR_PAD_LEFT);
            
            // Primera quincena del mes
            $fecha_desde_q1 = $mes_completo . '-01';
            $fecha_hasta_q1 = $mes_completo . '-15';
            
            // Segunda quincena del mes
            $fecha_desde_q2 = $mes_completo . '-16';
            $fecha_hasta_q2 = $fecha_fin_mes;
            
            // Calcular ingresos de ambas quincenas
            $ingresos_q1 = (float) ($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(total), 0) + COALESCE((SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada' AND fecha_creacion >= %s AND fecha_creacion <= %s), 0)
                     FROM servicios_gofast WHERE tracking_estado != 'cancelado' AND fecha >= %s AND fecha <= %s",
                    $fecha_desde_q1 . ' 00:00:00', $fecha_hasta_q1 . ' 23:59:59',
                    $fecha_desde_q1 . ' 00:00:00', $fecha_hasta_q1 . ' 23:59:59'
                )
            ) ?? 0);
            
            $ingresos_q2 = (float) ($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(total), 0) + COALESCE((SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada' AND fecha_creacion >= %s AND fecha_creacion <= %s), 0)
                     FROM servicios_gofast WHERE tracking_estado != 'cancelado' AND fecha >= %s AND fecha <= %s",
                    $fecha_desde_q2 . ' 00:00:00', $fecha_hasta_q2 . ' 23:59:59',
                    $fecha_desde_q2 . ' 00:00:00', $fecha_hasta_q2 . ' 23:59:59'
                )
            ) ?? 0);
            
            $comisiones_q1 = $ingresos_q1 * 0.20;
            $comisiones_q2 = $ingresos_q2 * 0.20;
            $total_mes = $comisiones_q1 + $comisiones_q2;
            
            $reporte_html .= '
    <div class="section">
        <h3>📊 Reporte Consolidado Mensual - ' . gofast_date_format($mes_completo . '-01', 'F Y') . '</h3>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="value">$' . number_format($comisiones_q1, 0, ',', '.') . '</div>
                <div class="label">Quincena 1 (1-15)</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($comisiones_q2, 0, ',', '.') . '</div>
                <div class="label">Quincena 2 (16-' . $ultimo_dia_mes_completo . ')</div>
            </div>
            <div class="summary-box">
                <div class="value">$' . number_format($total_mes, 0, ',', '.') . '</div>
                <div class="label">Total del Mes</div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th class="text-right">Ingresos</th>
                    <th class="text-right">Comisiones (20%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Primera Quincena</strong></td>
                    <td class="text-right">$' . number_format($ingresos_q1, 0, ',', '.') . '</td>
                    <td class="text-right"><strong>$' . number_format($comisiones_q1, 0, ',', '.') . '</strong></td>
                </tr>
                <tr>
                    <td><strong>Segunda Quincena</strong></td>
                    <td class="text-right">$' . number_format($ingresos_q2, 0, ',', '.') . '</td>
                    <td class="text-right"><strong>$' . number_format($comisiones_q2, 0, ',', '.') . '</strong></td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total del Mes</strong></td>
                    <td class="text-right"><strong>$' . number_format($ingresos_q1 + $ingresos_q2, 0, ',', '.') . '</strong></td>
                    <td class="text-right"><strong>$' . number_format($total_mes, 0, ',', '.') . '</strong></td>
                </tr>
            </tbody>
        </table>
    </div>';
        }

        $reporte_html .= '
    <div class="footer">
        <p>Reporte generado automáticamente por Go Fast</p>
    </div>
</div>';

        if ($accion === 'descargar') {
            // Limpiar buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Página limpia para PDF
            ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Go Fast - <?= ucfirst($tipo_reporte) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; }
        @media print { .no-print { display: none !important; } }
        .print-header {
            background: #f8f9fa;
            padding: 16px 20px;
            text-align: center;
            border-bottom: 2px solid #F4C524;
            position: sticky;
            top: 0;
        }
        .print-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            margin: 0 8px;
            font-weight: 600;
        }
        .print-btn:hover { background: #218838; }
        .close-btn {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="no-print print-header">
        <button class="print-btn" onclick="window.print()">📥 Guardar como PDF / Imprimir</button>
        <button class="close-btn" onclick="window.close()">✕ Cerrar</button>
        <p style="margin: 10px 0 0; font-size: 12px; color: #666;">
            💡 En el diálogo de impresión, selecciona "Guardar como PDF" como destino.
        </p>
    </div>
    <?= $reporte_html ?>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 300); };</script>
</body>
</html><?php
            die();
        }
        
        // Acción "ver" - mostrar vista previa
        if ($accion === 'ver') {
            // Continúa abajo para mostrar la interfaz con el reporte
        }
    }

    // ========================================
    // ENVIAR REPORTE POR CORREO
    // ========================================
    if (isset($_POST['gofast_enviar_reporte']) && wp_verify_nonce($_POST['gofast_enviar_reporte_nonce'], 'gofast_enviar_reporte')) {
        $tipo_reporte_email = sanitize_text_field($_POST['tipo_reporte'] ?? 'resumen');
        $email_destino = sanitize_email($_POST['email_destino'] ?? '');
        $asunto_reporte = sanitize_text_field($_POST['asunto_reporte'] ?? 'Reporte Financiero Go Fast');
        
        if (empty($email_destino) || !is_email($email_destino)) {
            $mensaje = 'Por favor ingresa un correo válido.';
            $mensaje_tipo = 'error';
        } else {
            // Generar el reporte HTML completo para el correo
            $logo_url = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
            $periodo_email = 'Quincena ' . $quincena_numero . ' de ' . gofast_date_format($quincena_mes . '-01', 'F Y') . ' (' . gofast_date_format($fecha_desde, 'd/m/Y') . ' - ' . gofast_date_format($fecha_hasta, 'd/m/Y') . ')';
            
            $email_html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #000; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #F4C524; padding: 20px; text-align: center; }
        .header img { max-height: 50px; }
        .content { padding: 20px; }
        .periodo { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .section h3 { margin-top: 0; color: #000; border-bottom: 2px solid #F4C524; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; color: #000; }
        th { background: #f8f9fa; font-weight: 600; }
        .text-right { text-align: right; }
        .total-row { background: #e8f5e9; font-weight: bold; }
        .negative { color: #dc3545; }
        .positive { color: #28a745; }
        .summary-grid { margin-bottom: 20px; }
        .summary-box { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; display: inline-block; width: 30%; margin: 1%; }
        .summary-box .value { font-size: 20px; font-weight: bold; color: #000; }
        .summary-box .label { font-size: 12px; color: #333; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="' . $logo_url . '" alt="Go Fast">
            <p style="margin: 8px 0 0; font-size: 14px; color: #000;">Reporte Financiero</p>
        </div>
        <div class="content">
            <div class="periodo">
                <strong>📅 Período:</strong> ' . esc_html($periodo_email) . '
                <br><small>Generado: ' . gofast_date_format(gofast_date_mysql(), 'd/m/Y H:i') . '</small>
            </div>';
            
            if ($tipo_reporte_email === 'resumen') {
                $email_html .= '
            <div class="section">
                <h3>📊 Resumen General</h3>
                <table>
                    <tr><td>💰 Ingresos</td><td class="text-right">$' . number_format($total_comisiones, 0, ',', '.') . '</td></tr>
                    <tr><td>📤 Total Egresos</td><td class="text-right negative">-$' . number_format($total_egresos, 0, ',', '.') . '</td></tr>
                    <tr><td>🏢 Vales Empresa</td><td class="text-right negative">-$' . number_format($total_vales_empresa, 0, ',', '.') . '</td></tr>
                    <tr><td>💸 Saldo Transferencias</td><td class="text-right">$' . number_format($saldo_transferencias, 0, ',', '.') . '</td></tr>
                    <tr><td>💵 Saldos Pendientes Mensajeros (Quincena Actual: ' . gofast_date_format($fecha_desde_saldos, 'd/m/Y') . ' - ' . gofast_date_format($fecha_hasta_saldos, 'd/m/Y') . ')</td><td class="text-right">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</td></tr>
                    <tr><td>➖ Total Descuentos</td><td class="text-right negative">-$' . number_format($total_descuentos, 0, ',', '.') . '</td></tr>
                    <tr class="total-row"><td><strong>📊 Subtotal</strong></td><td class="text-right"><strong>$' . number_format($subtotal, 0, ',', '.') . '</strong></td></tr>
                    <tr class="total-row"><td><strong>💵 Efectivo Disponible</strong></td><td class="text-right positive"><strong>$' . number_format($efectivo, 0, ',', '.') . '</strong></td></tr>
                    <tr class="total-row"><td><strong>📈 Utilidad Total</strong></td><td class="text-right positive"><strong>$' . number_format($utilidad_total, 0, ',', '.') . '</strong></td></tr>
                    <tr><td>👤 Utilidad Individual (÷2)</td><td class="text-right">$' . number_format($utilidad_individual, 0, ',', '.') . '</td></tr>
                </table>
            </div>';
            }
            
            if ($tipo_reporte_email === 'saldos') {
                $email_html .= '
            <div class="section">
                <h3>💵 Saldos por Mensajero (Quincena Actual: ' . gofast_date_format($fecha_desde_saldos, 'd/m/Y') . ' - ' . gofast_date_format($fecha_hasta_saldos, 'd/m/Y') . ')</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Mensajero</th>
                            <th class="text-right">Comisión</th>
                            <th class="text-right">Transferencias</th>
                            <th class="text-right">Descuentos</th>
                            <th class="text-right">Pagos</th>
                            <th class="text-right">Total a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($saldos_mensajeros as $saldo) {
                    $email_html .= '
                        <tr>
                            <td>' . esc_html($saldo->mensajero_nombre) . '</td>
                            <td class="text-right">$' . number_format($saldo->comision_generada, 0, ',', '.') . '</td>
                            <td class="text-right">$' . number_format($saldo->transferencias_aprobadas, 0, ',', '.') . '</td>
                            <td class="text-right negative">$' . number_format($saldo->total_descuentos, 0, ',', '.') . '</td>
                            <td class="text-right">$' . number_format($saldo->total_pagos_rango, 0, ',', '.') . '</td>
                            <td class="text-right ' . ($saldo->total_a_pagar >= 0 ? 'positive' : 'negative') . '"><strong>$' . number_format($saldo->total_a_pagar, 0, ',', '.') . '</strong></td>
                        </tr>';
                }
                
                $email_html .= '
                    </tbody>
                </table>
            </div>';
            }
            
            if ($tipo_reporte_email === 'pagos') {
                $total_pagos_email = 0;
                $email_html .= '
            <div class="section">
                <h3>📋 Historial de Pagos</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Mensajero</th>
                            <th class="text-right">Tipo</th>
                            <th class="text-right">Total Pagado</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($pagos_historial as $pago) {
                    $total_pagos_email += (float)$pago->total_a_pagar;
                    $email_html .= '
                        <tr>
                            <td>' . gofast_date_format($pago->fecha, 'd/m/Y') . '</td>
                            <td>' . esc_html($pago->mensajero_nombre ?? 'N/A') . '</td>
                            <td class="text-right">' . ($pago->tipo_pago === 'efectivo' ? '💵 Efectivo' : '💸 Transferencia') . '</td>
                            <td class="text-right positive">$' . number_format((float)$pago->total_a_pagar, 0, ',', '.') . '</td>
                        </tr>';
                }
                
                $email_html .= '
                        <tr class="total-row">
                            <td colspan="3"><strong>Total Pagos</strong></td>
                            <td class="text-right positive"><strong>$' . number_format($total_pagos_email, 0, ',', '.') . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>';
            }
            
            // Nota: Los demás reportes nuevos se pueden agregar aquí si se desea enviar por correo
            // Por ahora solo están disponibles los 3 principales (resumen, saldos, pagos)
            
            $email_html .= '
        </div>
        <div class="footer">
            <p>Reporte generado automáticamente por Go Fast</p>
        </div>
    </div>
</body>
</html>';
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            if (wp_mail($email_destino, $asunto_reporte, $email_html, $headers)) {
                $mensaje = '✅ Reporte enviado correctamente a ' . esc_html($email_destino);
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al enviar el correo.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // ========================================
    // INTERFAZ
    // ========================================
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Financieros - Go Fast</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; min-height: 100vh; color: #000; }
        .header { background: #F4C524; padding: 20px; text-align: center; }
        .header img { max-height: 40px; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { margin-top: 0; margin-bottom: 16px; color: #000; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 14px; }
        .btn-primary { background: #F4C524; color: #000; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-info { background: #17a2b8; color: #fff; }
        .btn-purple { background: #6f42c1; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn:hover { opacity: 0.9; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #000; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; color: #000; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .report-card { border: 2px solid #eee; border-radius: 8px; padding: 20px; background: #fff; }
        .report-card h4 { margin-top: 0; color: #000; }
        .report-card p { color: #333; font-size: 13px; margin-bottom: 16px; }
        .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #333; text-decoration: none; }
        .back-link:hover { color: #000; }
        .preview-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 0; margin-top: 20px; max-height: 600px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png" alt="Go Fast">
    </div>
    
    <div class="container">
        <a href="<?= esc_url(home_url('/admin-finanzas')) ?>" class="back-link">← Volver a Finanzas</a>
        
        <div class="card">
            <h3>📊 Reportes Financieros</h3>
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= $mensaje_tipo === 'success' ? 'success' : 'error' ?>">
                    <?= esc_html($mensaje) ?>
                </div>
            <?php endif; ?>
            
            <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; align-items: end; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Mes</label>
                    <input type="month" name="quincena_mes" value="<?= esc_attr($quincena_mes) ?>" required>
                </div>
                <div class="form-group">
                    <label>Quincena</label>
                    <select name="quincena_numero" required>
                        <option value="1" <?= selected($quincena_numero, 1) ?>>Primera (1-15)</option>
                        <option value="2" <?= selected($quincena_numero, 2) ?>>Segunda (16-fin)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Aplicar Quincena</button>
                </div>
            </form>
            
            <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                <strong>Quincena Seleccionada:</strong> Quincena <?= $quincena_numero ?> de <?= gofast_date_format($quincena_mes . '-01', 'F Y') ?> 
                (<?= gofast_date_format($fecha_desde, 'd/m/Y') ?> - <?= gofast_date_format($fecha_hasta, 'd/m/Y') ?>)
            </p>
        </div>

        <div class="grid">
            <!-- Resumen General -->
            <div class="report-card">
                <h4>📈 Resumen General</h4>
                <p>Ingresos, Egresos, Vales, Transferencias, Descuentos y Totales del período.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=resumen&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=resumen&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Saldos Mensajeros -->
            <div class="report-card">
                <h4>💵 Saldos Mensajeros</h4>
                <p>Comisiones, Transferencias, Descuentos, Pagos y Saldo Pendiente por mensajero.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=saldos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=saldos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Historial de Pagos -->
            <div class="report-card">
                <h4>📋 Historial de Pagos</h4>
                <p>Lista de todos los pagos registrados a mensajeros en el período.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=pagos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=pagos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Rendimiento de Mensajeros -->
            <div class="report-card">
                <h4>🏆 Rendimiento de Mensajeros</h4>
                <p>Pedidos entregados, cancelados, tasa de entrega y comisiones generadas.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=rendimiento&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=rendimiento&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Transferencias -->
            <div class="report-card">
                <h4>💸 Transferencias</h4>
                <p>Ingresos vs salidas de transferencias y saldo neto.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=transferencias&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=transferencias&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Descuentos -->
            <div class="report-card">
                <h4>➖ Descuentos</h4>
                <p>Análisis de descuentos por mensajero y motivos.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=descuentos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=descuentos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Egresos y Vales -->
            <div class="report-card">
                <h4>💸 Egresos y Vales</h4>
                <p>Egresos por categoría, vales empresa y personal.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=egresos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=egresos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Pedidos por Negocio -->
            <div class="report-card">
                <h4>🏪 Pedidos por Negocio</h4>
                <p>Ingresos y comisiones generadas por cada negocio.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=negocios&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=negocios&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Servicios Normal vs Intermunicipal -->
            <div class="report-card">
                <h4>🚚 Servicios Normal vs Intermunicipal</h4>
                <p>Comparación de ingresos por tipo de servicio.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=servicios&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=servicios&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Compras -->
            <div class="report-card">
                <h4>🛒 Compras</h4>
                <p>Análisis de compras realizadas y comisiones generadas.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=compras&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=compras&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Cancelaciones -->
            <div class="report-card">
                <h4>❌ Cancelaciones</h4>
                <p>Tasa de cancelación, valor perdido y análisis por mensajero.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=cancelaciones&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=cancelaciones&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Consolidado Mensual -->
            <div class="report-card">
                <h4>📊 Consolidado Mensual</h4>
                <p>Resumen de ambas quincenas del mes con comparaciones.</p>
                <div class="btn-group">
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=consolidado&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>&tipo=consolidado&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
        </div>
        
        <!-- Enviar por Correo -->
        <div class="card" style="margin-top: 20px;">
            <h3>📧 Enviar Reporte por Correo</h3>
            <form method="post" action="">
                <?php wp_nonce_field('gofast_enviar_reporte', 'gofast_enviar_reporte_nonce'); ?>
                <input type="hidden" name="gofast_enviar_reporte" value="1">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <div class="form-group">
                        <label>📧 Correo Destino</label>
                        <input type="email" name="email_destino" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <label>📄 Tipo de Reporte</label>
                        <select name="tipo_reporte">
                            <option value="resumen">📈 Resumen General</option>
                            <option value="saldos">💵 Saldos Mensajeros</option>
                            <option value="pagos">📋 Historial de Pagos</option>
                            <option value="rendimiento">🏆 Rendimiento de Mensajeros</option>
                            <option value="transferencias">💸 Transferencias</option>
                            <option value="descuentos">➖ Descuentos</option>
                            <option value="egresos">💸 Egresos y Vales</option>
                            <option value="negocios">🏪 Pedidos por Negocio</option>
                            <option value="servicios">🚚 Servicios Normal vs Intermunicipal</option>
                            <option value="compras">🛒 Compras</option>
                            <option value="cancelaciones">❌ Cancelaciones</option>
                            <option value="consolidado">📊 Consolidado Mensual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📝 Asunto (opcional)</label>
                        <input type="text" name="asunto_reporte" placeholder="Reporte Financiero Go Fast" value="Reporte Financiero Go Fast">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-purple" style="width: 100%;">📤 Enviar por Correo</button>
                    </div>
                </div>
                <p style="font-size: 12px; color: #666; margin-top: 8px;">
                    Se enviará un enlace al reporte de la quincena <?= $quincena_numero ?> de <?= gofast_date_format($quincena_mes . '-01', 'F Y') ?> (<?= gofast_date_format($fecha_desde, 'd/m/Y') ?> - <?= gofast_date_format($fecha_hasta, 'd/m/Y') ?>)
                </p>
            </form>
        </div>

        <?php if (!empty($tipo_reporte) && $accion === 'ver' && isset($reporte_html)): ?>
        <div class="card" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">👁️ Vista Previa: <?= ucfirst($tipo_reporte) ?></h3>
                <a href="?quincena_mes=<?= $quincena_mes ?>&quincena_numero=<?= $quincena_numero ?>" class="btn btn-secondary">✕ Cerrar</a>
            </div>
            <div class="preview-container">
                <?= $reporte_html ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}
add_shortcode('gofast_reportes_financieros', 'gofast_reportes_financieros_shortcode');
