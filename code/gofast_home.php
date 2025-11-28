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
    $url_trabaja      = esc_url( home_url('/trabaja-con-nosotros') );
    $url_logout       = esc_url( home_url('/?gofast_logout=1') );

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
                Cotiza tu domicilio en segundos, confirma por WhatsApp y un mensajero GoFast recoge tu pedido donde estés.
            </p>

            <div class="gofast-home-hero-ctas">
                <!-- Botón principal de cotizar - ULTRA VISIBLE -->
                <a href="<?php echo $url_cotizar; ?>" class="gofast-btn-hero-mega">
                    <span class="gofast-btn-hero-icon">🛵</span>
                    <span class="gofast-btn-hero-text">Cotizar envío ahora</span>
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
                            👋 Hola, <?php echo !empty($nombre_usuario) ? $nombre_usuario : 'bienvenido a GoFast'; ?>
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
                        <a href="<?php echo $url_mensajero_cotizar; ?>" class="gofast-home-panel-link gofast-home-panel-primary">
                            <span class="gofast-home-panel-icon">🚚</span>
                            <span class="gofast-home-panel-text">
                                <strong>Crear servicio</strong>
                                <small>Registrar nuevo envío</small>
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
                        <a href="<?php echo $url_admin_usuarios; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">👥</span>
                            <span class="gofast-home-panel-text">
                                <strong>Usuarios</strong>
                                <small>Gestionar cuentas</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $url_recargos; ?>" class="gofast-home-panel-link">
                            <span class="gofast-home-panel-icon">⚙️</span>
                            <span class="gofast-home-panel-text">
                                <strong>Recargos</strong>
                                <small>Configurar tarifas</small>
                            </span>
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>

    </section>

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
                    <h3>Recibe</h3>
                    <p>Un mensajero recoge y entrega tu pedido</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: NUESTROS SERVICIOS -->
    <section class="gofast-home-section">
        <h2>Nuestros servicios</h2>
        <p class="gofast-home-text">
            Soluciones de mensajería adaptadas a tus necesidades
        </p>
        <div class="gofast-home-grid">
            <div class="gofast-box gofast-home-service-card">
                <div class="gofast-home-service-icon">📦</div>
                <h3>Mensajería urbana</h3>
                <p>Envíos punto a punto dentro de la ciudad: documentos, paquetes pequeños y recaudos rápidos.</p>
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
        </div>
    </section>

    <!-- SECCIÓN: VENTAJAS -->
    <section class="gofast-home-section gofast-home-advantages">
        <h2>¿Por qué elegir GoFast?</h2>
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
                    ¿Tienes moto y papeles al día? Únete a nuestra red de mensajeros GoFast y genera ingresos
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

