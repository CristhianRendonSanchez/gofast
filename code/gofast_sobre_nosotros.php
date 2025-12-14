<?php
/***************************************************
 * GOFAST – SOBRE NOSOTROS
 * Shortcode: [gofast_sobre_nosotros]
 * URL: /sobre-nosotros
 * 
 * Página con información sobre la empresa:
 * - Quiénes somos
 * - Nuestro equipo
 * - Políticas (datos, envío, etc)
 ***************************************************/
function gofast_sobre_nosotros_shortcode() {
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
    
<div class="gofast-home">
    
    <!-- SECCIÓN: QUIÉNES SOMOS -->
    <section class="gofast-home-section">
        <div class="gofast-box">
            <h1 style="margin-top: 0; margin-bottom: 20px; color: #000; font-size: 32px;">
                🚀 Quiénes Somos
            </h1>
            <div style="color: #333; line-height: 1.8; font-size: 16px;">
                <p style="margin-bottom: 16px;">
                    <strong>Go Fast</strong> es una plataforma de mensajería express que conecta a clientes, negocios y mensajeros 
                    en la ciudad de Tuluá y sus alrededores. Nuestra misión es facilitar el envío de paquetes, documentos 
                    y productos de manera rápida, segura y confiable.
                </p>
                <p style="margin-bottom: 16px;">
                    Trabajamos en convenio con diferentes establecimientos públicos, comerciales, empresas y emprendedores, 
                    ofreciendo un servicio de calidad que se adapta a las necesidades de cada cliente.
                </p>
                <p style="margin-bottom: 0;">
                    Con tecnología moderna y un equipo comprometido, buscamos ser la opción preferida para todos tus envíos 
                    en la región.
                </p>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: NUESTRO EQUIPO -->
    <section class="gofast-home-section">
        <div class="gofast-box">
            <h2 style="margin-top: 0; margin-bottom: 24px; color: #000; font-size: 28px;">
                👥 Nuestro Equipo
            </h2>
            <div class="gofast-equipo-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                
                <!-- Equipo 1 -->
                <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; text-align: center; border: 2px solid #f0f0f0;">
                    <div style="width: 100px; height: 100px; background: var(--gofast-yellow); border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        🛵
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #000; font-size: 20px;">Mensajeros</h3>
                    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
                        Nuestro equipo de mensajeros profesionales, capacitados y comprometidos con la puntualidad 
                        y el cuidado de tus envíos.
                    </p>
                </div>

                <!-- Equipo 2 -->
                <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; text-align: center; border: 2px solid #f0f0f0;">
                    <div style="width: 100px; height: 100px; background: var(--gofast-yellow); border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        📞
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #000; font-size: 20px;">Atención al Cliente</h3>
                    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
                        Personal dedicado a resolver tus dudas, brindar soporte y garantizar la mejor experiencia 
                        en cada interacción.
                    </p>
                </div>

                <!-- Equipo 3 -->
                <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; text-align: center; border: 2px solid #f0f0f0;">
                    <div style="width: 100px; height: 100px; background: var(--gofast-yellow); border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        💼
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #000; font-size: 20px;">Administración</h3>
                    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
                        Equipo administrativo que gestiona operaciones, coordina servicios y mantiene la calidad 
                        de nuestros procesos.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- SECCIÓN: POLÍTICAS -->
    <section class="gofast-home-section">
        <div class="gofast-box">
            <h2 style="margin-top: 0; margin-bottom: 24px; color: #000; font-size: 28px;">
                📋 Políticas
            </h2>

            <!-- Política de Datos -->
            <div style="margin-bottom: 32px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--gofast-yellow);">
                <h3 style="margin: 0 0 12px 0; color: #000; font-size: 22px;">
                    🔒 Política de Protección de Datos
                </h3>
                <div style="color: #333; line-height: 1.8; font-size: 15px;">
                    <p style="margin-bottom: 12px;">
                        En <strong>Go Fast</strong> nos comprometemos a proteger tu información personal. Los datos que nos proporcionas 
                        son utilizados exclusivamente para:
                    </p>
                    <ul style="margin: 0 0 12px 20px; padding: 0;">
                        <li>Procesar y gestionar tus servicios de mensajería</li>
                        <li>Comunicarnos contigo sobre el estado de tus envíos</li>
                        <li>Mejorar nuestros servicios y experiencia de usuario</li>
                        <li>Cumplir con obligaciones legales y regulatorias</li>
                    </ul>
                    <p style="margin: 0;">
                        No compartimos tus datos personales con terceros sin tu consentimiento, excepto cuando sea necesario 
                        para cumplir con el servicio solicitado. Puedes solicitar acceso, rectificación o eliminación de tus 
                        datos en cualquier momento.
                    </p>
                </div>
            </div>

            <!-- Política de Envío -->
            <div style="margin-bottom: 32px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--gofast-yellow);">
                <h3 style="margin: 0 0 12px 0; color: #000; font-size: 22px;">
                    📦 Política de Envío
                </h3>
                <div style="color: #333; line-height: 1.8; font-size: 15px;">
                    <p style="margin-bottom: 12px;">
                        <strong>Tiempos de entrega:</strong>
                    </p>
                    <ul style="margin: 0 0 12px 20px; padding: 0;">
                        <li>Envíos locales: 20 minutos a 30 minutos (según distancia y disponibilidad)</li>
                        <li>Envíos intermunicipales: 1 a 2 horas (según destino)</li>
                        <li>Los tiempos pueden variar por condiciones climáticas o tráfico</li>
                    </ul>
                    <p style="margin-bottom: 12px;">
                        <strong>Cobertura:</strong>
                    </p>
                    <ul style="margin: 0 0 12px 20px; padding: 0;">
                        <li>Ciudad de Tuluá y zonas aledañas</li>
                        <li>Rutas intermunicipales según disponibilidad</li>
                    </ul>
                    <p style="margin: 0;">
                        <strong>Restricciones:</strong> No transportamos objetos peligrosos, ilegales, perecederos sin refrigeración 
                        adecuada, mascotas o personas o artículos que excedan las dimensiones permitidas.
                    </p>
                </div>
            </div>

            <!-- Política de Cancelación y Reembolsos -->
            <div style="margin-bottom: 32px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--gofast-yellow);">
                <h3 style="margin: 0 0 12px 0; color: #000; font-size: 22px;">
                    ❌ Política de Cancelación y Reembolsos
                </h3>
                <div style="color: #333; line-height: 1.8; font-size: 15px;">
                    <p style="margin-bottom: 12px;">
                        <strong>Cancelaciones:</strong>
                    </p>
                    <ul style="margin: 0 0 12px 20px; padding: 0;">
                        <li>Puedes cancelar un servicio antes de que el mensajero inicie el recorrido</li>
                        <li>Si el mensajero ya inició el servicio, se aplicará una tarifa de cancelación</li>
                        <li>Las cancelaciones deben realizarse a través de la plataforma o contacto directo</li>
                    </ul>
                    <p style="margin: 0;">
                        <strong>Reembolsos:</strong> Los reembolsos se procesan según el caso específico y pueden tardar entre 
                        3 a 5 días hábiles. Contacta a nuestro equipo de atención al cliente para más información.
                    </p>
                </div>
            </div>

            <!-- Política de Responsabilidad -->
            <div style="margin-bottom: 0; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--gofast-yellow);">
                <h3 style="margin: 0 0 12px 0; color: #000; font-size: 22px;">
                    ⚖️ Política de Responsabilidad
                </h3>
                <div style="color: #333; line-height: 1.8; font-size: 15px;">
                    <p style="margin-bottom: 12px;">
                        <strong>Go Fast</strong> se compromete a:
                    </p>
                    <ul style="margin: 0 0 12px 20px; padding: 0;">
                        <li>Manejar tus paquetes con el mayor cuidado posible</li>
                        <li>Mantener la confidencialidad de la información del envío</li>
                        <li>Proporcionar seguimiento en tiempo real cuando sea posible</li>
                    </ul>
                    <p style="margin: 0;">
                        En caso de pérdida o daño, evaluaremos cada situación de manera individual. Te recomendamos 
                        declarar el valor de los artículos al momento de realizar el envío.
                    </p>
                </div>
            </div>

        </div>
    </section>

    <!-- SECCIÓN: CONTACTO -->
    <section class="gofast-home-section">
        <div class="gofast-box" style="background: linear-gradient(135deg, var(--gofast-yellow) 0%, #e6b91d 100%); text-align: center; padding: 40px 24px;">
            <h2 style="margin: 0 0 16px 0; color: #000; font-size: 28px;">
                ¿Tienes preguntas?
            </h2>
            <p style="margin: 0 0 24px 0; color: #000; font-size: 16px; opacity: 0.9;">
                Estamos aquí para ayudarte. Contáctanos a través de nuestras redes sociales o WhatsApp.
            </p>
            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="https://wa.me/message/MEUXAL2T2I7IK1" target="_blank" rel="noopener noreferrer" 
                   class="gofast-btn" style="background: #000; color: #fff;">
                    💬 WhatsApp
                </a>
                <a href="<?php echo $url_cotizar; ?>" class="gofast-btn" style="background: #000; color: #fff;">
                    🛵 Cotizar Envío
                </a>
            </div>
        </div>
    </section>

    <!-- CRÉDITOS DEL DESARROLLADOR -->
    <section class="gofast-home-section" style="margin-top: 40px;">
        <div style="text-align: center; padding: 24px 0;">
            <p style="margin: 0; color: #999; font-size: 12px; font-style: italic; opacity: 0.7;">
                Desarrollado por 
                <span style="color: #888; font-weight: 500;">CRISTHIAN RENDON</span>
            </p>
        </div>
    </section>

</div>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_sobre_nosotros', 'gofast_sobre_nosotros_shortcode');

