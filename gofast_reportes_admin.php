/***************************************************
 * GOFAST – ADMIN REPORTES DE PEDIDOS
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
       0. Validar usuario admin
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

    if (!$usuario || strtolower($usuario->rol) !== 'admin') {
        return "<div class='gofast-box'>
                    ⚠️ Solo los administradores pueden ver reportes.
                </div>";
    }

    /* ==========================================================
       1. Filtros (GET)
    ========================================================== */
    $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $desde = isset($_GET['desde']) ? sanitize_text_field($_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? sanitize_text_field($_GET['hasta']) : '';
    $mensajero_id = isset($_GET['mensajero_id']) ? (int) $_GET['mensajero_id'] : 0;
    $buscar = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
    if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

    // Si no hay fechas, usar último mes por defecto
    if (!$desde && !$hasta) {
        $desde = date('Y-m-d', strtotime('-30 days'));
        $hasta = current_time('Y-m-d');
    }

    $where = "1=1";
    $params = [];

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

    if ($mensajero_id > 0) {
        $where .= " AND mensajero_id = %d";
        $params[] = $mensajero_id;
    }

    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where .= " AND (nombre_cliente LIKE %s OR telefono_cliente LIKE %s)";
        $params[] = $like;
        $params[] = $like;
    }

    /* ==========================================================
       2. Estadísticas
    ========================================================== */
    if (!empty($params)) {
        $sql_stats = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_pedidos,
                SUM(CASE WHEN tracking_estado = 'entregado' THEN total ELSE 0 END) as total_ingresos,
                AVG(CASE WHEN tracking_estado = 'entregado' THEN total ELSE NULL END) as promedio_pedido,
                COUNT(CASE WHEN tracking_estado = 'pendiente' THEN 1 END) as pendientes,
                COUNT(CASE WHEN tracking_estado = 'en_ruta' THEN 1 END) as en_ruta,
                COUNT(CASE WHEN tracking_estado = 'entregado' THEN 1 END) as entregados,
                COUNT(CASE WHEN tracking_estado = 'cancelado' THEN 1 END) as cancelados
             FROM $tabla 
             WHERE $where",
            $params
        );
    } else {
        $sql_stats = "SELECT 
                COUNT(*) as total_pedidos,
                SUM(CASE WHEN tracking_estado = 'entregado' THEN total ELSE 0 END) as total_ingresos,
                AVG(CASE WHEN tracking_estado = 'entregado' THEN total ELSE NULL END) as promedio_pedido,
                COUNT(CASE WHEN tracking_estado = 'pendiente' THEN 1 END) as pendientes,
                COUNT(CASE WHEN tracking_estado = 'en_ruta' THEN 1 END) as en_ruta,
                COUNT(CASE WHEN tracking_estado = 'entregado' THEN 1 END) as entregados,
                COUNT(CASE WHEN tracking_estado = 'cancelado' THEN 1 END) as cancelados
             FROM $tabla 
             WHERE $where";
    }

    $stats = $wpdb->get_row($sql_stats);
    $total_ingresos = (float) ($stats->total_ingresos ?? 0);
    $promedio_pedido = (float) ($stats->promedio_pedido ?? 0);

    // Top mensajeros
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

    // Pedidos por día (últimos 30 días)
    $pedidos_por_dia = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                DATE(fecha) as dia,
                COUNT(*) as cantidad,
                SUM(CASE WHEN tracking_estado = 'entregado' THEN total ELSE 0 END) as ingresos
             FROM $tabla
             WHERE fecha >= %s AND fecha <= %s
             GROUP BY DATE(fecha)
             ORDER BY dia DESC
             LIMIT 30",
            date('Y-m-d', strtotime('-30 days')) . ' 00:00:00',
            current_time('Y-m-d') . ' 23:59:59'
        )
    );

    /* ==========================================================
       3. Lista de mensajeros para filtro
    ========================================================== */
    $mensajeros = $wpdb->get_results(
        "SELECT id, nombre 
         FROM usuarios_gofast
         WHERE rol = 'mensajero' AND activo = 1
         ORDER BY nombre ASC
        "
    );

    /* ==========================================================
       4. Exportar a CSV
    ========================================================== */
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_pedidos_' . date('Y-m-d') . '.csv"');
        
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
                Analiza el rendimiento de los pedidos y genera reportes detallados.
            </p>
        </div>
        <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;">
            ← Volver al Dashboard
        </a>
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
            <div style="font-size:32px;margin-bottom:8px;">📦</div>
            <div style="font-size:28px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($stats->total_pedidos ?? 0); ?></div>
            <div style="font-size:13px;color:#666;">Total Pedidos</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💰</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;">$<?= number_format($total_ingresos, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Ingresos Totales</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📊</div>
            <div style="font-size:28px;font-weight:700;color:#2196F3;margin-bottom:4px;">$<?= number_format($promedio_pedido, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Promedio por Pedido</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;"><?= number_format($stats->entregados ?? 0); ?></div>
            <div style="font-size:13px;color:#666;">Entregados</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">⏳</div>
            <div style="font-size:28px;font-weight:700;color:#ff9800;margin-bottom:4px;"><?= number_format($stats->pendientes ?? 0); ?></div>
            <div style="font-size:13px;color:#666;">Pendientes</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">❌</div>
            <div style="font-size:28px;font-weight:700;color:#f44336;margin-bottom:4px;"><?= number_format($stats->cancelados ?? 0); ?></div>
            <div style="font-size:13px;color:#666;">Cancelados</div>
        </div>

    </div>

    <!-- =====================================================
         C) TOP MENSAJEROS
    ====================================================== -->
    <?php if (!empty($top_mensajeros)): ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">🏆 Top Mensajeros</h3>
            <div class="gofast-table-wrap">
                <table class="gofast-table">
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
            <div class="gofast-table-wrap">
                <table class="gofast-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cantidad de Pedidos</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_por_dia as $dia): ?>
                            <tr>
                                <td><?= esc_html( date_i18n('d/m/Y', strtotime($dia->dia)) ); ?></td>
                                <td><?= number_format($dia->cantidad); ?></td>
                                <td>$<?= number_format($dia->ingresos, 0, ',', '.'); ?></td>
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

