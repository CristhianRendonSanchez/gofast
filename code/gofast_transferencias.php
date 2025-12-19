<?php
/***************************************************
 * GOFAST – MÓDULO DE TRANSFERENCIAS
 * Shortcode: [gofast_transferencias]
 * URL: /transferencias
 * 
 * Funcionalidades:
 * - Mensajero: Crear transferencias (estado: pendiente) y ver sus transferencias
 * - Admin: Crear transferencias (estado: aprobada), ver todas, aprobar/rechazar
 ***************************************************/
function gofast_transferencias_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar sesión
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a las transferencias.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    // Solo mensajero y admin pueden acceder
    if ($rol !== 'mensajero' && $rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo mensajeros y administradores pueden acceder a las transferencias.</div>";
    }

    // Obtener datos del usuario
    $usuario = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, nombre, rol, activo FROM usuarios_gofast WHERE id = %d AND activo = 1",
            $user_id
        )
    );

    if (!$usuario) {
        return "<div class='gofast-box'>Usuario no encontrado.</div>";
    }

    $mensaje = '';
    $mensaje_tipo = ''; // success, error

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // 1. CREAR TRANSFERENCIA (Mensajero o Admin)
    if (isset($_POST['gofast_crear_transferencia']) && wp_verify_nonce($_POST['gofast_transferencia_nonce'], 'gofast_crear_transferencia')) {
        $valor = floatval($_POST['valor'] ?? 0);
        $mensajero_id = isset($_POST['mensajero_id']) ? (int) $_POST['mensajero_id'] : $user_id;

        if ($valor <= 0) {
            $mensaje = 'El valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            // Si es admin creando para otro mensajero, validar que existe
            if ($rol === 'admin' && $mensajero_id !== $user_id) {
                $mensajero_valido = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero' AND activo = 1",
                        $mensajero_id
                    )
                );
                if (!$mensajero_valido) {
                    $mensaje = 'El mensajero seleccionado no existe o está inactivo.';
                    $mensaje_tipo = 'error';
                } else {
                    // Admin crea → estado: aprobada
                    $estado = 'aprobada';
                }
            } else {
                // Mensajero crea para sí mismo → estado: pendiente
                $mensajero_id = $user_id;
                $estado = 'pendiente';
            }

            if (empty($mensaje)) {
                $insertado = $wpdb->insert(
                    'transferencias_gofast',
                    [
                        'mensajero_id' => $mensajero_id,
                        'valor' => $valor,
                        'estado' => $estado,
                        'tipo' => 'normal',
                        'creado_por' => $user_id,
                        'observaciones' => null,
                        'fecha_creacion' => gofast_date_mysql()
                    ],
                    ['%d', '%f', '%s', '%s', '%d', '%s', '%s']
                );

                if ($insertado) {
                    $mensaje = $rol === 'admin' 
                        ? 'Transferencia creada y aprobada automáticamente.' 
                        : 'Transferencia solicitada correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al crear la transferencia.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 2. APROBAR TRANSFERENCIA (Solo Admin)
    if (isset($_POST['gofast_aprobar_transferencia']) && wp_verify_nonce($_POST['gofast_aprobar_nonce'], 'gofast_aprobar_transferencia') && $rol === 'admin') {
        $transferencia_id = (int) $_POST['transferencia_id'];

        $actualizado = $wpdb->update(
            'transferencias_gofast',
            [
                'estado' => 'aprobada',
                'fecha_actualizacion' => gofast_date_mysql()
            ],
            ['id' => $transferencia_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($actualizado) {
            $mensaje = 'Transferencia aprobada correctamente.';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al aprobar la transferencia.';
            $mensaje_tipo = 'error';
        }
    }

    // 3. RECHAZAR TRANSFERENCIA (Solo Admin)
    if (isset($_POST['gofast_rechazar_transferencia']) && wp_verify_nonce($_POST['gofast_rechazar_nonce'], 'gofast_rechazar_transferencia') && $rol === 'admin') {
        $transferencia_id = (int) $_POST['transferencia_id'];
        $observaciones = sanitize_text_field($_POST['observaciones'] ?? '');

        $actualizado = $wpdb->update(
            'transferencias_gofast',
            [
                'estado' => 'rechazada',
                'observaciones' => $observaciones,
                'fecha_actualizacion' => gofast_date_mysql()
            ],
            ['id' => $transferencia_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($actualizado) {
            $mensaje = 'Transferencia rechazada correctamente.';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al rechazar la transferencia.';
            $mensaje_tipo = 'error';
        }
    }

    // 4. EDITAR TRANSFERENCIA (Solo Admin) - Puede editar fecha y valor sin importar el estado
    if (isset($_POST['gofast_editar_transferencia']) && wp_verify_nonce($_POST['gofast_editar_transferencia_nonce'], 'gofast_editar_transferencia') && $rol === 'admin') {
        $transferencia_id = (int) $_POST['transferencia_id'];
        $nueva_fecha = sanitize_text_field($_POST['fecha_creacion'] ?? '');
        $nuevo_valor = floatval($_POST['valor'] ?? 0);

        if ($nuevo_valor <= 0) {
            $mensaje = 'El valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } elseif (empty($nueva_fecha)) {
            $mensaje = 'La fecha es obligatoria.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar que la transferencia existe y obtener la hora original
            $transferencia = $wpdb->get_row($wpdb->prepare(
                "SELECT id, fecha_creacion FROM transferencias_gofast WHERE id = %d",
                $transferencia_id
            ));

            if (!$transferencia) {
                $mensaje = 'Transferencia no encontrada.';
                $mensaje_tipo = 'error';
            } else {
                // Mantener la hora original si existe, sino usar la hora actual
                $hora_original = '';
                if ($transferencia->fecha_creacion) {
                    $fecha_completa = DateTime::createFromFormat('Y-m-d H:i:s', $transferencia->fecha_creacion);
                    if ($fecha_completa) {
                        $hora_original = $fecha_completa->format('H:i:s');
                    } else {
                        $hora_original = date('H:i:s');
                    }
                } else {
                    $hora_original = date('H:i:s');
                }
                
                $fecha_completa_nueva = $nueva_fecha . ' ' . $hora_original;
                
                $actualizado = $wpdb->update(
                    'transferencias_gofast',
                    [
                        'valor' => $nuevo_valor,
                        'fecha_creacion' => $fecha_completa_nueva,
                        'fecha_actualizacion' => gofast_date_mysql()
                    ],
                    ['id' => $transferencia_id],
                    ['%f', '%s', '%s'],
                    ['%d']
                );

                if ($actualizado !== false) {
                    $mensaje = 'Transferencia actualizada correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar la transferencia.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 5. CAMBIAR TIPO DE TRANSFERENCIA (Solo Admin)
    if (isset($_POST['gofast_cambiar_tipo_transferencia']) && wp_verify_nonce($_POST['gofast_cambiar_tipo_nonce'], 'gofast_cambiar_tipo_transferencia') && $rol === 'admin') {
        $transferencia_id = (int) $_POST['transferencia_id'];
        $nuevo_tipo = sanitize_text_field($_POST['nuevo_tipo'] ?? '');

        if (!in_array($nuevo_tipo, ['normal', 'pago'])) {
            $mensaje = 'Tipo de transferencia inválido.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'transferencias_gofast',
                [
                    'tipo' => $nuevo_tipo,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $transferencia_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($actualizado) {
                $mensaje = 'Tipo de transferencia actualizado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el tipo de transferencia.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 6. ELIMINAR TRANSFERENCIA (Solo Admin)
    if (isset($_POST['gofast_eliminar_transferencia']) && wp_verify_nonce($_POST['gofast_eliminar_nonce'], 'gofast_eliminar_transferencia') && $rol === 'admin') {
        $transferencia_id = (int) $_POST['transferencia_id'];

        $eliminado = $wpdb->delete(
            'transferencias_gofast',
            ['id' => $transferencia_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Transferencia eliminada correctamente.';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al eliminar la transferencia.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * FILTROS
     *********************************************/

    // Filtros desde URL o POST
    $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
    $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';
    $valor_min = isset($_GET['valor_min']) ? floatval($_GET['valor_min']) : 0;
    $valor_max = isset($_GET['valor_max']) ? floatval($_GET['valor_max']) : 0;
    $estado_filtro = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $tipo_filtro = isset($_GET['tipo']) ? sanitize_text_field($_GET['tipo']) : '';
    $mensajero_filtro = isset($_GET['mensajero_id']) ? (int) $_GET['mensajero_id'] : 0;

    // Valores por defecto según rol
    if (empty($_GET['fecha_desde']) && empty($_GET['fecha_hasta']) && empty($_GET['estado'])) {
        if ($rol === 'admin') {
            // Admin: mostrar pendientes por defecto
            $estado_filtro = 'pendiente';
        } else {
            // Mensajero: mostrar día actual por defecto (zona horaria Colombia)
            $fecha_desde = gofast_date_today();
            $fecha_hasta = gofast_date_today();
        }
    }

    /*********************************************
     * OBTENER DATOS PARA MOSTRAR
     *********************************************/

    // Lista de mensajeros (solo para admin al crear)
    $mensajeros = [];
    if ($rol === 'admin') {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre, telefono 
             FROM usuarios_gofast 
             WHERE rol = 'mensajero' AND activo = 1 
             ORDER BY nombre ASC"
        );
    }

    // Construir consulta con filtros
    $where_parts = [];
    $where_values = [];

    // Filtro por rol
    if ($rol === 'mensajero') {
        $where_parts[] = "t.mensajero_id = %d";
        $where_values[] = $user_id;
    }
    
    // Filtro por mensajero (solo admin)
    if ($rol === 'admin' && $mensajero_filtro > 0) {
        $where_parts[] = "t.mensajero_id = %d";
        $where_values[] = $mensajero_filtro;
    }

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_parts[] = "DATE(t.fecha_creacion) >= %s";
        $where_values[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_parts[] = "DATE(t.fecha_creacion) <= %s";
        $where_values[] = $fecha_hasta;
    }

    // Filtro por valor
    if ($valor_min > 0) {
        $where_parts[] = "t.valor >= %f";
        $where_values[] = $valor_min;
    }
    if ($valor_max > 0) {
        $where_parts[] = "t.valor <= %f";
        $where_values[] = $valor_max;
    }

    // Filtro por estado
    if (!empty($estado_filtro) && in_array($estado_filtro, ['pendiente', 'aprobada', 'rechazada'])) {
        $where_parts[] = "t.estado = %s";
        $where_values[] = $estado_filtro;
    }

    // Filtro por tipo
    if (!empty($tipo_filtro) && in_array($tipo_filtro, ['normal', 'pago'])) {
        $where_parts[] = "t.tipo = %s";
        $where_values[] = $tipo_filtro;
    }

    // Construir consulta SQL
    $sql = "SELECT t.*, 
                   m.nombre as mensajero_nombre, 
                   m.telefono as mensajero_telefono,
                   c.nombre as creador_nombre
            FROM transferencias_gofast t
            LEFT JOIN usuarios_gofast m ON t.mensajero_id = m.id
            LEFT JOIN usuarios_gofast c ON t.creado_por = c.id";
    
    if (!empty($where_parts)) {
        $sql .= " WHERE " . implode(' AND ', $where_parts);
    }
    
    $sql .= " ORDER BY t.fecha_creacion DESC";

    // Ejecutar consulta con prepared statement
    if (!empty($where_values)) {
        $transferencias = $wpdb->get_results($wpdb->prepare($sql, $where_values));
    } else {
        $transferencias = $wpdb->get_results($sql);
    }

    // Contar total sin filtros (para estadísticas)
    $total_sin_filtros = 0;
    if ($rol === 'admin') {
        $total_sin_filtros = (int) $wpdb->get_var("SELECT COUNT(*) FROM transferencias_gofast");
    } else {
        $total_sin_filtros = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM transferencias_gofast WHERE mensajero_id = %d",
            $user_id
        ));
    }

    // Estadísticas (solo para admin)
    $stats = null;
    if ($rol === 'admin') {
        $stats = [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM transferencias_gofast"),
            'pendientes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM transferencias_gofast WHERE estado = 'pendiente'"),
            'aprobadas' => (int) $wpdb->get_var("SELECT COUNT(*) FROM transferencias_gofast WHERE estado = 'aprobada'"),
            'rechazadas' => (int) $wpdb->get_var("SELECT COUNT(*) FROM transferencias_gofast WHERE estado = 'rechazada'"),
            'total_valor_pendiente' => (float) $wpdb->get_var("SELECT SUM(valor) FROM transferencias_gofast WHERE estado = 'pendiente'"),
            'total_valor_aprobado' => (float) $wpdb->get_var("SELECT SUM(valor) FROM transferencias_gofast WHERE estado = 'aprobada'")
        ];
    }

    ob_start();
    ?>

<div class="gofast-home">
    <?php if ($rol === 'admin'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="margin-bottom:8px;">💰 Transferencias</h1>
                <p class="gofast-home-text" style="margin:0;">
                    Gestiona las transferencias de los mensajeros.
                </p>
            </div>
            <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
                ← Volver al Dashboard
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Mensaje de resultado -->
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="background: <?= $mensaje_tipo === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $mensaje_tipo === 'success' ? '#28a745' : '#dc3545' ?>; color: <?= $mensaje_tipo === 'success' ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Estadísticas (Solo Admin) -->
    <?php if ($rol === 'admin' && $stats): ?>
        <div class="gofast-box" style="margin-bottom: 20px;">
            <h3>📊 Estadísticas de Transferencias</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 15px;">
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #333;"><?= $stats['total'] ?></div>
                    <div style="font-size: 13px; color: #666;">Total</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #856404;"><?= $stats['pendientes'] ?></div>
                    <div style="font-size: 13px; color: #666;">Pendientes</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #155724;"><?= $stats['aprobadas'] ?></div>
                    <div style="font-size: 13px; color: #666;">Aprobadas</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #721c24;"><?= $stats['rechazadas'] ?></div>
                    <div style="font-size: 13px; color: #666;">Rechazadas</div>
                </div>
            </div>
            <?php if ($stats['total_valor_pendiente'] > 0 || $stats['total_valor_aprobado'] > 0): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <strong>Pendiente:</strong> $<?= number_format($stats['total_valor_pendiente'], 0, ',', '.') ?>
                        </div>
                        <div>
                            <strong>Aprobado:</strong> $<?= number_format($stats['total_valor_aprobado'], 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Formulario para crear transferencia -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3><?= $rol === 'admin' ? '💰 Crear Transferencia' : '💰 Solicitar Transferencia' ?></h3>
        
        <form method="post">
            <?php wp_nonce_field('gofast_crear_transferencia', 'gofast_transferencia_nonce'); ?>
            
            <?php if ($rol === 'admin'): ?>
                <label><strong>Mensajero</strong> <span style="color: #dc3545;">*</span></label>
                <select name="mensajero_id" class="gofast-select" id="mensajero-select-transferencia" required>
                    <option value="">— Selecciona mensajero —</option>
                    <?php foreach ($mensajeros as $m): ?>
                        <option value="<?= esc_attr($m->id) ?>">
                            <?= esc_html($m->nombre) ?> — <?= esc_html($m->telefono) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <div style="background: #e7f3ff; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                    <strong>Mensajero:</strong> <?= esc_html($usuario->nombre) ?>
                </div>
            <?php endif; ?>

            <label><strong>Valor de la transferencia</strong></label>
            <input type="number" 
                   name="valor" 
                   step="0.01" 
                   min="0.01" 
                   required 
                   placeholder="Ej: 50000"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">

            <div class="gofast-btn-group" style="margin-top: 20px;">
                <button type="submit" name="gofast_crear_transferencia" class="gofast-submit">
                    <?= $rol === 'admin' ? '✅ Crear y Aprobar' : '📤 Solicitar Transferencia' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>🔍 Filtros</h3>
        <form method="get" action="" class="gofast-filtros-form">
            <!-- Mantener otros parámetros GET si existen -->
            <?php foreach ($_GET as $key => $value): ?>
                <?php if (!in_array($key, ['fecha_desde', 'fecha_hasta', 'valor_min', 'valor_max', 'estado', 'tipo', 'mensajero_id'])): ?>
                    <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <?php if ($rol === 'admin'): ?>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Mensajero</label>
                    <select name="mensajero_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Todos</option>
                        <?php foreach ($mensajeros as $m): ?>
                            <option value="<?= esc_attr($m->id) ?>" <?= $mensajero_filtro == $m->id ? 'selected' : '' ?>>
                                <?= esc_html($m->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha desde</label>
                    <input type="date" 
                           name="fecha_desde" 
                           value="<?= esc_attr($fecha_desde) ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha hasta</label>
                    <input type="date" 
                           name="fecha_hasta" 
                           value="<?= esc_attr($fecha_hasta) ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor mínimo</label>
                    <input type="number" 
                           name="valor_min" 
                           value="<?= $valor_min > 0 ? esc_attr($valor_min) : '' ?>"
                           step="0.01"
                           min="0"
                           placeholder="Ej: 10000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor máximo</label>
                    <input type="number" 
                           name="valor_max" 
                           value="<?= $valor_max > 0 ? esc_attr($valor_max) : '' ?>"
                           step="0.01"
                           min="0"
                           placeholder="Ej: 100000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Estado</label>
                    <select name="estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                        <option value="aprobada" <?= $estado_filtro === 'aprobada' ? 'selected' : '' ?>>✅ Aprobada</option>
                        <option value="rechazada" <?= $estado_filtro === 'rechazada' ? 'selected' : '' ?>>❌ Rechazada</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Tipo</label>
                    <select name="tipo" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Todos</option>
                        <option value="normal" <?= $tipo_filtro === 'normal' ? 'selected' : '' ?>>📋 Normal</option>
                        <option value="pago" <?= $tipo_filtro === 'pago' ? 'selected' : '' ?>>💰 Pago</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); flex: 1; min-width: 120px;">
                    🔍 Filtrar
                </button>
                <?php
                // Construir URL sin los parámetros de filtro
                $clean_url = remove_query_arg(['fecha_desde', 'fecha_hasta', 'valor_min', 'valor_max', 'estado', 'tipo', 'mensajero_id']);
                if (empty($clean_url) || $clean_url === home_url('/')) {
                    $clean_url = home_url('/transferencias');
                }
                ?>
                <a href="<?= esc_url($clean_url) ?>" 
                   class="gofast-btn gofast-secondary" 
                   style="flex: 1; min-width: 120px; text-align: center; text-decoration: none; display: inline-block;">
                    🔄 Limpiar
                </a>
            </div>
        </form>
        
        <?php if (!empty($transferencias) || !empty($fecha_desde) || !empty($fecha_hasta) || $valor_min > 0 || $valor_max > 0 || !empty($estado_filtro) || !empty($tipo_filtro)): ?>
            <div style="margin-top: 12px; padding: 10px; background: #e7f3ff; border-radius: 6px; font-size: 13px;">
                <strong>Resultados:</strong> 
                <?= count($transferencias) ?> transferencia(s) encontrada(s)
                <?php if (isset($total_sin_filtros) && $total_sin_filtros > count($transferencias)): ?>
                    (de <?= $total_sin_filtros ?> total)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Listado de transferencias -->
    <div class="gofast-box">
        <h3><?= $rol === 'admin' ? '📋 Todas las Transferencias' : '📋 Mis Transferencias' ?></h3>
        
        <?php if (empty($transferencias)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No hay transferencias registradas.
            </p>
        <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="gofast-transferencias-table-wrapper gofast-transferencias-desktop">
                <div class="gofast-table-wrap">
                    <table class="gofast-table gofast-transferencias-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Mensajero</th>
                                <th>Creado por</th>
                            <?php endif; ?>
                            <th>Valor</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transferencias as $t): ?>
                            <tr>
                                <td>#<?= esc_html($t->id) ?></td>
                                <td><?= gofast_date_format($t->fecha_creacion, 'd/m/Y H:i') ?></td>
                                <?php if ($rol === 'admin'): ?>
                                    <td>
                                        <?= esc_html($t->mensajero_nombre) ?><br>
                                        <small style="color: #666;"><?= esc_html($t->mensajero_telefono) ?></small>
                                    </td>
                                    <td><?= esc_html($t->creador_nombre) ?></td>
                                <?php endif; ?>
                                <td><strong>$<?= number_format($t->valor, 0, ',', '.') ?></strong></td>
                                <td>
                                    <?php
                                    $tipo_text = '';
                                    $tipo_icon = '';
                                    $tipo_color = '';
                                    if (!empty($t->tipo) && $t->tipo === 'pago') {
                                        $tipo_text = '💰 Pago';
                                        $tipo_icon = '💰';
                                        $tipo_color = '#007bff';
                                    } else {
                                        $tipo_text = '📋 Normal';
                                        $tipo_icon = '📋';
                                        $tipo_color = '#6c757d';
                                    }
                                    ?>
                                    <span style="font-size: 12px; color: <?= $tipo_color ?>; font-weight: 600;">
                                        <?= $tipo_text ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    $estado_text = '';
                                    switch ($t->estado) {
                                        case 'pendiente':
                                            $estado_class = 'gofast-badge-estado-pendiente';
                                            $estado_text = '⏳ Pendiente';
                                            break;
                                        case 'aprobada':
                                            $estado_class = 'gofast-badge-estado-entregado';
                                            $estado_text = '✅ Aprobada';
                                            break;
                                        case 'rechazada':
                                            $estado_class = 'gofast-badge-estado-cancelado';
                                            $estado_text = '❌ Rechazada';
                                            break;
                                    }
                                    ?>
                                    <span class="gofast-badge-estado <?= $estado_class ?>">
                                        <?= $estado_text ?>
                                    </span>
                                    <?php if ($t->estado === 'rechazada' && !empty($t->observaciones)): ?>
                                        <br><small style="color: #666; font-size: 11px; margin-top: 4px; display: block;">
                                            <?= esc_html($t->observaciones) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($rol === 'admin'): ?>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <form method="post" style="display: inline-block;">
                                                <?php wp_nonce_field('gofast_aprobar_transferencia', 'gofast_aprobar_nonce'); ?>
                                                <input type="hidden" name="transferencia_id" value="<?= esc_attr($t->id) ?>">
                                                <button type="submit" name="gofast_aprobar_transferencia" 
                                                        class="gofast-btn-mini" 
                                                        style="background: #28a745; color: #fff; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                    ✅ Aprobar
                                                </button>
                                            </form>
                                            <button type="button" 
                                                    onclick="mostrarModalRechazar(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>)"
                                                    class="gofast-btn-mini" 
                                                    style="background: #dc3545; color: #fff; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                ❌ Rechazar
                                            </button>
                                            <button type="button" 
                                                    onclick="mostrarModalEditarTransferencia(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>, '<?= esc_js(date('Y-m-d', strtotime($t->fecha_creacion))) ?>', '<?= esc_js($t->estado) ?>')"
                                                    class="gofast-btn-mini" 
                                                    style="background: #ffc107; color: #000; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                                ✏️ Editar
                                            </button>
                                            <button type="button" 
                                                    onclick="mostrarModalCambiarTipo(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>, '<?= esc_js($t->tipo ?? 'normal') ?>')"
                                                    class="gofast-btn-mini" 
                                                    style="background: #17a2b8; color: #fff; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                🔄 Tipo
                                            </button>
                                            <button type="button" 
                                                    onclick="mostrarModalEliminar(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>)"
                                                    class="gofast-btn-mini" 
                                                    style="background: #6c757d; color: #fff; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Vista Móvil: Cards -->
            <div class="gofast-transferencias-cards gofast-transferencias-mobile">
                <?php foreach ($transferencias as $t): ?>
                    <?php
                    $estado_class = '';
                    $estado_text = '';
                    $estado_color = '';
                    switch ($t->estado) {
                        case 'pendiente':
                            $estado_class = 'gofast-badge-estado-pendiente';
                            $estado_text = '⏳ Pendiente';
                            $estado_color = '#fff3cd';
                            break;
                        case 'aprobada':
                            $estado_class = 'gofast-badge-estado-entregado';
                            $estado_text = '✅ Aprobada';
                            $estado_color = '#d4edda';
                            break;
                        case 'rechazada':
                            $estado_class = 'gofast-badge-estado-cancelado';
                            $estado_text = '❌ Rechazada';
                            $estado_color = '#f8d7da';
                            break;
                    }
                    ?>
                    <div class="gofast-transferencia-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid <?= $estado_color ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">ID: #<?= esc_html($t->id) ?></div>
                                <div style="font-size: 11px; color: #999;"><?= gofast_date_format($t->fecha_creacion, 'd/m/Y H:i') ?></div>
                                <div style="font-size: 11px; color: <?= (!empty($t->tipo) && $t->tipo === 'pago') ? '#007bff' : '#6c757d' ?>; margin-top: 4px; font-weight: 600;">
                                    <?= (!empty($t->tipo) && $t->tipo === 'pago') ? '💰 Pago' : '📋 Normal' ?>
                                </div>
                            </div>
                            <span class="gofast-badge-estado <?= $estado_class ?>" style="font-size: 12px; padding: 4px 10px;">
                                <?= $estado_text ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <div style="font-size: 24px; font-weight: 700; color: #000;">
                                $<?= number_format($t->valor, 0, ',', '.') ?>
                            </div>
                        </div>

                        <?php if ($rol === 'admin'): ?>
                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Mensajero:</div>
                                <div style="font-size: 14px; font-weight: 600; color: #000;">
                                    <?= esc_html($t->mensajero_nombre) ?>
                                </div>
                                <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                    <?= esc_html($t->mensajero_telefono) ?>
                                </div>
                            </div>
                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Creado por:</div>
                                <div style="font-size: 14px; font-weight: 600; color: #000;">
                                    <?= esc_html($t->creador_nombre) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($t->estado === 'rechazada' && !empty($t->observaciones)): ?>
                            <div style="margin-bottom: 12px; padding: 10px; background: #ffe5e5; border-radius: 6px; border-left: 3px solid #dc3545;">
                                <div style="font-size: 12px; color: #721c24; font-weight: 600; margin-bottom: 4px;">Observaciones:</div>
                                <div style="font-size: 13px; color: #721c24;">
                                    <?= esc_html($t->observaciones) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($rol === 'admin'): ?>
                            <div style="display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;">
                                <form method="post" style="flex: 1; min-width: 100px;">
                                    <?php wp_nonce_field('gofast_aprobar_transferencia', 'gofast_aprobar_nonce'); ?>
                                    <input type="hidden" name="transferencia_id" value="<?= esc_attr($t->id) ?>">
                                    <button type="submit" name="gofast_aprobar_transferencia" 
                                            style="width: 100%; background: #28a745; color: #fff; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                        ✅ Aprobar
                                    </button>
                                </form>
                                <button type="button" 
                                        onclick="mostrarModalRechazar(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>)"
                                        style="flex: 1; min-width: 100px; background: #dc3545; color: #fff; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    ❌ Rechazar
                                </button>
                                <button type="button" 
                                        onclick="mostrarModalEditarTransferencia(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>, '<?= esc_js(date('Y-m-d', strtotime($t->fecha_creacion))) ?>', '<?= esc_js($t->estado) ?>')"
                                        style="flex: 1; min-width: 100px; background: #ffc107; color: #000; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    ✏️ Editar
                                </button>
                                <button type="button" 
                                        onclick="mostrarModalCambiarTipo(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>, '<?= esc_js($t->tipo ?? 'normal') ?>')"
                                        style="flex: 1; min-width: 100px; background: #17a2b8; color: #fff; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    🔄 Tipo
                                </button>
                                <button type="button" 
                                        onclick="mostrarModalEliminar(<?= esc_js($t->id) ?>, '<?= esc_js($t->mensajero_nombre) ?>', <?= esc_js($t->valor) ?>)"
                                        style="flex: 1; min-width: 100px; background: #6c757d; color: #fff; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal para editar transferencia (Solo Admin) -->
<?php if ($rol === 'admin'): ?>
    <div id="modal-editar-transferencia" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10003; overflow-y: auto; padding: 20px;">
        <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 12px; font-size: 20px;">✏️ Editar Transferencia</h2>
            
            <!-- Información de la transferencia -->
            <div id="transferencia-info-editar" style="background: #f8f9fa; border-left: 4px solid #ffc107; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;">
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 12px; align-items: start;">
                    <strong style="color: #666;">Mensajero:</strong>
                    <span id="modal-mensajero-nombre-editar" style="font-weight: 600; color: #000;"></span>
                    
                    <strong style="color: #666;">Estado Actual:</strong>
                    <span id="modal-estado-actual-editar" style="font-weight: 600; color: #000;"></span>
                </div>
            </div>
            
            <form method="post" id="form-editar-transferencia" action="">
                <?php wp_nonce_field('gofast_editar_transferencia', 'gofast_editar_transferencia_nonce'); ?>
                <input type="hidden" name="transferencia_id" id="modal-transferencia-id-editar">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #000;">Fecha <span style="color: #dc3545;">*</span></label>
                    <input type="date" 
                           name="fecha_creacion" 
                           id="modal-fecha-editar"
                           required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #000;">Valor <span style="color: #dc3545;">*</span></label>
                    <input type="number" 
                           name="valor" 
                           id="modal-valor-editar"
                           required
                           min="0.01"
                           step="0.01"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">El valor debe ser mayor a cero.</small>
                </div>
                
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
                    <button type="button" 
                            onclick="cerrarModalEditarTransferencia()" 
                            class="gofast-btn-mini gofast-btn-outline">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="gofast_editar_transferencia" 
                            class="gofast-btn-mini" 
                            style="background: #ffc107; color: #000;">
                        💾 Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Modal para rechazar transferencia (Solo Admin) -->
    <div id="modal-rechazar-transferencia" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 20px;">
        <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 12px; font-size: 20px;">❌ Rechazar Transferencia</h2>
            
            <!-- Información de la transferencia -->
            <div id="transferencia-info" style="background: #f8f9fa; border-left: 4px solid #dc3545; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;">
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 12px; align-items: start;">
                    <strong style="color: #666;">Mensajero:</strong>
                    <span id="modal-mensajero-nombre" style="font-weight: 600; color: #000;"></span>
                    
                    <strong style="color: #666;">Valor:</strong>
                    <span id="modal-transferencia-valor" style="font-weight: 600; color: #000;"></span>
                </div>
            </div>
            
            <form method="post" id="form-rechazar-transferencia" action="">
                <?php wp_nonce_field('gofast_rechazar_transferencia', 'gofast_rechazar_nonce'); ?>
                <input type="hidden" name="transferencia_id" id="modal-transferencia-id">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #000;">Observaciones (opcional)</label>
                    <textarea name="observaciones" 
                              rows="3" 
                              placeholder="Motivo del rechazo..."
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;"></textarea>
                </div>
                
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
                    <button type="button" 
                            onclick="cerrarModalRechazar()" 
                            class="gofast-btn-mini gofast-btn-outline">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="gofast_rechazar_transferencia" 
                            class="gofast-btn-mini" 
                            style="background: #dc3545; color: #fff;">
                        ❌ Rechazar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para cambiar tipo de transferencia (Solo Admin) -->
    <div id="modal-cambiar-tipo-transferencia" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10002; overflow-y: auto; padding: 20px;">
        <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 12px; font-size: 20px;">🔄 Cambiar Tipo de Transferencia</h2>
            
            <!-- Información de la transferencia -->
            <div id="transferencia-info-tipo" style="background: #f8f9fa; border-left: 4px solid #17a2b8; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;">
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 12px; align-items: start;">
                    <strong style="color: #666;">Mensajero:</strong>
                    <span id="modal-mensajero-nombre-tipo" style="font-weight: 600; color: #000;"></span>
                    
                    <strong style="color: #666;">Valor:</strong>
                    <span id="modal-transferencia-valor-tipo" style="font-weight: 600; color: #000;"></span>
                    
                    <strong style="color: #666;">Tipo Actual:</strong>
                    <span id="modal-tipo-actual" style="font-weight: 600; color: #000;"></span>
                </div>
            </div>
            
            <form method="post" id="form-cambiar-tipo-transferencia" action="">
                <?php wp_nonce_field('gofast_cambiar_tipo_transferencia', 'gofast_cambiar_tipo_nonce'); ?>
                <input type="hidden" name="transferencia_id" id="modal-transferencia-id-tipo">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #000;">Nuevo Tipo</label>
                    <select name="nuevo_tipo" 
                            id="modal-nuevo-tipo"
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="normal">📋 Normal</option>
                        <option value="pago">💰 Pago</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
                    <button type="button" 
                            onclick="cerrarModalCambiarTipo()" 
                            class="gofast-btn-mini gofast-btn-outline">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="gofast_cambiar_tipo_transferencia" 
                            class="gofast-btn-mini" 
                            style="background: #17a2b8; color: #fff;">
                        🔄 Cambiar Tipo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para eliminar transferencia (Solo Admin) -->
    <div id="modal-eliminar-transferencia" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10001; overflow-y: auto; padding: 20px;">
        <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 12px; font-size: 20px;">🗑️ Eliminar Transferencia</h2>
            
            <!-- Información de la transferencia -->
            <div id="transferencia-info-eliminar" style="background: #f8f9fa; border-left: 4px solid #6c757d; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;">
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 12px; align-items: start;">
                    <strong style="color: #666;">Mensajero:</strong>
                    <span id="modal-mensajero-nombre-eliminar" style="font-weight: 600; color: #000;"></span>
                    
                    <strong style="color: #666;">Valor:</strong>
                    <span id="modal-transferencia-valor-eliminar" style="font-weight: 600; color: #000;"></span>
                </div>
            </div>
            
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                <strong style="color: #856404;">⚠️ Advertencia:</strong>
                <p style="margin: 8px 0 0 0; color: #856404; font-size: 13px;">
                    Esta acción no se puede deshacer. La transferencia será eliminada permanentemente de la base de datos.
                </p>
            </div>
            
            <form method="post" id="form-eliminar-transferencia" action="">
                <?php wp_nonce_field('gofast_eliminar_transferencia', 'gofast_eliminar_nonce'); ?>
                <input type="hidden" name="transferencia_id" id="modal-transferencia-id-eliminar">
                
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
                    <button type="button" 
                            onclick="cerrarModalEliminar()" 
                            class="gofast-btn-mini gofast-btn-outline">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="gofast_eliminar_transferencia" 
                            class="gofast-btn-mini" 
                            style="background: #6c757d; color: #fff;">
                        🗑️ Eliminar Permanentemente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function mostrarModalEditarTransferencia(id, mensajero, valor, fecha, estado) {
        document.getElementById('modal-transferencia-id-editar').value = id;
        document.getElementById('modal-mensajero-nombre-editar').textContent = mensajero;
        document.getElementById('modal-estado-actual-editar').textContent = estado === 'pendiente' ? '⏳ Pendiente' : estado === 'aprobada' ? '✅ Aprobada' : '❌ Rechazada';
        document.getElementById('modal-fecha-editar').value = fecha;
        document.getElementById('modal-valor-editar').value = valor;
        document.getElementById('modal-editar-transferencia').style.display = 'block';
    }
    
    function cerrarModalEditarTransferencia() {
        document.getElementById('modal-editar-transferencia').style.display = 'none';
        document.getElementById('form-editar-transferencia').reset();
    }
    
    function mostrarModalRechazar(id, mensajero, valor) {
        document.getElementById('modal-transferencia-id').value = id;
        document.getElementById('modal-mensajero-nombre').textContent = mensajero;
        document.getElementById('modal-transferencia-valor').textContent = '$' + new Intl.NumberFormat('es-CO').format(valor);
        document.getElementById('modal-rechazar-transferencia').style.display = 'block';
    }
    
    function cerrarModalRechazar() {
        document.getElementById('modal-rechazar-transferencia').style.display = 'none';
        document.getElementById('form-rechazar-transferencia').reset();
    }
    
    function mostrarModalCambiarTipo(id, mensajero, valor, tipoActual) {
        document.getElementById('modal-transferencia-id-tipo').value = id;
        document.getElementById('modal-mensajero-nombre-tipo').textContent = mensajero;
        document.getElementById('modal-transferencia-valor-tipo').textContent = '$' + new Intl.NumberFormat('es-CO').format(valor);
        document.getElementById('modal-tipo-actual').textContent = tipoActual === 'pago' ? '💰 Pago' : '📋 Normal';
        document.getElementById('modal-nuevo-tipo').value = tipoActual === 'pago' ? 'normal' : 'pago';
        document.getElementById('modal-cambiar-tipo-transferencia').style.display = 'block';
    }
    
    function cerrarModalCambiarTipo() {
        document.getElementById('modal-cambiar-tipo-transferencia').style.display = 'none';
        document.getElementById('form-cambiar-tipo-transferencia').reset();
    }
    
    function mostrarModalEliminar(id, mensajero, valor) {
        document.getElementById('modal-transferencia-id-eliminar').value = id;
        document.getElementById('modal-mensajero-nombre-eliminar').textContent = mensajero;
        document.getElementById('modal-transferencia-valor-eliminar').textContent = '$' + new Intl.NumberFormat('es-CO').format(valor);
        document.getElementById('modal-eliminar-transferencia').style.display = 'block';
    }
    
    function cerrarModalEliminar() {
        document.getElementById('modal-eliminar-transferencia').style.display = 'none';
        document.getElementById('form-eliminar-transferencia').reset();
    }
    
    // Cerrar al hacer clic fuera del modal de editar
    document.getElementById('modal-editar-transferencia').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalEditarTransferencia();
        }
    });
    
    // Cerrar al hacer clic fuera del modal de rechazar
    document.getElementById('modal-rechazar-transferencia').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalRechazar();
        }
    });
    
    // Cerrar al hacer clic fuera del modal de cambiar tipo
    document.getElementById('modal-cambiar-tipo-transferencia').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalCambiarTipo();
        }
    });
    
    // Cerrar al hacer clic fuera del modal de eliminar
    document.getElementById('modal-eliminar-transferencia').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalEliminar();
        }
    });
    </script>
<?php endif; ?>

<style>
/* Los estilos de gofast-home ya están en css.css */

/* Estilos para tabla de transferencias */
.gofast-transferencias-table-wrapper .gofast-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
    display: block;
    margin: 0;
    padding: 0;
}

.gofast-transferencias-table-wrapper .gofast-transferencias-table {
    min-width: 800px;
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

/* Vista Desktop: Mostrar tabla, ocultar cards */
.gofast-transferencias-desktop {
    display: block;
}

/* Vista Móvil: Cards (oculta en desktop) */
.gofast-transferencias-mobile {
    display: none;
}

.gofast-transferencias-cards {
    width: 100%;
    max-width: 100%;
}

.gofast-transferencia-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Estilos para formulario de filtros */
.gofast-filtros-form {
    width: 100%;
}

.gofast-filtros-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 13px;
    color: #000;
}

.gofast-filtros-form input[type="date"],
.gofast-filtros-form input[type="number"],
.gofast-filtros-form select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    color: #000;
    box-sizing: border-box;
}

/* Responsive para móvil - tabla de transferencias */
@media (max-width: 768px) {
    
    .gofast-transferencias-table-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: visible !important;
        margin: 0;
        padding: 0;
        display: block !important;
        visibility: visible !important;
    }
    
    .gofast-transferencias-table-wrapper .gofast-table-wrap {
        overflow-x: scroll !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        margin: 0;
        padding: 0;
        width: 100%;
        max-width: 100%;
        display: block !important;
        visibility: visible !important;
    }
    
    .gofast-transferencias-table-wrapper .gofast-table-wrap::-webkit-scrollbar {
        height: 8px;
    }
    
    .gofast-transferencias-table-wrapper .gofast-table-wrap::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .gofast-transferencias-table-wrapper .gofast-table-wrap::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .gofast-transferencias-table-wrapper .gofast-transferencias-table {
        min-width: 850px;
        font-size: 13px;
        display: table !important;
        visibility: visible !important;
    }
    
    .gofast-transferencias-table-wrapper .gofast-transferencias-table th,
    .gofast-transferencias-table-wrapper .gofast-transferencias-table td {
        padding: 10px 8px;
        font-size: 12px;
        white-space: nowrap;
        display: table-cell !important;
        visibility: visible !important;
    }
    
    /* Ocultar columna "Creado por" en móvil para admin */
    .gofast-transferencias-table-wrapper .gofast-transferencias-table th:nth-child(4),
    .gofast-transferencias-table-wrapper .gofast-transferencias-table td:nth-child(4) {
        display: none !important;
    }
    
    /* Ocultar tabla en móvil, mostrar cards */
    .gofast-transferencias-desktop {
        display: none !important;
    }
    
    .gofast-transferencias-mobile {
        display: block !important;
    }
    
    .gofast-transferencias-cards {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    
    .gofast-transferencia-card {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .gofast-transferencia-card * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    
    /* Filtros en móvil */
    .gofast-filtros-form > div {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    .gofast-filtros-form button,
    .gofast-filtros-form a {
        width: 100% !important;
        min-width: 100% !important;
    }
    
}

/* Para pantallas muy pequeñas */
@media (max-width: 480px) {
    
    .gofast-transferencias-table-wrapper .gofast-transferencias-table {
        min-width: 750px;
        font-size: 12px;
    }
    
    .gofast-transferencias-table-wrapper .gofast-transferencias-table th,
    .gofast-transferencias-table-wrapper .gofast-transferencias-table td {
        padding: 8px 6px;
        font-size: 11px;
    }
    
    /* Ocultar ID en móvil muy pequeño */
    .gofast-transferencias-table-wrapper .gofast-transferencias-table th:first-child,
    .gofast-transferencias-table-wrapper .gofast-transferencias-table td:first-child {
        display: none !important;
    }
}

/* Desktop: Mostrar tabla, ocultar cards */
@media (min-width: 769px) {
    .gofast-transferencias-desktop {
        display: block !important;
    }
    
    .gofast-transferencias-mobile {
        display: none !important;
    }
}
</style>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_transferencias', 'gofast_transferencias_shortcode');

