<?php
/***************************************************
 * GOFAST – LÓGICA AUTH (LOGIN, REGISTRO, LOGOUT)
 * Ahora con sesión persistente (cookies 30 días)
 ***************************************************/
function gofast_handle_auth_requests() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    /*********************************************
     * LOGOUT (?gofast_logout=1)
     *********************************************/
    if (isset($_GET['gofast_logout'])) {

        // Eliminar sesión
        unset($_SESSION['gofast_user_id'], $_SESSION['gofast_user_rol']);
        session_destroy();

        // Eliminar cookie y token de la base de datos
        if (!empty($_COOKIE['gofast_token'])) {
            $token = sanitize_text_field($_COOKIE['gofast_token']);
            global $wpdb;
            $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
            if (!empty($tabla_info)) {
                // Limpiar token de la base de datos
                $wpdb->update(
                    'usuarios_gofast',
                    ['remember_token' => null],
                    ['remember_token' => $token],
                    ['%s'],
                    ['%s']
                );
            }
        }
        
        // Eliminar cookie
        if (PHP_VERSION_ID >= 70300) {
            setcookie("gofast_token", "", [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie("gofast_token", "", time() - 3600, "/", "", false, true);
        }

        wp_redirect(home_url('/'));
        exit;
    }

    // Si NO es POST → no hay login ni registro
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    /*********************************************
     * LOGIN
     *********************************************/
    if (isset($_POST['gofast_login'])) {

        $user_raw = trim($_POST['user'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']); // checkbox

        if ($user_raw === '' || $password === '') {
            $_SESSION['gofast_auth_error'] = 'Por favor ingresa usuario y contraseña.';
            wp_redirect(home_url('/auth'));
            exit;
        }

        $tel_norm = preg_replace('/\D+/', '', $user_raw);
        $tabla    = 'usuarios_gofast';

        // Buscar usuario
        $sql = "
            SELECT *
            FROM $tabla
            WHERE activo = 1
              AND (
                    email = %s
                 OR REPLACE(REPLACE(REPLACE(telefono, '+',''), ' ','') ,'-','') = %s
              )
            LIMIT 1
        ";
        $usuario = $wpdb->get_row($wpdb->prepare($sql, $user_raw, $tel_norm));

        // Validar
        if (
            !$usuario ||
            empty($usuario->password_hash) ||
            !password_verify($password, $usuario->password_hash)
        ) {
            $_SESSION['gofast_auth_error'] = 'Usuario o contraseña incorrectos.';
            wp_redirect(home_url('/auth'));
            exit;
        }

        // Guardar sesión normal
        $_SESSION['gofast_user_id']  = (int) $usuario->id;
        $_SESSION['gofast_user_rol'] = strtolower($usuario->rol ?: 'cliente');

        /*********************************************
         * 🔥 COOKIE PERSISTENTE (30 DÍAS)
         * Solo si el usuario aceptó cookies
         *********************************************/
        if ($remember) {
            // Verificar si el usuario aceptó cookies
            $cookies_accepted = isset($_POST['gofast_cookies_accepted']) && $_POST['gofast_cookies_accepted'] === '1';
            
            // Si aceptó cookies o no se especificó (compatibilidad), crear cookie
            if ($cookies_accepted || !isset($_POST['gofast_cookies_accepted'])) {
                gofast_create_persistent_cookie($usuario->id, $tabla);
            }
        }

        wp_redirect(home_url('/'));
        exit;
    }

    /*********************************************
     * REGISTRO
     *********************************************/
    if (isset($_POST['gofast_registro'])) {

        $nombre    = trim($_POST['nombre'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($nombre === '' || $telefono === '' || $email === '' || $password === '' || $password2 === '') {
            $_SESSION['gofast_auth_error'] = 'Todos los campos son obligatorios.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        if (!is_email($email)) {
            $_SESSION['gofast_auth_error'] = 'El correo no es válido.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        if ($password !== $password2) {
            $_SESSION['gofast_auth_error'] = 'Las contraseñas no coinciden.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        // Validar longitud mínima de contraseña
        if (strlen($password) < 6) {
            $_SESSION['gofast_auth_error'] = 'La contraseña debe tener al menos 6 caracteres.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        // Normalizar y validar teléfono
        $tel_norm = preg_replace('/\D+/', '', $telefono);
        if (strlen($tel_norm) < 10) {
            $_SESSION['gofast_auth_error'] = 'El teléfono debe tener al menos 10 dígitos.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        $tabla    = 'usuarios_gofast';

        // Verificar duplicados
        $sql = "
            SELECT id
            FROM $tabla
            WHERE email = %s
               OR REPLACE(REPLACE(REPLACE(telefono, '+',''), ' ','') ,'-','') = %s
            LIMIT 1
        ";
        $existe = $wpdb->get_row($wpdb->prepare($sql, $email, $tel_norm));

        if ($existe) {
            $_SESSION['gofast_auth_error'] = 'Ya existe un usuario con ese correo o teléfono.';
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Preparar datos para inserción
        $datos_insert = [
            'nombre'         => $nombre,
            'telefono'       => $telefono,
            'email'          => $email,
            'password_hash'  => $hash,
            'rol'            => 'cliente',
            'activo'         => 1,
            'fecha_registro' => gofast_current_time('mysql')
        ];
        
        // Verificar si el campo remember_token existe en la tabla
        $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
        if (!empty($tabla_info)) {
            $datos_insert['remember_token'] = null;
        }
        
        // Preparar formatos según los campos
        $formatos = ['%s','%s','%s','%s','%s','%d','%s'];
        if (!empty($tabla_info)) {
            $formatos[] = '%s'; // remember_token
        }

        $ok = $wpdb->insert($tabla, $datos_insert, $formatos);

        if (!$ok) {
            $error_db = $wpdb->last_error;
            $_SESSION['gofast_auth_error'] = 'No se pudo crear la cuenta. Intenta de nuevo.';
            // Log para debug (solo en desarrollo)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GOFAST AUTH - Error al crear usuario: " . $error_db);
                error_log("GOFAST AUTH - Datos: " . print_r($datos_insert, true));
            }
            wp_redirect(home_url('/auth/?registro=1'));
            exit;
        }

        $user_id = (int)$wpdb->insert_id;

        // Login automático
        $_SESSION['gofast_user_id']  = $user_id;
        $_SESSION['gofast_user_rol'] = 'cliente';

        // Crear cookie persistente automáticamente en registro (30 días)
        // Solo si el usuario aceptó cookies
        $cookies_accepted = isset($_POST['gofast_cookies_accepted']) && $_POST['gofast_cookies_accepted'] === '1';
        
        // Si aceptó cookies o no se especificó (compatibilidad), crear cookie
        if ($cookies_accepted || !isset($_POST['gofast_cookies_accepted'])) {
            gofast_create_persistent_cookie($user_id, $tabla);
        }

        wp_redirect(home_url('/'));
        exit;
    }
}

/**
 * Crear cookie persistente para mantener sesión (30 días)
 * Solo se crea si el usuario aceptó cookies (verificado por JavaScript)
 */
function gofast_create_persistent_cookie($user_id, $tabla = 'usuarios_gofast') {
    global $wpdb;
    
    // Verificar si el campo remember_token existe
    $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
    if (empty($tabla_info)) {
        return false; // Campo no existe
    }
    
    
    // Crear token único
    $token = wp_generate_uuid4();
    
    // Guardarlo en DB
    $wpdb->update(
        $tabla,
        ['remember_token' => $token],
        ['id' => $user_id],
        ['%s'],
        ['%d']
    );
    
    // Guardar cookie 30 días con configuración mejorada
    $cookie_expire = time() + (86400 * 30); // 30 días
    $cookie_path = '/';
    $cookie_domain = ''; // Usar dominio actual
    $cookie_secure = false; // Cambiar a true si usas HTTPS
    $cookie_httponly = true;
    
    // Usar setcookie con SameSite (PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        setcookie(
            "gofast_token",
            $token,
            [
                'expires' => $cookie_expire,
                'path' => $cookie_path,
                'domain' => $cookie_domain,
                'secure' => $cookie_secure,
                'httponly' => $cookie_httponly,
                'samesite' => 'Lax'
            ]
        );
    } else {
        // Fallback para versiones anteriores de PHP
        setcookie(
            "gofast_token",
            $token,
            $cookie_expire,
            $cookie_path . '; SameSite=Lax',
            $cookie_domain,
            $cookie_secure,
            $cookie_httponly
        );
    }
    
    return true;
}

add_action('init', 'gofast_handle_auth_requests', 5);

