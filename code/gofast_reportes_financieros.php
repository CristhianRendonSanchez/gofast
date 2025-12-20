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

    // Obtener filtros de fecha
    $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
    $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';
    
    // Si no hay fechas, usar el mes actual
    if (empty($fecha_desde)) {
        $fecha_desde = date('Y-m-01');
    }
    if (empty($fecha_hasta)) {
        $fecha_hasta = date('Y-m-d');
    }

    $mensaje = '';
    $mensaje_tipo = '';

    // ========================================
    // CALCULAR DATOS FINANCIEROS (igual que gofast_finanzas_admin.php)
    // ========================================
    
    // Determinar si es un rango específico o acumulado
    $es_rango_especifico = ($fecha_desde !== $fecha_hasta);
    
    if ($es_rango_especifico) {
        // RANGO DE FECHAS: filtrar por fecha_desde y fecha_hasta
        
        // Total Ingresos (servicios + compras)
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
        
        // Total Egresos
        $total_egresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast 
                 WHERE fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
        
        // Total Vales Empresa
        $total_vales_empresa = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast 
                 WHERE fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Ingresos (solo normales, excluir de tipo 'pago')
        $total_transferencias_ingresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE estado = 'aprobada' 
                 AND (tipo IS NULL OR tipo = 'normal' OR tipo = '')
                 AND fecha_creacion >= %s AND fecha_creacion <= %s",
                $fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'
            )
        ) ?? 0);
        
        // Total Pagos en el rango (pagos a mensajeros tipo efectivo + transferencia)
        $total_pagos_dia = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE tipo_pago IN ('efectivo', 'transferencia')
                 AND fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Salidas
        $total_transferencias_salidas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast 
                 WHERE fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
        
        // Total Descuentos
        $total_descuentos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
        
    } else {
        // TOTALES ACUMULADOS HASTA fecha_hasta (cuando fecha_desde == fecha_hasta)
        
        // Total Ingresos hasta fecha_hasta
        $total_ingresos_servicios = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                 WHERE tracking_estado != 'cancelado' AND DATE(fecha) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        $total_ingresos_compras = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                 WHERE estado != 'cancelada' AND DATE(fecha_creacion) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        $total_ingresos = $total_ingresos_servicios + $total_ingresos_compras;
        
        // Total Egresos hasta fecha_hasta
        $total_egresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Vales Empresa hasta fecha_hasta
        $total_vales_empresa = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Ingresos hasta fecha_hasta (solo normales, excluir de tipo 'pago')
        $total_transferencias_ingresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE estado = 'aprobada' 
                 AND (tipo IS NULL OR tipo = 'normal' OR tipo = '')
                 AND DATE(fecha_creacion) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Pagos hasta fecha_hasta
        $total_pagos_dia = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE tipo_pago IN ('efectivo', 'transferencia')
                 AND fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Salidas hasta fecha_hasta
        $total_transferencias_salidas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Descuentos hasta fecha_hasta
        $total_descuentos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
    }
    
    // Saldo Transferencias
    $saldo_transferencias = $total_transferencias_ingresos - $total_transferencias_salidas;
    
    // Calcular comisiones (20% de los ingresos totales)
    $total_comisiones = $total_ingresos * 0.20;

    // Saldos Mensajeros - Calculado con historial completo hasta fecha_hasta (igual que gofast_finanzas_admin.php)
    // NOTA: No filtramos por activo=1 para incluir mensajeros deshabilitados en reportes financieros
    $mensajeros = $wpdb->get_results("SELECT id, nombre, telefono, email FROM usuarios_gofast WHERE rol = 'mensajero' ORDER BY nombre ASC");
    
    $saldos_mensajeros = [];
    foreach ($mensajeros as $mensajero) {
        $mensajero_id = (int) $mensajero->id;
        
        // Ingresos en el rango (para mostrar en reporte)
        $ingresos_servicios = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado'
                 AND DATE(fecha) >= %s AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        $ingresos_compras = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada'
                 AND DATE(fecha_creacion) >= %s AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        $ingresos_totales = $ingresos_servicios + $ingresos_compras;
        $comision_generada = $ingresos_totales * 0.20;
        
        // Transferencias aprobadas en el rango (solo normales, excluir de tipo 'pago')
        $transferencias_aprobadas = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE mensajero_id = %d AND estado = 'aprobada'
                 AND (tipo IS NULL OR tipo = 'normal' OR tipo = '')
                 AND DATE(fecha_creacion) >= %s AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        // Descuentos en el rango
        $descuentos = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND DATE(fecha) >= %s AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        // Pagos en el rango
        $pagos_rango = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia')
                 AND DATE(fecha) >= %s AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_desde, $fecha_hasta
            )
        );
        
        // TOTAL A PAGAR: Usar historial completo hasta fecha_hasta (igual que finanzas_admin)
        $comision_historica = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) * 0.20 FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado' 
                 AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $comision_compras_historica = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) * 0.20 FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada' 
                 AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $transferencias_historicas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE mensajero_id = %d AND estado = 'aprobada' 
                 AND (tipo IS NULL OR tipo = 'normal' OR tipo = '')
                 AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $descuentos_historicos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $pagos_historicos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia') 
                 AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        // Total pendiente histórico hasta la fecha seleccionada
        $total_a_pagar = ($comision_historica + $comision_compras_historica) - $transferencias_historicas - $descuentos_historicos - $pagos_historicos;
        
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
    
    // Total saldos pendientes (igual que finanzas_admin)
    // Fórmula: Comisión(20% de ingresos) - Transferencias Ingresos - Descuentos - Pagos
    if ($es_rango_especifico) {
        $total_pagos_mensajeros = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE tipo_pago = 'efectivo' 
                 AND fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
    } else {
        $total_pagos_mensajeros = (float) ($wpdb->get_var(
            "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
             WHERE tipo_pago = 'efectivo'"
        ) ?? 0);
    }
    
    // Saldos Pendientes = Comisión - Transferencias Ingresos - Descuentos - Pagos
    $total_saldos_pendientes = $total_comisiones - $total_transferencias_ingresos - $total_descuentos - $total_pagos_mensajeros;
    if ($total_saldos_pendientes < 0) {
        $total_saldos_pendientes = 0;
    }

    // Historial de Pagos
    $pagos_historial = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.*, m.nombre as mensajero_nombre, u.nombre as creador_nombre
             FROM pagos_mensajeros_gofast p
             LEFT JOIN usuarios_gofast m ON p.mensajero_id = m.id
             LEFT JOIN usuarios_gofast u ON p.creado_por = u.id
             WHERE p.tipo_pago IN ('efectivo', 'transferencia')
             AND DATE(p.fecha) >= %s AND DATE(p.fecha) <= %s
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
    // GENERAR REPORTE
    // ========================================
    
    $tipo_reporte = isset($_GET['tipo']) ? sanitize_text_field($_GET['tipo']) : '';
    $accion = isset($_GET['accion']) ? sanitize_text_field($_GET['accion']) : '';
    
    if (!empty($tipo_reporte) && in_array($accion, ['ver', 'descargar'])) {
        $logo_url = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
        
        $periodo = gofast_date_format($fecha_desde, 'd/m/Y') . ' al ' . gofast_date_format($fecha_hasta, 'd/m/Y');
        
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
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .summary-box { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; }
    .summary-box .value { font-size: 24px; font-weight: bold; color: #333; }
    .summary-box .label { font-size: 12px; color: #666; }
    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; padding-top: 20px; border-top: 1px solid #ddd; }
    @media print { body { padding: 10px; } .container { max-width: 100%; } }
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
            $reporte_html .= '
    <div class="summary-grid">
        <div class="summary-box">
            <div class="value">$' . number_format($total_comisiones, 0, ',', '.') . '</div>
            <div class="label">Ingresos</div>
        </div>
        <div class="summary-box">
            <div class="value">$' . number_format($total_egresos, 0, ',', '.') . '</div>
            <div class="label">Total Egresos</div>
        </div>
        <div class="summary-box">
            <div class="value">$' . number_format($total_pagos_dia, 0, ',', '.') . '</div>
            <div class="label">💰 Pagos en el Día</div>
        </div>
        <div class="summary-box">
            <div class="value">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</div>
            <div class="label">Saldos Pendientes</div>
        </div>
    </div>
    
    <div class="section">
        <h3>📊 Resumen General</h3>
        <table>
            <tr><td>💰 Ingresos</td><td class="text-right">$' . number_format($total_comisiones, 0, ',', '.') . '</td></tr>
            <tr><td>📤 Total Egresos</td><td class="text-right negative">-$' . number_format($total_egresos, 0, ',', '.') . '</td></tr>
            <tr><td>🏢 Vales Empresa</td><td class="text-right negative">-$' . number_format($total_vales_empresa, 0, ',', '.') . '</td></tr>
            <tr><td>💸 Saldo Transferencias (Ingresos - Salidas)</td><td class="text-right">$' . number_format($saldo_transferencias, 0, ',', '.') . '</td></tr>
            <tr><td>💰 Pagos en el Día</td><td class="text-right positive">$' . number_format($total_pagos_dia, 0, ',', '.') . '</td></tr>
            <tr><td>💵 Saldos Pendientes Mensajeros</td><td class="text-right">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</td></tr>
            <tr><td>➖ Total Descuentos</td><td class="text-right negative">-$' . number_format($total_descuentos, 0, ',', '.') . '</td></tr>
            <tr class="total-row"><td><strong>📊 Subtotal (Comisiones - Egresos - Vales - Descuentos)</strong></td><td class="text-right"><strong>$' . number_format($subtotal, 0, ',', '.') . '</strong></td></tr>
            <tr class="total-row"><td><strong>💵 Efectivo Disponible</strong></td><td class="text-right positive"><strong>$' . number_format($efectivo, 0, ',', '.') . '</strong></td></tr>
            <tr class="total-row"><td><strong>📈 Utilidad Total</strong></td><td class="text-right positive"><strong>$' . number_format($utilidad_total, 0, ',', '.') . '</strong></td></tr>
            <tr><td>👤 Utilidad Individual (÷2)</td><td class="text-right">$' . number_format($utilidad_individual, 0, ',', '.') . '</td></tr>
        </table>
    </div>';
        }

        if ($tipo_reporte === 'saldos') {
            $reporte_html .= '
    <div class="section">
        <h3>💵 Saldos por Mensajero</h3>
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
            $periodo_email = gofast_date_format($fecha_desde, 'd/m/Y') . ' al ' . gofast_date_format($fecha_hasta, 'd/m/Y');
            
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
                    <tr><td>💰 Pagos en el Día</td><td class="text-right positive">$' . number_format($total_pagos_dia, 0, ',', '.') . '</td></tr>
                    <tr><td>💵 Saldos Pendientes Mensajeros</td><td class="text-right">$' . number_format($total_saldos_pendientes, 0, ',', '.') . '</td></tr>
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
                <h3>💵 Saldos por Mensajero</h3>
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
                    <label>Fecha Desde</label>
                    <input type="date" name="fecha_desde" value="<?= esc_attr($fecha_desde) ?>">
                </div>
                <div class="form-group">
                    <label>Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" value="<?= esc_attr($fecha_hasta) ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Aplicar Fechas</button>
                </div>
            </form>
            
            <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                <strong>Período:</strong> <?= gofast_date_format($fecha_desde, 'd/m/Y') ?> al <?= gofast_date_format($fecha_hasta, 'd/m/Y') ?>
            </p>
        </div>

        <div class="grid">
            <!-- Resumen General -->
            <div class="report-card">
                <h4>📈 Resumen General</h4>
                <p>Ingresos, Egresos, Vales, Transferencias, Descuentos y Totales del período.</p>
                <div class="btn-group">
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=resumen&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=resumen&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Saldos Mensajeros -->
            <div class="report-card">
                <h4>💵 Saldos Mensajeros</h4>
                <p>Comisiones, Transferencias, Descuentos, Pagos y Saldo Pendiente por mensajero.</p>
                <div class="btn-group">
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=saldos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=saldos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
                </div>
            </div>
            
            <!-- Historial de Pagos -->
            <div class="report-card">
                <h4>📋 Historial de Pagos</h4>
                <p>Lista de todos los pagos registrados a mensajeros en el período.</p>
                <div class="btn-group">
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=pagos&accion=ver" class="btn btn-info">👁️ Ver</a>
                    <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&tipo=pagos&accion=descargar" class="btn btn-success" target="_blank">📥 PDF</a>
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
                    Se enviará un enlace al reporte del período seleccionado (<?= gofast_date_format($fecha_desde, 'd/m/Y') ?> - <?= gofast_date_format($fecha_hasta, 'd/m/Y') ?>)
                </p>
            </form>
        </div>

        <?php if (!empty($tipo_reporte) && $accion === 'ver' && isset($reporte_html)): ?>
        <div class="card" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">👁️ Vista Previa: <?= ucfirst($tipo_reporte) ?></h3>
                <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" class="btn btn-secondary">✕ Cerrar</a>
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
