/***************************************************
 * GOFAST – ADMIN GESTIÓN DE USUARIOS
 * Shortcode: [gofast_usuarios_admin]
 * URL: /admin-usuarios
 ***************************************************/
function gofast_usuarios_admin_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    $tabla = 'usuarios_gofast';
    $mensaje = '';
    $debug_info = []; // Para debugging

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
                    ⚠️ Solo los administradores pueden gestionar usuarios.
                </div>";
    }

    /* ==========================================================
       1. Procesar POST (crear, editar, eliminar)
    ========================================================== */
    // Log SIEMPRE para ver qué se está recibiendo
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("GOFAST ADMIN - POST recibido. Keys: " . implode(', ', array_keys($_POST)));
        error_log("GOFAST ADMIN - gofast_crear_usuario: " . ($_POST['gofast_crear_usuario'] ?? 'NO PRESENTE'));
        error_log("GOFAST ADMIN - gofast_guardar_usuarios: " . ($_POST['gofast_guardar_usuarios'] ?? 'NO PRESENTE'));
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        /* ----------------------------------------------
           A) Crear nuevo usuario (COPIA EXACTA DE AUTH)
        ---------------------------------------------- */
        // Verificar que sea el botón de CREAR, no el de GUARDAR
        if (!empty($_POST['gofast_crear_usuario'])) {
            
            // Verificar nonce de seguridad
            if (
                empty($_POST['gofast_usuarios_nonce']) ||
                !wp_verify_nonce($_POST['gofast_usuarios_nonce'], 'gofast_usuarios_admin')
            ) {
                $mensaje = "🔒 Error de seguridad al crear usuario.";
                error_log("GOFAST ADMIN - Error: nonce no válido o faltante");
            } else {
            
                // Log para confirmar que se detectó el botón correcto
                error_log("GOFAST ADMIN - ✅ Botón CREAR USUARIO detectado correctamente");
                error_log("GOFAST ADMIN - gofast_crear_usuario: " . ($_POST['gofast_crear_usuario'] ?? 'NO'));
                error_log("GOFAST ADMIN - gofast_guardar_usuarios: " . ($_POST['gofast_guardar_usuarios'] ?? 'NO'));
                
                // Obtener datos del POST
                $nombre    = trim($_POST['nuevo_nombre'] ?? '');
                $telefono  = trim($_POST['nuevo_telefono'] ?? '');
                $email     = trim($_POST['nuevo_email'] ?? '');
                $password  = $_POST['nuevo_password'] ?? '';
                $rol       = sanitize_text_field($_POST['nuevo_rol'] ?? 'cliente');
                $activo    = !empty($_POST['nuevo_activo']) ? 1 : 0;
                
                // Log inicial para debugging
                error_log("GOFAST ADMIN - Intentando crear usuario. POST recibido: " . print_r($_POST, true));
            
            // Validaciones (igual que auth)
            if ($nombre === '' || $telefono === '' || $email === '' || $password === '') {
                $mensaje = "⚠️ Todos los campos son obligatorios.";
                error_log("GOFAST ADMIN - Validación falló: campos vacíos");
            } elseif (!is_email($email)) {
                $mensaje = "⚠️ El correo no es válido.";
                error_log("GOFAST ADMIN - Validación falló: email inválido: " . $email);
            } elseif (strlen($password) < 6) {
                $mensaje = "⚠️ La contraseña debe tener al menos 6 caracteres.";
                error_log("GOFAST ADMIN - Validación falló: contraseña muy corta");
            } elseif (!in_array($rol, ['cliente', 'mensajero', 'admin'], true)) {
                $mensaje = "⚠️ Rol no válido.";
                error_log("GOFAST ADMIN - Validación falló: rol inválido: " . $rol);
            } else {
                // Normalizar y validar teléfono (igual que auth)
                $tel_norm = preg_replace('/\D+/', '', $telefono);
                if (strlen($tel_norm) < 10) {
                    $mensaje = "⚠️ El teléfono debe tener al menos 10 dígitos.";
                    error_log("GOFAST ADMIN - Validación falló: teléfono muy corto: " . $tel_norm);
                } else {
                    // Verificar duplicados (igual que auth)
                    $sql = "
                        SELECT id
                        FROM $tabla
                        WHERE email = %s
                           OR REPLACE(REPLACE(REPLACE(telefono, '+',''), ' ','') ,'-','') = %s
                        LIMIT 1
                    ";
                    $existe = $wpdb->get_row($wpdb->prepare($sql, $email, $tel_norm));

                    if ($existe) {
                        $mensaje = "⚠️ Ya existe un usuario con ese correo o teléfono (ID: #{$existe->id}).";
                        error_log("GOFAST ADMIN - Usuario duplicado detectado. ID existente: " . $existe->id);
                    } else {
                        // Hash de contraseña (igual que auth)
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        if (!$hash) {
                            $mensaje = "❌ Error al generar hash de contraseña.";
                            error_log("GOFAST ADMIN - Error al generar hash de contraseña");
                        } else {
                            // Preparar datos para inserción (igual que auth)
                            $datos_insert = [
                                'nombre'         => $nombre,
                                'telefono'       => $telefono,
                                'email'          => $email,
                                'password_hash'  => $hash,
                                'rol'            => $rol,
                                'activo'         => $activo,
                                'fecha_registro' => gofast_current_time('mysql')
                            ];
                            
                            // Verificar si el campo remember_token existe (igual que auth)
                            $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM $tabla LIKE 'remember_token'");
                            if (!empty($tabla_info)) {
                                $datos_insert['remember_token'] = null;
                            }
                            
                            // Preparar formatos (igual que auth)
                            $formatos = ['%s','%s','%s','%s','%s','%d','%s'];
                            if (!empty($tabla_info)) {
                                $formatos[] = '%s'; // remember_token
                            }

                            // Inserción (igual que auth - EXACTAMENTE IGUAL)
                            error_log("GOFAST ADMIN - Intentando insertar usuario. Datos: " . print_r($datos_insert, true));
                            error_log("GOFAST ADMIN - Formatos: " . print_r($formatos, true));
                            
                            $ok = $wpdb->insert($tabla, $datos_insert, $formatos);
                            
                            error_log("GOFAST ADMIN - Resultado insert: " . var_export($ok, true));
                            error_log("GOFAST ADMIN - Insert ID: " . $wpdb->insert_id);
                            error_log("GOFAST ADMIN - Last Error: " . ($wpdb->last_error ?: 'NINGUNO'));
                            error_log("GOFAST ADMIN - Last Query: " . $wpdb->last_query);

                            if (!$ok) {
                                $error_db = $wpdb->last_error;
                                $mensaje = "❌ Error al crear usuario en la base de datos.";
                                
                                // Log detallado SIEMPRE
                                error_log("GOFAST ADMIN - ERROR al crear usuario: " . ($error_db ?: 'SIN ERROR PERO FALSE'));
                                error_log("GOFAST ADMIN - Datos intentados: " . print_r($datos_insert, true));
                                error_log("GOFAST ADMIN - Formatos: " . print_r($formatos, true));
                                error_log("GOFAST ADMIN - SQL: " . $wpdb->last_query);
                                
                                // Agregar más detalles al mensaje
                                if ($error_db) {
                                    $mensaje .= " Detalle: " . esc_html($error_db);
                                } else {
                                    $mensaje .= " (Ver logs para más detalles)";
                                }
                            } else {
                                $user_id = (int) $wpdb->insert_id;
                                
                                if ($user_id > 0) {
                                    $mensaje = "✅ Usuario creado correctamente (ID: #{$user_id}).";
                                    
                                    error_log("GOFAST ADMIN - ✅ Usuario creado exitosamente. ID: {$user_id}");
                                    
                                    // Redirigir para evitar reenvío del formulario
                                    wp_redirect(add_query_arg(['usuario_creado' => '1'], get_permalink()));
                                    exit;
                                } else {
                                    $mensaje = "❌ Error: Usuario no se creó (insert_id = 0). Ver logs.";
                                    error_log("GOFAST ADMIN - ERROR: Insert retornó true pero insert_id es 0");
                                }
                            }
                        }
                    }
                }
            }
            } // Cierre del else del nonce
        }

        /* ----------------------------------------------
           B) Actualizar usuario individual
        ---------------------------------------------- */
        if (!empty($_POST['gofast_actualizar_usuario']) && is_array($_POST['gofast_actualizar_usuario'])) {
            $user_id_actualizar = key($_POST['gofast_actualizar_usuario']);
            $user_id_actualizar = (int) $user_id_actualizar;
            
            if ($user_id_actualizar > 0) {
                if (
                    empty($_POST['gofast_usuarios_nonce']) ||
                    !wp_verify_nonce($_POST['gofast_usuarios_nonce'], 'gofast_usuarios_admin')
                ) {
                    $mensaje = "🔒 Error de seguridad al actualizar usuario.";
                } else {
                    error_log("GOFAST ADMIN - Actualización individual. User ID: " . $user_id_actualizar);
                    error_log("GOFAST ADMIN - POST['usuarios'] completo: " . print_r($_POST['usuarios'] ?? 'NO EXISTE', true));
                    
                    if (!empty($_POST['usuarios'][$user_id_actualizar]) && is_array($_POST['usuarios'][$user_id_actualizar])) {
                        $data = $_POST['usuarios'][$user_id_actualizar];
                        
                        error_log("GOFAST ADMIN - Datos recibidos para usuario #{$user_id_actualizar}: " . print_r($data, true));
                        
                        $nombre   = sanitize_text_field($data['nombre'] ?? '');
                        $telefono = sanitize_text_field($data['telefono'] ?? '');
                        $email    = sanitize_email($data['email'] ?? '');
                        $rol      = sanitize_text_field($data['rol'] ?? 'cliente');
                        $activo   = !empty($data['activo']) ? 1 : 0;
                        $password = $data['password'] ?? '';
                        
                        error_log("GOFAST ADMIN - Datos procesados - nombre: '{$nombre}', email: '{$email}', rol: '{$rol}', activo: {$activo}");

                        if (!in_array($rol, ['cliente', 'mensajero', 'admin'], true)) {
                            $mensaje = "⚠️ Rol no válido.";
                        } else {
                            // Obtener datos actuales para comparar
                            $usuario_actual = $wpdb->get_row($wpdb->prepare("SELECT nombre, telefono, email, rol, activo FROM $tabla WHERE id = %d", $user_id_actualizar));
                            
                            if (!$usuario_actual) {
                                $mensaje = "⚠️ Usuario no encontrado.";
                            } else {
                                // Verificar si hay cambios reales
                                $hay_cambios = false;
                                if ($usuario_actual->nombre !== $nombre ||
                                    $usuario_actual->telefono !== $telefono ||
                                    $usuario_actual->email !== $email ||
                                    $usuario_actual->rol !== $rol ||
                                    $usuario_actual->activo != $activo ||
                                    $password !== '') {
                                    $hay_cambios = true;
                                }
                                
                                if (!$hay_cambios) {
                                    $mensaje = "ℹ️ No se detectaron cambios en el usuario.";
                                } else {
                                    $update_data = [
                                        'nombre'   => $nombre,
                                        'telefono' => $telefono,
                                        'email'    => $email,
                                        'rol'      => $rol,
                                        'activo'   => $activo
                                    ];

                                    if ($password !== '') {
                                        $update_data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                                    }

                                    $resultado = $wpdb->update(
                                        $tabla,
                                        $update_data,
                                        ['id' => $user_id_actualizar],
                                        $password !== '' ? ['%s','%s','%s','%s','%d','%s'] : ['%s','%s','%s','%s','%d'],
                                        ['%d']
                                    );

                                    if ($resultado === false) {
                                        $error_db = $wpdb->last_error;
                                        $mensaje = "❌ Error al actualizar usuario: " . ($error_db ?: 'Error desconocido');
                                    } else {
                                        // Si resultado es 0 pero sabemos que hay cambios, puede ser un problema de comparación
                                        // En este caso, consideramos que se actualizó correctamente
                                        $mensaje = "✅ Usuario actualizado correctamente.";
                                    }
                                }
                            }
                        }
                    } else {
                        error_log("GOFAST ADMIN - ⚠️ No se recibieron datos del usuario #{$user_id_actualizar}");
                        error_log("GOFAST ADMIN - POST['usuarios'] existe: " . (isset($_POST['usuarios']) ? 'SÍ' : 'NO'));
                        error_log("GOFAST ADMIN - POST['usuarios'] es array: " . (is_array($_POST['usuarios'] ?? null) ? 'SÍ' : 'NO'));
                        if (isset($_POST['usuarios'])) {
                            error_log("GOFAST ADMIN - Keys en POST['usuarios']: " . implode(', ', array_keys($_POST['usuarios'])));
                        }
                        $mensaje = "⚠️ No se recibieron datos del usuario.";
                    }
                }
            }
        }

        /* ----------------------------------------------
           C) Editar usuarios existentes (actualización masiva - verificar nonce específico)
        ---------------------------------------------- */
        // Verificar que sea el botón de GUARDAR, no el de CREAR
        if (!empty($_POST['gofast_guardar_usuarios']) && empty($_POST['gofast_crear_usuario']) && empty($_POST['gofast_actualizar_usuario'])) {
            
            // Log para confirmar que se detectó el botón correcto
            error_log("GOFAST ADMIN - ✅ Botón GUARDAR CAMBIOS detectado correctamente");
            error_log("GOFAST ADMIN - gofast_guardar_usuarios: " . ($_POST['gofast_guardar_usuarios'] ?? 'NO'));
            error_log("GOFAST ADMIN - gofast_crear_usuario: " . ($_POST['gofast_crear_usuario'] ?? 'NO'));

            if (
                empty($_POST['gofast_usuarios_nonce']) ||
                !wp_verify_nonce($_POST['gofast_usuarios_nonce'], 'gofast_usuarios_admin')
            ) {
                $mensaje = "🔒 Error de seguridad al guardar cambios.";
            } else {

                // Actualizar usuarios
                if (!empty($_POST['usuarios']) && is_array($_POST['usuarios'])) {
                    foreach ($_POST['usuarios'] as $user_id => $data) {
                        $user_id = (int) $user_id;
                        if ($user_id <= 0) continue;

                        $nombre   = sanitize_text_field($data['nombre'] ?? '');
                        $telefono = sanitize_text_field($data['telefono'] ?? '');
                        $email    = sanitize_email($data['email'] ?? '');
                        $rol      = sanitize_text_field($data['rol'] ?? 'cliente');
                        $activo   = !empty($data['activo']) ? 1 : 0;
                        $password = $data['password'] ?? '';

                        if (!in_array($rol, ['cliente', 'mensajero', 'admin'], true)) {
                            continue;
                        }

                        $update_data = [
                            'nombre'   => $nombre,
                            'telefono' => $telefono,
                            'email'    => $email,
                            'rol'      => $rol,
                            'activo'   => $activo
                        ];

                        // Si hay nueva contraseña, actualizarla
                        if ($password !== '') {
                            $update_data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                        }

                        $wpdb->update(
                            $tabla,
                            $update_data,
                            ['id' => $user_id],
                            $password !== '' ? ['%s','%s','%s','%s','%d','%s'] : ['%s','%s','%s','%s','%d'],
                            ['%d']
                        );
                    }
                }

                // Desactivar/Activar usuarios
                if (!empty($_POST['desactivar_usuario']) && is_array($_POST['desactivar_usuario'])) {
                    foreach ($_POST['desactivar_usuario'] as $user_id => $flag) {
                        if ($flag != '1') continue;
                        $user_id = (int) $user_id;
                        if ($user_id > 0 && $user_id !== (int) $_SESSION['gofast_user_id']) {
                            // Verificar estado actual del usuario
                            $usuario_actual = $wpdb->get_row($wpdb->prepare("SELECT activo FROM $tabla WHERE id = %d", $user_id));
                            if ($usuario_actual) {
                                $nuevo_estado = $usuario_actual->activo ? 0 : 1;
                                $wpdb->update($tabla, ['activo' => $nuevo_estado], ['id' => $user_id], ['%d'], ['%d']);
                                $mensaje = $nuevo_estado ? "✅ Usuario activado correctamente." : "✅ Usuario desactivado correctamente.";
                            }
                        }
                    }
                }

                // Borrar usuarios permanentemente
                if (!empty($_POST['borrar_usuario']) && is_array($_POST['borrar_usuario'])) {
                    foreach ($_POST['borrar_usuario'] as $user_id => $flag) {
                        if ($flag != '1') continue;
                        $user_id = (int) $user_id;
                        if ($user_id > 0 && $user_id !== (int) $_SESSION['gofast_user_id']) {
                            // No permitir auto-borrarse
                            $wpdb->delete($tabla, ['id' => $user_id], ['%d']);
                            $mensaje = "✅ Usuario borrado permanentemente.";
                        }
                    }
                }

                if ($mensaje === '') {
                    $mensaje = "✅ Cambios guardados correctamente.";
                }
            }
        }
    }

    // Mostrar mensaje de éxito si viene de redirección
    if (isset($_GET['usuario_creado']) && $_GET['usuario_creado'] === '1') {
        $mensaje = "✅ Usuario creado correctamente.";
    }
    
    // Si hay POST pero no hay mensaje, podría ser un problema silencioso
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($mensaje) && !empty($_POST['gofast_crear_usuario'])) {
        $mensaje = "⚠️ No se pudo procesar la solicitud. Verifica los datos e intenta de nuevo.";
        error_log("GOFAST ADMIN - ⚠️ POST recibido pero mensaje vacío. POST: " . print_r($_POST, true));
        error_log("GOFAST ADMIN - ⚠️ Variables: nombre=" . ($_POST['nuevo_nombre'] ?? 'VACÍO') . ", email=" . ($_POST['nuevo_email'] ?? 'VACÍO'));
    }

    /* ==========================================================
       2. Filtros (GET)
    ========================================================== */
    $filtro_rol = isset($_GET['rol']) ? sanitize_text_field($_GET['rol']) : '';
    $filtro_activo = isset($_GET['activo']) ? sanitize_text_field($_GET['activo']) : '';
    $buscar = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    $where = "1=1";
    $params = [];

    if ($filtro_rol !== '' && $filtro_rol !== 'todos') {
        $where .= " AND rol = %s";
        $params[] = $filtro_rol;
    }

    if ($filtro_activo !== '' && $filtro_activo !== 'todos') {
        $where .= " AND activo = %d";
        $params[] = ($filtro_activo === 'activo') ? 1 : 0;
    }

    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where .= " AND (nombre LIKE %s OR email LIKE %s OR telefono LIKE %s)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    /* ==========================================================
       3. Paginación
    ========================================================== */
    $por_pagina = 20;
    $pagina = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    $offset = ($pagina - 1) * $por_pagina;

    if (!empty($params)) {
        $sql_count = $wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE $where",
            $params
        );
    } else {
        $sql_count = "SELECT COUNT(*) FROM $tabla WHERE $where";
    }

    $total_registros = (int) $wpdb->get_var($sql_count);
    $total_paginas = max(1, (int) ceil($total_registros / $por_pagina));

    $params_datos = $params;
    $params_datos[] = $por_pagina;
    $params_datos[] = $offset;

    $sql_datos = $wpdb->prepare(
        "SELECT * FROM $tabla 
         WHERE $where
         ORDER BY fecha_registro DESC
         LIMIT %d OFFSET %d",
        $params_datos
    );

    $usuarios = $wpdb->get_results($sql_datos);

    /* ==========================================================
       4. HTML
    ========================================================== */
    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h1 style="margin-bottom:8px;">👥 Gestión de Usuarios</h1>
            <p class="gofast-home-text">
                Crea, edita y gestiona usuarios del sistema.
            </p>
        </div>
        <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;">
            ← Volver al Dashboard
        </a>
    </div>

    <?php 
    // Debug: verificar si hay POST pero no mensaje
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['gofast_crear_usuario']) && empty($mensaje)) {
        error_log("GOFAST ADMIN - ⚠️ POST recibido pero mensaje vacío. Esto indica un problema.");
    }
    ?>
    
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="margin-bottom:15px;background:<?= strpos($mensaje, '✅') !== false ? '#d4edda' : (strpos($mensaje, '❌') !== false ? '#f8d7da' : '#fff3cd'); ?>;border-left:4px solid <?= strpos($mensaje, '✅') !== false ? '#28a745' : (strpos($mensaje, '❌') !== false ? '#dc3545' : '#ffc107'); ?>;padding:15px;">
            <strong style="font-size:16px;"><?= esc_html($mensaje); ?></strong>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['gofast_crear_usuario']) && empty($mensaje)): ?>
        <!-- Si hay POST pero no hay mensaje, mostrar advertencia -->
        <div class="gofast-box" style="margin-bottom:15px;background:#f8d7da;border-left:4px solid #dc3545;padding:15px;">
            <strong style="font-size:16px;">⚠️ Se recibió el formulario pero no se procesó. Verifica los logs o contacta al administrador.</strong>
            <br><small>POST recibido: <?= !empty($_POST['gofast_crear_usuario']) ? 'SÍ' : 'NO'; ?></small>
        </div>
    <?php endif; ?>
    
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['gofast_crear_usuario'])): ?>
        <!-- Debug temporal: mostrar que se recibió el POST -->
        <div class="gofast-box" style="margin-bottom:15px;background:#e3f2fd;border-left:4px solid #2196F3;padding:15px;font-size:12px;">
            <strong>🔍 Debug POST recibido:</strong><br>
            Nombre: <?= esc_html($_POST['nuevo_nombre'] ?? 'VACÍO'); ?><br>
            Email: <?= esc_html($_POST['nuevo_email'] ?? 'VACÍO'); ?><br>
            Teléfono: <?= esc_html($_POST['nuevo_telefono'] ?? 'VACÍO'); ?><br>
            Password: <?= !empty($_POST['nuevo_password']) ? 'SET (' . strlen($_POST['nuevo_password']) . ' chars)' : 'VACÍO'; ?><br>
            Rol: <?= esc_html($_POST['nuevo_rol'] ?? 'VACÍO'); ?><br>
            Activo: <?= !empty($_POST['nuevo_activo']) ? 'SÍ' : 'NO'; ?><br>
            Nonce recibido: <?= !empty($_POST['gofast_usuarios_nonce']) ? 'SÍ (' . substr($_POST['gofast_usuarios_nonce'], 0, 8) . '...)' : 'NO'; ?><br>
            Mensaje actual: <strong style="color:<?= $mensaje ? (strpos($mensaje, '✅') !== false ? '#28a745' : (strpos($mensaje, '❌') !== false ? '#dc3545' : '#ffc107')) : '#dc3545'; ?>"><?= $mensaje ? esc_html($mensaje) : 'NINGUNO - ESTO ES UN PROBLEMA'; ?></strong>
        </div>
    <?php endif; ?>
    
    <?php if (defined('WP_DEBUG') && WP_DEBUG && !empty($debug_info)): ?>
        <div class="gofast-box" style="margin-bottom:15px;background:#f8f9fa;font-size:12px;font-family:monospace;padding:15px;max-height:300px;overflow-y:auto;">
            <strong>🔍 Debug Info:</strong><br>
            <?php foreach ($debug_info as $info): ?>
                <?= esc_html($info); ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (defined('WP_DEBUG') && WP_DEBUG && !empty($_POST)): ?>
        <div class="gofast-box" style="margin-bottom:15px;background:#e8f4f8;font-size:11px;font-family:monospace;padding:10px;max-height:200px;overflow-y:auto;">
            <strong>📋 POST Data recibido:</strong><br>
            <pre><?= esc_html(print_r($_POST, true)); ?></pre>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         A) CREAR NUEVO USUARIO (FORMULARIO SEPARADO)
    ====================================================== -->
    <form method="post" action="<?php echo esc_url( get_permalink() ); ?>" style="margin-bottom:20px;">
        <?php wp_nonce_field('gofast_usuarios_admin', 'gofast_usuarios_nonce'); ?>
        <div class="gofast-box">
            <h3 style="margin-top:0;">➕ Nuevo usuario</h3>

            <div class="gofast-recargo-nuevo" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;">
                <div>
                    <label>Nombre completo</label>
                    <input type="text" name="nuevo_nombre" placeholder="Ej: Juan Pérez" required>
                </div>
                <div>
                    <label>Teléfono</label>
                    <input type="text" name="nuevo_telefono" placeholder="Ej: 3001234567" required>
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="nuevo_email" placeholder="Ej: juan@example.com" required>
                </div>
                <div>
                    <label>Contraseña</label>
                    <input type="password" name="nuevo_password" placeholder="Mínimo 6 caracteres" required minlength="6">
                </div>
                <div>
                    <label>Rol</label>
                    <select name="nuevo_rol" required>
                        <option value="cliente">Cliente</option>
                        <option value="mensajero">Mensajero</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <label class="gofast-switch">
                        <input type="checkbox" name="nuevo_activo" value="1" checked>
                        <span class="gofast-switch-slider"></span>
                        <span class="gofast-switch-label">Activo</span>
                    </label>
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" name="gofast_crear_usuario" value="1" class="gofast-small-btn">
                        💾 Crear usuario
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- =====================================================
         B) FILTROS (FORMULARIO SEPARADO)
    ====================================================== -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <form method="get" class="gofast-pedidos-filtros">
                <div class="gofast-pedidos-filtros-row">
                    <div>
                        <label>Rol</label>
                        <select name="rol">
                            <option value="todos"<?php selected($filtro_rol, 'todos'); ?>>Todos</option>
                            <option value="cliente"<?php selected($filtro_rol, 'cliente'); ?>>Cliente</option>
                            <option value="mensajero"<?php selected($filtro_rol, 'mensajero'); ?>>Mensajero</option>
                            <option value="admin"<?php selected($filtro_rol, 'admin'); ?>>Admin</option>
                        </select>
                    </div>

                    <div>
                        <label>Estado</label>
                        <select name="activo">
                            <option value="todos"<?php selected($filtro_activo, 'todos'); ?>>Todos</option>
                            <option value="activo"<?php selected($filtro_activo, 'activo'); ?>>Activo</option>
                            <option value="inactivo"<?php selected($filtro_activo, 'inactivo'); ?>>Inactivo</option>
                        </select>
                    </div>

                    <div>
                        <label>Buscar</label>
                        <input type="text" name="q" placeholder="Nombre, email o teléfono" value="<?php echo esc_attr($buscar); ?>">
                    </div>

                    <div class="gofast-pedidos-filtros-actions">
                        <button type="submit" class="gofast-btn-mini">Filtrar</button>
                        <a href="<?php echo esc_url( get_permalink() ); ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
                    </div>
                </div>
        </form>
    </div>

    <!-- =====================================================
         C) LISTADO DE USUARIOS (FORMULARIO SEPARADO)
    ====================================================== -->
    <form method="post">
        <input type="hidden" name="gofast_usuarios_nonce" value="<?= wp_create_nonce('gofast_usuarios_admin'); ?>">
        <div class="gofast-box">
            <h3 style="margin-top:0;">📋 Usuarios (<?= number_format($total_registros); ?>)</h3>

            <?php if (empty($usuarios)): ?>
                <p>No se encontraron usuarios con los filtros seleccionados.</p>
            <?php else: ?>

                <!-- Vista Desktop: Tabla -->
                <div class="gofast-usuarios-table-wrapper gofast-usuarios-desktop">
                    <div class="gofast-table-wrap">
                        <table class="gofast-table gofast-usuarios-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usuarios as $u):
                            $es_yo = ((int) $u->id === (int) $_SESSION['gofast_user_id']);
                            ?>
                            <tr class="<?= $u->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>">
                                <td>#<?= (int) $u->id; ?></td>
                                <td>
                                    <input type="text"
                                           name="usuarios[<?= esc_attr($u->id); ?>][nombre]"
                                           value="<?= esc_attr($u->nombre); ?>"
                                           style="width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                                </td>
                                <td>
                                    <input type="email"
                                           name="usuarios[<?= esc_attr($u->id); ?>][email]"
                                           value="<?= esc_attr($u->email); ?>"
                                           style="width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                                </td>
                                <td>
                                    <input type="text"
                                           name="usuarios[<?= esc_attr($u->id); ?>][telefono]"
                                           value="<?= esc_attr($u->telefono); ?>"
                                           style="width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                                </td>
                                <td>
                                    <select name="usuarios[<?= esc_attr($u->id); ?>][rol]"
                                            style="width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                                        <option value="cliente"<?php selected($u->rol, 'cliente'); ?>>Cliente</option>
                                        <option value="mensajero"<?php selected($u->rol, 'mensajero'); ?>>Mensajero</option>
                                        <option value="admin"<?php selected($u->rol, 'admin'); ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <label class="gofast-switch">
                                        <input type="checkbox"
                                               name="usuarios[<?= esc_attr($u->id); ?>][activo]"
                                               value="1"
                                               <?= $u->activo ? 'checked' : ''; ?>>
                                        <span class="gofast-switch-slider"></span>
                                        <span class="gofast-switch-label">Activo</span>
                                    </label>
                                </td>
                                <td class="gofast-td-fecha"><?= esc_html( gofast_date_format($u->fecha_registro, 'Y-m-d') ); ?></td>
                                <td class="gofast-td-acciones">
                                    <div class="gofast-acciones-usuario">
                                        <input type="password"
                                               name="usuarios[<?= esc_attr($u->id); ?>][password]"
                                               placeholder="Nueva contraseña (opcional)"
                                               class="gofast-input-password">
                                        <button type="button"
                                                class="gofast-btn-accion gofast-btn-actualizar"
                                                data-usuario-id="<?= esc_attr($u->id); ?>"
                                                data-usuario-nombre="<?= esc_attr($u->nombre); ?>"
                                                style="width:100%;margin-bottom:8px;">
                                            💾 Actualizar
                                        </button>
                                        <?php if (!$es_yo): ?>
                                            <div class="gofast-botones-accion">
                                                <?php if ($u->activo): ?>
                                                    <button type="button"
                                                            class="gofast-btn-accion gofast-btn-desactivar"
                                                            data-usuario-id="<?= esc_attr($u->id); ?>"
                                                            data-usuario-nombre="<?= esc_attr($u->nombre); ?>">
                                                        ⏸️ Desactivar
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                            class="gofast-btn-accion gofast-btn-activar"
                                                            data-usuario-id="<?= esc_attr($u->id); ?>"
                                                            data-usuario-nombre="<?= esc_attr($u->nombre); ?>">
                                                        ▶️ Activar
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button"
                                                        class="gofast-btn-accion gofast-btn-borrar"
                                                        data-usuario-id="<?= esc_attr($u->id); ?>"
                                                        data-usuario-nombre="<?= esc_attr($u->nombre); ?>">
                                                    🗑️ Borrar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="gofast-tu-usuario">(Tú)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Vista Móvil: Cards -->
                <div class="gofast-usuarios-cards gofast-usuarios-mobile">
                    <?php foreach ($usuarios as $u): 
                        $es_yo = ((int) $u->id === (int) $_SESSION['gofast_user_id']);
                        $rol_badge = '';
                        $rol_color = '#6c757d';
                        switch(strtolower($u->rol)) {
                            case 'admin':
                                $rol_badge = '👑 Admin';
                                $rol_color = '#dc3545';
                                break;
                            case 'mensajero':
                                $rol_badge = '🚚 Mensajero';
                                $rol_color = '#007bff';
                                break;
                            case 'cliente':
                                $rol_badge = '👤 Cliente';
                                $rol_color = '#28a745';
                                break;
                        }
                    ?>
                        <div class="gofast-usuario-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid <?= $u->activo ? '#28a745' : '#dc3545'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                        ID: #<?= (int) $u->id; ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999;">
                                        <?= esc_html( gofast_date_format($u->fecha_registro, 'Y-m-d') ); ?>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <span style="font-size: 11px; padding: 4px 8px; background: <?= $rol_color ?>; color: #fff; border-radius: 4px; font-weight: 600;">
                                        <?= $rol_badge ?>
                                    </span>
                                    <span style="font-size: 11px; padding: 4px 8px; background: <?= $u->activo ? '#28a745' : '#dc3545'; ?>; color: #fff; border-radius: 4px; font-weight: 600;">
                                        <?= $u->activo ? '✅ Activo' : '❌ Inactivo'; ?>
                                    </span>
                                </div>
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Nombre:</div>
                                <input type="text"
                                       name="usuarios[<?= esc_attr($u->id); ?>][nombre]"
                                       value="<?= esc_attr($u->nombre); ?>"
                                       style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-weight:600;">
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Email:</div>
                                <input type="email"
                                       name="usuarios[<?= esc_attr($u->id); ?>][email]"
                                       value="<?= esc_attr($u->email); ?>"
                                       style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Teléfono:</div>
                                <input type="text"
                                       name="usuarios[<?= esc_attr($u->id); ?>][telefono]"
                                       value="<?= esc_attr($u->telefono); ?>"
                                       style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Rol:</div>
                                <select name="usuarios[<?= esc_attr($u->id); ?>][rol]"
                                        style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                                    <option value="cliente"<?php selected($u->rol, 'cliente'); ?>>Cliente</option>
                                    <option value="mensajero"<?php selected($u->rol, 'mensajero'); ?>>Mensajero</option>
                                    <option value="admin"<?php selected($u->rol, 'admin'); ?>>Admin</option>
                                </select>
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Estado:</div>
                                <label class="gofast-switch" style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox"
                                           name="usuarios[<?= esc_attr($u->id); ?>][activo]"
                                           value="1"
                                           <?= $u->activo ? 'checked' : ''; ?>>
                                    <span class="gofast-switch-slider"></span>
                                    <span class="gofast-switch-label" style="font-size: 14px;">Activo</span>
                                </label>
                            </div>

                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Nueva contraseña (opcional):</div>
                                <input type="password"
                                       name="usuarios[<?= esc_attr($u->id); ?>][password]"
                                       placeholder="Dejar vacío para mantener la actual"
                                       style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
                                <button type="button"
                                        class="gofast-btn-accion gofast-btn-actualizar"
                                        data-usuario-id="<?= esc_attr($u->id); ?>"
                                        data-usuario-nombre="<?= esc_attr($u->nombre); ?>"
                                        style="width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                                    💾 Actualizar Usuario
                                </button>
                                <?php if (!$es_yo): ?>
                                    <?php if ($u->activo): ?>
                                        <button type="button"
                                                class="gofast-btn-accion gofast-btn-desactivar"
                                                data-usuario-id="<?= esc_attr($u->id); ?>"
                                                data-usuario-nombre="<?= esc_attr($u->nombre); ?>"
                                                style="width: 100%; padding: 10px; background: #ffc107; color: #000; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                                            ⏸️ Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                class="gofast-btn-accion gofast-btn-activar"
                                                data-usuario-id="<?= esc_attr($u->id); ?>"
                                                data-usuario-nombre="<?= esc_attr($u->nombre); ?>"
                                                style="width: 100%; padding: 10px; background: #28a745; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                                            ▶️ Activar
                                        </button>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="gofast-btn-accion gofast-btn-borrar"
                                            data-usuario-id="<?= esc_attr($u->id); ?>"
                                            data-usuario-nombre="<?= esc_attr($u->nombre); ?>"
                                            style="width: 100%; padding: 10px; background: #dc3545; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                                        🗑️ Borrar Permanentemente
                                    </button>
                                <?php else: ?>
                                    <div style="padding: 10px; background: #e7f3ff; border-radius: 6px; text-align: center; font-size: 14px; color: #0066cc; font-weight: 600;">
                                        👤 (Tu usuario)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="gofast-pagination">
                        <?php
                        $base_url = get_permalink();
                        $query_args = $_GET;

                        for ($i = 1; $i <= $total_paginas; $i++):
                            $query_args['pg'] = $i;
                            $url = esc_url( add_query_arg($query_args, $base_url) );
                            $active = ($i === $pagina) ? 'gofast-page-current' : '';
                            ?>
                            <a href="<?php echo $url; ?>" class="gofast-page-link <?php echo $active; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // Proteger contra errores de toggleOtro si no existe
    if (typeof toggleOtro === 'function') {
        try {
            const tipoSelect = document.getElementById("tipo_negocio");
            const wrapperOtro = document.getElementById("tipo_otro_wrapper");
            if (tipoSelect && wrapperOtro) {
                setTimeout(toggleOtro, 200);
            }
        } catch(e) {
            console.log('toggleOtro error capturado:', e);
        }
    }
    
    // Manejar botón de actualizar usuario individual
    document.querySelectorAll('.gofast-btn-actualizar').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = btn.getAttribute('data-usuario-id');
            if (!userId) {
                alert('Error: No se encontró el ID del usuario');
                return;
            }
            
            const form = btn.closest('form');
            if (!form) {
                alert('Error: No se encontró el formulario');
                return;
            }
            
            // Verificar que el nonce esté presente
            const nonceInput = form.querySelector('input[name="gofast_usuarios_nonce"]');
            if (!nonceInput) {
                alert('Error de seguridad: No se encontró el token de seguridad');
                return;
            }
            
            // Detectar qué vista está visible
            const vistaDesktop = document.querySelector('.gofast-usuarios-desktop');
            const vistaMobile = document.querySelector('.gofast-usuarios-mobile');
            const desktopVisible = vistaDesktop && window.getComputedStyle(vistaDesktop).display !== 'none';
            const mobileVisible = vistaMobile && window.getComputedStyle(vistaMobile).display !== 'none';
            
            // Cambiar nombres de campos de otros usuarios para que no se envíen
            const todosLosInputs = form.querySelectorAll('input[name^="usuarios["], select[name^="usuarios["]');
            todosLosInputs.forEach(function(input) {
                const name = input.getAttribute('name') || '';
                // Si el campo no pertenece al usuario que se está actualizando
                if (name.includes('usuarios[') && !name.includes('usuarios[' + userId + ']')) {
                    // Cambiar el nombre para que no se envíe
                    if (!name.includes('_skip_')) {
                        input.setAttribute('data-original-name', name);
                        input.setAttribute('name', name.replace('usuarios[', 'usuarios_skip_['));
                    }
                }
            });
            
            // También manejar la vista oculta: cambiar nombres de campos de la vista no visible
            if (desktopVisible && vistaMobile) {
                // Desktop visible, cambiar nombres de campos móvil
                const camposMobile = vistaMobile.querySelectorAll('input[name^="usuarios["], select[name^="usuarios["]');
                camposMobile.forEach(function(campo) {
                    const name = campo.getAttribute('name') || '';
                    if (name.includes('usuarios[') && !name.includes('_skip_')) {
                        campo.setAttribute('data-original-name', name);
                        campo.setAttribute('name', name.replace('usuarios[', 'usuarios_skip_['));
                    }
                });
            } else if (mobileVisible && vistaDesktop) {
                // Móvil visible, cambiar nombres de campos desktop
                const camposDesktop = vistaDesktop.querySelectorAll('input[name^="usuarios["], select[name^="usuarios["]');
                camposDesktop.forEach(function(campo) {
                    const name = campo.getAttribute('name') || '';
                    if (name.includes('usuarios[') && !name.includes('_skip_')) {
                        campo.setAttribute('data-original-name', name);
                        campo.setAttribute('name', name.replace('usuarios[', 'usuarios_skip_['));
                    }
                });
            }
            
            // Agregar campo hidden para indicar que se actualiza solo este usuario
            let inputActualizar = form.querySelector('input[name^="gofast_actualizar_usuario"]');
            if (inputActualizar) {
                inputActualizar.remove();
            }
            
            inputActualizar = document.createElement('input');
            inputActualizar.type = 'hidden';
            inputActualizar.name = 'gofast_actualizar_usuario[' + userId + ']';
            inputActualizar.value = '1';
            form.appendChild(inputActualizar);
            
            // Enviar formulario
            form.submit();
        });
    });
    
    // Desactivar/Activar usuario
    document.querySelectorAll('.gofast-btn-desactivar, .gofast-btn-activar').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-usuario-id');
            const nombre = btn.getAttribute('data-usuario-nombre') || 'este usuario';
            const accion = btn.classList.contains('gofast-btn-desactivar') ? 'desactivar' : 'activar';
            if (!id) return;

            if (!confirm('¿Seguro que quieres ' + accion + ' a ' + nombre + '?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="desactivar_usuario['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'desactivar_usuario['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_usuarios"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_usuarios';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });

    // Borrar usuario permanentemente
    document.querySelectorAll('.gofast-btn-borrar').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-usuario-id');
            const nombre = btn.getAttribute('data-usuario-nombre') || 'este usuario';
            if (!id) return;

            if (!confirm('⚠️ ¿Estás SEGURO que quieres BORRAR PERMANENTEMENTE a ' + nombre + '?\n\nEsta acción NO se puede deshacer.')) {
                return;
            }

            // Confirmación doble
            if (!confirm('⚠️ ÚLTIMA CONFIRMACIÓN:\n\n¿Realmente quieres eliminar permanentemente a ' + nombre + '?\n\nEsta acción es IRREVERSIBLE.')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="borrar_usuario['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'borrar_usuario['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_usuarios"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_usuarios';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });
});
</script>

<style>
/* =====================================================
   ESTILOS PARA TABLA Y CARDS
   ====================================================== */
.gofast-usuarios-table-wrapper {
    width: 100%;
    max-width: 100%;
}

.gofast-usuarios-table-wrapper .gofast-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.gofast-usuarios-table-wrapper .gofast-usuarios-table {
    min-width: 1000px;
    width: 100%;
}

/* Vista Desktop: Mostrar tabla, ocultar cards */
.gofast-usuarios-desktop {
    display: block;
}

.gofast-usuarios-mobile {
    display: none;
}

.gofast-usuarios-cards {
    width: 100%;
    max-width: 100%;
}

.gofast-usuario-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.gofast-usuario-card * {
    max-width: 100%;
    box-sizing: border-box;
}

/* =====================================================
   ESTILOS PARA FILTROS EN DESKTOP
   ====================================================== */
.gofast-pedidos-filtros {
    margin: 0;
}

.gofast-pedidos-filtros-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
    margin: 0;
}

.gofast-pedidos-filtros-row > div {
    flex: 1;
    min-width: 150px;
}

.gofast-pedidos-filtros-row label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #333;
    margin-bottom: 6px;
}

.gofast-pedidos-filtros-row select,
.gofast-pedidos-filtros-row input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.gofast-pedidos-filtros-row select:focus,
.gofast-pedidos-filtros-row input[type="text"]:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.gofast-pedidos-filtros-actions {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.gofast-pedidos-filtros-actions button,
.gofast-pedidos-filtros-actions a {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}

.gofast-btn-mini {
    background: #007bff;
    color: #fff;
    border: none;
    cursor: pointer;
}

.gofast-btn-mini:hover {
    background: #0056b3;
}

.gofast-btn-outline {
    background: #fff;
    color: #6c757d;
    border: 1px solid #ddd;
}

.gofast-btn-outline:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

/* =====================================================
   ESTILOS RESPONSIVE PARA MÓVIL
   ====================================================== */
@media (max-width: 768px) {
    .gofast-home {
        padding: 10px;
    }
    
    .gofast-box {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    /* Ocultar tabla en móvil, mostrar cards */
    .gofast-usuarios-desktop {
        display: none !important;
    }
    
    .gofast-usuarios-mobile {
        display: block !important;
    }
    
    .gofast-usuarios-cards {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .gofast-usuario-card {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Formulario de crear usuario responsive */
    .gofast-recargo-nuevo {
        grid-template-columns: 1fr !important;
    }
    
    /* Filtros responsive */
    .gofast-pedidos-filtros-row {
        flex-direction: column;
        gap: 12px;
    }
    
    .gofast-pedidos-filtros-row > div {
        width: 100%;
    }
    
    .gofast-pedidos-filtros-actions {
        display: flex;
        gap: 10px;
    }
    
    .gofast-pedidos-filtros-actions button,
    .gofast-pedidos-filtros-actions a {
        flex: 1;
    }
    
    /* Botón guardar cambios */
    .gofast-btn-request {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
    }
    
    /* Paginación responsive */
    .gofast-pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .gofast-page-link {
        min-width: 36px;
        padding: 8px 12px;
        font-size: 14px;
    }
    
    /* Header responsive */
    .gofast-home > div:first-child {
        flex-direction: column;
        gap: 15px;
    }
    
    .gofast-home > div:first-child > a {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 22px !important;
    }
    
    h3 {
        font-size: 18px !important;
    }
}

/* Desktop: Mostrar tabla, ocultar cards */
@media (min-width: 769px) {
    .gofast-usuarios-desktop {
        display: block !important;
    }
    
    .gofast-usuarios-mobile {
        display: none !important;
    }
}

/* Estilos para botones de acción */
.gofast-acciones-usuario {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 150px;
}

.gofast-input-password {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 12px;
}

.gofast-botones-accion {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.gofast-btn-accion {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.gofast-btn-actualizar {
    background: #007bff;
    color: #fff;
}

.gofast-btn-actualizar:hover {
    background: #0056b3;
}

.gofast-btn-desactivar {
    background: #ffc107;
    color: #000;
}

.gofast-btn-desactivar:hover {
    background: #e0a800;
}

.gofast-btn-activar {
    background: #28a745;
    color: #fff;
}

.gofast-btn-activar:hover {
    background: #218838;
}

.gofast-btn-borrar {
    background: #dc3545;
    color: #fff;
}

.gofast-btn-borrar:hover {
    background: #c82333;
}

.gofast-tu-usuario {
    font-size: 12px;
    color: #666;
    font-style: italic;
    padding: 8px 0;
}

/* Mejorar visibilidad de la tabla */
.gofast-table {
    font-size: 14px;
    border-collapse: separate;
    border-spacing: 0;
}

.gofast-table th {
    font-weight: 600;
    padding: 14px 12px;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.gofast-table td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.gofast-table tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.2s;
}

.gofast-row-inactive {
    opacity: 0.7;
    background: #f8f9fa;
}

.gofast-row-active {
    background: #fff;
}

/* Estilos para campos editables en la tabla */
.gofast-table input[type="text"],
.gofast-table input[type="email"],
.gofast-table input[type="password"],
.gofast-table select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    transition: all 0.2s;
    background: #fff;
}

.gofast-table input[type="text"]:focus,
.gofast-table input[type="email"]:focus,
.gofast-table input[type="password"]:focus,
.gofast-table select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    background: #fff;
}

.gofast-table input[type="text"]:hover,
.gofast-table input[type="email"]:hover,
.gofast-table input[type="password"]:hover,
.gofast-table select:hover {
    border-color: #adb5bd;
}

/* Mejorar columna de acciones */
.gofast-td-acciones {
    min-width: 200px;
}

.gofast-td-fecha {
    color: #6c757d;
    font-size: 13px;
    white-space: nowrap;
}

/* Estilos para formulario de crear usuario */
.gofast-recargo-nuevo {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
}

.gofast-recargo-nuevo > div {
    display: flex;
    flex-direction: column;
}

.gofast-recargo-nuevo label {
    font-weight: 600;
    font-size: 13px;
    color: #333;
    margin-bottom: 6px;
}

.gofast-recargo-nuevo input[type="text"],
.gofast-recargo-nuevo input[type="email"],
.gofast-recargo-nuevo input[type="password"],
.gofast-recargo-nuevo select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.gofast-recargo-nuevo input[type="text"]:focus,
.gofast-recargo-nuevo input[type="email"]:focus,
.gofast-recargo-nuevo input[type="password"]:focus,
.gofast-recargo-nuevo select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.gofast-small-btn {
    padding: 10px 20px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    width: 100%;
}

.gofast-small-btn:hover {
    background: #218838;
}
</style>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_usuarios_admin', 'gofast_usuarios_admin_shortcode');

