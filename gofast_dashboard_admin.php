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
    $total_pedidos = (int) $wpdb->get_var("SELECT COUNT(*) FROM servicios_gofast");
    $pedidos_pendientes = (int) $wpdb->get_var("SELECT COUNT(*) FROM servicios_gofast WHERE tracking_estado = 'pendiente'");
    $pedidos_en_ruta = (int) $wpdb->get_var("SELECT COUNT(*) FROM servicios_gofast WHERE tracking_estado = 'en_ruta'");
    $pedidos_entregados = (int) $wpdb->get_var("SELECT COUNT(*) FROM servicios_gofast WHERE tracking_estado = 'entregado'");
    
    $total_usuarios = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE activo = 1");
    $total_clientes = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE rol = 'cliente' AND activo = 1");
    $total_mensajeros = (int) $wpdb->get_var("SELECT COUNT(*) FROM usuarios_gofast WHERE rol = 'mensajero' AND activo = 1");
    
    $total_ingresos = (float) $wpdb->get_var("SELECT SUM(total) FROM servicios_gofast WHERE tracking_estado = 'entregado'");
    $total_ingresos = $total_ingresos ?: 0;
    
    $pedidos_hoy = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM servicios_gofast WHERE DATE(fecha) = %s",
            current_time('Y-m-d')
        )
    );

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

    </div>

    <!-- Tarjetas de estadísticas - DESPUÉS -->
    <div class="gofast-dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin:32px 0;">
        
        <!-- Pedidos -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📦</div>
            <div style="font-size:28px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($total_pedidos); ?></div>
            <div style="font-size:13px;color:#666;">Total Pedidos</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">⏳</div>
            <div style="font-size:28px;font-weight:700;color:#ff9800;margin-bottom:4px;"><?= number_format($pedidos_pendientes); ?></div>
            <div style="font-size:13px;color:#666;">Pendientes</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🚚</div>
            <div style="font-size:28px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($pedidos_en_ruta); ?></div>
            <div style="font-size:13px;color:#666;">En Ruta</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;"><?= number_format($pedidos_entregados); ?></div>
            <div style="font-size:13px;color:#666;">Entregados</div>
        </div>

        <!-- Usuarios -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">👥</div>
            <div style="font-size:28px;font-weight:700;color:#9C27B0;margin-bottom:4px;"><?= number_format($total_usuarios); ?></div>
            <div style="font-size:13px;color:#666;">Total Usuarios</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🛒</div>
            <div style="font-size:28px;font-weight:700;color:#E91E63;margin-bottom:4px;"><?= number_format($total_clientes); ?></div>
            <div style="font-size:13px;color:#666;">Clientes</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">🚚</div>
            <div style="font-size:28px;font-weight:700;color:#00BCD4;margin-bottom:4px;"><?= number_format($total_mensajeros); ?></div>
            <div style="font-size:13px;color:#666;">Mensajeros</div>
        </div>

        <!-- Ingresos -->
        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">💰</div>
            <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;">$<?= number_format($total_ingresos, 0, ',', '.'); ?></div>
            <div style="font-size:13px;color:#666;">Ingresos Totales</div>
        </div>

        <div class="gofast-box" style="text-align:center;padding:20px;">
            <div style="font-size:32px;margin-bottom:8px;">📅</div>
            <div style="font-size:28px;font-weight:700;color:#FF5722;margin-bottom:4px;"><?= number_format($pedidos_hoy); ?></div>
            <div style="font-size:13px;color:#666;">Pedidos Hoy</div>
        </div>

    </div>

</div>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_dashboard_admin', 'gofast_dashboard_admin_shortcode');

