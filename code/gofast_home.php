<?php
/***************************************************
 * GOFAST – HOME PRINCIPAL
 * Shortcode: [gofast_home]
 * URL: (página principal del sitio)
 ***************************************************/
function gofast_home_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Obtener rol y nombre del usuario
    $rol = 'visitante';
    $nombre_usuario = '';

    if (!empty($_SESSION['gofast_user_id'])) {
        $user_id = (int) $_SESSION['gofast_user_id'];

        if (!empty($_SESSION['gofast_user_rol'])) {
            $rol = strtolower($_SESSION['gofast_user_rol']);
        }

        // Obtener nombre del usuario
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, nombre, rol 
                 FROM usuarios_gofast 
                 WHERE id=%d AND activo=1",
                $user_id
            )
        );

        if ($user) {
            $nombre_usuario = esc_html($user->nombre);
            if (empty($_SESSION['gofast_user_rol'])) {
                $rol = strtolower($user->rol);
                $_SESSION['gofast_user_rol'] = $rol;
            }
        }
    }

    // URLs base
    $url_cotizar      = esc_url( home_url('/cotizar') );
    $url_auth         = esc_url( home_url('/auth') );
    $url_registro     = esc_url( home_url('/auth/?registro=1') );
    $url_mis_pedidos  = esc_url( home_url('/mis-pedidos') );
    $url_mi_negocio   = esc_url( home_url('/mi-negocio') );
    $url_dashboard    = esc_url( home_url('/dashboard-admin') );
    $url_recargos     = esc_url( home_url('/recargos') );
    $url_admin_usuarios = esc_url( home_url('/admin-usuarios') );
    $url_mensajero_cotizar = esc_url( home_url('/mensajero-cotizar') );
    $url_transferencias = esc_url( home_url('/transferencias') );
    $url_compras = esc_url( home_url('/compras') );
    $url_reportes = esc_url( home_url('/admin-reportes') );
    $url_trabaja      = esc_url( home_url('/trabaja-con-nosotros') );
    $url_logout       = esc_url( home_url('/?gofast_logout=1') );
    $url_intermunicipal = esc_url( home_url('/cotizar-intermunicipal') );
    $url_admin_cotizar = esc_url( home_url('/admin-cotizar') );
    $url_admin_cotizar_intermunicipal = esc_url( home_url('/admin-cotizar-intermunicipal') );
    $url_mensajero_cotizar_intermunicipal = esc_url( home_url('/mensajero-cotizar-intermunicipal') );
    
    // URL del botón principal según el rol del usuario
    if ($rol === 'mensajero') {
        $url_cotizar_principal = $url_mensajero_cotizar;
    } elseif ($rol === 'admin') {
        // Admin usa cotización normal del cliente en el botón principal
        $url_cotizar_principal = $url_cotizar;
    } else {
        // visitante o cliente
        $url_cotizar_principal = $url_cotizar;
    }

    /* ==========================================================
       Estadísticas para mensajero y admin
    ========================================================== */
    $fecha_hoy = gofast_current_time('Y-m-d');
    $top_mensajeros = [];
    $stats_admin = [];

    // Top mensajeros del día (basado en ingresos totales)
    // Usar subconsultas para evitar duplicación por JOIN entre servicios y compras
    // Obtener todos los mensajeros (sin límite) para poder desplegar el resto
    if ($rol === 'mensajero' || $rol === 'admin') {
        $sql_top_mensajeros = $wpdb->prepare(
            "SELECT 
                u.id,
                u.nombre,
                COALESCE((
                    SELECT SUM(s.total)
                    FROM servicios_gofast s
                    WHERE s.mensajero_id = u.id 
                    AND DATE(s.fecha) = %s
                    AND s.tracking_estado != 'cancelado'
                ), 0) + COALESCE((
                    SELECT SUM(c.valor)
                    FROM compras_gofast c
                    WHERE c.mensajero_id = u.id 
                    AND DATE(c.fecha_creacion) = %s
                    AND c.estado != 'cancelada'
                ), 0) as ingresos_totales
            FROM usuarios_gofast u
            WHERE u.rol = 'mensajero' AND u.activo = 1
            HAVING ingresos_totales > 0
            ORDER BY ingresos_totales DESC",
            $fecha_hoy,
            $fecha_hoy
        );
        
        $top_mensajeros = $wpdb->get_results($sql_top_mensajeros);
    }

    // Estadísticas para admin
    if ($rol === 'admin') {
        // Total destinos
        $stats_admin['total_destinos'] = (int) ($wpdb->get_var(
            "SELECT SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))) FROM servicios_gofast"
        ) ?? 0);
        
        // Total compras (excluyendo canceladas)
        $stats_admin['total_compras'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado != 'cancelada'");
        
        // Ingresos totales (servicios no cancelados + compras no canceladas)
        $ingresos_servicios = (float) ($wpdb->get_var("SELECT SUM(total) FROM servicios_gofast WHERE tracking_estado != 'cancelado'") ?? 0);
        $ingresos_compras = (float) ($wpdb->get_var("SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada'") ?? 0);
        $stats_admin['ingresos_totales'] = $ingresos_servicios + $ingresos_compras;
    }

    ob_start();
    ?>

<div class="gofast-home">

    <!-- HERO SUPERIOR: título + botón cotizar + panel según rol -->
    <section class="gofast-home-hero">

        <!-- LADO IZQUIERDO: mensaje comercial -->
        <div class="gofast-home-hero-text">
            <h1>
                Envíos rápidos, <span>seguros</span> y sin complicaciones.
            </h1>

            <p class="gofast-home-subtitle">
                <?php if ($rol === 'mensajero'): ?>
                    Crea servicios, agrega destinos y confirma por WhatsApp.
                <?php elseif ($rol === 'admin'): ?>
                    Cotiza, agrega destino, y confirma valor a cliente.
                <?php else: ?>
                    Cotiza tu domicilio en segundos, confirma por WhatsApp y un mensajero Go Fast recoge tu pedido donde estés.
                <?php endif; ?>
            </p>

            <div class="gofast-home-hero-ctas">
                <!-- Botón principal de cotizar - ULTRA VISIBLE -->
                <a href="<?php echo $url_cotizar_principal; ?>" class="gofast-btn-hero-mega">
                    <span class="gofast-btn-hero-icon">🛵</span>
                    <span class="gofast-btn-hero-text"><?php echo $rol === 'mensajero' ? 'Crear servicio' : 'Cotizar envío ahora'; ?></span>
                    <span class="gofast-btn-hero-arrow">→</span>
                </a>

                <?php if ($rol === 'visitante'): ?>
                    <div class="gofast-home-links">
                        <a href="<?php echo $url_auth; ?>">Iniciar sesión</a>
                        <span>·</span>
                        <a href="<?php echo $url_registro; ?>">Registrar mi negocio</a>
                    </div>
                <?php else: ?>
                    <div class="gofast-home-links">
                        <span>
                            👋 Hola, <?php echo !empty($nombre_usuario) ? $nombre_usuario : 'bienvenido a Go Fast'; ?>
                        </span>
                        <span>·</span>
                        <a href="<?php echo $url_logout; ?>">Salir</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Beneficios clave con iconos más visibles -->
            <div class="gofast-home-benefits">
                <div class="gofast-home-benefit-item">
                    <span class="gofast-home-benefit-icon">💰</span>
                    <span class="gofast-home-benefit-text">Tarifas claras y transparentes</span>
                </div>
                <div class="gofast-home-benefit-item">
                    <span class="gofast-home-benefit-icon">⚡</span>
                    <span class="gofast-home-benefit-text">Confirmación instantánea</span>
                </div>
                <div class="gofast-home-benefit-item">
                    <span class="gofast-home-benefit-icon">📱</span>
                    <span class="gofast-home-benefit-text">Seguimiento por WhatsApp</span>
                </div>
            </div>
        </div>

        <!-- LADO DERECHO: accesos rápidos según rol -->
        <div class="gofast-box gofast-home-hero-panel">
            <h3>Accesos rápidos</h3>
            <ul class="gofast-home-panel-list">

                <?php if ($rol === 'visitante'): ?>

                    <li>
                        <a href="<?php echo $url_cotizar; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🛵</span>
                            <span class="gofast-home-panel-text">
                                <strong>Cotizar envío</strong>
                                <small>Calcula el costo en segundos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_intermunicipal; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🚚</span>
                            <span class="gofast-home-panel-text">
                                <strong>Envíos intermunicipales</strong>
                                <small>Desde Tuluá a otros municipios</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_registro; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🏪</span>
                            <span class="gofast-home-panel-text">
                                <strong>Registrar negocio</strong>
                                <small>Para clientes frecuentes</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_trabaja; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">💼</span>
                            <span class="gofast-home-panel-text">
                                <strong>Ser mensajero</strong>
                                <small>Únete a nuestro equipo</small>
                            </span>
                        </a>
                    </li>

                <?php elseif ($rol === 'cliente'): ?>

                    <li>
                        <a href="<?php echo $url_cotizar; ?>" class="gofast-home-panel-link gofast-home-panel-primary">
                            <span class="gofast-home-panel-icon">🛵</span>
                            <span class="gofast-home-panel-text">
                                <strong>Nuevo envío</strong>
                                <small>Crear un nuevo pedido</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_intermunicipal; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🚚</span>
                            <span class="gofast-home-panel-text">
                                <strong>Envíos intermunicipales</strong>
                                <small>Desde Tuluá a otros municipios</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_mis_pedidos; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">📦</span>
                            <span class="gofast-home-panel-text">
                                <strong>Mis pedidos</strong>
                                <small>Ver historial y estado</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_mi_negocio; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🏪</span>
                            <span class="gofast-home-panel-text">
                                <strong>Mi negocio</strong>
                                <small>Gestionar direcciones</small>
                            </span>
                        </a>
                    </li>

                <?php elseif ($rol === 'mensajero'): ?>

                    <li>
                        <a href="<?php echo $url_cotizar_principal; ?>" class="gofast-home-panel-link gofast-home-panel-primary">
                            <span class="gofast-home-panel-icon">🚚</span>
                            <span class="gofast-home-panel-text">
                                <strong>Crear servicio</strong>
                                <small>Registrar nuevo envío</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_mensajero_cotizar_intermunicipal; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🌐</span>
                            <span class="gofast-home-panel-text">
                                <strong>Envíos intermunicipales</strong>
                                <small>Desde Tuluá a otros municipios</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_mis_pedidos; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">📦</span>
                            <span class="gofast-home-panel-text">
                                <strong>Mis servicios</strong>
                                <small>Ver mis envíos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_transferencias; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">💰</span>
                            <span class="gofast-home-panel-text">
                                <strong>Transferencias</strong>
                                <small>Solicitar pagos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_compras; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🛒</span>
                            <span class="gofast-home-panel-text">
                                <strong>Compras</strong>
                                <small>Gestionar compras</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_reportes; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">📊</span>
                            <span class="gofast-home-panel-text">
                                <strong>Reportes</strong>
                                <small>Ver estadísticas</small>
                            </span>
                        </a>
                    </li>

                <?php elseif ($rol === 'admin'): ?>

                    <li>
                        <a href="<?php echo $url_dashboard; ?>" class="gofast-home-panel-link gofast-home-panel-primary">
                            <span class="gofast-home-panel-icon">📊</span>
                            <span class="gofast-home-panel-text">
                                <strong>Panel admin</strong>
                                <small>Dashboard principal</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_mis_pedidos; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">📦</span>
                            <span class="gofast-home-panel-text">
                                <strong>Todos los pedidos</strong>
                                <small>Gestionar envíos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_transferencias; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">💰</span>
                            <span class="gofast-home-panel-text">
                                <strong>Transferencias</strong>
                                <small>Gestionar pagos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_compras; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🛒</span>
                            <span class="gofast-home-panel-text">
                                <strong>Compras</strong>
                                <small>Gestionar compras</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_reportes; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">📊</span>
                            <span class="gofast-home-panel-text">
                                <strong>Reportes</strong>
                                <small>Ver estadísticas</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_admin_finanzas ?? home_url('/admin-finanzas'); ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">💹</span>
                            <span class="gofast-home-panel-text">
                                <strong>Finanzas</strong>
                                <small>Gestionar egresos y saldos</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_admin_cotizar; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🚚</span>
                            <span class="gofast-home-panel-text">
                                <strong>Crear Servicio</strong>
                                <small>Asignar a mensajero</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_admin_cotizar_intermunicipal; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">🌐</span>
                            <span class="gofast-home-panel-text">
                                <strong>Envio Intermunicipal</strong>
                                <small>Asignar a mensajero</small>
                            </span>
                        </a>
                    </li>

                <?php endif; ?>

    </section>

    <!-- SECCIÓN: TOP MENSAJEROS DEL DÍA - DESPUÉS DE ACCESOS RÁPIDOS -->
    <?php if (($rol === 'mensajero' || $rol === 'admin') && !empty($top_mensajeros)): 
        $total_mensajeros = count($top_mensajeros);
        $mostrar_inicial = 5;
        $hay_mas = $total_mensajeros > $mostrar_inicial;
    ?>
    <section class="gofast-home-section" style="margin-bottom:32px;">
        <div class="gofast-box">
            <h2 style="margin-top:0;">🏆 Top Mensajeros del Día</h2>
            <p class="gofast-home-text" style="margin-bottom:16px;">
                Ranking de mensajeros según ingresos totales de hoy
            </p>
            <div id="gofast-top-mensajeros-list" style="display:flex;flex-direction:column;gap:8px;">
                <?php 
                $posicion = 1;
                foreach ($top_mensajeros as $mensajero): 
                    $medalla = '';
                    if ($posicion == 1) $medalla = '🥇';
                    elseif ($posicion == 2) $medalla = '🥈';
                    elseif ($posicion == 3) $medalla = '🥉';
                    else $medalla = $posicion . '°';
                    
                    $clase_oculta = ($posicion > $mostrar_inicial) ? 'gofast-mensajero-oculto' : '';
                ?>
                    <div class="gofast-mensajero-item <?php echo $clase_oculta; ?>" style="display:flex;align-items:center;<?php echo $rol === 'admin' ? 'justify-content:space-between;' : ''; ?>gap:12px;padding:12px;background:#f8f9fa;border-radius:8px;border-left:4px solid #F4C524;">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;">
                            <div style="font-size:24px;font-weight:700;min-width:40px;text-align:center;">
                                <?php echo $medalla; ?>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:16px;font-weight:600;color:#1a1a1a;">
                                    <?php echo esc_html($mensajero->nombre); ?>
                                </div>
                                <?php if ($rol === 'mensajero'): ?>
                                <div style="font-size:13px;color:#666;margin-top:2px;">
                                    Posición <?php echo $posicion; ?> de <?php echo $total_mensajeros; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($rol === 'admin'): ?>
                        <div style="text-align:right;">
                            <div style="font-size:18px;font-weight:700;color:#4CAF50;">
                                $<?php echo number_format($mensajero->ingresos_totales, 0, ',', '.'); ?>
                            </div>
                            <div style="font-size:11px;color:#999;">
                                Ingresos hoy
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    $posicion++;
                endforeach; 
                ?>
            </div>
            <?php if ($hay_mas): ?>
            <div style="text-align:center;margin-top:16px;">
                <button id="gofast-toggle-mensajeros" class="gofast-btn-mini" style="cursor:pointer;border:none;background:#F4C524;color:#1a1a1a;padding:10px 20px;border-radius:6px;font-weight:600;">
                    <span id="gofast-toggle-text">Ver todos (<?php echo $total_mensajeros; ?> mensajeros)</span>
                    <span id="gofast-toggle-icon">▼</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <style>
        .gofast-mensajero-oculto {
            display: none !important;
        }
    </style>
    <script>
        (function() {
            var toggleBtn = document.getElementById('gofast-toggle-mensajeros');
            if (!toggleBtn) return;
            
            var itemsOcultos = document.querySelectorAll('.gofast-mensajero-oculto');
            var toggleText = document.getElementById('gofast-toggle-text');
            var toggleIcon = document.getElementById('gofast-toggle-icon');
            var desplegado = false;
            
            toggleBtn.addEventListener('click', function() {
                itemsOcultos.forEach(function(item) {
                    if (desplegado) {
                        item.classList.add('gofast-mensajero-oculto');
                    } else {
                        item.classList.remove('gofast-mensajero-oculto');
                    }
                });
                
                desplegado = !desplegado;
                
                if (desplegado) {
                    toggleText.textContent = 'Ver menos (Top 5)';
                    toggleIcon.textContent = '▲';
                } else {
                    toggleText.textContent = 'Ver todos (<?php echo $total_mensajeros; ?> mensajeros)';
                    toggleIcon.textContent = '▼';
                }
            });
        })();
    </script>
    <?php endif; ?>

    <!-- SECCIÓN: ESTADÍSTICAS ADMIN - DESPUÉS DEL RANKING -->
    <?php if ($rol === 'admin' && !empty($stats_admin)): ?>
    <section class="gofast-home-section" style="margin-bottom:32px;">
        <div class="gofast-dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;">
            <div class="gofast-box" style="text-align:center;padding:20px;">
                <div style="font-size:32px;margin-bottom:8px;">📍</div>
                <div style="font-size:28px;font-weight:700;color:#F4C524;margin-bottom:4px;">
                    <?php echo number_format($stats_admin['total_destinos']); ?>
                </div>
                <div style="font-size:13px;color:#666;">Total Destinos</div>
            </div>
            <div class="gofast-box" style="text-align:center;padding:20px;">
                <div style="font-size:32px;margin-bottom:8px;">🛒</div>
                <div style="font-size:28px;font-weight:700;color:#E91E63;margin-bottom:4px;">
                    <?php echo number_format($stats_admin['total_compras']); ?>
                </div>
                <div style="font-size:13px;color:#666;">Total Compras</div>
            </div>
            <div class="gofast-box" style="text-align:center;padding:20px;">
                <div style="font-size:32px;margin-bottom:8px;">💰</div>
                <div style="font-size:28px;font-weight:700;color:#4CAF50;margin-bottom:4px;">
                    $<?php echo number_format($stats_admin['ingresos_totales'], 0, ',', '.'); ?>
                </div>
                <div style="font-size:13px;color:#666;">Ingresos Totales</div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- SECCIÓN: CÓMO FUNCIONA (NUEVA) -->
    <section class="gofast-home-section gofast-home-how">
        <h2>¿Cómo funciona?</h2>
        <p class="gofast-home-text">
            En 3 simples pasos puedes enviar tu pedido
        </p>
        <div class="gofast-home-steps">
            <div class="gofast-home-step">
                <div class="gofast-home-step-number">1</div>
                <div class="gofast-home-step-content">
                    <h3>Cotiza</h3>
                    <p>Ingresa origen y destino, obtén el precio al instante</p>
                </div>
            </div>
            <div class="gofast-home-step">
                <div class="gofast-home-step-number">2</div>
                <div class="gofast-home-step-content">
                    <h3>Confirma</h3>
                    <p>Revisa los detalles y confirma tu envío</p>
                </div>
            </div>
            <div class="gofast-home-step">
                <div class="gofast-home-step-number">3</div>
                <div class="gofast-home-step-content">
                    <h3>Entrega</h3>
                    <p>Un mensajero recoge y entrega tu pedido</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: NUESTROS SERVICIOS -->
    <section class="gofast-home-section gofast-home-services">
        <h2>Nuestros servicios</h2>
        <p class="gofast-home-text">
            Soluciones de mensajería adaptadas a tus necesidades
        </p>
        <div class="gofast-home-grid gofast-home-services-grid">
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">📦</div>
                <h3>Mensajería urbana</h3>
                <p>Envíos punto a punto dentro de la ciudad: documentos, paquetes pequeños y recaudos rápidos.</p>
            </div>
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">🚚</div>
                <h3>Envíos intermunicipales</h3>
                <p>Envíos desde Tuluá a otros municipios cercanos. Pago anticipado requerido. Solo zona urbana.</p>
                <a href="<?php echo $url_intermunicipal; ?>" class="gofast-btn-mini" style="margin-top: 12px; display: inline-block;">
                    Cotizar ahora →
                </a>
            </div>
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">🏪</div>
                <h3>Domicilios para negocios</h3>
                <p>Ideal para restaurantes, tiendas y comercios con alto volumen de pedidos durante el día.</p>
            </div>
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">💵</div>
                <h3>Pagos contraentrega</h3>
                <p>Entregamos tu producto y recolectamos el dinero para llevarlo de forma segura a tu negocio.</p>
            </div>
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">🛒</div>
                <h3>Compras</h3>
                <p>Realizamos compras de productos en tiendas y supermercados. Tú pides, nosotros compramos y entregamos.</p>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: VENTAJAS -->
    <section class="gofast-home-section gofast-home-advantages">
        <h2>¿Por qué elegir Go Fast?</h2>
        <div class="gofast-home-grid gofast-home-advantages-grid">
            <div class="gofast-box gofast-home-advantage-card">
                <div class="gofast-home-advantage-icon">⚡</div>
                <h3>Rápido</h3>
                <p>Cotización instantánea y envíos en el mismo día</p>
            </div>
            <div class="gofast-box gofast-home-advantage-card">
                <div class="gofast-home-advantage-icon">🔒</div>
                <h3>Seguro</h3>
                <p>Mensajeros verificados y seguimiento en tiempo real</p>
            </div>
            <div class="gofast-box gofast-home-advantage-card">
                <div class="gofast-home-advantage-icon">💰</div>
                <h3>Económico</h3>
                <p>Tarifas competitivas sin costos ocultos</p>
            </div>
            <div class="gofast-box gofast-home-advantage-card">
                <div class="gofast-home-advantage-icon">📱</div>
                <h3>Fácil</h3>
                <p>Todo desde tu celular, sin complicaciones</p>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: TRABAJA CON NOSOTROS -->
    <section class="gofast-home-section">
        <div class="gofast-box gofast-home-work">
            <div>
                <h2>Trabaja con nosotros</h2>
                <p>
                    ¿Tienes moto y papeles al día? Únete a nuestra red de mensajeros Go Fast y genera ingresos
                    adicionales haciendo domicilios en tu ciudad.
                </p>
            </div>
            <div class="gofast-home-work-cta">
                <a href="<?php echo $url_trabaja; ?>" class="gofast-btn">
                    💼 Quiero ser mensajero
                </a>
            </div>
        </div>
    </section>

</div>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_home', 'gofast_home_shortcode');

