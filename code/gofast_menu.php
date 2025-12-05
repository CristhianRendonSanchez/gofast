<?php
/***************************************************
 * GOFAST – MENÚ DINÁMICO MODERNO + RESPONSIVE
 * Shortcode: [gofast_menu_topbar] (se ejecuta automáticamente)
 ***************************************************/
function gofast_menu_topbar() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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
    ?>
    <!-- BARRA SUPERIOR CON SALUDO Y REDES SOCIALES -->
    <div class="gofast-topbar">
        <div class="gofast-topbar-inner">
            <div class="gofast-topbar-left">
                <?php if (!empty($nombre_usuario)): ?>
                    <span class="gofast-welcome">👋 Bienvenido, <strong><?php echo $nombre_usuario; ?></strong></span>
                <?php endif; ?>
            </div>
            <div class="gofast-topbar-right">
                <a href="https://www.tiktok.com/@gofastulua?_r=1&_t=ZS-91GhG5Z2Xpf" target="_blank" rel="noopener noreferrer" class="gofast-social-link" aria-label="TikTok">
                    <span class="gofast-social-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                        </svg>
                    </span>
                    <span class="gofast-social-text">TikTok</span>
                </a>
                <a href="https://www.instagram.com/gofastulua?igsh=ZXhoMHE0bTQ3azgy" target="_blank" rel="noopener noreferrer" class="gofast-social-link" aria-label="Instagram">
                    <span class="gofast-social-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </span>
                    <span class="gofast-social-text">Instagram</span>
                </a>
                <a href="https://www.facebook.com/share/1BPYXAQJ7Y/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" class="gofast-social-link" aria-label="Facebook">
                    <span class="gofast-social-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </span>
                    <span class="gofast-social-text">Facebook</span>
                </a>
                <a href="https://wa.me/message/MEUXAL2T2I7IK1" target="_blank" rel="noopener noreferrer" class="gofast-social-link" aria-label="WhatsApp">
                    <span class="gofast-social-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.272-.099-.47-.148-.67.15-.197.297-.769.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                    </span>
                    <span class="gofast-social-text">WhatsApp</span>
                </a>
            </div>
        </div>
    </div>

    <header class="gofast-menu">
        <div class="gofast-menu-inner">
            <!-- LOGO -->
            <a href="<?php echo esc_url( home_url('/') ); ?>" class="gofast-logo" aria-label="Ir al inicio">
                <img src="https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png" alt="GoFast Mensajería Express">
            </a>

            <!-- BOTÓN HAMBURGUESA (solo móvil) -->
            <button class="gofast-hamburger" type="button" aria-label="Abrir menú">
                ☰
            </button>

            <!-- MENÚ -->
            <nav class="gofast-menu-links" aria-label="Menú principal">
                <!-- BOTÓN CERRAR (solo móvil) -->
                <button class="gofast-menu-close" type="button" aria-label="Cerrar menú">
                    <span class="gofast-close-icon">×</span>
                </button>
                
                <?php if ($rol === 'visitante'): ?>

                    <a href="<?php echo esc_url( home_url('/cotizar') ); ?>" class="gofast-tab gofast-tab-primary">
                        🛵 Cotizar envío
                    </a>
                    <a href="<?php echo esc_url( home_url('/auth') ); ?>" class="gofast-tab">
                        Iniciar sesión
                    </a>
                    <a href="<?php echo esc_url( home_url('/auth/?registro=1') ); ?>" class="gofast-tab">
                        Registrarse
                    </a>

                <?php elseif ($rol === 'cliente'): ?>

                    <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-tab gofast-tab-primary">
                        📦 Mis pedidos
                    </a>
                    <a href="<?php echo esc_url( home_url('/mi-negocio') ); ?>" class="gofast-tab">
                        🏪 Mi negocio
                    </a>
                    <a href="<?php echo esc_url( home_url('/cotizar') ); ?>" class="gofast-tab">
                        🛵 Nuevo envío
                    </a>
                    <a href="<?php echo esc_url( home_url('/?gofast_logout=1') ); ?>" class="gofast-tab">
                        🚪 Salir
                    </a>

                <?php elseif ($rol === 'mensajero'): ?>

                    <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-tab gofast-tab-primary">
                        📦 Mis servicios
                    </a>
                    <a href="<?php echo esc_url( home_url('/mensajero-cotizar') ); ?>" class="gofast-tab">
                        🚚 Crear servicio
                    </a>
                    <a href="<?php echo esc_url( home_url('/transferencias') ); ?>" class="gofast-tab">
                        💰 Transferencias
                    </a>
                    <a href="<?php echo esc_url( home_url('/compras') ); ?>" class="gofast-tab">
                        🛒 Compras
                    </a>
                    <a href="<?php echo esc_url( home_url('/?gofast_logout=1') ); ?>" class="gofast-tab">
                        🚪 Salir
                    </a>

                <?php elseif ($rol === 'admin'): ?>

                    <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-tab gofast-tab-primary">
                        📊 Panel admin
                    </a>
                    <a href="<?php echo esc_url( home_url('/mis-pedidos') ); ?>" class="gofast-tab">
                        📦 Pedidos
                    </a>
                    <a href="<?php echo esc_url( home_url('/transferencias') ); ?>" class="gofast-tab">
                        💰 Transferencias
                    </a>
                    <a href="<?php echo esc_url( home_url('/recargos') ); ?>" class="gofast-tab">
                        ⚙️ Recargos
                    </a>
                    <a href="<?php echo esc_url( home_url('/admin-reportes') ); ?>" class="gofast-tab">
                        📊 Reportes
                    </a>
                    <a href="<?php echo esc_url( home_url('/?gofast_logout=1') ); ?>" class="gofast-tab">
                        🚪 Salir
                    </a>

                <?php endif; ?>
            </nav>
        </div>
    </header>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn  = document.querySelector('.gofast-hamburger');
        const menu = document.querySelector('.gofast-menu-links');
        const closeBtn = document.querySelector('.gofast-menu-close');

        if (!btn || !menu) return;

        // Función para cerrar el menú
        function closeMenu() {
            document.body.classList.remove('gofast-menu-open');
        }

        // Abrir menú
        btn.addEventListener('click', function () {
            document.body.classList.toggle('gofast-menu-open');
        });

        // Cerrar menú con botón X
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMenu);
        }

        // Cerrar menú al hacer clic en enlaces
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        // Cerrar menú al redimensionar
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
    });
    </script>
    <?php
}
add_action('generate_before_header', 'gofast_menu_topbar');

add_action('wp', function() {
    remove_action( 'generate_after_header',  'generate_add_navigation_after_header', 5 );
    remove_action( 'generate_before_header', 'generate_add_navigation_before_header', 5 );
    remove_action( 'generate_header',        'generate_construct_header', 10 );
});

