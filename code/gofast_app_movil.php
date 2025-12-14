<?php
/***************************************************
 * GOFAST – APP MÓVIL
 * Shortcode: [gofast_app_movil]
 * URL: /app-movil
 * 
 * Página para descargar la aplicación móvil
 ***************************************************/
function gofast_app_movil_shortcode() {
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
    
    <!-- HERO SECTION -->
    <section class="gofast-home-section">
        <div class="gofast-box" style="background: linear-gradient(135deg, var(--gofast-yellow) 0%, #e6b91d 100%); text-align: center; padding: 60px 24px;">
            <div style="font-size: 80px; margin-bottom: 24px;">📱</div>
            <h1 style="margin: 0 0 16px 0; color: #000; font-size: 36px; font-weight: 700;">
                Descarga la App Go Fast
            </h1>
            <p style="margin: 0; color: #000; font-size: 18px; opacity: 0.9; max-width: 600px; margin: 0 auto;">
                Accede a todas las funcionalidades de Go Fast desde tu dispositivo móvil. 
                La app es una versión optimizada de nuestra plataforma web.
            </p>
        </div>
    </section>

    <!-- BENEFICIOS -->
    <section class="gofast-home-section">
        <div class="gofast-box">
            <h2 style="margin-top: 0; margin-bottom: 32px; color: #000; font-size: 28px; text-align: center;">
                ✨ Beneficios de la App
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                
                <div style="text-align: center; padding: 24px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
                    <h3 style="margin: 0 0 12px 0; color: #000; font-size: 20px;">Rápido y Fácil</h3>
                    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
                        Cotiza y solicita envíos en segundos desde cualquier lugar
                    </p>
                </div>

                <div style="text-align: center; padding: 24px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                    <h3 style="margin: 0 0 12px 0; color: #000; font-size: 20px;">Historial Completo</h3>
                    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
                        Accede a todos tus pedidos anteriores y estados
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- DESCARGAS -->
    <section class="gofast-home-section">
        <div class="gofast-box" style="text-align: center;">
            <h2 style="margin-top: 0; margin-bottom: 32px; color: #000; font-size: 28px;">
                📥 Descarga Ahora
            </h2>
            
            <div style="display: flex; justify-content: center; margin-bottom: 32px;">
                
                <!-- Android -->
                <div style="background: #f8f9fa; padding: 32px; border-radius: 12px; min-width: 250px; border: 2px solid #e0e0e0; text-align: center;">
                    <div style="margin-bottom: 16px; display: flex; justify-content: center;">
                        <img src="https://gofastdomicilios.com/wp-content/uploads/2025/11/cropped-LOGO2-scaled-1.png" 
                             alt="Go Fast App" 
                             style="width: 80px; height: 80px; object-fit: contain;">
                    </div>
                    <h3 style="margin: 0 0 16px 0; color: #000; font-size: 22px;">Android</h3>
                    <a href="<?php echo esc_url( home_url('/wp-content/uploads/apk/_GOFAST_19332837.apk') ); ?>" 
                       download="_GOFAST_19332837.apk"
                       style="display: inline-block; background: var(--gofast-yellow); color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; transition: background 0.2s;">
                        📥 Descargar APK
                    </a>
                    <p style="margin: 16px 0 0 0; color: #4CAF50; font-size: 13px; font-weight: 600;">
                        ✅ Disponible ahora
                    </p>
                    <p style="margin: 8px 0 0 0; color: #666; font-size: 12px;">
                        Tamaño: 31.63 MB
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- INSTRUCCIONES DE INSTALACIÓN -->
    <section class="gofast-home-section">
        <div class="gofast-box">
            <h2 style="margin-top: 0; margin-bottom: 24px; color: #000; font-size: 28px; text-align: center;">
                📲 Cómo Instalar la App
            </h2>
            
            <div style="background: #fff3cd; border-left: 4px solid var(--gofast-yellow); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <p style="margin: 0; color: #856404; font-size: 15px; font-weight: 600;">
                    ⚠️ Importante: Para instalar aplicaciones desde fuera de Google Play, necesitas habilitar 
                    "Orígenes desconocidos" en tu dispositivo Android.
                </p>
            </div>

            <div style="max-width: 800px; margin: 0 auto;">
                <h3 style="margin: 0 0 16px 0; color: #000; font-size: 20px;">
                    Pasos para habilitar "Orígenes desconocidos":
                </h3>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                    <h4 style="margin: 0 0 12px 0; color: #000; font-size: 18px;">
                        Método 1: Desde Configuración (Android 8.0+)
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; color: #333; line-height: 1.8;">
                        <li>Abre <strong>Configuración</strong> en tu dispositivo Android</li>
                        <li>Ve a <strong>Aplicaciones</strong> o <strong>Apps</strong></li>
                        <li>Toca en <strong>Acceso especial</strong> o <strong>Acceso a aplicaciones</strong></li>
                        <li>Selecciona <strong>Instalar aplicaciones desconocidas</strong></li>
                        <li>Elige el navegador que usarás (Chrome, Firefox, etc.)</li>
                        <li>Activa la opción <strong>Permitir desde esta fuente</strong></li>
                    </ol>
                </div>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                    <h4 style="margin: 0 0 12px 0; color: #000; font-size: 18px;">
                        Método 2: Durante la instalación (Android 7.0 y anteriores)
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; color: #333; line-height: 1.8;">
                        <li>Descarga el archivo APK desde el botón de arriba</li>
                        <li>Abre el archivo descargado desde las notificaciones o desde el administrador de archivos</li>
                        <li>Si aparece un mensaje de seguridad, toca <strong>Configuración</strong></li>
                        <li>Activa <strong>Permitir desde esta fuente</strong></li>
                        <li>Vuelve atrás y toca <strong>Instalar</strong></li>
                    </ol>
                </div>

                <div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 16px; border-radius: 8px; margin-top: 24px;">
                    <h4 style="margin: 0 0 8px 0; color: #2e7d32; font-size: 16px;">
                        ✅ Después de habilitar "Orígenes desconocidos":
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; color: #2e7d32; line-height: 1.8;">
                        <li>Descarga el APK haciendo clic en el botón <strong>"Descargar APK"</strong></li>
                        <li>Abre el archivo descargado desde las notificaciones</li>
                        <li>Toca <strong>Instalar</strong> cuando aparezca el diálogo</li>
                        <li>Espera a que termine la instalación</li>
                        <li>¡Listo! Ya puedes abrir la app Go Fast desde tu dispositivo</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- INFORMACIÓN ADICIONAL -->
    <section class="gofast-home-section">
        <div class="gofast-box" style="background: #f8f9fa; padding: 32px; border-radius: 12px;">
            <h3 style="margin: 0 0 16px 0; color: #000; font-size: 22px; text-align: center;">
                ℹ️ Información Importante
            </h3>
            <div style="color: #333; line-height: 1.8; font-size: 15px; max-width: 700px; margin: 0 auto;">
                <p style="margin-bottom: 12px;">
                    <strong>Requisitos del dispositivo:</strong>
                </p>
                <ul style="margin: 0 0 20px 20px; padding: 0;">
                    <li>Android 6.0 o superior</li>
                    <li>Conexión a internet</li>
                </ul>
                <p style="margin-bottom: 12px;">
                    <strong>Nota:</strong> La aplicación móvil es básicamente un frame que almacena 
                    el mismo contenido que la plataforma web, optimizado para dispositivos móviles.
                </p>
                <p style="margin: 0; text-align: center; color: #666; margin-top: 20px;">
                    ¿Necesitas ayuda? Contáctanos a través de 
                    <a href="https://wa.me/message/MEUXAL2T2I7IK1" target="_blank" rel="noopener noreferrer" 
                       style="color: var(--gofast-yellow); text-decoration: none; font-weight: 600;">WhatsApp</a>
                </p>
            </div>
        </div>
    </section>

    <!-- CTA FINAL -->
    <section class="gofast-home-section">
        <div class="gofast-box" style="background: #fff; text-align: center; padding: 40px 24px; border: 1px solid var(--gofast-gray-300);">
            <h2 style="margin: 0 0 16px 0; font-size: 28px; color: #000;">
                ¿Aún no tienes la app?
            </h2>
            <p style="margin: 0 0 24px 0; font-size: 16px; color: #666;">
                Mientras tanto, puedes usar nuestra plataforma web desde cualquier dispositivo
            </p>
            <a href="<?php echo $url_cotizar; ?>" 
               class="gofast-btn" style="background: var(--gofast-yellow); color: #000;">
                🛵 Cotizar Envío Ahora
            </a>
        </div>
    </section>

</div>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_app_movil', 'gofast_app_movil_shortcode');

