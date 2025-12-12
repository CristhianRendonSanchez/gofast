<?php
/***************************************************
 * GOFAST – MÓDULO DE FINANZAS ADMINISTRATIVO
 * Shortcode: [gofast_finanzas_admin]
 * URL: /admin-finanzas
 * 
 * Funcionalidades:
 * - Tab Ingresos: Visualizar ingresos y comisiones diarias
 * - Tab Egresos: CRUD de egresos
 * - Tab Vales Empresa: CRUD de vales de empresa
 * - Tab Vales Personal: CRUD de vales del personal
 * - Tab Transferencias Entradas: Visualizar transferencias entrantes
 * - Tab Transferencias Salidas: CRUD de transferencias salientes
 * - Tab Saldos Mensajeros: Gestión de pagos y descuentos a mensajeros
 * - Bloque de Resultados Generales: Cálculos consolidados
 ***************************************************/
function gofast_finanzas_admin_shortcode() {
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
    $tab_activo = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'ingresos';
    
    // Mostrar mensajes de éxito
    if (isset($_GET['mensaje'])) {
        if ($_GET['mensaje'] === 'pago_registrado') {
            $mensaje = '✅ Pago registrado correctamente.';
            $mensaje_tipo = 'success';
        }
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // ========== PAGOS MENSAJEROS ==========
    
    // Registrar pago a mensajero
    if (isset($_POST['gofast_registrar_pago_mensajero']) && wp_verify_nonce($_POST['gofast_pago_mensajero_nonce'], 'gofast_registrar_pago_mensajero')) {
        $mensajero_id = (int) ($_POST['mensajero_id'] ?? 0);
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $tipo_pago = sanitize_text_field($_POST['tipo_pago'] ?? '');
        $comision_total = floatval($_POST['comision_total'] ?? 0);
        $transferencias_total = floatval($_POST['transferencias_total'] ?? 0);
        $descuentos_total = floatval($_POST['descuentos_total'] ?? 0);
        $total_a_pagar = floatval($_POST['total_a_pagar'] ?? 0);

        if ($mensajero_id <= 0 || empty($fecha) || !in_array($tipo_pago, ['efectivo', 'transferencia'])) {
            $mensaje = 'Datos inválidos para registrar el pago.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'pagos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'mensajero_id' => $mensajero_id,
                    'comision_total' => $comision_total,
                    'transferencias_total' => $transferencias_total,
                    'descuentos_total' => $descuentos_total,
                    'total_a_pagar' => $total_a_pagar,
                    'tipo_pago' => $tipo_pago,
                    'fecha_pago' => gofast_date_mysql(),
                    'creado_por' => $user_id
                ],
                ['%s', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%d']
            );

            if ($insertado) {
                // Redirigir para mostrar mensaje de éxito
                $redirect_url = add_query_arg([
                    'mensaje' => 'pago_registrado',
                    'tab' => 'saldos_mensajeros'
                ], remove_query_arg(['mensaje', 'tab'], $_SERVER['REQUEST_URI']));
                echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                return '';
            } else {
                $mensaje = 'Error al registrar el pago: ' . $wpdb->last_error;
                $mensaje_tipo = 'error';
            }
        }
    }

    // ========== EGRESOS ==========
    
    // 1. CREAR EGRESO
    if (isset($_POST['gofast_crear_egreso']) && wp_verify_nonce($_POST['gofast_egreso_nonce'], 'gofast_crear_egreso')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'egresos_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Egreso creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'egresos';
            } else {
                $mensaje = 'Error al crear el egreso.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR EGRESO
    if (isset($_POST['gofast_editar_egreso']) && wp_verify_nonce($_POST['gofast_editar_egreso_nonce'], 'gofast_editar_egreso')) {
        $egreso_id = (int) $_POST['egreso_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'egresos_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $egreso_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Egreso actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'egresos';
            } else {
                $mensaje = 'Error al actualizar el egreso.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR EGRESO
    if (isset($_POST['gofast_eliminar_egreso']) && wp_verify_nonce($_POST['gofast_eliminar_egreso_nonce'], 'gofast_eliminar_egreso')) {
        $egreso_id = (int) $_POST['egreso_id'];

        $eliminado = $wpdb->delete(
            'egresos_gofast',
            ['id' => $egreso_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Egreso eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'egresos';
        } else {
            $mensaje = 'Error al eliminar el egreso.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== VALES EMPRESA ==========
    
    // 1. CREAR VALE EMPRESA
    if (isset($_POST['gofast_crear_vale_empresa']) && wp_verify_nonce($_POST['gofast_vale_empresa_nonce'], 'gofast_crear_vale_empresa')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'vales_empresa_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Vale de empresa creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_empresa';
            } else {
                $mensaje = 'Error al crear el vale de empresa.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR VALE EMPRESA
    if (isset($_POST['gofast_editar_vale_empresa']) && wp_verify_nonce($_POST['gofast_editar_vale_empresa_nonce'], 'gofast_editar_vale_empresa')) {
        $vale_id = (int) $_POST['vale_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'vales_empresa_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $vale_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Vale de empresa actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_empresa';
            } else {
                $mensaje = 'Error al actualizar el vale de empresa.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR VALE EMPRESA
    if (isset($_POST['gofast_eliminar_vale_empresa']) && wp_verify_nonce($_POST['gofast_eliminar_vale_empresa_nonce'], 'gofast_eliminar_vale_empresa')) {
        $vale_id = (int) $_POST['vale_id'];

        $eliminado = $wpdb->delete(
            'vales_empresa_gofast',
            ['id' => $vale_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Vale de empresa eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'vales_empresa';
        } else {
            $mensaje = 'Error al eliminar el vale de empresa.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== VALES PERSONAL ==========
    
    // 1. CREAR VALE PERSONAL
    if (isset($_POST['gofast_crear_vale_personal']) && wp_verify_nonce($_POST['gofast_vale_personal_nonce'], 'gofast_crear_vale_personal')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $persona_id = (int) ($_POST['persona_id'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || $persona_id <= 0 || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'vales_personal_gofast',
                [
                    'fecha' => $fecha,
                    'persona_id' => $persona_id,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%d', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Vale de personal creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_personal';
            } else {
                $mensaje = 'Error al crear el vale de personal.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR VALE PERSONAL
    if (isset($_POST['gofast_editar_vale_personal']) && wp_verify_nonce($_POST['gofast_editar_vale_personal_nonce'], 'gofast_editar_vale_personal')) {
        $vale_id = (int) $_POST['vale_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $persona_id = (int) ($_POST['persona_id'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || $persona_id <= 0 || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'vales_personal_gofast',
                [
                    'fecha' => $fecha,
                    'persona_id' => $persona_id,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $vale_id],
                ['%s', '%d', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Vale de personal actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_personal';
            } else {
                $mensaje = 'Error al actualizar el vale de personal.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR VALE PERSONAL
    if (isset($_POST['gofast_eliminar_vale_personal']) && wp_verify_nonce($_POST['gofast_eliminar_vale_personal_nonce'], 'gofast_eliminar_vale_personal')) {
        $vale_id = (int) $_POST['vale_id'];

        $eliminado = $wpdb->delete(
            'vales_personal_gofast',
            ['id' => $vale_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Vale de personal eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'vales_personal';
        } else {
            $mensaje = 'Error al eliminar el vale de personal.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== TRANSFERENCIAS SALIDAS ==========
    
    // 1. CREAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_crear_transferencia_salida']) && wp_verify_nonce($_POST['gofast_transferencia_salida_nonce'], 'gofast_crear_transferencia_salida')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'transferencias_salidas_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Transferencia salida creada correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'transferencias_salidas';
            } else {
                $mensaje = 'Error al crear la transferencia salida.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_editar_transferencia_salida']) && wp_verify_nonce($_POST['gofast_editar_transferencia_salida_nonce'], 'gofast_editar_transferencia_salida')) {
        $transferencia_id = (int) $_POST['transferencia_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'transferencias_salidas_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $transferencia_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Transferencia salida actualizada correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'transferencias_salidas';
            } else {
                $mensaje = 'Error al actualizar la transferencia salida.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_eliminar_transferencia_salida']) && wp_verify_nonce($_POST['gofast_eliminar_transferencia_salida_nonce'], 'gofast_eliminar_transferencia_salida')) {
        $transferencia_id = (int) $_POST['transferencia_id'];

        $eliminado = $wpdb->delete(
            'transferencias_salidas_gofast',
            ['id' => $transferencia_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Transferencia salida eliminada correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'transferencias_salidas';
        } else {
            $mensaje = 'Error al eliminar la transferencia salida.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== DESCUENTOS MENSAJEROS ==========
    
    // CREAR DESCUENTO
    if (isset($_POST['gofast_crear_descuento']) && wp_verify_nonce($_POST['gofast_descuento_nonce'], 'gofast_crear_descuento')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $mensajero_id = (int) ($_POST['mensajero_id'] ?? 0);
        $valor = floatval($_POST['valor'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');

        if (empty($fecha) || $mensajero_id <= 0 || $valor <= 0) {
            $mensaje = 'Fecha, mensajero y valor son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'descuentos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'mensajero_id' => $mensajero_id,
                    'valor' => $valor,
                    'descripcion' => $descripcion,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%d', '%f', '%s', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Descuento aplicado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'saldos_mensajeros';
            } else {
                $mensaje = 'Error al aplicar el descuento: ' . $wpdb->last_error;
                $mensaje_tipo = 'error';
            }
        }
    }

    /*********************************************
     * FILTROS GENERALES
     *********************************************/
    $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
    $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';

    // Valores por defecto: día actual
    if (empty($fecha_desde) && empty($fecha_hasta)) {
        $fecha_actual = gofast_current_time('Y-m-d');
        $fecha_desde = $fecha_actual;
        $fecha_hasta = $fecha_actual;
    }

    /*********************************************
     * CÁLCULOS DEL BLOQUE DE RESULTADOS GENERALES
     *********************************************/
    
    // Construir WHERE para filtros de fecha
    $params_fecha = [];
    $where_conditions = [];

    // Total Ingresos (servicios + compras, excluyendo cancelados)
    $where_ingresos_servicios = ["tracking_estado != 'cancelado'"];
    $params_ingresos_servicios = [];
    
    if (!empty($fecha_desde)) {
        $where_ingresos_servicios[] = "DATE(fecha) >= %s";
        $params_ingresos_servicios[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_ingresos_servicios[] = "DATE(fecha) <= %s";
        $params_ingresos_servicios[] = $fecha_hasta;
    }

    $total_ingresos_servicios = (float) ($wpdb->get_var(
        !empty($params_ingresos_servicios)
            ? $wpdb->prepare("SELECT SUM(total) FROM servicios_gofast WHERE " . implode(' AND ', $where_ingresos_servicios), $params_ingresos_servicios)
            : "SELECT SUM(total) FROM servicios_gofast WHERE tracking_estado != 'cancelado'"
    ) ?? 0);

    $where_ingresos_compras = ["estado != 'cancelada'"];
    $params_ingresos_compras = [];
    
    if (!empty($fecha_desde)) {
        $where_ingresos_compras[] = "DATE(fecha_creacion) >= %s";
        $params_ingresos_compras[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_ingresos_compras[] = "DATE(fecha_creacion) <= %s";
        $params_ingresos_compras[] = $fecha_hasta;
    }

    $total_ingresos_compras = (float) ($wpdb->get_var(
        !empty($params_ingresos_compras)
            ? $wpdb->prepare("SELECT SUM(valor) FROM compras_gofast WHERE " . implode(' AND ', $where_ingresos_compras), $params_ingresos_compras)
            : "SELECT SUM(valor) FROM compras_gofast WHERE estado != 'cancelada'"
    ) ?? 0);

    $total_ingresos = $total_ingresos_servicios + $total_ingresos_compras;

    // Total Egresos
    $where_egresos = [];
    $params_egresos = [];
    
    if (!empty($fecha_desde)) {
        $where_egresos[] = "fecha >= %s";
        $params_egresos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_egresos[] = "fecha <= %s";
        $params_egresos[] = $fecha_hasta;
    }

    $total_egresos = (float) ($wpdb->get_var(
        !empty($params_egresos)
            ? $wpdb->prepare("SELECT SUM(valor) FROM egresos_gofast WHERE " . implode(' AND ', $where_egresos), $params_egresos)
            : "SELECT SUM(valor) FROM egresos_gofast"
    ) ?? 0);

    // Total Vales Empresa
    $where_vales_empresa = [];
    $params_vales_empresa = [];
    
    if (!empty($fecha_desde)) {
        $where_vales_empresa[] = "fecha >= %s";
        $params_vales_empresa[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_empresa[] = "fecha <= %s";
        $params_vales_empresa[] = $fecha_hasta;
    }

    $total_vales_empresa = (float) ($wpdb->get_var(
        !empty($params_vales_empresa)
            ? $wpdb->prepare("SELECT SUM(valor) FROM vales_empresa_gofast WHERE " . implode(' AND ', $where_vales_empresa), $params_vales_empresa)
            : "SELECT SUM(valor) FROM vales_empresa_gofast"
    ) ?? 0);

    // Total Vales Personal
    $where_vales_personal = [];
    $params_vales_personal = [];
    
    if (!empty($fecha_desde)) {
        $where_vales_personal[] = "fecha >= %s";
        $params_vales_personal[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_personal[] = "fecha <= %s";
        $params_vales_personal[] = $fecha_hasta;
    }

    $total_vales_personal = (float) ($wpdb->get_var(
        !empty($params_vales_personal)
            ? $wpdb->prepare("SELECT SUM(valor) FROM vales_personal_gofast WHERE " . implode(' AND ', $where_vales_personal), $params_vales_personal)
            : "SELECT SUM(valor) FROM vales_personal_gofast"
    ) ?? 0);

    // Total Transferencias Ingresos (aprobadas)
    $where_transf_entradas = ["estado = 'aprobada'"];
    $params_transf_entradas = [];
    
    if (!empty($fecha_desde)) {
        $where_transf_entradas[] = "DATE(fecha_creacion) >= %s";
        $params_transf_entradas[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_transf_entradas[] = "DATE(fecha_creacion) <= %s";
        $params_transf_entradas[] = $fecha_hasta;
    }

    $total_transferencias_ingresos = (float) ($wpdb->get_var(
        !empty($params_transf_entradas)
            ? $wpdb->prepare("SELECT SUM(valor) FROM transferencias_gofast WHERE " . implode(' AND ', $where_transf_entradas), $params_transf_entradas)
            : "SELECT SUM(valor) FROM transferencias_gofast WHERE estado = 'aprobada'"
    ) ?? 0);

    // Total Transferencias Salidas
    $where_transf_salidas = [];
    $params_transf_salidas = [];
    
    if (!empty($fecha_desde)) {
        $where_transf_salidas[] = "fecha >= %s";
        $params_transf_salidas[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_transf_salidas[] = "fecha <= %s";
        $params_transf_salidas[] = $fecha_hasta;
    }

    $total_transferencias_salidas = (float) ($wpdb->get_var(
        !empty($params_transf_salidas)
            ? $wpdb->prepare("SELECT SUM(valor) FROM transferencias_salidas_gofast WHERE " . implode(' AND ', $where_transf_salidas), $params_transf_salidas)
            : "SELECT SUM(valor) FROM transferencias_salidas_gofast"
    ) ?? 0);

    // Saldo Transferencias
    $saldo_transferencias = $total_transferencias_ingresos - $total_transferencias_salidas;

    // Total Saldos Pendientes por Pagar
    $where_pagos_pendientes = ["tipo_pago = 'pendiente'"];
    $params_pagos_pendientes = [];
    
    if (!empty($fecha_desde)) {
        $where_pagos_pendientes[] = "fecha >= %s";
        $params_pagos_pendientes[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_pagos_pendientes[] = "fecha <= %s";
        $params_pagos_pendientes[] = $fecha_hasta;
    }

    $total_saldos_pendientes = (float) ($wpdb->get_var(
        !empty($params_pagos_pendientes)
            ? $wpdb->prepare("SELECT SUM(total_a_pagar) FROM pagos_mensajeros_gofast WHERE " . implode(' AND ', $where_pagos_pendientes), $params_pagos_pendientes)
            : "SELECT SUM(total_a_pagar) FROM pagos_mensajeros_gofast WHERE tipo_pago = 'pendiente'"
    ) ?? 0);

    // Total Descuentos
    $where_descuentos = [];
    $params_descuentos = [];
    
    if (!empty($fecha_desde)) {
        $where_descuentos[] = "fecha >= %s";
        $params_descuentos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_descuentos[] = "fecha <= %s";
        $params_descuentos[] = $fecha_hasta;
    }

    $total_descuentos = (float) ($wpdb->get_var(
        !empty($params_descuentos)
            ? $wpdb->prepare("SELECT SUM(valor) FROM descuentos_mensajeros_gofast WHERE " . implode(' AND ', $where_descuentos), $params_descuentos)
            : "SELECT SUM(valor) FROM descuentos_mensajeros_gofast"
    ) ?? 0);

    // Cálculos finales
    $subtotal = $total_ingresos - $total_egresos - $total_vales_empresa - $total_descuentos;
    $efectivo = $subtotal - $saldo_transferencias - $total_saldos_pendientes;
    $utilidad_total = $subtotal;
    $utilidad_individual = $utilidad_total / 2;

    /*********************************************
     * DATOS PARA TAB DE INGRESOS
     *********************************************/
    $ingresos_diarios = [];
    
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        // Obtener ingresos agrupados por día
        $sql_ingresos = $wpdb->prepare(
            "SELECT 
                DATE(fecha) as fecha_dia,
                COUNT(*) as num_pedidos,
                SUM(total) as total_servicios
            FROM servicios_gofast
            WHERE DATE(fecha) >= %s 
            AND DATE(fecha) <= %s 
            AND tracking_estado != 'cancelado'
            GROUP BY DATE(fecha)
            ORDER BY fecha_dia DESC",
            $fecha_desde,
            $fecha_hasta
        );

        $ingresos_servicios = $wpdb->get_results($sql_ingresos);

        $sql_compras = $wpdb->prepare(
            "SELECT 
                DATE(fecha_creacion) as fecha_dia,
                COUNT(*) as num_compras,
                SUM(valor) as total_compras
            FROM compras_gofast
            WHERE DATE(fecha_creacion) >= %s 
            AND DATE(fecha_creacion) <= %s 
            AND estado != 'cancelada'
            GROUP BY DATE(fecha_creacion)
            ORDER BY fecha_dia DESC",
            $fecha_desde,
            $fecha_hasta
        );

        $ingresos_compras = $wpdb->get_results($sql_compras);

        // Combinar datos por fecha
        $ingresos_por_fecha = [];
        
        foreach ($ingresos_servicios as $row) {
            $fecha = $row->fecha_dia;
            $ingresos_por_fecha[$fecha] = [
                'fecha' => $fecha,
                'num_pedidos' => (int) $row->num_pedidos,
                'num_compras' => 0,
                'total_servicios' => (float) $row->total_servicios,
                'total_compras' => 0,
                'total_ingresos' => (float) $row->total_servicios,
                'total_comisiones' => (float) $row->total_servicios * 0.20
            ];
        }

        foreach ($ingresos_compras as $row) {
            $fecha = $row->fecha_dia;
            if (isset($ingresos_por_fecha[$fecha])) {
                $ingresos_por_fecha[$fecha]['num_compras'] = (int) $row->num_compras;
                $ingresos_por_fecha[$fecha]['total_compras'] = (float) $row->total_compras;
                $ingresos_por_fecha[$fecha]['total_ingresos'] += (float) $row->total_compras;
                $ingresos_por_fecha[$fecha]['total_comisiones'] = $ingresos_por_fecha[$fecha]['total_ingresos'] * 0.20;
            } else {
                $ingresos_por_fecha[$fecha] = [
                    'fecha' => $fecha,
                    'num_pedidos' => 0,
                    'num_compras' => (int) $row->num_compras,
                    'total_servicios' => 0,
                    'total_compras' => (float) $row->total_compras,
                    'total_ingresos' => (float) $row->total_compras,
                    'total_comisiones' => (float) $row->total_compras * 0.20
                ];
            }
        }

        $ingresos_diarios = array_values($ingresos_por_fecha);
    }

    /*********************************************
     * DATOS PARA TAB DE EGRESOS
     *********************************************/
    $where_egresos = [];
    $params_egresos = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_egresos[] = "e.fecha >= %s";
        $params_egresos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_egresos[] = "e.fecha <= %s";
        $params_egresos[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion = isset($_GET['filtro_descripcion']) ? sanitize_text_field($_GET['filtro_descripcion']) : '';
    if (!empty($filtro_descripcion)) {
        $where_egresos[] = "e.descripcion LIKE %s";
        $params_egresos[] = '%' . $wpdb->esc_like($filtro_descripcion) . '%';
    }

    $sql_egresos = "SELECT e.*, u.nombre as creador_nombre
                    FROM egresos_gofast e
                    LEFT JOIN usuarios_gofast u ON e.creado_por = u.id";
    
    if (!empty($where_egresos)) {
        $sql_egresos .= " WHERE " . implode(' AND ', $where_egresos);
    }
    
    $sql_egresos .= " ORDER BY e.fecha DESC, e.id DESC";

    if (!empty($params_egresos)) {
        $egresos = $wpdb->get_results($wpdb->prepare($sql_egresos, $params_egresos));
    } else {
        $egresos = $wpdb->get_results($sql_egresos);
    }

    /*********************************************
     * DATOS PARA TAB DE VALES EMPRESA
     *********************************************/
    $where_vales_empresa_list = [];
    $params_vales_empresa_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_vales_empresa_list[] = "v.fecha >= %s";
        $params_vales_empresa_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_empresa_list[] = "v.fecha <= %s";
        $params_vales_empresa_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_vales_empresa = isset($_GET['filtro_descripcion_vales_empresa']) ? sanitize_text_field($_GET['filtro_descripcion_vales_empresa']) : '';
    if (!empty($filtro_descripcion_vales_empresa)) {
        $where_vales_empresa_list[] = "v.descripcion LIKE %s";
        $params_vales_empresa_list[] = '%' . $wpdb->esc_like($filtro_descripcion_vales_empresa) . '%';
    }

    $sql_vales_empresa = "SELECT v.*, u.nombre as creador_nombre
                          FROM vales_empresa_gofast v
                          LEFT JOIN usuarios_gofast u ON v.creado_por = u.id";
    
    if (!empty($where_vales_empresa_list)) {
        $sql_vales_empresa .= " WHERE " . implode(' AND ', $where_vales_empresa_list);
    }
    
    $sql_vales_empresa .= " ORDER BY v.fecha DESC, v.id DESC";

    if (!empty($params_vales_empresa_list)) {
        $vales_empresa = $wpdb->get_results($wpdb->prepare($sql_vales_empresa, $params_vales_empresa_list));
    } else {
        $vales_empresa = $wpdb->get_results($sql_vales_empresa);
    }

    /*********************************************
     * DATOS PARA TAB DE VALES PERSONAL
     *********************************************/
    // Obtener las 4 personas activas (usuarios admin activos, limitado a 4)
    $personas_activas = $wpdb->get_results(
        "SELECT id, nombre 
         FROM usuarios_gofast
         WHERE rol = 'admin' AND activo = 1
         ORDER BY nombre ASC
         LIMIT 4
        "
    );

    $where_vales_personal_list = [];
    $params_vales_personal_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_vales_personal_list[] = "v.fecha >= %s";
        $params_vales_personal_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_personal_list[] = "v.fecha <= %s";
        $params_vales_personal_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_vales_personal = isset($_GET['filtro_descripcion_vales_personal']) ? sanitize_text_field($_GET['filtro_descripcion_vales_personal']) : '';
    if (!empty($filtro_descripcion_vales_personal)) {
        $where_vales_personal_list[] = "v.descripcion LIKE %s";
        $params_vales_personal_list[] = '%' . $wpdb->esc_like($filtro_descripcion_vales_personal) . '%';
    }

    // Filtro por persona
    $filtro_persona = isset($_GET['filtro_persona']) ? (int) $_GET['filtro_persona'] : 0;
    if ($filtro_persona > 0) {
        $where_vales_personal_list[] = "v.persona_id = %d";
        $params_vales_personal_list[] = $filtro_persona;
    }

    $sql_vales_personal = "SELECT v.*, 
                                  p.nombre as persona_nombre,
                                  u.nombre as creador_nombre
                           FROM vales_personal_gofast v
                           LEFT JOIN usuarios_gofast p ON v.persona_id = p.id
                           LEFT JOIN usuarios_gofast u ON v.creado_por = u.id";
    
    if (!empty($where_vales_personal_list)) {
        $sql_vales_personal .= " WHERE " . implode(' AND ', $where_vales_personal_list);
    }
    
    $sql_vales_personal .= " ORDER BY v.fecha DESC, v.id DESC";

    if (!empty($params_vales_personal_list)) {
        $vales_personal = $wpdb->get_results($wpdb->prepare($sql_vales_personal, $params_vales_personal_list));
    } else {
        $vales_personal = $wpdb->get_results($sql_vales_personal);
    }

    /*********************************************
     * DATOS PARA TAB DE TRANSFERENCIAS SALIDAS
     *********************************************/
    $where_transf_salidas_list = [];
    $params_transf_salidas_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_transf_salidas_list[] = "t.fecha >= %s";
        $params_transf_salidas_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_transf_salidas_list[] = "t.fecha <= %s";
        $params_transf_salidas_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_transf_salidas = isset($_GET['filtro_descripcion_transf_salidas']) ? sanitize_text_field($_GET['filtro_descripcion_transf_salidas']) : '';
    if (!empty($filtro_descripcion_transf_salidas)) {
        $where_transf_salidas_list[] = "t.descripcion LIKE %s";
        $params_transf_salidas_list[] = '%' . $wpdb->esc_like($filtro_descripcion_transf_salidas) . '%';
    }

    $sql_transf_salidas = "SELECT t.*, u.nombre as creador_nombre
                           FROM transferencias_salidas_gofast t
                           LEFT JOIN usuarios_gofast u ON t.creado_por = u.id";
    
    if (!empty($where_transf_salidas_list)) {
        $sql_transf_salidas .= " WHERE " . implode(' AND ', $where_transf_salidas_list);
    }
    
    $sql_transf_salidas .= " ORDER BY t.fecha DESC, t.id DESC";

    if (!empty($params_transf_salidas_list)) {
        $transferencias_salidas = $wpdb->get_results($wpdb->prepare($sql_transf_salidas, $params_transf_salidas_list));
    } else {
        $transferencias_salidas = $wpdb->get_results($sql_transf_salidas);
    }

    /*********************************************
     * DATOS PARA TAB DE TRANSFERENCIAS ENTRADAS
     *********************************************/
    $where_transf_entradas = [];
    $params_transf_entradas = [];

    // Solo transferencias aprobadas
    $where_transf_entradas[] = "t.estado = 'aprobada'";

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_transf_entradas[] = "DATE(t.fecha_creacion) >= %s";
        $params_transf_entradas[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_transf_entradas[] = "DATE(t.fecha_creacion) <= %s";
        $params_transf_entradas[] = $fecha_hasta;
    }

    // Filtro por mensajero
    $filtro_mensajero = isset($_GET['filtro_mensajero']) ? (int) $_GET['filtro_mensajero'] : 0;
    if ($filtro_mensajero > 0) {
        $where_transf_entradas[] = "t.mensajero_id = %d";
        $params_transf_entradas[] = $filtro_mensajero;
    }

    // Filtro por origen (si existe el campo en la tabla)
    // Por ahora no hay campo origen, pero dejamos preparado
    $filtro_origen = isset($_GET['filtro_origen']) ? sanitize_text_field($_GET['filtro_origen']) : '';
    // TODO: Implementar cuando se defina qué es "origen"

    $sql_transf_entradas = "SELECT t.*, 
                                   m.nombre as mensajero_nombre, 
                                   m.telefono as mensajero_telefono,
                                   u.nombre as creador_nombre
                            FROM transferencias_gofast t
                            LEFT JOIN usuarios_gofast m ON t.mensajero_id = m.id
                            LEFT JOIN usuarios_gofast u ON t.creado_por = u.id";
    
    if (!empty($where_transf_entradas)) {
        $sql_transf_entradas .= " WHERE " . implode(' AND ', $where_transf_entradas);
    }
    
    $sql_transf_entradas .= " ORDER BY t.fecha_creacion DESC";

    if (!empty($params_transf_entradas)) {
        $transferencias_entradas = $wpdb->get_results($wpdb->prepare($sql_transf_entradas, $params_transf_entradas));
    } else {
        $transferencias_entradas = $wpdb->get_results($sql_transf_entradas);
    }

    // Obtener lista de mensajeros para el filtro
    $mensajeros = [];
    $mensajeros = $wpdb->get_results(
        "SELECT id, nombre 
         FROM usuarios_gofast
         WHERE rol = 'mensajero' AND activo = 1
         ORDER BY nombre ASC
        "
    );

    /*********************************************
     * DATOS PARA TAB DE SALDOS MENSAJEROS
     *********************************************/
    $filtro_mensajero_saldos = isset($_GET['filtro_mensajero_saldos']) ? (int) $_GET['filtro_mensajero_saldos'] : 0;
    $filtro_estado_saldos = isset($_GET['filtro_estado_saldos']) ? sanitize_text_field($_GET['filtro_estado_saldos']) : '';

    // Construir WHERE para saldos mensajeros
    $where_saldos = [];
    $params_saldos = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_saldos[] = "DATE(s.fecha) >= %s";
        $params_saldos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_saldos[] = "DATE(s.fecha) <= %s";
        $params_saldos[] = $fecha_hasta;
    }

    // Filtro por mensajero
    if ($filtro_mensajero_saldos > 0) {
        $where_saldos[] = "s.mensajero_id = %d";
        $params_saldos[] = $filtro_mensajero_saldos;
    }

    // Filtro por estado
    if (!empty($filtro_estado_saldos) && in_array($filtro_estado_saldos, ['pendiente', 'efectivo', 'transferencia'])) {
        $where_saldos[] = "p.tipo_pago = %s";
        $params_saldos[] = $filtro_estado_saldos;
    }

    // Calcular estadísticas por mensajero (similar a reportes)
    // Por cada mensajero, calcular: destinos, compras, ingresos, comisión, transferencias, descuentos, total a pagar
    
    $where_servicios_mensajero = "tracking_estado != 'cancelado'";
    $params_servicios_mensajero = [];
    
    if (!empty($fecha_desde)) {
        $where_servicios_mensajero .= " AND DATE(fecha) >= %s";
        $params_servicios_mensajero[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_servicios_mensajero .= " AND DATE(fecha) <= %s";
        $params_servicios_mensajero[] = $fecha_hasta;
    }
    if ($filtro_mensajero_saldos > 0) {
        $where_servicios_mensajero .= " AND mensajero_id = %d";
        $params_servicios_mensajero[] = $filtro_mensajero_saldos;
    }

    $where_compras_mensajero = "estado != 'cancelada'";
    $params_compras_mensajero = [];
    
    if (!empty($fecha_desde)) {
        $where_compras_mensajero .= " AND DATE(fecha_creacion) >= %s";
        $params_compras_mensajero[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_compras_mensajero .= " AND DATE(fecha_creacion) <= %s";
        $params_compras_mensajero[] = $fecha_hasta;
    }
    if ($filtro_mensajero_saldos > 0) {
        $where_compras_mensajero .= " AND mensajero_id = %d";
        $params_compras_mensajero[] = $filtro_mensajero_saldos;
    }

    // Obtener datos agrupados por mensajero
    $where_mensajeros = "rol = 'mensajero' AND activo = 1";
    $params_mensajeros = [];
    
    if ($filtro_mensajero_saldos > 0) {
        $where_mensajeros .= " AND id = %d";
        $params_mensajeros[] = $filtro_mensajero_saldos;
    }

    // Obtener lista de mensajeros para cálculos (puede estar filtrada)
    // Nota: Incluimos telefono porque se muestra en la tabla de saldos
    if (!empty($params_mensajeros)) {
        $mensajeros_saldos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE $where_mensajeros ORDER BY nombre ASC",
                $params_mensajeros
            )
        );
    } else {
        $mensajeros_saldos = $wpdb->get_results(
            "SELECT id, nombre, telefono FROM usuarios_gofast WHERE $where_mensajeros ORDER BY nombre ASC"
        );
    }

    // Calcular estadísticas para cada mensajero
    $saldos_mensajeros = [];
    foreach ($mensajeros_saldos as $mensajero) {
        $mensajero_id = (int) $mensajero->id;
        
        // Total destinos
        $where_destinos = "mensajero_id = %d AND tracking_estado != 'cancelado'";
        $params_destinos = [$mensajero_id];
        if (!empty($fecha_desde)) {
            $where_destinos .= " AND DATE(fecha) >= %s";
            $params_destinos[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_destinos .= " AND DATE(fecha) <= %s";
            $params_destinos[] = $fecha_hasta;
        }
        
        $total_destinos = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos'))), 0) FROM servicios_gofast WHERE $where_destinos",
                $params_destinos
            ) ?? 0)
        );

        // Total compras
        $where_compras = "mensajero_id = %d AND estado != 'cancelada'";
        $params_compras = [$mensajero_id];
        if (!empty($fecha_desde)) {
            $where_compras .= " AND DATE(fecha_creacion) >= %s";
            $params_compras[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_compras .= " AND DATE(fecha_creacion) <= %s";
            $params_compras[] = $fecha_hasta;
        }
        
        $total_compras = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM compras_gofast WHERE $where_compras",
                $params_compras
            ) ?? 0)
        );

        // Ingresos servicios
        $ingresos_servicios = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total) FROM servicios_gofast WHERE $where_destinos",
                $params_destinos
            ) ?? 0)
        );

        // Ingresos compras
        $ingresos_compras = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM compras_gofast WHERE $where_compras",
                $params_compras
            ) ?? 0)
        );

        $ingresos_totales = $ingresos_servicios + $ingresos_compras;

        // Transferencias aprobadas
        $where_transf = "mensajero_id = %d AND estado = 'aprobada'";
        $params_transf = [$mensajero_id];
        if (!empty($fecha_desde)) {
            $where_transf .= " AND DATE(fecha_creacion) >= %s";
            $params_transf[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_transf .= " AND DATE(fecha_creacion) <= %s";
            $params_transf[] = $fecha_hasta;
        }
        
        $transferencias_aprobadas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast WHERE $where_transf",
                $params_transf
            ) ?? 0)
        );

        // Descuentos
        $where_descuentos = "mensajero_id = %d";
        $params_descuentos = [$mensajero_id];
        if (!empty($fecha_desde)) {
            $where_descuentos .= " AND fecha >= %s";
            $params_descuentos[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_descuentos .= " AND fecha <= %s";
            $params_descuentos[] = $fecha_hasta;
        }
        
        $total_descuentos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast WHERE $where_descuentos",
                $params_descuentos
            ) ?? 0)
        );

        // Calcular comisión y total a pagar (igual que en reportes: comisión - transferencias)
        $comision_generada = $ingresos_totales * 0.20;
        $utilidad_neta = $ingresos_totales - $comision_generada;
        $total_a_pagar = $comision_generada - $transferencias_aprobadas;

        // Agregar mensajero a la lista (incluso si todos los valores son 0)
        $saldos_mensajeros[] = (object) [
            'mensajero_id' => $mensajero_id,
            'mensajero_nombre' => $mensajero->nombre,
            'mensajero_telefono' => $mensajero->telefono,
            'total_destinos' => $total_destinos,
            'total_compras' => $total_compras,
            'ingresos_totales' => $ingresos_totales,
            'comision_generada' => $comision_generada,
            'utilidad_neta' => $utilidad_neta,
            'transferencias_aprobadas' => $transferencias_aprobadas,
            'total_descuentos' => $total_descuentos,
            'total_a_pagar' => $total_a_pagar
        ];
    }

    // Contar pedidos sin asignar (es el mismo para todos los mensajeros)
    $where_pedidos_sin_asignar = "tracking_estado != 'cancelado' AND mensajero_id IS NULL";
    $params_pedidos_sin_asignar = [];
    
    if (!empty($fecha_desde)) {
        $where_pedidos_sin_asignar .= " AND DATE(fecha) >= %s";
        $params_pedidos_sin_asignar[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_pedidos_sin_asignar .= " AND DATE(fecha) <= %s";
        $params_pedidos_sin_asignar[] = $fecha_hasta;
    }

    $pedidos_sin_asignar_saldos = (int) ($wpdb->get_var(
        !empty($params_pedidos_sin_asignar)
            ? $wpdb->prepare("SELECT COUNT(*) FROM servicios_gofast WHERE $where_pedidos_sin_asignar", $params_pedidos_sin_asignar)
            : "SELECT COUNT(*) FROM servicios_gofast WHERE tracking_estado != 'cancelado' AND mensajero_id IS NULL"
    ) ?? 0);

    // Totales generales (suma de todos los mensajeros o del mensajero filtrado)
    $total_destinos_saldos = array_sum(array_column($saldos_mensajeros, 'total_destinos'));
    $total_compras_saldos = array_sum(array_column($saldos_mensajeros, 'total_compras'));
    $ingresos_totales_saldos = array_sum(array_column($saldos_mensajeros, 'ingresos_totales'));
    $comision_generada_saldos = array_sum(array_column($saldos_mensajeros, 'comision_generada'));
    $utilidad_neta_saldos = array_sum(array_column($saldos_mensajeros, 'utilidad_neta'));
    $transferencias_aprobadas_saldos = array_sum(array_column($saldos_mensajeros, 'transferencias_aprobadas'));
    $total_a_pagar_saldos = array_sum(array_column($saldos_mensajeros, 'total_a_pagar'));

    // Calcular totales por día
    $transferencias_por_dia = [];
    foreach ($transferencias_entradas as $transf) {
        $fecha = gofast_date_format($transf->fecha_creacion, 'Y-m-d');
        if (!isset($transferencias_por_dia[$fecha])) {
            $transferencias_por_dia[$fecha] = [
                'fecha' => $fecha,
                'total' => 0,
                'cantidad' => 0
            ];
        }
        $transferencias_por_dia[$fecha]['total'] += (float) $transf->valor;
        $transferencias_por_dia[$fecha]['cantidad']++;
    }
    krsort($transferencias_por_dia); // Ordenar por fecha descendente

    ob_start();
    ?>

<div class="gofast-home">
    <script>
    // Función global para cambiar tabs - debe estar disponible inmediatamente
    window.mostrarTabFinanzas = function(tab, element) {
        // Ocultar todos los tabs
        document.querySelectorAll('.gofast-config-tab-content').forEach(function(content) {
            content.style.display = 'none';
        });
        
        // Remover clase activa de todos los botones
        document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
            btn.classList.remove('gofast-config-tab-active');
        });
        
        // Mostrar el tab seleccionado
        const tabContent = document.getElementById('tab-' + tab);
        if (tabContent) {
            tabContent.style.display = 'block';
        }
        
        // Agregar clase activa al botón
        if (element) {
            element.classList.add('gofast-config-tab-active');
        } else {
            // Si no hay element, buscar el botón correspondiente
            document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
                if (btn.textContent.includes(tab === 'ingresos' ? 'Ingresos' : 
                                             tab === 'egresos' ? 'Egresos' : 
                                             tab === 'vales_empresa' ? 'Vales Empresa' : 
                                             tab === 'vales_personal' ? 'Vales Personal' : 
                                             tab === 'transferencias_entradas' ? 'Transferencias Entradas' : 
                                             tab === 'transferencias_salidas' ? 'Transferencias Salidas' : 
                                             'Saldos Mensajeros')) {
                    btn.classList.add('gofast-config-tab-active');
                }
            });
        }
        
        // Actualizar el campo hidden del formulario de filtros para preservar el tab
        const filtroTabActivo = document.getElementById('filtro-tab-activo');
        if (filtroTabActivo) {
            filtroTabActivo.value = tab;
        }
        
        // Actualizar URL sin recargar
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
        
        // Inicializar Select2 cuando se muestre cualquier tab
        if (typeof inicializarSelect2Filtros === 'function') {
            setTimeout(function() {
                inicializarSelect2Filtros();
            }, 100);
        }
    };
    </script>
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">💰 Módulo de Finanzas</h1>
            <p class="gofast-home-text" style="margin:0;">
                Gestiona ingresos, egresos, vales, transferencias y saldos de mensajeros.
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

    <!-- BLOQUE DE RESULTADOS GENERALES -->
    <div class="gofast-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #fff;">📊 Resultados Generales</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Total Ingresos</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_ingresos, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Total Egresos</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_egresos, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Vales Empresa</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_vales_empresa, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Vales Personal</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_vales_personal, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Transf. Ingresos</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_transferencias_ingresos, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Transf. Salidas</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_transferencias_salidas, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Saldo Transferencias</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($saldo_transferencias, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Saldos Pendientes</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_saldos_pendientes, 0, ',', '.') ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">Total Descuentos</div>
                <div style="font-size: 20px; font-weight: 700;">$<?= number_format($total_descuentos, 0, ',', '.') ?></div>
            </div>
        </div>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">Subtotal</div>
                    <div style="font-size: 24px; font-weight: 700;">$<?= number_format($subtotal, 0, ',', '.') ?></div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">Efectivo</div>
                    <div style="font-size: 24px; font-weight: 700;">$<?= number_format($efectivo, 0, ',', '.') ?></div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">Utilidad Total</div>
                    <div style="font-size: 24px; font-weight: 700;">$<?= number_format($utilidad_total, 0, ',', '.') ?></div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">Utilidad Individual</div>
                    <div style="font-size: 24px; font-weight: 700;">$<?= number_format($utilidad_individual, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs de navegación -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'ingresos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('ingresos', this)">
                💰 Ingresos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'egresos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('egresos', this)">
                💸 Egresos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'vales_empresa' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('vales_empresa', this)">
                🏢 Vales Empresa
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'vales_personal' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('vales_personal', this)">
                👥 Vales Personal
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'transferencias_entradas' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('transferencias_entradas', this)">
                📥 Transferencias Entradas
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'transferencias_salidas' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('transferencias_salidas', this)">
                📤 Transferencias Salidas
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'saldos_mensajeros' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('saldos_mensajeros', this)">
                💵 Saldos Mensajeros
            </button>
        </div>

        <!-- Filtros generales -->
        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
            <h4 style="margin-top: 0; margin-bottom: 12px;">🔍 Filtros</h4>
            <form method="get" action="" id="form-filtros-generales" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                <!-- Mantener otros parámetros GET (excepto los que vamos a actualizar) -->
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if (!in_array($key, ['fecha_desde', 'fecha_hasta', 'tab', 'filtro_descripcion', 'filtro_mensajero', 'filtro_origen'])): ?>
                        <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- SIEMPRE preservar el tab activo -->
                <input type="hidden" name="tab" id="filtro-tab-activo" value="<?= esc_attr($tab_activo) ?>">
                
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
                    <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                        🔍 Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB: INGRESOS -->
        <div id="tab-ingresos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'ingresos' ? 'block' : 'none' ?>;">
            <h3>💰 Ingresos</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Ingresos diarios calculados automáticamente desde servicios y compras. Las comisiones se calculan como el 20% del total de ingresos.
            </p>

            <?php if (empty($ingresos_diarios)): ?>
                <div class="gofast-box" style="text-align: center; padding: 40px;">
                    <p style="color: #666; margin: 0;">No hay ingresos registrados en el rango de fechas seleccionado.</p>
                </div>
            <?php else: ?>
                <div class="gofast-table-wrap">
                    <table class="gofast-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th># Pedidos</th>
                                <th># Compras</th>
                                <th>Total Servicios</th>
                                <th>Total Compras</th>
                                <th>Total Ingresos</th>
                                <th>Total Comisiones (20%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingresos_diarios as $ingreso): ?>
                                <tr>
                                    <td><?= gofast_date_format($ingreso['fecha'], 'd/m/Y') ?></td>
                                    <td><?= $ingreso['num_pedidos'] ?></td>
                                    <td><?= $ingreso['num_compras'] ?></td>
                                    <td>$<?= number_format($ingreso['total_servicios'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($ingreso['total_compras'], 0, ',', '.') ?></td>
                                    <td><strong>$<?= number_format($ingreso['total_ingresos'], 0, ',', '.') ?></strong></td>
                                    <td><strong style="color: #28a745;">$<?= number_format($ingreso['total_comisiones'], 0, ',', '.') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: EGRESOS -->
        <div id="tab-egresos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'egresos' ? 'block' : 'none' ?>;">
            <h3>💸 Egresos</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los egresos de la empresa. Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear egreso -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Egreso</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_egreso', 'gofast_egreso_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Pago de servicios"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_egreso" class="gofast-btn-mini">✅ Crear Egreso</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion" 
                               value="<?= esc_attr($filtro_descripcion) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filtro_descripcion)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_descripcion')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de egresos -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Egresos</h4>
                
                <?php if (empty($egresos)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay egresos registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($egresos as $egreso): ?>
                                    <tr>
                                        <td>#<?= esc_html($egreso->id) ?></td>
                                        <td><?= gofast_date_format($egreso->fecha, 'd/m/Y') ?></td>
                                        <td><?= esc_html($egreso->descripcion) ?></td>
                                        <td><strong>$<?= number_format($egreso->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($egreso->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-egreso" 
                                                    data-egreso-id="<?= esc_attr($egreso->id) ?>"
                                                    data-egreso-fecha="<?= esc_attr($egreso->fecha) ?>"
                                                    data-egreso-descripcion="<?= esc_attr($egreso->descripcion) ?>"
                                                    data-egreso-valor="<?= esc_attr($egreso->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este egreso? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_egreso', 'gofast_eliminar_egreso_nonce'); ?>
                                                <input type="hidden" name="egreso_id" value="<?= esc_attr($egreso->id) ?>">
                                                <button type="submit" name="gofast_eliminar_egreso" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
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

        <!-- Modal para editar egreso -->
        <div id="modal-editar-egreso" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Egreso</h2>
                
                <form method="post" id="form-editar-egreso">
                    <?php wp_nonce_field('gofast_editar_egreso', 'gofast_editar_egreso_nonce'); ?>
                    <input type="hidden" name="egreso_id" id="editar-egreso-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-egreso-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-egreso-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-egreso-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarEgreso()">Cancelar</button>
                        <button type="submit" name="gofast_editar_egreso" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar egreso
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-egreso').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const egresoId = this.getAttribute('data-egreso-id');
                    const egresoFecha = this.getAttribute('data-egreso-fecha');
                    const egresoDescripcion = this.getAttribute('data-egreso-descripcion');
                    const egresoValor = this.getAttribute('data-egreso-valor');
                    
                    document.getElementById('editar-egreso-id').value = egresoId;
                    document.getElementById('editar-egreso-fecha').value = egresoFecha;
                    document.getElementById('editar-egreso-descripcion').value = egresoDescripcion;
                    document.getElementById('editar-egreso-valor').value = egresoValor;
                    
                    document.getElementById('modal-editar-egreso').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            document.getElementById('modal-editar-egreso').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModalEditarEgreso();
                }
            });
        });
        
        function cerrarModalEditarEgreso() {
            document.getElementById('modal-editar-egreso').style.display = 'none';
            document.getElementById('form-editar-egreso').reset();
        }
        </script>

        <!-- TAB: VALES EMPRESA -->
        <div id="tab-vales_empresa" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'vales_empresa' ? 'block' : 'none' ?>;">
            <h3>🏢 Vales de la Empresa</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los vales de la empresa. Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear vale empresa -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Vale de la Empresa</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_vale_empresa', 'gofast_vale_empresa_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Vale para combustible"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_vale_empresa" class="gofast-btn-mini">✅ Crear Vale</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion_vales_empresa'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_vales_empresa" 
                               value="<?= esc_attr($filtro_descripcion_vales_empresa) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filtro_descripcion_vales_empresa)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_descripcion_vales_empresa')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de vales empresa -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Vales de la Empresa</h4>
                
                <?php if (empty($vales_empresa)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay vales de empresa registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vales_empresa as $vale): ?>
                                    <tr>
                                        <td>#<?= esc_html($vale->id) ?></td>
                                        <td><?= gofast_date_format($vale->fecha, 'd/m/Y') ?></td>
                                        <td><?= esc_html($vale->descripcion) ?></td>
                                        <td><strong>$<?= number_format($vale->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($vale->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-vale-empresa" 
                                                    data-vale-id="<?= esc_attr($vale->id) ?>"
                                                    data-vale-fecha="<?= esc_attr($vale->fecha) ?>"
                                                    data-vale-descripcion="<?= esc_attr($vale->descripcion) ?>"
                                                    data-vale-valor="<?= esc_attr($vale->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este vale? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_vale_empresa', 'gofast_eliminar_vale_empresa_nonce'); ?>
                                                <input type="hidden" name="vale_id" value="<?= esc_attr($vale->id) ?>">
                                                <button type="submit" name="gofast_eliminar_vale_empresa" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
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

        <!-- Modal para editar vale empresa -->
        <div id="modal-editar-vale-empresa" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Vale de la Empresa</h2>
                
                <form method="post" id="form-editar-vale-empresa">
                    <?php wp_nonce_field('gofast_editar_vale_empresa', 'gofast_editar_vale_empresa_nonce'); ?>
                    <input type="hidden" name="vale_id" id="editar-vale-empresa-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-vale-empresa-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-vale-empresa-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-vale-empresa-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarValeEmpresa()">Cancelar</button>
                        <button type="submit" name="gofast_editar_vale_empresa" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar vale empresa
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-vale-empresa').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const valeId = this.getAttribute('data-vale-id');
                    const valeFecha = this.getAttribute('data-vale-fecha');
                    const valeDescripcion = this.getAttribute('data-vale-descripcion');
                    const valeValor = this.getAttribute('data-vale-valor');
                    
                    document.getElementById('editar-vale-empresa-id').value = valeId;
                    document.getElementById('editar-vale-empresa-fecha').value = valeFecha;
                    document.getElementById('editar-vale-empresa-descripcion').value = valeDescripcion;
                    document.getElementById('editar-vale-empresa-valor').value = valeValor;
                    
                    document.getElementById('modal-editar-vale-empresa').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarValeEmpresa = document.getElementById('modal-editar-vale-empresa');
            if (modalEditarValeEmpresa) {
                modalEditarValeEmpresa.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarValeEmpresa();
                    }
                });
            }
        });
        
        function cerrarModalEditarValeEmpresa() {
            document.getElementById('modal-editar-vale-empresa').style.display = 'none';
            document.getElementById('form-editar-vale-empresa').reset();
        }
        </script>

        <!-- TAB: VALES PERSONAL -->
        <div id="tab-vales_personal" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'vales_personal' ? 'block' : 'none' ?>;">
            <h3>👥 Vales del Personal</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los vales del personal (4 personas activas). Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear vale personal -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Vale del Personal</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_vale_personal', 'gofast_vale_personal_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Persona <span style="color: #dc3545;">*</span></label>
                            <select name="persona_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">Seleccione una persona</option>
                                <?php foreach ($personas_activas as $persona): ?>
                                    <option value="<?= (int) $persona->id; ?>"><?= esc_html($persona->nombre); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Vale para viáticos"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_vale_personal" class="gofast-btn-mini">✅ Crear Vale</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion_vales_personal', 'filtro_persona'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por persona</label>
                        <select name="filtro_persona" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todas las personas</option>
                            <?php foreach ($personas_activas as $persona): ?>
                                <option value="<?= (int) $persona->id; ?>"<?php selected($filtro_persona, $persona->id); ?>>
                                    <?= esc_html($persona->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_vales_personal" 
                               value="<?= esc_attr($filtro_descripcion_vales_personal) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_persona > 0 || !empty($filtro_descripcion_vales_personal)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg(['filtro_persona', 'filtro_descripcion_vales_personal'])) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de vales personal -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Vales del Personal</h4>
                
                <?php if (empty($vales_personal)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay vales de personal registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Persona</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vales_personal as $vale): ?>
                                    <tr>
                                        <td>#<?= esc_html($vale->id) ?></td>
                                        <td><?= gofast_date_format($vale->fecha, 'd/m/Y') ?></td>
                                        <td><strong><?= esc_html($vale->persona_nombre ?? '—') ?></strong></td>
                                        <td><?= esc_html($vale->descripcion) ?></td>
                                        <td><strong>$<?= number_format($vale->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($vale->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-vale-personal" 
                                                    data-vale-id="<?= esc_attr($vale->id) ?>"
                                                    data-vale-fecha="<?= esc_attr($vale->fecha) ?>"
                                                    data-vale-persona-id="<?= esc_attr($vale->persona_id) ?>"
                                                    data-vale-descripcion="<?= esc_attr($vale->descripcion) ?>"
                                                    data-vale-valor="<?= esc_attr($vale->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este vale? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_vale_personal', 'gofast_eliminar_vale_personal_nonce'); ?>
                                                <input type="hidden" name="vale_id" value="<?= esc_attr($vale->id) ?>">
                                                <button type="submit" name="gofast_eliminar_vale_personal" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
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

        <!-- Modal para editar vale personal -->
        <div id="modal-editar-vale-personal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Vale del Personal</h2>
                
                <form method="post" id="form-editar-vale-personal">
                    <?php wp_nonce_field('gofast_editar_vale_personal', 'gofast_editar_vale_personal_nonce'); ?>
                    <input type="hidden" name="vale_id" id="editar-vale-personal-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-vale-personal-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Persona:</label>
                        <select name="persona_id" id="editar-vale-personal-persona-id" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                            <option value="">Seleccione una persona</option>
                            <?php foreach ($personas_activas as $persona): ?>
                                <option value="<?= (int) $persona->id; ?>"><?= esc_html($persona->nombre); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-vale-personal-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-vale-personal-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarValePersonal()">Cancelar</button>
                        <button type="submit" name="gofast_editar_vale_personal" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar vale personal
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-vale-personal').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const valeId = this.getAttribute('data-vale-id');
                    const valeFecha = this.getAttribute('data-vale-fecha');
                    const valePersonaId = this.getAttribute('data-vale-persona-id');
                    const valeDescripcion = this.getAttribute('data-vale-descripcion');
                    const valeValor = this.getAttribute('data-vale-valor');
                    
                    document.getElementById('editar-vale-personal-id').value = valeId;
                    document.getElementById('editar-vale-personal-fecha').value = valeFecha;
                    document.getElementById('editar-vale-personal-persona-id').value = valePersonaId;
                    document.getElementById('editar-vale-personal-descripcion').value = valeDescripcion;
                    document.getElementById('editar-vale-personal-valor').value = valeValor;
                    
                    document.getElementById('modal-editar-vale-personal').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarValePersonal = document.getElementById('modal-editar-vale-personal');
            if (modalEditarValePersonal) {
                modalEditarValePersonal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarValePersonal();
                    }
                });
            }
        });
        
        function cerrarModalEditarValePersonal() {
            document.getElementById('modal-editar-vale-personal').style.display = 'none';
            document.getElementById('form-editar-vale-personal').reset();
        }
        </script>

        <!-- TAB: TRANSFERENCIAS ENTRADAS -->
        <div id="tab-transferencias_entradas" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'transferencias_entradas' ? 'block' : 'none' ?>;">
            <h3>📥 Transferencias (Entradas)</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Visualiza las transferencias entrantes desde los mensajeros (transferencias aprobadas). Los datos se cargan automáticamente desde el sistema de transferencias.
            </p>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_mensajero', 'filtro_origen'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label>Mensajero</label>
                        <select name="filtro_mensajero" id="filtro-mensajero-entradas" class="gofast-select-filtro" data-placeholder="Todos los mensajeros">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_mensajero > 0): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_mensajero')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Resumen por día -->
            <?php if (!empty($transferencias_por_dia)): ?>
                <div class="gofast-box" style="background: #e7f3ff; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">📊 Resumen por Día</h4>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cantidad</th>
                                    <th>Total del Día</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_por_dia as $dia): ?>
                                    <tr>
                                        <td><?= gofast_date_format($dia['fecha'], 'd/m/Y') ?></td>
                                        <td><?= $dia['cantidad'] ?> transferencia(s)</td>
                                        <td><strong>$<?= number_format($dia['total'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Listado detallado de transferencias -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado Detallado de Transferencias</h4>
                
                <?php if (empty($transferencias_entradas)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay transferencias aprobadas en el rango de fechas seleccionado.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Mensajero</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_entradas as $transf): ?>
                                    <tr>
                                        <td>#<?= esc_html($transf->id) ?></td>
                                        <td><?= gofast_date_format($transf->fecha_creacion, 'd/m/Y H:i') ?></td>
                                        <td>
                                            <?= esc_html($transf->mensajero_nombre) ?><br>
                                            <small style="color: #666;"><?= esc_html($transf->mensajero_telefono) ?></small>
                                        </td>
                                        <td><strong>$<?= number_format($transf->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($transf->creador_nombre ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: 700;">
                                    <td colspan="3" style="text-align: right;">Total:</td>
                                    <td>$<?= number_format(array_sum(array_column($transferencias_entradas, 'valor')), 0, ',', '.') ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: TRANSFERENCIAS SALIDAS -->
        <div id="tab-transferencias_salidas" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'transferencias_salidas' ? 'block' : 'none' ?>;">
            <h3>📤 Transferencias (Salidas)</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona las transferencias salientes de la empresa. Puedes crear, editar y eliminar registros.
            </p>
            <p style="background: #fff3cd; padding: 12px; border-radius: 6px; color: #856404; margin-bottom: 16px;">
                ⚠️ Esta funcionalidad estará disponible próximamente.
            </p>
        </div>

        <!-- TAB: SALDOS MENSAJEROS -->
        <div id="tab-saldos_mensajeros" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'saldos_mensajeros' ? 'block' : 'none' ?>;">
            <h3>💵 Saldos Mensajeros</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los pagos a mensajeros, descuentos y estados de pago (efectivo, transferencia, pendiente).
            </p>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_mensajero_saldos', 'filtro_estado_saldos'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label>Mensajero</label>
                        <select name="filtro_mensajero_saldos" id="filtro-mensajero-saldos" class="gofast-select-filtro" data-placeholder="Todos los mensajeros">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero_saldos, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por estado</label>
                        <select name="filtro_estado_saldos" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?= $filtro_estado_saldos === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="efectivo" <?= $filtro_estado_saldos === 'efectivo' ? 'selected' : '' ?>>Pago en Efectivo</option>
                            <option value="transferencia" <?= $filtro_estado_saldos === 'transferencia' ? 'selected' : '' ?>>Pago por Transferencia</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_mensajero_saldos > 0 || !empty($filtro_estado_saldos)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg(['filtro_mensajero_saldos', 'filtro_estado_saldos'])) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de saldos por mensajero -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Saldos por Mensajero</h4>
                
                <?php if (empty($saldos_mensajeros)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay datos de mensajeros en el rango de fechas seleccionado.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>Mensajero</th>
                                    <th>📍 Total Destinos</th>
                                    <th>🛒 Total Compras</th>
                                    <th>💰 Ingresos Totales</th>
                                    <th>💵 Comisión (20%)</th>
                                    <th>📈 Utilidad Neta</th>
                                    <th>💸 Transferencias Aprobadas</th>
                                    <th>💳 Total a Pagar</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saldos_mensajeros as $saldo): ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc_html($saldo->mensajero_nombre) ?></strong><br>
                                            <small style="color: #666;"><?= esc_html($saldo->mensajero_telefono) ?></small>
                                        </td>
                                        <td style="text-align: center;"><?= number_format($saldo->total_destinos) ?></td>
                                        <td style="text-align: center;"><?= number_format($saldo->total_compras) ?></td>
                                        <td style="text-align: right;"><strong>$<?= number_format($saldo->ingresos_totales, 0, ',', '.') ?></strong></td>
                                        <td style="text-align: right;"><strong>$<?= number_format($saldo->comision_generada, 0, ',', '.') ?></strong></td>
                                        <td style="text-align: right;"><strong>$<?= number_format($saldo->utilidad_neta, 0, ',', '.') ?></strong></td>
                                        <td style="text-align: right;">$<?= number_format($saldo->transferencias_aprobadas, 0, ',', '.') ?></td>
                                        <td style="text-align: right;">
                                            <strong style="color: <?= $saldo->total_a_pagar >= 0 ? '#4CAF50' : '#f44336'; ?>; font-size: 16px;">
                                                $<?= number_format($saldo->total_a_pagar, 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini btn-pago-efectivo" 
                                                    style="background: #28a745; color: #fff; margin-right: 4px;"
                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>"
                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                    data-comision="<?= number_format((float)($saldo->comision_generada ?? 0), 2, '.', '') ?>"
                                                    data-transferencias="<?= number_format((float)($saldo->transferencias_aprobadas ?? 0), 2, '.', '') ?>"
                                                    data-descuentos="<?= number_format((float)($saldo->total_descuentos ?? 0), 2, '.', '') ?>"
                                                    data-total="<?= number_format((float)($saldo->total_a_pagar ?? 0), 2, '.', '') ?>">
                                                💵 Pago Efectivo
                                            </button>
                                            <button type="button" 
                                                    class="gofast-btn-mini btn-pago-transferencia" 
                                                    style="background: #2196F3; color: #fff; margin-right: 4px;"
                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>"
                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                    data-comision="<?= number_format((float)($saldo->comision_generada ?? 0), 2, '.', '') ?>"
                                                    data-transferencias="<?= number_format((float)($saldo->transferencias_aprobadas ?? 0), 2, '.', '') ?>"
                                                    data-descuentos="<?= number_format((float)($saldo->total_descuentos ?? 0), 2, '.', '') ?>"
                                                    data-total="<?= number_format((float)($saldo->total_a_pagar ?? 0), 2, '.', '') ?>">
                                                💸 Pago Transferencia
                                            </button>
                                            <button type="button" class="gofast-btn-mini" style="background: var(--gofast-yellow); color: #000;">
                                                ➖ Generar Descuento
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal para registrar pago -->
<div id="modal-registrar-pago" class="gofast-modal" style="display: none;">
    <div class="gofast-modal-content" style="max-width: 500px;">
        <div class="gofast-modal-header">
            <h3 id="modal-pago-titulo">Registrar Pago</h3>
            <span class="gofast-modal-close" onclick="cerrarModalPago()">&times;</span>
        </div>
        <form id="form-registrar-pago" method="post">
            <?php wp_nonce_field('gofast_registrar_pago_mensajero', 'gofast_pago_mensajero_nonce'); ?>
            <input type="hidden" name="gofast_registrar_pago_mensajero" value="1">
            <input type="hidden" name="mensajero_id" id="pago-mensajero-id">
            <input type="hidden" name="tipo_pago" id="pago-tipo">
            <input type="hidden" name="comision_total" id="pago-comision">
            <input type="hidden" name="transferencias_total" id="pago-transferencias">
            <input type="hidden" name="descuentos_total" id="pago-descuentos">
            <input type="hidden" name="total_a_pagar" id="pago-total">
            
            <div class="gofast-modal-body">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Mensajero</label>
                    <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; font-weight: 600;" id="pago-mensajero-nombre"></div>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha del Pago *</label>
                    <input type="date" name="fecha" id="pago-fecha" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;" value="<?= gofast_current_time('Y-m-d') ?>">
                </div>
                
                <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #666;">Comisión Generada:</span>
                        <strong id="pago-comision-display">$0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #666;">Transferencias Aprobadas:</span>
                        <strong id="pago-transferencias-display">$0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #666;">Descuentos:</span>
                        <strong id="pago-descuentos-display">$0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid #ddd;">
                        <span style="font-weight: 600; font-size: 16px;">Total a Pagar:</span>
                        <strong id="pago-total-display" style="font-size: 18px; color: #4CAF50;">$0</strong>
                    </div>
                </div>
                
                <div style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                    <small style="color: #856404;">
                        ⚠️ Al registrar el pago, se guardará un registro con los valores calculados para el período seleccionado.
                    </small>
                </div>
            </div>
            
            <div class="gofast-modal-footer">
                <button type="button" class="gofast-btn gofast-secondary" onclick="cerrarModalPago()">Cancelar</button>
                <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow);">💾 Registrar Pago</button>
            </div>
        </form>
    </div>
</div>

<script>
// Función para abrir modal de pago
function abrirModalPago(tipoPago, mensajeroId, mensajeroNombre, comision, transferencias, descuentos, total) {
    document.getElementById('pago-mensajero-id').value = mensajeroId || '';
    document.getElementById('pago-tipo').value = tipoPago || '';
    document.getElementById('pago-comision').value = comision || '0';
    document.getElementById('pago-transferencias').value = transferencias || '0';
    document.getElementById('pago-descuentos').value = descuentos || '0';
    document.getElementById('pago-total').value = total || '0';
    
    document.getElementById('pago-mensajero-nombre').textContent = mensajeroNombre || '';
    
    // Asegurar que los valores sean numéricos antes de usar toLocaleString
    var comisionNum = parseFloat(comision || 0);
    var transferenciasNum = parseFloat(transferencias || 0);
    var descuentosNum = parseFloat(descuentos || 0);
    var totalNum = parseFloat(total || 0);
    
    // Verificar que no sean NaN antes de formatear
    if (isNaN(comisionNum)) comisionNum = 0;
    if (isNaN(transferenciasNum)) transferenciasNum = 0;
    if (isNaN(descuentosNum)) descuentosNum = 0;
    if (isNaN(totalNum)) totalNum = 0;
    
    document.getElementById('pago-comision-display').textContent = '$' + comisionNum.toLocaleString('es-CO');
    document.getElementById('pago-transferencias-display').textContent = '$' + transferenciasNum.toLocaleString('es-CO');
    document.getElementById('pago-descuentos-display').textContent = '$' + descuentosNum.toLocaleString('es-CO');
    document.getElementById('pago-total-display').textContent = '$' + totalNum.toLocaleString('es-CO');
    
    document.getElementById('modal-pago-titulo').textContent = tipoPago === 'efectivo' ? 'Registrar Pago en Efectivo' : 'Registrar Pago por Transferencia';
    document.getElementById('modal-registrar-pago').style.display = 'block';
}

// Función para cerrar modal de pago
function cerrarModalPago() {
    document.getElementById('modal-registrar-pago').style.display = 'none';
    document.getElementById('form-registrar-pago').reset();
}

// Función para inicializar Select2 en todos los filtros (igual que en reportes y pedidos)
function inicializarSelect2Filtros() {
    if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
        jQuery('.gofast-select-filtro').each(function() {
            if (jQuery(this).data('select2')) {
                return;
            }
            
            jQuery(this).select2({
                placeholder: function() {
                    return jQuery(this).data('placeholder') || '🔍 Escribe para buscar...';
                },
                width: '100%',
                allowClear: false,
                minimumResultsForSearch: 0,
                matcher: window.matcherDestinos,
                sorter: function(results) {
                    return results.sort(function(a, b) {
                        return (b.matchScore || 0) - (a.matchScore || 0);
                    });
                },
                templateResult: function(data, container) {
                    if (!data || !data.text) {
                        return data ? data.text : '';
                    }
                    
                    if (!data.id) return data.text;
                    
                    let originalText = data.text;
                    let searchTerm = "";
                    const $activeField = jQuery('.select2-container--open .select2-search__field');
                    if ($activeField.length) {
                        searchTerm = $activeField.val() || "";
                    }
                    
                    if (!searchTerm || !searchTerm.trim()) {
                        const $result = jQuery('<span>').text(originalText);
                        if (data.matchScore !== undefined) {
                            $result.attr('data-match-score', data.matchScore);
                        }
                        return $result;
                    }
                    
                    const normalizedSearch = window.normalize(searchTerm);
                    const normalizedText = window.normalize(originalText);
                    const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                    const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                        return word.length > 2 && !stopWords.includes(word);
                    });
                    const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                    const highlightRanges = [];
                    
                    wordsToHighlight.forEach(function(word) {
                        let searchPos = 0;
                        while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                            const endPos = searchPos + word.length;
                            let origStart = -1;
                            let origEnd = -1;
                            let normPos = 0;
                            
                            for (let i = 0; i < originalText.length && origStart === -1; i++) {
                                const charNorm = window.normalize(originalText[i]);
                                if (normPos === searchPos) {
                                    origStart = i;
                                }
                                normPos += charNorm.length;
                            }
                            
                            if (origStart >= 0) {
                                normPos = searchPos;
                                for (let i = origStart; i < originalText.length; i++) {
                                    const charNorm = window.normalize(originalText[i]);
                                    normPos += charNorm.length;
                                    if (normPos >= endPos) {
                                        origEnd = i + 1;
                                        break;
                                    }
                                }
                                
                                if (origStart >= 0 && origEnd > origStart) {
                                    highlightRanges.push({ start: origStart, end: origEnd });
                                }
                            }
                            
                            searchPos = endPos;
                        }
                    });
                    
                    if (highlightRanges.length > 0) {
                        highlightRanges.sort((a, b) => a.start - b.start);
                        const mergedRanges = [highlightRanges[0]];
                        
                        for (let i = 1; i < highlightRanges.length; i++) {
                            const current = highlightRanges[i];
                            const last = mergedRanges[mergedRanges.length - 1];
                            
                            if (current.start <= last.end) {
                                last.end = Math.max(last.end, current.end);
                            } else {
                                mergedRanges.push(current);
                            }
                        }
                        
                        const parts = [];
                        let lastIndex = 0;
                        
                        mergedRanges.forEach(function(range) {
                            if (range.start > lastIndex) {
                                parts.push(originalText.substring(lastIndex, range.start));
                            }
                            
                            const matchText = originalText.substring(range.start, range.end);
                            parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                       matchText + '</span>');
                            
                            lastIndex = range.end;
                        });
                        
                        if (lastIndex < originalText.length) {
                            parts.push(originalText.substring(lastIndex));
                        }
                        
                        const result = parts.join('');
                        const $result = jQuery('<span>').html(result);
                        if (data.matchScore !== undefined) {
                            $result.attr('data-match-score', data.matchScore);
                        }
                        return $result;
                    }
                    
                    const $result = jQuery('<span>').text(originalText);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
            }).on('select2:open', function(e) {
                setTimeout(function() {
                    const $dropdown = jQuery('.select2-dropdown');
                    const $searchContainer = $dropdown.find('.select2-search--dropdown');
                    const $searchField = $searchContainer.find('.select2-search__field');
                    
                    if ($searchContainer.length) {
                        $searchContainer.css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                    }
                    
                    if ($searchField.length) {
                        $searchField.css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                        
                        setTimeout(function() {
                            $searchField.focus();
                        }, 100);
                    }
                }, 50);
            });
        });
        
        // Ocultar select original cuando Select2 está activo
        jQuery('.gofast-select-filtro').each(function() {
            if (jQuery(this).data('select2')) {
                jQuery(this).css({
                    'visibility': 'hidden',
                    'position': 'absolute',
                    'width': '1px',
                    'height': '1px',
                    'opacity': '0',
                    'pointer-events': 'none'
                });
            }
        });
    } else {
        // Reintentar después de un breve delay
        setTimeout(function() {
            if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
                inicializarSelect2Filtros();
            }
        }, 500);
    }
}

    // Asegurar que al enviar el formulario de filtros, se preserve el tab activo
    document.addEventListener('DOMContentLoaded', function() {
        const formFiltros = document.getElementById('form-filtros-generales');
        if (formFiltros) {
            formFiltros.addEventListener('submit', function() {
                // El tab ya está en el input hidden, pero nos aseguramos de que esté actualizado
                const urlParams = new URLSearchParams(window.location.search);
                const tabActivo = urlParams.get('tab') || 'ingresos';
                const filtroTabActivo = document.getElementById('filtro-tab-activo');
                if (filtroTabActivo) {
                    filtroTabActivo.value = tabActivo;
                }
            });
        }
        
        // Los tabs ahora usan onclick directamente en el HTML, no necesitan event listeners adicionales
        
        // Agregar event listeners a los botones de pago
        document.querySelectorAll('.btn-pago-efectivo').forEach(function(btn) {
            btn.addEventListener('click', function() {
                abrirModalPago(
                    'efectivo',
                    this.getAttribute('data-mensajero-id'),
                    this.getAttribute('data-mensajero-nombre'),
                    this.getAttribute('data-comision'),
                    this.getAttribute('data-transferencias'),
                    this.getAttribute('data-descuentos'),
                    this.getAttribute('data-total')
                );
            });
        });
        
        document.querySelectorAll('.btn-pago-transferencia').forEach(function(btn) {
            btn.addEventListener('click', function() {
                abrirModalPago(
                    'transferencia',
                    this.getAttribute('data-mensajero-id'),
                    this.getAttribute('data-mensajero-nombre'),
                    this.getAttribute('data-comision'),
                    this.getAttribute('data-transferencias'),
                    this.getAttribute('data-descuentos'),
                    this.getAttribute('data-total')
                );
            });
        });
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modal-registrar-pago').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalPago();
            }
        });
        
        // Inicializar Select2 para todos los filtros con clase gofast-select-filtro (igual que en reportes y pedidos)
        inicializarSelect2Filtros();
        
        // Reintentar después de un breve delay si no se inicializó
        setTimeout(function() {
            inicializarSelect2Filtros();
        }, 500);
    });
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_finanzas_admin', 'gofast_finanzas_admin_shortcode');

