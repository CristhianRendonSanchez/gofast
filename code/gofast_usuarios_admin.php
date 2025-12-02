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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        /* ----------------------------------------------
           A) Crear nuevo usuario (verificar nonce específico)
        ---------------------------------------------- */
        if (!empty($_POST['gofast_crear_usuario'])) {
            
            if (
                empty($_POST['gofast_usuarios_nonce']) ||
                !wp_verify_nonce($_POST['gofast_usuarios_nonce'], 'gofast_usuarios_admin')
            ) {
                $mensaje = "🔒 Error de seguridad al crear usuario.";
            } else {
                $nombre    = sanitize_text_field($_POST['nuevo_nombre'] ?? '');
                $telefono  = sanitize_text_field($_POST['nuevo_telefono'] ?? '');
                $email     = sanitize_email($_POST['nuevo_email'] ?? '');
                $password  = $_POST['nuevo_password'] ?? '';
                $rol       = sanitize_text_field($_POST['nuevo_rol'] ?? 'cliente');
                $activo    = !empty($_POST['nuevo_activo']) ? 1 : 0;

                if ($nombre === '' || $telefono === '' || $email === '' || $password === '') {
                    $mensaje = "⚠️ Todos los campos son obligatorios.";
                } elseif (!is_email($email)) {
                    $mensaje = "⚠️ El correo no es válido.";
                } elseif (!in_array($rol, ['cliente', 'mensajero', 'admin'], true)) {
                    $mensaje = "⚠️ Rol no válido.";
                } else {
                    $tel_norm = preg_replace('/\D+/', '', $telefono);

                    // Verificar duplicados
                    $existe = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id FROM $tabla 
                             WHERE email = %s 
                                OR REPLACE(REPLACE(REPLACE(telefono, '+',''), ' ','') ,'-','') = %s",
                            $email,
                            $tel_norm
                        )
                    );

                    if ($existe) {
                        $mensaje = "⚠️ Ya existe un usuario con ese correo o teléfono.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);

                        // Preparar datos para inserción
                        $datos_insert = [
                            'nombre'         => $nombre,
                            'telefono'       => $telefono,
                            'email'          => $email,
                            'password_hash'  => $hash,
                            'rol'            => $rol,
                            'activo'         => $activo,
                            'fecha_registro' => current_time('mysql')
                        ];
                        
                        // Verificar si el campo remember_token existe en la tabla
                        $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM $tabla LIKE 'remember_token'");
                        if (!empty($tabla_info)) {
                            $datos_insert['remember_token'] = null;
                        }
                        
                        // Preparar formatos según los campos
                        $formatos = ['%s','%s','%s','%s','%s','%d','%s'];
                        if (!empty($tabla_info)) {
                            $formatos[] = '%s'; // remember_token
                        }

                        $ok = $wpdb->insert($tabla, $datos_insert, $formatos);

                        if ($ok !== false) {
                            $mensaje = "✅ Usuario creado correctamente.";
                            // Redirigir para evitar reenvío del formulario
                            wp_redirect(add_query_arg(['usuario_creado' => '1'], get_permalink()));
                            exit;
                        } else {
                            $error_db = $wpdb->last_error;
                            $mensaje = "❌ Error al crear usuario: " . esc_html($error_db);
                            // Log para debug (solo en desarrollo)
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("GOFAST - Error al crear usuario: " . $error_db);
                                error_log("GOFAST - Datos: " . print_r($datos_insert, true));
                            }
                        }
                    }
                }
            }
        }

        /* ----------------------------------------------
           B) Editar usuarios existentes (verificar nonce específico)
        ---------------------------------------------- */
        if (!empty($_POST['gofast_guardar_usuarios'])) {

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

                // Eliminar usuarios
                if (!empty($_POST['eliminar_usuario']) && is_array($_POST['eliminar_usuario'])) {
                    foreach ($_POST['eliminar_usuario'] as $user_id => $flag) {
                        if ($flag != '1') continue;
                        $user_id = (int) $user_id;
                        if ($user_id > 0 && $user_id !== (int) $_SESSION['gofast_user_id']) {
                            // No permitir auto-eliminarse
                            $wpdb->update($tabla, ['activo' => 0], ['id' => $user_id]);
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

    <?php if ($mensaje): ?>
        <div class="gofast-box" style="margin-bottom:15px;">
            <?= esc_html($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         A) CREAR NUEVO USUARIO (FORMULARIO SEPARADO)
    ====================================================== -->
    <form method="post" style="margin-bottom:20px;">
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
                    <button type="submit" name="gofast_crear_usuario" class="gofast-small-btn">
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
        <?php wp_nonce_field('gofast_usuarios_admin', 'gofast_usuarios_nonce'); ?>
        <div class="gofast-box">
            <h3 style="margin-top:0;">📋 Usuarios (<?= number_format($total_registros); ?>)</h3>

            <?php if (empty($usuarios)): ?>
                <p>No se encontraron usuarios con los filtros seleccionados.</p>
            <?php else: ?>

                <div class="gofast-table-wrap">
                    <table class="gofast-table">
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
                                <td><?= esc_html( date_i18n('Y-m-d', strtotime($u->fecha_registro)) ); ?></td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:4px;">
                                        <input type="password"
                                               name="usuarios[<?= esc_attr($u->id); ?>][password]"
                                               placeholder="Nueva contraseña (opcional)"
                                               style="width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">
                                        <?php if (!$es_yo): ?>
                                            <button type="button"
                                                    class="gofast-small-btn gofast-chip-danger gofast-delete-usuario"
                                                    data-usuario-id="<?= esc_attr($u->id); ?>">
                                                🗑️ Desactivar
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:11px;color:#666;">(Tú)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
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

                <div style="margin-top:18px;text-align:right;">
                    <button type="submit"
                            name="gofast_guardar_usuarios"
                            value="1"
                            class="gofast-btn-request"
                            style="max-width:260px;margin-left:auto;">
                        💾 Guardar cambios
                    </button>
                </div>

            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // Eliminar usuario
    document.querySelectorAll('.gofast-delete-usuario').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-usuario-id');
            if (!id) return;

            if (!confirm('¿Seguro que quieres desactivar este usuario?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="eliminar_usuario['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_usuario['+id+']';
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

<?php
    return ob_get_clean();
}
add_shortcode('gofast_usuarios_admin', 'gofast_usuarios_admin_shortcode');

