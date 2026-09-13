<?php

/**
 * gofast_admin_solicitudes_trabajo
 */
/***************************************************
 * GOFAST – ADMINISTRACIÓN DE SOLICITUDES DE TRABAJO (SOLO ADMIN)
 * Shortcode: [gofast_admin_solicitudes_trabajo]
 * URL: /admin-solicitudes-trabajo
 * 
 * Funcionalidades:
 * - Ver todas las solicitudes de trabajo
 * - Cambiar estado de las solicitudes
 * - Descargar hojas de vida
 * - Agregar notas internas
 * - Filtrar por estado
 ***************************************************/
function gofast_admin_solicitudes_trabajo_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar usuario admin
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a esta sección.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    if ($rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo los administradores pueden acceder a esta sección.</div>";
    }

    $mensaje = '';
    $mensaje_tipo = '';
    
    // Mostrar mensajes de éxito/error desde redirección
    if (isset($_GET['actualizado']) && $_GET['actualizado'] == '1') {
        $mensaje = 'Solicitud actualizada correctamente.';
        $mensaje_tipo = 'success';
    }
    if (isset($_GET['eliminado']) && $_GET['eliminado'] == '1') {
        $mensaje = 'Solicitud eliminada correctamente.';
        $mensaje_tipo = 'success';
    }

    /*********************************************
     * VER DETALLES DE SOLICITUD (NUEVA PÁGINA)
     *********************************************/
    if (isset($_GET['ver']) && !empty($_GET['ver'])) {
        $solicitud_id = (int) $_GET['ver'];
        
        $solicitud = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM solicitudes_trabajo WHERE id = %d",
            $solicitud_id
        ));
        
        if (!$solicitud) {
            return "<div class='gofast-box'>⚠️ Solicitud no encontrada.</div>";
        }
        
        // Mensaje de éxito si viene de actualización
        $mensaje_detalle = '';
        $mensaje_tipo_detalle = '';
        if (isset($_GET['actualizado']) && $_GET['actualizado'] == '1') {
            $mensaje_detalle = 'Solicitud actualizada correctamente.';
            $mensaje_tipo_detalle = 'success';
        }
        
        ob_start();
        ?>
        <div class="gofast-home">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="margin-bottom:8px;">📋 Detalles de la Solicitud #<?= esc_html($solicitud->id) ?></h1>
                    <p class="gofast-home-text" style="margin:0;">
                        Información completa de la solicitud de trabajo.
                    </p>
                </div>
                <a href="<?php echo esc_url( remove_query_arg(['ver', 'actualizado']) ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
                    ← Volver a Solicitudes
                </a>
            </div>

            <!-- Mensaje de resultado -->
            <?php if ($mensaje_detalle): ?>
                <div class="gofast-box" style="background: <?= $mensaje_tipo_detalle === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $mensaje_tipo_detalle === 'success' ? '#28a745' : '#dc3545' ?>; color: <?= $mensaje_tipo_detalle === 'success' ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
                    <?= esc_html($mensaje_detalle) ?>
                </div>
            <?php endif; ?>

            <!-- Información del candidato -->
            <div class="gofast-box" style="margin-bottom: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 20px;">👤 Información del Candidato</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Nombre completo:</div>
                        <div style="font-size: 18px; font-weight: 700; color: #000;"><?= esc_html($solicitud->nombre) ?></div>
                    </div>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 4px;">WhatsApp:</div>
                        <div style="font-size: 18px; font-weight: 700; color: #000;">
                            <a href="https://wa.me/<?= esc_attr(preg_replace('/[^0-9]/', '', $solicitud->whatsapp)) ?>" 
                               target="_blank" 
                               style="color: #25D366; text-decoration: none;">
                                📱 <?= esc_html($solicitud->whatsapp) ?>
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($solicitud->email): ?>
                    <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Email:</div>
                        <div style="font-size: 18px; font-weight: 700; color: #000;">
                            <a href="mailto:<?= esc_attr($solicitud->email) ?>" style="color: #007bff; text-decoration: none;">
                                ✉️ <?= esc_html($solicitud->email) ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Estado:</div>
                        <div>
                            <span class="gofast-badge-estado 
                                <?php
                                switch($solicitud->estado) {
                                    case 'pendiente': echo 'gofast-badge-estado-pendiente'; break;
                                    case 'revisado': echo 'gofast-badge-estado-asignado'; break;
                                    case 'contactado': echo 'gofast-badge-estado-en_ruta'; break;
                                    case 'rechazado': echo 'gofast-badge-estado-cancelado'; break;
                                }
                                ?>">
                                <?php
                                switch($solicitud->estado) {
                                    case 'pendiente': echo '⏳ Pendiente'; break;
                                    case 'revisado': echo '👀 Revisado'; break;
                                    case 'contactado': echo '📞 Contactado'; break;
                                    case 'rechazado': echo '❌ Rechazado'; break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Respuestas a las preguntas -->
            <div class="gofast-box" style="margin-bottom: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 20px;">❓ Respuestas del Candidato</h3>
                
                <div style="display: grid; gap: 16px;">
                    <div style="padding: 16px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: 6px;">
                        <div style="font-size: 14px; font-weight: 700; color: #000; margin-bottom: 8px;">
                            1. ¿Tiene experiencia en reparto a domicilio y conocimiento en direcciones? 🦉
                        </div>
                        <div style="font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                            <?= esc_html($solicitud->pregunta1) ?>
                        </div>
                    </div>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: 6px;">
                        <div style="font-size: 14px; font-weight: 700; color: #000; margin-bottom: 8px;">
                            2. ¿Cuál es su disponibilidad de tiempo? 🕐
                        </div>
                        <div style="font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                            <?= esc_html($solicitud->pregunta2) ?>
                        </div>
                    </div>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: 6px;">
                        <div style="font-size: 14px; font-weight: 700; color: #000; margin-bottom: 8px;">
                            3. ¿Tiene vehículo propio con documentación al día? ✔
                        </div>
                        <div style="font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                            <?= esc_html($solicitud->pregunta3) ?>
                        </div>
                    </div>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: 6px;">
                        <div style="font-size: 14px; font-weight: 700; color: #000; margin-bottom: 8px;">
                            4. ¿Tipo de motocicleta? 🛵
                        </div>
                        <div style="font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                            <?= esc_html($solicitud->pregunta4) ?>
                        </div>
                    </div>
                    
                    <div style="padding: 16px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: 6px;">
                        <div style="font-size: 14px; font-weight: 700; color: #000; margin-bottom: 8px;">
                            5. ¿Ciudad de residencia? 🏠
                        </div>
                        <div style="font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                            <?= esc_html($solicitud->pregunta5) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hoja de vida -->
            <?php if ($solicitud->archivo_cv): ?>
            <div class="gofast-box" style="margin-bottom: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 16px;">📄 Hoja de Vida</h3>
                <div style="padding: 16px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Archivo:</div>
                            <div style="font-size: 15px; font-weight: 600; color: #000;">
                                <?= esc_html($solicitud->nombre_archivo) ?>
                            </div>
                        </div>
                        <a href="<?= esc_url($solicitud->archivo_cv) ?>" 
                           target="_blank" 
                           download
                           class="gofast-btn-mini" 
                           style="background: #28a745; color: #fff; text-decoration: none; padding: 12px 20px;">
                            📥 Descargar CV
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Información adicional -->
            <div class="gofast-box" style="margin-bottom: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 16px;">📅 Información Adicional</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Fecha de solicitud:</div>
                        <div style="font-size: 14px; font-weight: 600; color: #000;">
                            <?= gofast_date_format($solicitud->created_at, 'd/m/Y H:i') ?>
                        </div>
                    </div>
                    <?php if ($solicitud->updated_at): ?>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Última actualización:</div>
                        <div style="font-size: 14px; font-weight: 600; color: #000;">
                            <?= gofast_date_format($solicitud->updated_at, 'd/m/Y H:i') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario de gestión -->
            <div class="gofast-box" style="margin-bottom: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 16px;">⚙️ Gestionar Solicitud</h3>
                
                <form method="post">
                    <?php wp_nonce_field('gofast_actualizar_solicitud', 'gofast_actualizar_solicitud_nonce'); ?>
                    <input type="hidden" name="gofast_actualizar_solicitud" value="1">
                    <input type="hidden" name="solicitud_id" value="<?= esc_attr($solicitud->id) ?>">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #000;">Estado:</label>
                            <select name="estado" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px;">
                                <option value="pendiente" <?= $solicitud->estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                <option value="revisado" <?= $solicitud->estado === 'revisado' ? 'selected' : '' ?>>👀 Revisado</option>
                                <option value="contactado" <?= $solicitud->estado === 'contactado' ? 'selected' : '' ?>>📞 Contactado</option>
                                <option value="rechazado" <?= $solicitud->estado === 'rechazado' ? 'selected' : '' ?>>❌ Rechazado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #000;">Notas internas:</label>
                        <textarea name="notas" 
                                  rows="4"
                                  placeholder="Notas sobre esta solicitud..."
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical; font-family: inherit; box-sizing: border-box;"><?= esc_textarea($solicitud->notas) ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" class="gofast-btn-mini" style="background: var(--gofast-yellow); color: #000;">
                            💾 Guardar Cambios
                        </button>
                        <a href="https://wa.me/<?= esc_attr(preg_replace('/[^0-9]/', '', $solicitud->whatsapp)) ?>?text=<?= rawurlencode('Hola ' . $solicitud->nombre . ', hemos revisado tu solicitud de trabajo en GO FAST mensajería express. ¿Podrías contactarnos para continuar con el proceso?') ?>" 
                           target="_blank" 
                           class="gofast-btn-request" 
                           style="background: #25D366; color: #fff; text-decoration: none; padding: 14px 24px; font-size: 16px; display: inline-block;">
                            📱 Contactar por WhatsApp
                        </a>
                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar esta solicitud?');">
                            <?php wp_nonce_field('gofast_eliminar_solicitud', 'gofast_eliminar_solicitud_nonce'); ?>
                            <input type="hidden" name="gofast_eliminar_solicitud" value="1">
                            <input type="hidden" name="solicitud_id" value="<?= esc_attr($solicitud->id) ?>">
                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                🗑️ Eliminar Solicitud
                            </button>
                        </form>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // 1. ACTUALIZAR ESTADO Y NOTAS
    if (isset($_POST['gofast_actualizar_solicitud']) && wp_verify_nonce($_POST['gofast_actualizar_solicitud_nonce'], 'gofast_actualizar_solicitud')) {
        $solicitud_id = (int) $_POST['solicitud_id'];
        $estado = sanitize_text_field($_POST['estado'] ?? 'pendiente');
        $notas = sanitize_textarea_field($_POST['notas'] ?? '');

        $actualizado = $wpdb->update(
            'solicitudes_trabajo',
            [
                'estado' => $estado,
                'notas' => $notas,
                'updated_at' => gofast_current_time('mysql')
            ],
            ['id' => $solicitud_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($actualizado !== false) {
            // Redirigir a la página de detalles si estamos viendo una solicitud
            if (isset($_GET['ver'])) {
                wp_safe_redirect(add_query_arg(['ver' => $solicitud_id, 'actualizado' => '1']));
            } else {
                wp_safe_redirect(add_query_arg(['actualizado' => '1']));
            }
            exit;
        } else {
            $mensaje = 'Error al actualizar la solicitud.';
            $mensaje_tipo = 'error';
        }
    }

    // 2. ELIMINAR SOLICITUD
    if (isset($_POST['gofast_eliminar_solicitud']) && wp_verify_nonce($_POST['gofast_eliminar_solicitud_nonce'], 'gofast_eliminar_solicitud')) {
        $solicitud_id = (int) $_POST['solicitud_id'];

        // Obtener información del archivo antes de eliminar
        $solicitud = $wpdb->get_row($wpdb->prepare(
            "SELECT archivo_cv FROM solicitudes_trabajo WHERE id = %d",
            $solicitud_id
        ));

        // Eliminar archivo físico si existe
        if ($solicitud && $solicitud->archivo_cv) {
            $upload_dir = wp_upload_dir();
            $ruta_archivo = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $solicitud->archivo_cv);
            if (file_exists($ruta_archivo)) {
                @unlink($ruta_archivo);
            }
        }

        $eliminado = $wpdb->delete('solicitudes_trabajo', ['id' => $solicitud_id], ['%d']);

        if ($eliminado) {
            // Si estamos en la página de detalles, redirigir a la lista
            if (isset($_GET['ver'])) {
                wp_safe_redirect(remove_query_arg('ver'));
            } else {
                wp_safe_redirect(add_query_arg(['eliminado' => '1']));
            }
            exit;
        } else {
            $mensaje = 'Error al eliminar la solicitud.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * OBTENER DATOS
     *********************************************/

    // Filtro por estado
    $filtro_estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';

    $where = '';
    if (!empty($filtro_estado) && in_array($filtro_estado, ['pendiente', 'revisado', 'contactado', 'rechazado'])) {
        $where = $wpdb->prepare(" WHERE estado = %s", $filtro_estado);
    }

    // Obtener todas las solicitudes
    $solicitudes = $wpdb->get_results(
        "SELECT * FROM solicitudes_trabajo" . $where . " ORDER BY created_at DESC"
    );

    // Estadísticas
    $total_solicitudes = count($solicitudes);
    $pendientes = $wpdb->get_var("SELECT COUNT(*) FROM solicitudes_trabajo WHERE estado = 'pendiente'");
    $revisados = $wpdb->get_var("SELECT COUNT(*) FROM solicitudes_trabajo WHERE estado = 'revisado'");
    $contactados = $wpdb->get_var("SELECT COUNT(*) FROM solicitudes_trabajo WHERE estado = 'contactado'");
    $rechazados = $wpdb->get_var("SELECT COUNT(*) FROM solicitudes_trabajo WHERE estado = 'rechazado'");

    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">📋 Solicitudes de Trabajo</h1>
            <p class="gofast-home-text" style="margin:0;">
                Gestiona las solicitudes de trabajo de mensajeros.
            </p>
        </div>
        <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
            ← Volver al Dashboard
        </a>
    </div>

    <!-- Mensaje de resultado -->
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="background: <?= $mensaje_tipo === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $mensaje_tipo === 'success' ? '#28a745' : '#dc3545' ?>; color: <?= $mensaje_tipo === 'success' ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>📊 Estadísticas</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 15px;">
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #333;"><?= $total_solicitudes ?></div>
                <div style="font-size: 13px; color: #666;">Total Solicitudes</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #856404;"><?= $pendientes ?></div>
                <div style="font-size: 13px; color: #666;">Pendientes</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #d1ecf1; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #0c5460;"><?= $revisados ?></div>
                <div style="font-size: 13px; color: #666;">Revisados</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #cce5ff; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #004085;"><?= $contactados ?></div>
                <div style="font-size: 13px; color: #666;">Contactados</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #721c24;"><?= $rechazados ?></div>
                <div style="font-size: 13px; color: #666;">Rechazados</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtrar por estado:</label>
                <select name="estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="revisado" <?= $filtro_estado === 'revisado' ? 'selected' : '' ?>>Revisado</option>
                    <option value="contactado" <?= $filtro_estado === 'contactado' ? 'selected' : '' ?>>Contactado</option>
                    <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                </select>
            </div>
            <button type="submit" class="gofast-btn-mini" style="background: var(--gofast-yellow); color: #000;">
                🔍 Filtrar
            </button>
            <?php if ($filtro_estado): ?>
                <a href="<?php echo esc_url( remove_query_arg('estado') ); ?>" class="gofast-btn-mini gofast-btn-outline">
                    Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Listado de solicitudes -->
    <div class="gofast-box">
        <h3>📋 Todas las Solicitudes</h3>
        
        <?php if (empty($solicitudes)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No hay solicitudes registradas.
            </p>
        <?php else: ?>
            <div class="gofast-table-wrap" style="overflow-x: auto;">
                <table class="gofast-table" style="min-width: 1000px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>CV</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td>#<?= esc_html($s->id) ?></td>
                                <td><strong><?= esc_html($s->nombre) ?></strong></td>
                                <td>
                                    <a href="https://wa.me/<?= esc_attr(preg_replace('/[^0-9]/', '', $s->whatsapp)) ?>" 
                                       target="_blank" 
                                       style="color: #25D366; text-decoration: none;">
                                        📱 <?= esc_html($s->whatsapp) ?>
                                    </a>
                                </td>
                                <td><?= $s->email ? esc_html($s->email) : '—' ?></td>
                                <td>
                                    <span class="gofast-badge-estado 
                                        <?php
                                        switch($s->estado) {
                                            case 'pendiente': echo 'gofast-badge-estado-pendiente'; break;
                                            case 'revisado': echo 'gofast-badge-estado-asignado'; break;
                                            case 'contactado': echo 'gofast-badge-estado-en_ruta'; break;
                                            case 'rechazado': echo 'gofast-badge-estado-cancelado'; break;
                                        }
                                        ?>">
                                        <?php
                                        switch($s->estado) {
                                            case 'pendiente': echo '⏳ Pendiente'; break;
                                            case 'revisado': echo '👀 Revisado'; break;
                                            case 'contactado': echo '📞 Contactado'; break;
                                            case 'rechazado': echo '❌ Rechazado'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s->archivo_cv): ?>
                                        <a href="<?= esc_url($s->archivo_cv) ?>" 
                                           target="_blank" 
                                           download
                                           class="gofast-btn-mini" 
                                           style="background: #28a745; color: #fff; text-decoration: none;">
                                            📥 Descargar CV
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">Sin CV</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= gofast_date_format($s->created_at, 'd/m/Y H:i') ?></td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?= esc_url( add_query_arg('ver', $s->id) ) ?>" 
                                           class="gofast-btn-mini" 
                                           style="background: var(--gofast-yellow); color: #000; text-decoration: none; margin-right: 4px; display: inline-block; padding: 8px 14px;">
                                            👁️ Ver Detalles
                                        </a>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar esta solicitud?');">
                                            <?php wp_nonce_field('gofast_eliminar_solicitud', 'gofast_eliminar_solicitud_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_solicitud" value="1">
                                            <input type="hidden" name="solicitud_id" value="<?= esc_attr($s->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


    <?php
    return ob_get_clean();
}
add_shortcode('gofast_admin_solicitudes_trabajo', 'gofast_admin_solicitudes_trabajo_shortcode');
