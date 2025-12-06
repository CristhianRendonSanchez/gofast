<?php
/***************************************************
 * GOFAST – TRABAJA CON NOSOTROS
 * Shortcode: [gofast_trabaja_con_nosotros]
 * URL: /trabaja-con-nosotros
 * 
 * Formulario de solicitud de trabajo para mensajeros
 * Incluye subida de hoja de vida y redirección a WhatsApp
 ***************************************************/
function gofast_trabaja_con_nosotros_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $mensaje = '';
    $mensaje_tipo = '';
    $mostrar_formulario = true;

    /*********************************************
     * PROCESAMIENTO DEL FORMULARIO
     *********************************************/

    if (isset($_POST['gofast_enviar_solicitud']) && wp_verify_nonce($_POST['gofast_solicitud_nonce'], 'gofast_enviar_solicitud')) {
        
        // Validar y sanitizar datos
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $whatsapp = sanitize_text_field($_POST['whatsapp'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $pregunta1 = sanitize_textarea_field($_POST['pregunta1'] ?? '');
        $pregunta2 = sanitize_textarea_field($_POST['pregunta2'] ?? '');
        $pregunta3 = sanitize_textarea_field($_POST['pregunta3'] ?? '');
        $pregunta4 = sanitize_textarea_field($_POST['pregunta4'] ?? '');
        $pregunta5 = sanitize_textarea_field($_POST['pregunta5'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($whatsapp) || empty($pregunta1) || empty($pregunta2) || 
            empty($pregunta3) || empty($pregunta4) || empty($pregunta5)) {
            $mensaje = 'Por favor completa todos los campos obligatorios.';
            $mensaje_tipo = 'error';
        } else {
            // Procesar archivo de hoja de vida
            $archivo_cv = null;
            $nombre_archivo = null;

            if (isset($_FILES['hoja_vida']) && $_FILES['hoja_vida']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['hoja_vida'];
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $allowed_extensions = ['pdf', 'doc', 'docx'];
                
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $file_type = $file['type'];
                
                // Validar tipo y extensión
                if (in_array($file_type, $allowed_types) || in_array($file_extension, $allowed_extensions)) {
                    // Validar tamaño (máximo 5MB)
                    if ($file['size'] <= 5 * 1024 * 1024) {
                        // Crear directorio si no existe
                        $upload_dir = wp_upload_dir();
                        $gofast_dir = $upload_dir['basedir'] . '/gofast-cvs';
                        
                        if (!file_exists($gofast_dir)) {
                            wp_mkdir_p($gofast_dir);
                        }

                        // Generar nombre único
                        $nombre_sanitizado = sanitize_file_name($nombre);
                        $timestamp = time();
                        $nombre_archivo_original = $file['name'];
                        $nombre_archivo_nuevo = $nombre_sanitizado . '_' . $timestamp . '_' . uniqid() . '.' . $file_extension;
                        $ruta_completa = $gofast_dir . '/' . $nombre_archivo_nuevo;

                        // Mover archivo
                        if (move_uploaded_file($file['tmp_name'], $ruta_completa)) {
                            $archivo_cv = $upload_dir['baseurl'] . '/gofast-cvs/' . $nombre_archivo_nuevo;
                            $nombre_archivo = $nombre_archivo_original;
                        } else {
                            $mensaje = 'Error al subir el archivo. Por favor intenta nuevamente.';
                            $mensaje_tipo = 'error';
                        }
                    } else {
                        $mensaje = 'El archivo es demasiado grande. El tamaño máximo es 5MB.';
                        $mensaje_tipo = 'error';
                    }
                } else {
                    $mensaje = 'Tipo de archivo no permitido. Solo se aceptan PDF, DOC o DOCX.';
                    $mensaje_tipo = 'error';
                }
            }

            // Si no hay errores, guardar en la base de datos
            if (empty($mensaje)) {
                $insertado = $wpdb->insert(
                    'solicitudes_trabajo',
                    [
                        'nombre' => $nombre,
                        'whatsapp' => $whatsapp,
                        'email' => $email ?: null,
                        'pregunta1' => $pregunta1,
                        'pregunta2' => $pregunta2,
                        'pregunta3' => $pregunta3,
                        'pregunta4' => $pregunta4,
                        'pregunta5' => $pregunta5,
                        'archivo_cv' => $archivo_cv,
                        'nombre_archivo' => $nombre_archivo,
                        'estado' => 'pendiente',
                        'created_at' => gofast_current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($insertado) {
                    // Limpiar WhatsApp (solo números)
                    $whatsapp_limpio = preg_replace('/[^0-9]/', '', $whatsapp);
                    
                    // Mensaje para WhatsApp
                    $mensaje_whatsapp = urlencode("Hola! Soy *" . $nombre . "* y acabo de completar mi solicitud de trabajo en GO FAST mensajería express. Mi WhatsApp es: *" . $whatsapp_limpio . "*. Estoy interesado en la vacante de mensajería. ¿Podrían contactarme?");
                    
                    // Redirigir a WhatsApp (CAMBIAR ESTE NÚMERO POR EL NÚMERO REAL DE GO FAST)
                    $whatsapp_gofast = "573001234567"; // TODO: Cambiar por el número real de WhatsApp de GO FAST
                    $url_whatsapp = "https://wa.me/" . $whatsapp_gofast . "?text=" . $mensaje_whatsapp;
                    
                    // Mostrar mensaje de éxito y redirigir
                    $mostrar_formulario = false;
                    ob_start();
                    ?>
                    <div class="gofast-home">
                        <div class="gofast-box" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 40px auto;">
                            <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
                            <h2 style="margin: 0 0 16px 0; color: #000;">¡Solicitud Enviada Exitosamente!</h2>
                            <p style="margin: 0 0 24px 0; color: #666; font-size: 16px; line-height: 1.6;">
                                Gracias <strong><?= esc_html($nombre) ?></strong>, hemos recibido tu solicitud correctamente.
                            </p>
                            <p style="margin: 0 0 32px 0; color: #666; font-size: 14px;">
                                Serás redirigido a WhatsApp en unos segundos para continuar el proceso...
                            </p>
                            <a href="<?= esc_url($url_whatsapp) ?>" 
                               target="_blank" 
                               class="gofast-btn-request" 
                               style="display: inline-block; text-decoration: none; margin-top: 16px;">
                                📱 Continuar en WhatsApp
                            </a>
                        </div>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.open('<?= esc_js($url_whatsapp) ?>', '_blank');
                        }, 2000);
                    </script>
                    <?php
                    return ob_get_clean();
                } else {
                    $mensaje = 'Error al guardar la solicitud. Por favor intenta nuevamente.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    ob_start();
    ?>

<div class="gofast-home">
    <div class="gofast-box" style="margin-bottom: 24px;">
        <h1 style="margin-top: 0; margin-bottom: 12px; color: #000;">📦 Trabaja con Nosotros</h1>
        <p style="margin: 0; color: #666; font-size: 15px; line-height: 1.6;">
            Somos una agencia de mensajería en convenio con diferentes establecimientos públicos, comerciales, empresas y emprendedores de la ciudad de Tulúa.
        </p>
    </div>

    <!-- Información sobre rentabilidad y tarifas -->
    <div class="gofast-box" style="background: #fff9d6; border-left: 5px solid var(--gofast-yellow); margin-bottom: 24px;">
        <h3 style="margin-top: 0; margin-bottom: 16px; color: #000;">💰 Rentabilidad</h3>
        <p style="margin: 0 0 12px 0; color: #333; font-size: 15px;">
            <strong>La rentabilidad del mensajero es del 80% del total producido en el día.</strong>
        </p>
        <p style="margin: 0; color: #666; font-size: 14px;">
            Las tarifas de cada servicio varían de acuerdo a la distancia entre punto de recogida y entrega:
        </p>
        <ul style="margin: 12px 0 0 0; padding-left: 20px; color: #666; font-size: 14px;">
            <li><strong>$3,500</strong> - Distancia mínima</li>
            <li><strong>$4,000</strong> - Distancia intermedia</li>
            <li><strong>$4,500</strong> - Distancia lejana</li>
            <li><strong>$5,000 / $6,000</strong> - Distancia extralejana</li>
            <li><strong>$6,000 / $7,000 / $8,000</strong> - Zonas aledañas</li>
        </ul>
    </div>

    <!-- Requisitos -->
    <div class="gofast-box" style="background: #e8f4ff; border-left: 5px solid #1f6feb; margin-bottom: 24px;">
        <h3 style="margin-top: 0; margin-bottom: 16px; color: #000;">📝 Requisitos para Laborar</h3>
        <ul style="margin: 0; padding-left: 20px; color: #333; font-size: 14px; line-height: 1.8;">
            <li>Conocimiento en direcciones</li>
            <li>Disponibilidad de tiempo</li>
            <li>Moto con documentación al día</li>
            <li>Teléfono con WhatsApp</li>
            <li>Excelente actitud y presentación personal</li>
            <li>Traje de lluvia</li>
            <li>Base de efectivo (50,000 mínimo)</li>
        </ul>
    </div>

    <!-- Mensaje de error si existe -->
    <?php if ($mensaje && $mensaje_tipo === 'error'): ?>
        <div class="gofast-box" style="background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; margin-bottom: 20px;">
            ⚠️ <?= esc_html($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario de solicitud -->
    <?php if ($mostrar_formulario): ?>
    <div class="gofast-box">
        <h3 style="margin-top: 0; margin-bottom: 20px; color: #000;">📝 Formulario de Solicitud</h3>
        <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
            Por favor completa el siguiente formulario para aplicar a la vacante de mensajería.
        </p>

        <form method="post" enctype="multipart/form-data" id="form-solicitud-trabajo">
            <?php wp_nonce_field('gofast_enviar_solicitud', 'gofast_solicitud_nonce'); ?>
            
            <!-- Datos personales -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <h4 style="margin-top: 0; margin-bottom: 16px; color: #000; font-size: 16px;">👤 Datos Personales</h4>
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    Nombre completo <span style="color: #dc3545;">*</span>
                </label>
                <input type="text" 
                       name="nombre" 
                       required 
                       placeholder="Ej: Juan Pérez"
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box;"
                       value="<?= isset($_POST['nombre']) ? esc_attr($_POST['nombre']) : '' ?>">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    WhatsApp <span style="color: #dc3545;">*</span>
                </label>
                <input type="text" 
                       name="whatsapp" 
                       required 
                       placeholder="Ej: 3001234567"
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box;"
                       value="<?= isset($_POST['whatsapp']) ? esc_attr($_POST['whatsapp']) : '' ?>">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    Email (opcional)
                </label>
                <input type="email" 
                       name="email" 
                       placeholder="Ej: juan@email.com"
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 0; box-sizing: border-box;"
                       value="<?= isset($_POST['email']) ? esc_attr($_POST['email']) : '' ?>">
            </div>

            <!-- Preguntas -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <h4 style="margin-top: 0; margin-bottom: 16px; color: #000; font-size: 16px;">❓ Preguntas de Selección</h4>

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    1. ¿Tiene experiencia en reparto a domicilio y conocimiento en direcciones? 🦉 <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="pregunta1" 
                          required 
                          rows="3"
                          placeholder="Describe tu experiencia..."
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box; resize: vertical; font-family: inherit;"><?= isset($_POST['pregunta1']) ? esc_textarea($_POST['pregunta1']) : '' ?></textarea>

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    2. ¿Cuál es su disponibilidad de tiempo? 🕐 <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="pregunta2" 
                          required 
                          rows="3"
                          placeholder="Ej: Disponible de lunes a sábado de 8am a 6pm"
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box; resize: vertical; font-family: inherit;"><?= isset($_POST['pregunta2']) ? esc_textarea($_POST['pregunta2']) : '' ?></textarea>

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    3. ¿Tiene vehículo propio con documentación al día? ✔ <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="pregunta3" 
                          required 
                          rows="3"
                          placeholder="Indica si tienes vehículo y el estado de la documentación..."
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box; resize: vertical; font-family: inherit;"><?= isset($_POST['pregunta3']) ? esc_textarea($_POST['pregunta3']) : '' ?></textarea>

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    4. ¿Tipo de motocicleta? 🛵 <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="pregunta4" 
                          required 
                          rows="3"
                          placeholder="Ej: Moto 150cc, marca Honda"
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 16px; box-sizing: border-box; resize: vertical; font-family: inherit;"><?= isset($_POST['pregunta4']) ? esc_textarea($_POST['pregunta4']) : '' ?></textarea>

                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    5. ¿Ciudad de residencia? 🏠 <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="pregunta5" 
                          required 
                          rows="3"
                          placeholder="Ej: Tuluá, Valle del Cauca"
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 0; box-sizing: border-box; resize: vertical; font-family: inherit;"><?= isset($_POST['pregunta5']) ? esc_textarea($_POST['pregunta5']) : '' ?></textarea>
            </div>

            <!-- Subida de hoja de vida -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <h4 style="margin-top: 0; margin-bottom: 16px; color: #000; font-size: 16px;">📄 Hoja de Vida</h4>
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #000; font-size: 14px;">
                    Subir hoja de vida (PDF, DOC o DOCX) <span style="color: #dc3545;">*</span>
                </label>
                <input type="file" 
                       name="hoja_vida" 
                       required 
                       accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-bottom: 8px; box-sizing: border-box; background: #fff;"
                       onchange="validarArchivo(this)">
                <small style="display: block; color: #666; font-size: 12px; margin-top: 4px;">
                    Tamaño máximo: 5MB. Formatos permitidos: PDF, DOC, DOCX
                </small>
                <div id="mensaje-archivo" style="margin-top: 8px; font-size: 13px;"></div>
            </div>

            <!-- Botón enviar -->
            <button type="submit" 
                    name="gofast_enviar_solicitud" 
                    class="gofast-btn-request"
                    style="width: 100%; font-size: 18px; padding: 16px;">
                ✅ Enviar Solicitud
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function validarArchivo(input) {
    const mensajeDiv = document.getElementById('mensaje-archivo');
    const file = input.files[0];
    
    if (!file) {
        mensajeDiv.innerHTML = '';
        return;
    }
    
    // Validar tamaño (5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB en bytes
    if (file.size > maxSize) {
        mensajeDiv.innerHTML = '<span style="color: #dc3545;">⚠️ El archivo es demasiado grande. Máximo 5MB.</span>';
        input.value = '';
        return;
    }
    
    // Validar extensión
    const allowedExtensions = ['pdf', 'doc', 'docx'];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExtension)) {
        mensajeDiv.innerHTML = '<span style="color: #dc3545;">⚠️ Tipo de archivo no permitido. Solo PDF, DOC o DOCX.</span>';
        input.value = '';
        return;
    }
    
    // Todo bien
    mensajeDiv.innerHTML = '<span style="color: #28a745;">✅ Archivo válido (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)</span>';
}
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_trabaja_con_nosotros', 'gofast_trabaja_con_nosotros_shortcode');

