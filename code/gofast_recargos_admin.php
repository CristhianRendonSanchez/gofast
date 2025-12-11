<?php
/***************************************************
 * GOFAST – ADMIN RECARGOS (shortcode)
 * [gofast_recargos_admin]
 ***************************************************/
function gofast_recargos_admin_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    $tabla_recargos = 'recargos';
    $tabla_rangos   = 'recargos_rangos';

    $mensaje = '';
    $advertencia_tipo = '';

    // Verificar si el tipo 'por_volumen_peso' está disponible en la base de datos
    $tipos_disponibles = $wpdb->get_col("SHOW COLUMNS FROM {$tabla_recargos} WHERE Field = 'tipo'");
    $tipo_enum = '';
    if (!empty($tipos_disponibles)) {
        $columna_info = $wpdb->get_row("SHOW COLUMNS FROM {$tabla_recargos} WHERE Field = 'tipo'");
        if ($columna_info && !empty($columna_info->Type)) {
            $tipo_enum = $columna_info->Type;
        }
    }
    
    if (!empty($tipo_enum) && strpos($tipo_enum, 'por_volumen_peso') === false) {
        $advertencia_tipo = "<div style='background:#fff3cd;border-left:4px solid #ffc107;padding:16px;border-radius:8px;margin-bottom:20px;'>";
        $advertencia_tipo .= "⚠️ <strong>Advertencia:</strong> El tipo de recargo 'por_volumen_peso' no está disponible en la base de datos. ";
        $advertencia_tipo .= "Por favor ejecuta el script SQL: <code>db/recargos_alter_volumen_peso.sql</code>";
        $advertencia_tipo .= "</div>";
    }

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
                    ⚠️ Solo los administradores pueden gestionar recargos.
                </div>";
    }

    /* ==========================================================
       1. Procesar POST (crear, editar, eliminar)
    ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Verificar nonce
        $nonce_valido = false;
        $nonce_recibido = isset($_POST['gofast_recargos_nonce']) ? $_POST['gofast_recargos_nonce'] : '';
        
        if (!empty($nonce_recibido)) {
            $nonce_valido = wp_verify_nonce($nonce_recibido, 'gofast_recargos_admin');
        }
        
        // Si no hay nonce válido y hay POST, mostrar error
        if (!$nonce_valido && !empty($_POST)) {
            $mensaje = "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:16px;border-radius:8px;'>";
            $mensaje .= "🔒 <strong>Error de seguridad:</strong> El nonce no es válido. ";
            $mensaje .= "Por favor, recarga la página e intenta de nuevo.";
            $mensaje .= "</div>";
        }

        /* ----------------------------------------------
           A) Crear nuevo recargo FIJO
        ---------------------------------------------- */
        if (isset($_POST['gofast_crear_fijo']) && $_POST['gofast_crear_fijo'] == '1') {
            if (!$nonce_valido) {
                $mensaje = "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:16px;border-radius:8px;'>";
                $mensaje .= "🔒 <strong>Error de seguridad:</strong> El nonce no es válido. ";
                $mensaje .= "Por favor, recarga la página e intenta de nuevo.";
                $mensaje .= "</div>";
            } else {
                $nombre     = sanitize_text_field($_POST['nuevo_fijo_nombre'] ?? '');
                $valor_fijo = (int) ($_POST['nuevo_fijo_valor'] ?? 0);
                $activo     = !empty($_POST['nuevo_fijo_activo']) ? 1 : 0;

            if ($nombre !== '' && $valor_fijo > 0) {
                // Generar slug único
                $slug_base = sanitize_title($nombre);
                if (empty($slug_base)) {
                    $slug_base = 'recargo-' . time();
                }
                $slug = $slug_base;
                $contador = 1;
                while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tabla_recargos} WHERE slug = %s", $slug)) > 0) {
                    $slug = $slug_base . '-' . $contador;
                    $contador++;
                }

                $slug = substr($slug, 0, 50);
                if (empty($slug)) {
                    $slug = 'recargo-' . time();
                }

                $ok = $wpdb->insert($tabla_recargos, [
                    'slug'       => $slug,
                    'nombre'     => $nombre,
                    'tipo'       => 'fijo',
                    'valor_fijo' => $valor_fijo,
                    'activo'     => $activo,
                ], [
                    '%s', '%s', '%s', '%d', '%d'
                ]);

                if ($ok !== false) {
                    // Guardar mensaje y redirigir con JavaScript (no se puede usar wp_safe_redirect en shortcode)
                    $redirect_url = add_query_arg([
                        'mensaje' => 'recargo_fijo_creado',
                        'tab' => 'fijos'
                    ], remove_query_arg(['mensaje', 'tab'], $_SERVER['REQUEST_URI']));
                    echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                    return '';
                } else {
                    $error_msg = $wpdb->last_error ?: 'Error desconocido al insertar en la base de datos';
                    $mensaje = "❌ Error al crear recargo fijo: " . esc_html($error_msg);
                }
                } else {
                    $mensaje = "⚠️ Debes indicar nombre y valor fijo mayor a 0.";
                }
            }
        }

        /* ----------------------------------------------
           B) Crear nuevo recargo POR VALOR + rangos
        ---------------------------------------------- */
        if (isset($_POST['gofast_crear_variable']) && $_POST['gofast_crear_variable'] == '1') {
            if (!$nonce_valido) {
                $mensaje = "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:16px;border-radius:8px;'>";
                $mensaje .= "🔒 <strong>Error de seguridad:</strong> El nonce no es válido. ";
                $mensaje .= "Por favor, recarga la página e intenta de nuevo.";
                $mensaje .= "</div>";
            } else {
            $nombre = sanitize_text_field($_POST['nuevo_var_nombre'] ?? '');
            $activo = !empty($_POST['nuevo_var_activo']) ? 1 : 0;

            $mins   = $_POST['nuevo_var_rango_min']   ?? [];
            $maxs   = $_POST['nuevo_var_rango_max']   ?? [];
            $valors = $_POST['nuevo_var_rango_valor'] ?? [];

            if ($nombre === '') {
                $mensaje = "⚠️ Debes indicar un nombre para el recargo variable.";
            } else {
                // Generar slug único
                $slug_base = sanitize_title($nombre);
                if (empty($slug_base)) {
                    $slug_base = 'recargo-' . time();
                }
                $slug = $slug_base;
                $contador = 1;
                while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tabla_recargos} WHERE slug = %s", $slug)) > 0) {
                    $slug = $slug_base . '-' . $contador;
                    $contador++;
                }

                $slug = substr($slug, 0, 50);
                if (empty($slug)) {
                    $slug = 'recargo-' . time();
                }

                $ok = $wpdb->insert($tabla_recargos, [
                    'slug'       => $slug,
                    'nombre'     => $nombre,
                    'tipo'       => 'por_valor',
                    'valor_fijo' => 0,
                    'activo'     => $activo,
                ], [
                    '%s', '%s', '%s', '%d', '%d'
                ]);

                if ($ok === false) {
                    $mensaje = "❌ Error al crear recargo variable: " . esc_html($wpdb->last_error);
                } else {
                    $recargo_id = (int) $wpdb->insert_id;

                    // Insertar rangos iniciales
                    if (!empty($mins) && is_array($mins)) {
                        foreach ($mins as $idx => $min) {
                            $min   = (int) ($mins[$idx]   ?? 0);
                            $max   = (int) ($maxs[$idx]   ?? 0);
                            $rec   = (int) ($valors[$idx] ?? 0);

                            if ($rec <= 0) continue;

                            $wpdb->insert($tabla_rangos, [
                                'recargo_id' => $recargo_id,
                                'monto_min'  => $min,
                                'monto_max'  => $max,
                                'recargo'    => $rec,
                            ], [
                                '%d', '%d', '%d', '%d'
                            ]);
                        }
                    }

                    // Guardar mensaje y redirigir con JavaScript
                    $redirect_url = add_query_arg([
                        'mensaje' => 'recargo_por_valor_creado',
                        'tab' => 'por_valor'
                    ], remove_query_arg(['mensaje', 'tab'], $_SERVER['REQUEST_URI']));
                    echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                    return '';
                }
            }
            }
        }

        /* ----------------------------------------------
           B2) Crear nuevo recargo FIJO por volumen y peso
        ---------------------------------------------- */
        if (isset($_POST['gofast_crear_volumen_peso']) && $_POST['gofast_crear_volumen_peso'] == '1') {
            if (!$nonce_valido) {
                $mensaje = "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:16px;border-radius:8px;'>";
                $mensaje .= "🔒 <strong>Error de seguridad:</strong> El nonce no es válido. ";
                $mensaje .= "Por favor, recarga la página e intenta de nuevo.";
                $mensaje .= "</div>";
            } else {
            $nombre     = sanitize_text_field($_POST['nuevo_vp_nombre'] ?? '');
            $valor_fijo = (int) ($_POST['nuevo_vp_valor'] ?? 0);
            $activo     = !empty($_POST['nuevo_vp_activo']) ? 1 : 0;

            if ($nombre !== '' && $valor_fijo > 0) {
                // Verificar que el tipo esté disponible
                $tipos_disponibles = $wpdb->get_row("SHOW COLUMNS FROM {$tabla_recargos} WHERE Field = 'tipo'");
                $tipo_disponible = false;
                if ($tipos_disponibles && !empty($tipos_disponibles->Type)) {
                    $tipo_disponible = (strpos($tipos_disponibles->Type, 'por_volumen_peso') !== false);
                }
                
                if (!$tipo_disponible) {
                    $mensaje = "❌ <strong>Error:</strong> El tipo 'por_volumen_peso' no está disponible en la base de datos. ";
                    $mensaje .= "Por favor ejecuta el script SQL: <code>db/recargos_alter_volumen_peso.sql</code>";
                } else {
                    // Generar slug único
                    $slug_base = sanitize_title($nombre);
                    if (empty($slug_base)) {
                        $slug_base = 'recargo-' . time();
                    }
                    $slug = $slug_base;
                    $contador = 1;
                    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tabla_recargos} WHERE slug = %s", $slug)) > 0) {
                        $slug = $slug_base . '-' . $contador;
                        $contador++;
                    }

                    $slug = substr($slug, 0, 50);
                    if (empty($slug)) {
                        $slug = 'recargo-' . time();
                    }

                    $ok = $wpdb->insert($tabla_recargos, [
                        'slug'       => $slug,
                        'nombre'     => $nombre,
                        'tipo'       => 'por_volumen_peso',
                        'valor_fijo' => $valor_fijo,
                        'activo'     => $activo,
                    ], [
                        '%s', '%s', '%s', '%d', '%d'
                    ]);

                    if ($ok !== false) {
                        // Guardar mensaje y redirigir con JavaScript
                        $redirect_url = add_query_arg([
                            'mensaje' => 'recargo_seleccionable_creado',
                            'tab' => 'seleccionables'
                        ], remove_query_arg(['mensaje', 'tab'], $_SERVER['REQUEST_URI']));
                        echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                        return '';
                    } else {
                        $error_msg = $wpdb->last_error ?: 'Error desconocido al insertar en la base de datos';
                        $mensaje = "❌ Error al crear recargo: " . esc_html($error_msg);
                    }
                }
                } else {
                    $mensaje = "⚠️ Debes indicar nombre y valor fijo mayor a 0.";
                }
            }
        }

        /* ----------------------------------------------
           C) Editar / eliminar recargos + rangos
        ---------------------------------------------- */
        if (isset($_POST['gofast_guardar_recargos']) && $_POST['gofast_guardar_recargos'] !== '' && !isset($_POST['gofast_crear_fijo']) && !isset($_POST['gofast_crear_variable']) && !isset($_POST['gofast_crear_volumen_peso'])) {
            if (!$nonce_valido) {
                $mensaje = "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:16px;border-radius:8px;'>";
                $mensaje .= "🔒 <strong>Error de seguridad:</strong> El nonce no es válido. ";
                $mensaje .= "Por favor, recarga la página e intenta de nuevo.";
                $mensaje .= "</div>";
            } else {

            // 1) Eliminar RANGOS seleccionados
            if (!empty($_POST['eliminar_rango']) && is_array($_POST['eliminar_rango'])) {
                foreach ($_POST['eliminar_rango'] as $rid => $flag) {
                    if ($flag != '1') continue;
                    $rid = (int) $rid;
                    if ($rid > 0) {
                        $wpdb->delete($tabla_rangos, ['id' => $rid], ['%d']);
                    }
                }
            }

            // 2) Eliminar RECARGOS seleccionados (y sus rangos)
            if (!empty($_POST['eliminar_recargo']) && is_array($_POST['eliminar_recargo'])) {
                foreach ($_POST['eliminar_recargo'] as $rid => $flag) {
                    if ($flag != '1') continue;
                    $rid = (int) $rid;
                    if ($rid > 0) {
                        $wpdb->delete($tabla_rangos, ['recargo_id' => $rid], ['%d']);
                        $wpdb->delete($tabla_recargos, ['id' => $rid], ['%d']);
                    }
                }
            }

            // 3) Actualizar recargos existentes
            if (!empty($_POST['recargos']) && is_array($_POST['recargos'])) {
                foreach ($_POST['recargos'] as $rid => $data) {
                    $rid = (int) $rid;
                    if ($rid <= 0) continue;

                    $activo     = !empty($data['activo']) ? 1 : 0;
                    $nombre     = sanitize_text_field($data['nombre'] ?? '');
                    $tipo       = $data['tipo'] ?? 'fijo';
                    $valor_fijo = (int) ($data['valor_fijo'] ?? 0);

                    $update_data = [
                        'activo' => $activo,
                        'nombre' => $nombre,
                    ];

                    if ($tipo === 'fijo' || $tipo === 'por_volumen_peso') {
                        $update_data['valor_fijo'] = $valor_fijo;
                    }

                    $wpdb->update(
                        $tabla_recargos,
                        $update_data,
                        ['id' => $rid],
                        ['%d', '%s', '%d'],
                        ['%d']
                    );
                }
            }

            // 4) Actualizar rangos existentes
            if (!empty($_POST['rangos']) && is_array($_POST['rangos'])) {
                foreach ($_POST['rangos'] as $recargo_id => $rangos) {
                    foreach ($rangos as $rango_id => $datos) {
                        $rango_id = (int) $rango_id;
                        if ($rango_id <= 0) continue;

                        $min = (int) ($datos['min']   ?? 0);
                        $max = (int) ($datos['max']   ?? 0);
                        $rec = (int) ($datos['valor'] ?? 0);

                        $wpdb->update(
                            $tabla_rangos,
                            [
                                'monto_min' => $min,
                                'monto_max' => $max,
                                'recargo'   => $rec,
                            ],
                            ['id' => $rango_id],
                            ['%d', '%d', '%d'],
                            ['%d']
                        );
                    }
                }
            }

            // 5) Nuevos rangos para recargos existentes
            if (!empty($_POST['nuevos_rangos']) && is_array($_POST['nuevos_rangos'])) {
                foreach ($_POST['nuevos_rangos'] as $recargo_id => $datos) {
                    $recargo_id = (int) $recargo_id;
                    if ($recargo_id <= 0) continue;

                    $mins   = $datos['min']   ?? [];
                    $maxs   = $datos['max']   ?? [];
                    $valors = $datos['valor'] ?? [];

                    if (!is_array($mins)) continue;

                    foreach ($mins as $idx => $min) {
                        $min = (int) ($mins[$idx]   ?? 0);
                        $max = (int) ($maxs[$idx]   ?? 0);
                        $rec = (int) ($valors[$idx] ?? 0);

                        if ($rec <= 0) continue;

                        $wpdb->insert($tabla_rangos, [
                            'recargo_id' => $recargo_id,
                            'monto_min'  => $min,
                            'monto_max'  => $max,
                            'recargo'    => $rec,
                        ], [
                            '%d', '%d', '%d', '%d'
                        ]);
                    }
                }
            }

                if ($mensaje === '') {
                    $mensaje = "<div style='background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:8px;'>";
                    $mensaje .= "✅ Cambios guardados correctamente.";
                    $mensaje .= "</div>";
                }
            }
        }
    }

    /* ==========================================================
       1.5. Mostrar mensajes de éxito desde URL
    ========================================================== */
    if (isset($_GET['mensaje'])) {
        $mensaje_get = sanitize_text_field($_GET['mensaje']);
        if ($mensaje_get === 'recargo_fijo_creado') {
            $mensaje = "<div style='background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:8px;'>";
            $mensaje .= "✅ Recargo fijo creado correctamente.";
            $mensaje .= "</div>";
        } elseif ($mensaje_get === 'recargo_por_valor_creado') {
            $mensaje = "<div style='background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:8px;'>";
            $mensaje .= "✅ Recargo por valor creado correctamente.";
            $mensaje .= "</div>";
        } elseif ($mensaje_get === 'recargo_seleccionable_creado') {
            $mensaje = "<div style='background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:8px;'>";
            $mensaje .= "✅ Recargo seleccionable creado correctamente.";
            $mensaje .= "</div>";
        }
    }

    /* ==========================================================
       2. Cargar recargos + rangos actuales
    ========================================================== */
    $recargos = $wpdb->get_results("
        SELECT * FROM {$tabla_recargos}
        ORDER BY tipo ASC, nombre ASC
    ");

    $recargos_fijos    = [];
    $recargos_var      = [];
    $recargos_vp       = [];
    $rangos_por_recargo = [];

    if ($recargos) {
        foreach ($recargos as $r) {
            if ($r->tipo === 'por_valor') {
                $recargos_var[] = $r;
            } elseif ($r->tipo === 'por_volumen_peso') {
                $recargos_vp[] = $r;
            } else {
                $recargos_fijos[] = $r;
            }
        }

        // Cargar rangos para recargos por valor
        if (!empty($recargos_var)) {
            $ids = implode(',', array_map('intval', wp_list_pluck($recargos_var, 'id')));
            $rangos = $wpdb->get_results("
                SELECT * FROM {$tabla_rangos}
                WHERE recargo_id IN ($ids)
                ORDER BY recargo_id ASC, monto_min ASC
            ");

            foreach ($rangos as $rg) {
                $rangos_por_recargo[$rg->recargo_id][] = $rg;
            }
        }
    }

    /* ==========================================================
       3. HTML
    ========================================================== */
    $tab_activo = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'fijos';
    
    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">⚙️ Administración de recargos</h1>
            <p class="gofast-home-text" style="margin:0;">
                Define recargos fijos y por valor que se aplican automáticamente a las cotizaciones.
            </p>
        </div>
        <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
            ← Volver al Dashboard
        </a>
    </div>

    <?php if ($advertencia_tipo): ?>
        <div class="gofast-box" style="margin-bottom:15px;">
            <?= $advertencia_tipo; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="margin-bottom:15px;">
            <?= $mensaje; ?>
        </div>
    <?php endif; ?>

    <!-- Tabs de navegación -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'fijos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabRecargos('fijos')">
                💰 Recargos Fijos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'por_valor' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabRecargos('por_valor')">
                📊 Recargos por Valor
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'seleccionables' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabRecargos('seleccionables')">
                ✅ Recargos Seleccionables
            </button>
        </div>

        <form method="post">
            <?php wp_nonce_field('gofast_recargos_admin', 'gofast_recargos_nonce'); ?>

            <!-- TAB: RECARGOS FIJOS -->
            <div id="tab-recargos-fijos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'fijos' ? 'block' : 'none' ?>;">
                <h3 style="margin-top:0;">💰 Recargos Fijos</h3>
                <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                    Estos recargos se aplican automáticamente con un valor fijo a todas las cotizaciones.
                </p>

                <!-- Formulario crear recargo fijo -->
                <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">➕ Crear Nuevo Recargo Fijo</h4>
                    <div class="gofast-recargo-nuevo">
                        <div>
                            <label>Nombre del recargo</label>
                            <input type="text" name="nuevo_fijo_nombre" placeholder="Ej: Recargo nocturno" />
                        </div>
                        <div>
                            <label>Valor fijo (COP)</label>
                            <input type="number" name="nuevo_fijo_valor" placeholder="Ej: 2000" min="0" />
                        </div>
                        <div style="display:flex;align-items:flex-end;">
                            <label class="gofast-switch">
                                <input type="checkbox" name="nuevo_fijo_activo" value="1" checked>
                                <span class="gofast-switch-slider"></span>
                                <span class="gofast-switch-label">Activo</span>
                            </label>
                        </div>
                        <div style="display:flex;align-items:flex-end;">
                            <button type="submit" name="gofast_crear_fijo" value="1" class="gofast-small-btn">
                                💾 Crear recargo fijo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Listado de recargos fijos -->
                <div class="gofast-box">
                    <h4 style="margin-top:0;">🔧 Recargos Fijos Existentes</h4>
                    <?php if (empty($recargos_fijos)): ?>
                        <p style="margin:0;">No hay recargos fijos creados.</p>
                    <?php else: ?>
                        <div class="gofast-table-wrap" style="overflow-x: auto;">
                            <table class="gofast-recargos-table">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Nombre</th>
                                        <th>Valor fijo (COP)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recargos_fijos as $r): ?>
                                    <tr class="<?= $r->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>">
                                        <td>
                                            <label class="gofast-switch">
                                                <input type="checkbox"
                                                       name="recargos[<?= esc_attr($r->id); ?>][activo]"
                                                       value="1"
                                                       <?= $r->activo ? 'checked' : ''; ?>>
                                                <span class="gofast-switch-slider"></span>
                                                <span class="gofast-switch-label">Activo</span>
                                            </label>
                                            <br>
                                            <button type="button"
                                                    class="gofast-small-btn gofast-chip-danger gofast-delete-recargo"
                                                    data-recargo-id="<?= esc_attr($r->id); ?>">
                                                🗑 Eliminar
                                            </button>
                                            <input type="hidden"
                                                   name="recargos[<?= esc_attr($r->id); ?>][tipo]"
                                                   value="fijo">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="recargos[<?= esc_attr($r->id); ?>][nombre]"
                                                   value="<?= esc_attr($r->nombre); ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="recargos[<?= esc_attr($r->id); ?>][valor_fijo]"
                                                   value="<?= esc_attr($r->valor_fijo); ?>"
                                                   min="0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB: RECARGOS POR VALOR -->
            <div id="tab-recargos-por-valor" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'por_valor' ? 'block' : 'none' ?>;">
                <h3 style="margin-top:0;">📊 Recargos por Valor</h3>
                <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                    Estos recargos se calculan según el valor de la cotización usando rangos de montos.
                </p>

                <!-- Formulario crear recargo por valor -->
                <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">➕ Crear Nuevo Recargo por Valor</h4>
                    <div class="gofast-recargo-nuevo">
                        <div>
                            <label>Nombre del recargo</label>
                            <input type="text" name="nuevo_var_nombre" placeholder="Ej: Recargo por lluvia" />
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label class="gofast-switch">
                                <input type="checkbox" name="nuevo_var_activo" value="1" checked>
                                <span class="gofast-switch-slider"></span>
                                <span class="gofast-switch-label">Activo</span>
                            </label>
                        </div>

                        <div style="flex:1;min-width:100%;">
                            <label style="display:block;margin-bottom:8px;font-weight:600;">Rangos de valores:</label>
                            <div class="gofast-table-wrap" style="overflow-x: auto;">
                                <table class="gofast-rangos-table">
                                    <thead>
                                        <tr>
                                            <th>Monto mínimo</th>
                                            <th>Monto máximo</th>
                                            <th>Recargo (COP)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gofast-nuevo-var-rangos">
                                        <tr>
                                            <td><input type="number" name="nuevo_var_rango_min[]"   placeholder="0" min="0"></td>
                                            <td><input type="number" name="nuevo_var_rango_max[]"   placeholder="0 = sin límite" min="0"></td>
                                            <td><input type="number" name="nuevo_var_rango_valor[]" placeholder="Ej: 2000" min="0"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button"
                                    class="gofast-small-btn"
                                    id="btn-add-nuevo-var-rango">
                                ➕ Agregar rango
                            </button>
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <button type="submit" name="gofast_crear_variable" value="1" class="gofast-small-btn">
                                💾 Crear recargo por valor
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Listado de recargos por valor -->
                <div class="gofast-box">
                    <h4 style="margin-top:0;">🔧 Recargos por Valor Existentes</h4>
                    <?php if (empty($recargos_var)): ?>
                        <p style="margin:0;">No hay recargos por valor creados.</p>
                    <?php else: ?>
                        <?php foreach ($recargos_var as $r): ?>
                            <div class="gofast-recargo-bloque <?= $r->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>" style="margin-bottom:20px;">

                                <div class="gofast-recargo-header">
                                    <div>
                                        <label class="gofast-switch">
                                            <input type="checkbox"
                                                   name="recargos[<?= esc_attr($r->id); ?>][activo]"
                                                   value="1"
                                                   <?= $r->activo ? 'checked' : ''; ?>>
                                            <span class="gofast-switch-slider"></span>
                                            <span class="gofast-switch-label">Activo</span>
                                        </label>

                                        <span style="margin-left:10px;font-weight:600;">
                                            <?= esc_html($r->nombre); ?>
                                        </span>
                                    </div>

                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:11px;background:#000;color:#fff;padding:3px 8px;border-radius:999px;">
                                            Tipo: por valor
                                        </span>

                                        <button type="button"
                                                class="gofast-small-btn gofast-chip-danger gofast-delete-recargo"
                                                data-recargo-id="<?= esc_attr($r->id); ?>">
                                            🗑 Eliminar
                                        </button>
                                    </div>

                                    <input type="hidden"
                                           name="recargos[<?= esc_attr($r->id); ?>][tipo]"
                                           value="por_valor">
                                    <input type="hidden"
                                           name="recargos[<?= esc_attr($r->id); ?>][nombre]"
                                           value="<?= esc_attr($r->nombre); ?>">
                                </div>

                                <div class="gofast-table-wrap" style="overflow-x: auto;">
                                    <table class="gofast-rangos-table">
                                        <thead>
                                            <tr>
                                                <th>Monto mínimo</th>
                                                <th>Monto máximo</th>
                                                <th>Recargo (COP)</th>
                                                <th style="width:60px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rangos_por_recargo[$r->id] ?? [] as $rg): ?>
                                                <tr>
                                                    <td>
                                                        <input type="number"
                                                               name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][min]"
                                                               value="<?= esc_attr($rg->monto_min); ?>"
                                                               min="0">
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][max]"
                                                               value="<?= esc_attr($rg->monto_max); ?>"
                                                               min="0">
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][valor]"
                                                               value="<?= esc_attr($rg->recargo); ?>"
                                                               min="0">
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <button type="button"
                                                                class="gofast-icon-btn gofast-chip-danger gofast-delete-rango"
                                                                data-rango-id="<?= esc_attr($rg->id); ?>"
                                                                title="Eliminar rango">
                                                            🗑
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Fila para nuevos rangos -->
                                            <tr class="gofast-rango-nuevo" data-recargo-id="<?= esc_attr($r->id); ?>">
                                                <td>
                                                    <input type="number"
                                                           name="nuevos_rangos[<?= esc_attr($r->id); ?>][min][]"
                                                           placeholder="0" min="0">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="nuevos_rangos[<?= esc_attr($r->id); ?>][max][]"
                                                           placeholder="0 = sin límite" min="0">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="nuevos_rangos[<?= esc_attr($r->id); ?>][valor][]"
                                                           placeholder="Ej: 2000" min="0">
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <button type="button"
                                        class="gofast-small-btn"
                                        onclick="gofastAgregarRangoExistente(<?= esc_attr($r->id); ?>)">
                                    ➕ Agregar rango
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB: RECARGOS SELECCIONABLES -->
            <div id="tab-recargos-seleccionables" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'seleccionables' ? 'block' : 'none' ?>;">
                <h3 style="margin-top:0;">✅ Recargos Fijos Seleccionables</h3>
                <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                    Estos recargos son fijos y se pueden seleccionar manualmente durante la cotización. 
                    Útiles para recargos por volumen, peso, o cualquier otro concepto.
                </p>

                <!-- Formulario crear recargo seleccionable -->
                <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">➕ Crear Nuevo Recargo Seleccionable</h4>
                    <div class="gofast-recargo-nuevo">
                        <div>
                            <label>Nombre del recargo</label>
                            <input type="text" name="nuevo_vp_nombre" placeholder="Ej: Recargo por paquete grande" />
                        </div>
                        <div>
                            <label>Valor fijo (COP)</label>
                            <input type="number" name="nuevo_vp_valor" placeholder="Ej: 5000" min="0" />
                        </div>
                        <div style="display:flex;align-items:flex-end;">
                            <label class="gofast-switch">
                                <input type="checkbox" name="nuevo_vp_activo" value="1" checked>
                                <span class="gofast-switch-slider"></span>
                                <span class="gofast-switch-label">Activo</span>
                            </label>
                        </div>
                        <div style="display:flex;align-items:flex-end;">
                            <button type="submit" name="gofast_crear_volumen_peso" value="1" class="gofast-small-btn">
                                💾 Crear recargo seleccionable
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Listado de recargos seleccionables -->
                <div class="gofast-box">
                    <h4 style="margin-top:0;">🔧 Recargos Seleccionables Existentes</h4>
                    <?php if (empty($recargos_vp)): ?>
                        <p style="margin:0;">No hay recargos fijos seleccionables creados.</p>
                    <?php else: ?>
                        <div class="gofast-table-wrap" style="overflow-x: auto;">
                            <table class="gofast-recargos-table">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Nombre</th>
                                        <th>Valor fijo (COP)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recargos_vp as $r): ?>
                                    <tr class="<?= $r->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>">
                                        <td>
                                            <label class="gofast-switch">
                                                <input type="checkbox"
                                                       name="recargos[<?= esc_attr($r->id); ?>][activo]"
                                                       value="1"
                                                       <?= $r->activo ? 'checked' : ''; ?>>
                                                <span class="gofast-switch-slider"></span>
                                                <span class="gofast-switch-label">Activo</span>
                                            </label>
                                            <br>
                                            <button type="button"
                                                    class="gofast-small-btn gofast-chip-danger gofast-delete-recargo"
                                                    data-recargo-id="<?= esc_attr($r->id); ?>">
                                                🗑 Eliminar
                                            </button>
                                            <input type="hidden"
                                                   name="recargos[<?= esc_attr($r->id); ?>][tipo]"
                                                   value="por_volumen_peso">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="recargos[<?= esc_attr($r->id); ?>][nombre]"
                                                   value="<?= esc_attr($r->nombre); ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="recargos[<?= esc_attr($r->id); ?>][valor_fijo]"
                                                   value="<?= esc_attr($r->valor_fijo); ?>"
                                                   min="0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top:18px;text-align:right;">
                <button type="submit"
                        name="gofast_guardar_recargos"
                        value="1"
                        class="gofast-btn-request"
                        style="max-width:260px;margin-left:auto;">
                    💾 Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
(function() {
    const tipoSelectExists = document.getElementById("tipo_negocio");
    const wrapperOtroExists = document.getElementById("tipo_otro_wrapper");
    
    // Si los elementos no existen, crear una función segura desde el inicio
    if (!tipoSelectExists || !wrapperOtroExists) {
        if (typeof window.toggleOtro === 'undefined') {
            window.toggleOtro = function() {
                // Función vacía segura - no hacer nada
                return;
            };
        } else if (typeof window.toggleOtro === 'function') {
            // Si existe y los elementos no están presentes, proteger la función
            const originalToggleOtro = window.toggleOtro;
            window.toggleOtro = function() {
                try {
                    const tipoSelect = document.getElementById("tipo_negocio");
                    const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                    if (tipoSelect && wrapperOtro && tipoSelect.parentNode && wrapperOtro.parentNode) {
                        return originalToggleOtro();
                    }
                } catch(e) {
                    // Silenciar error completamente
                    return;
                }
            };
        }
    }
    
    // También prevenir que setTimeout ejecute toggleOtro si no están los elementos
    const originalSetTimeout = window.setTimeout;
    window.setTimeout = function(func, delay) {
        if (typeof func === 'function') {
            const funcStr = func.toString();
            if (funcStr.includes('toggleOtro')) {
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                if (!tipoSelect || !wrapperOtro) {
                    return null; // No ejecutar si no existen los elementos
                }
            }
        }
        return originalSetTimeout.apply(this, arguments);
    };
})();

// Función global para cambiar tabs de recargos
window.mostrarTabRecargos = function(tab) {
    document.querySelectorAll('.gofast-config-tab-content').forEach(function(content) {
        content.style.display = 'none';
    });
    
    document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
        btn.classList.remove('gofast-config-tab-active');
    });
    
    const tabId = 'tab-recargos-' + (tab === 'fijos' ? 'fijos' : tab === 'por_valor' ? 'por-valor' : 'seleccionables');
    const tabElement = document.getElementById(tabId);
    if (tabElement) {
        tabElement.style.display = 'block';
    }
    
    document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
        const btnText = btn.textContent.trim();
        if ((tab === 'fijos' && btnText.includes('Fijos')) ||
            (tab === 'por_valor' && btnText.includes('Valor')) ||
            (tab === 'seleccionables' && btnText.includes('Seleccionables'))) {
            btn.classList.add('gofast-config-tab-active');
        }
    });
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
};

document.addEventListener('DOMContentLoaded', function(){
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && (tabParam === 'fijos' || tabParam === 'por_valor' || tabParam === 'seleccionables')) {
        window.mostrarTabRecargos(tabParam);
    }

    // Duplicar fila de rangos para "nuevo recargo por valor"
    const nuevoVarBody = document.getElementById('gofast-nuevo-var-rangos');
    const btnAddNuevoVar = document.getElementById('btn-add-nuevo-var-rango');
    if (btnAddNuevoVar && nuevoVarBody) {
        btnAddNuevoVar.addEventListener('click', function(){
            const first = nuevoVarBody.querySelector('tr');
            if (!first) return;
            const clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(i => i.value = '');
            nuevoVarBody.appendChild(clone);
        });
    }

    // Función global para agregar rango en recargos existentes
    window.gofastAgregarRangoExistente = function(recargoId){
        const filas = document.querySelectorAll(
            '.gofast-rango-nuevo[data-recargo-id="'+recargoId+'"]'
        );
        if (!filas.length) return;
        const ref = filas[filas.length - 1];
        const clone = ref.cloneNode(true);
        clone.querySelectorAll('input').forEach(i => i.value = '');
        ref.parentNode.appendChild(clone);
    };

    // Eliminar recargo completo
    document.querySelectorAll('.gofast-delete-recargo').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-recargo-id');
            if (!id) return;

            if (!confirm('¿Seguro que quieres eliminar este recargo y todos sus rangos?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="eliminar_recargo['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_recargo['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_recargos"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_recargos';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });

    // Eliminar rango individual
    document.querySelectorAll('.gofast-delete-rango').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-rango-id');
            if (!id) return;

            if (!confirm('¿Eliminar este rango?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="eliminar_rango['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_rango['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_recargos"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_recargos';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });
});
</script>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_recargos_admin', 'gofast_recargos_admin_shortcode');
