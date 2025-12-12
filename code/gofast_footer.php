<?php
/***************************************************
 * GOFAST – FOOTER / PIE DE PÁGINA
 * Se ejecuta automáticamente en el footer de todas las páginas
 * 
 * Incluye:
 * - Enlaces importantes
 * - Redes sociales
 * - Información de contacto
 * - Créditos del desarrollador (sutil)
 ***************************************************/
function gofast_footer_content() {
    // Detectar rol del usuario
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $rol = 'visitante';
    if (!empty($_SESSION['gofast_user_id']) && !empty($_SESSION['gofast_user_rol'])) {
        $rol = strtolower($_SESSION['gofast_user_rol']);
    }
    
    // URL de cotizar según el rol
    if ($rol === 'mensajero') {
        $url_cotizar = esc_url( home_url('/mensajero-cotizar') );
    } elseif ($rol === 'admin') {
        $url_cotizar = esc_url( home_url('/admin-cotizar') );
    } else {
        // visitante o cliente
        $url_cotizar = esc_url( home_url('/cotizar') );
    }
    
    ob_start();
    ?>
    
<footer class="gofast-footer">
    <div class="gofast-footer-inner">
        
        <!-- CONTENIDO PRINCIPAL DEL FOOTER -->
        <div class="gofast-footer-content">
            
            <!-- COLUMNA 1: INFORMACIÓN -->
            <div class="gofast-footer-col">
                <img src="https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png" 
                     alt="GoFast Mensajería Express" 
                     class="gofast-footer-logo">
                <p class="gofast-footer-text">
                    GoFast Mensajería express en Tulúa y alrededores. Rápido, seguro y confiable.
                </p>
            </div>

            <!-- COLUMNA 2: ENLACES RÁPIDOS -->
            <div class="gofast-footer-col">
                <h3 class="gofast-footer-title">
                    Enlaces Rápidos
                </h3>
                <ul class="gofast-footer-links">
                    <li>
                        <a href="<?php echo $url_cotizar; ?>">
                            🛵 Cotizar Envío
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( home_url('/sobre-nosotros') ); ?>">
                            ℹ️ Sobre Nosotros
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( home_url('/app-movil') ); ?>">
                            📱 App Móvil
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( home_url('/trabaja-con-nosotros') ); ?>">
                            💼 Trabaja con Nosotros
                        </a>
                    </li>
                </ul>
            </div>

            <!-- COLUMNA 3: CONTACTO -->
            <div class="gofast-footer-col">
                <h3 class="gofast-footer-title">
                    Contacto
                </h3>
                <ul class="gofast-footer-contact">
                    <li>
                        <a href="https://wa.me/message/MEUXAL2T2I7IK1" target="_blank" rel="noopener noreferrer">
                            💬 WhatsApp
                        </a>
                    </li>
                    <li>
                        📍 Tulúa, Valle del Cauca
                    </li>
                    <li>
                        ⏰ Atención: Lunes a Domingo
                    </li>
                </ul>
            </div>

        </div>

        <!-- LÍNEA SEPARADORA Y COPYRIGHT -->
        <div class="gofast-footer-bottom">
            <div class="gofast-footer-copyright">
                © <?php echo date('Y'); ?> GoFast. Todos los derechos reservados.
            </div>
        </div>

    </div>
</footer>


    <?php
    return ob_get_clean();
}

// Agregar el footer al final de todas las páginas (incluyendo la principal)
add_action('wp_footer', function() {
    echo gofast_footer_content();
}, 999);

// Ocultar el footer por defecto de GeneratePress
add_action('wp', function() {
    remove_action( 'generate_footer', 'generate_construct_footer', 10 );
    remove_action( 'generate_after_footer', 'generate_after_footer_widget_area', 5 );
    remove_action( 'generate_before_footer', 'generate_before_footer_widget_area', 5 );
    remove_action( 'generate_after_footer', 'generate_add_footer_navigation', 5 );
});

