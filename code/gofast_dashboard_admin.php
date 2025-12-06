/***************************************************
 * GOFAST – DASHBOARD ADMINISTRATIVO
 * Shortcode: [gofast_dashboard_admin]
 * URL: /dashboard-admin
 ***************************************************/
function gofast_dashboard_admin_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

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
                    ⚠️ Solo los administradores pueden acceder al panel.
                </div>";
    }

    /* ==========================================================
       1. Estadísticas rápidas
    ========================================================== */
    // Total de destinos (suma de todos los destinos en JSON)
    $total_destinos = (int) ($wpdb->get_var(
        "SELECT SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) FROM servicios_gofast"
    ) ?? 0);
    
    // Total compras (excluyendo canceladas)
    $total_compras = (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado != 'cancelada'");
    
    // Usuarios
    $total_usuarios = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE activo = 1");
    $total_clientes = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE rol = 'cliente' AND activo = 1");
    $total_mensajeros = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE rol = 'mensajero' AND activo = 1");
    
    // Ingresos totales (servicios no cancelados + compras no canceladas)
    $ingresos_servicios = (float) ($wpdb->get_var("SELECT SUM(total) FROM servicios_gofast WHERE tracking_estado != 'cancelado'") ?? 0);
    $ingresos_compras = (float) ($wpdb->get_var("SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada'") ?? 0);
    $total_ingresos = $ingresos_servicios + $ingresos_compras;
    
    // Destinos hoy
    $destinos_hoy = (int) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) 
             FROM servicios_gofast 
             WHERE DATE(fecha) = %s",
            gofast_current_time('Y-m-d')
        )
    ) ?? 0);
    
    // Ingresos de hoy (servicios + compras del día actual)
    $ingresos_servicios_hoy = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(total) FROM servicios_gofast 
             WHERE DATE(fecha) = %s AND tracking_estado != 'cancelado'",
            gofast_current_time('Y-m-d')
        )
    ) ?? 0);
    
    $ingresos_compras_hoy = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(valor) FROM compras_gofast 
             WHERE DATE(fecha) = %s AND estado != 'cancelada'",
            gofast_current_time('Y-m-d')
        )
    ) ?? 0);
    
    $ingresos_hoy = $ingresos_servicios_hoy + $ingresos_compras_hoy;
    $comision_hoy = $ingresos_hoy * 0.20;
    
    // Comisión del mes actual (del primer día al último día del mes) - zona horaria Colombia
    $fecha_actual = gofast_current_time('Y-m-d');
    $timezone = new DateTimeZone('America/Bogota');
    $datetime = new DateTime($fecha_actual, $timezone);
    $primer_dia_mes = $datetime->format('Y-m-01');
    $ultimo_dia_mes = $datetime->format('Y-m-t');
    
    $ingresos_servicios_mes = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(total) FROM servicios_gofast 
             WHERE fecha >= %s AND fecha <= %s AND tracking_estado != 'cancelado'",
            $primer_dia_mes . ' 00:00:00',
            $ultimo_dia_mes . ' 23:59:59'
        )
    ) ?? 0);
    
    $ingresos_compras_mes = (float) ($wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(valor) FROM compras_gofast 
             WHERE fecha >= %s AND fecha <= %s AND estado != 'cancelada'",
            $primer_dia_mes . ' 00:00:00',
            $ultimo_dia_mes . ' 23:59:59'
        )
    ) ?? 0);
    
    $ingresos_mes = $ingresos_servicios_mes + $ingresos_compras_mes;
    $comision_mes = $ingresos_mes * 0.20;

    /* ==========================================================
       2. HTML
    ========================================================== */
    ob_start();
    ?>

<div class="gofast-home">
    <h1 style="margin-bottom:8px;">⚙️ Panel Administrativo</h1>
    <p class="gofast-home-text">
        Gestiona pedidos, usuarios, recargos y visualiza reportes del sistema.
    </p>

    <!-- Enlaces de navegación - PRIMERO -->
    <div class="gofast-dashboard-links" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:16px;margin:24px 0;">
        
        <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">📦</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Gestión de Pedidos</h3>
            <p style="margin:0;color:#666;font-size:14px;">Ver, filtrar y gestionar todos los pedidos del sistema</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-usuarios') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">👥</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Gestión de Usuarios</h3>
            <p style="margin:0;color:#666;font-size:14px;">Crear, editar y gestionar usuarios, clientes y mensajeros</p>
        </a>

        <a href="<?php echo esc_url( home_url('/recargos') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">⚙️</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Administración de Recargos</h3>
            <p style="margin:0;color:#666;font-size:14px;">Configurar recargos fijos y por valor para las cotizaciones</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-reportes') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">📊</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Reportes y Estadísticas</h3>
            <p style="margin:0;color:#666;font-size:14px;">Ver reportes detallados, estadísticas y exportar datos</p>
        </a>

        <a href="<?php echo esc_url( home_url('/transferencias') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">💰</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Transferencias</h3>
            <p style="margin:0;color:#666;font-size:14px;">Gestionar transferencias de mensajeros y aprobar pagos</p>
        </a>

        <a href="<?php echo esc_url( home_url('/compras') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">🛒</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Compras</h3>
            <p style="margin:0;color:#666;font-size:14px;">Gestionar compras de mensajeros y asignar servicios</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-cotizar') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">🚚</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Crear Servicio</h3>
            <p style="margin:0;color:#666;font-size:14px;">Crear servicio y asignarlo a un mensajero</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-cotizar-intermunicipal') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">🌐</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Envío Intermunicipal</h3>
            <p style="margin:0;color:#666;font-size:14px;">Crear envío intermunicipal y asignarlo a un mensajero</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-negocios') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">🏪</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Gestión de Negocios</h3>
            <p style="margin:0;color:#666;font-size:14px;">Ver, editar y eliminar todos los negocios registrados</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-configuracion') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">⚙️</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Configuración del Sistema</h3>
            <p style="margin:0;color:#666;font-size:14px;">Gestionar tarifas, barrios, sectores y destinos intermunicipales</p>
        </a>

        <a href="<?php echo esc_url( home_url('/admin-solicitudes-trabajo') ); ?>" class="gofast-box" style="display:block;text-decoration:none;padding:24px;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="font-size:40px;margin-bottom:12px;">📋</div>
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;">Solicitudes de Trabajo</h3>
            <p style="margin:0;color:#666;font-size:14px;">Ver y gestionar solicitudes de trabajo de mensajeros</p>
        </a>

    </div>

    <!-- Tarjetas de estadísticas - DESPUÉS -->
    <div class="gofast-dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin:32px 0;">
        
        <!-- Total Destinos -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📍</div>
            <div style="font-size:28px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($total_destinos); ?></div>
            <div style="font-size:13px;color:#666;">Total Destinos</div>
        </div>

        <!-- Total Compras -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🛒</div>
            <div style="font-size:28px;font-weight:700;color:#E91E63;margin-bottom:4px;"><?= number_format($total_compras); ?></div>
            <div style="font-size:13px;color:#666;">Total Compras</div>
        </div>

        <!-- Total Usuarios -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">👥</div>
            <div style="font-size:28px;font-weight:700;color:#9C27B0;margin-bottom:4px;"><?= number_format($total_usuarios); ?></div>
            <div style="font-size:13px;color:#666;">Total Usuarios</div>
        </div>

        <!-- Total Clientes -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🛍️</div>
            <div style="font-size:28px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($total_clientes); ?></div>
            <div style="font-size:13px;color:#666;">Total Clientes</div>
        </div>

        <!-- Total Mensajeros -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🚚</div>
            <div style="font-size:28px;font-weight:700;color:#00BCD4;margin-bottom:4px;"><?= number_format($total_mensajeros); ?></div>
            <div style="font-size:13px;color:#666;">Total Mensajeros</div>
        </div>

        <!-- Ingresos Totales -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💰</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;">$<?= number_format($total_ingresos, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Ingresos Totales</div>
        </div>

        <!-- Destinos Hoy -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📅</div>
            <div style="font-size:28px;font-weight:700;color:#FF5722;margin-bottom:4px;"><?= number_format($destinos_hoy); ?></div>
            <div style="font-size:13px;color:#666;">Destinos Hoy</div>
        </div>

        <!-- Comisión Hoy -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💵</div>
            <div style="font-size:28px;font-weight:700;color:#FF9800;margin-bottom:4px;">$<?= number_format($comision_hoy, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Comisión Hoy</div>
        </div>

        <!-- Comisión del Mes -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📊</div>
            <div style="font-size:28px;font-weight:700;color:#9C27B0;margin-bottom:4px;">$<?= number_format($comision_mes, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Comisión del Mes</div>
        </div>

    </div>

</div>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_dashboard_admin', 'gofast_dashboard_admin_shortcode');

