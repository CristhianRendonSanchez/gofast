<?php
/***************************************************
 * GOFAST – ACTIVAR SESIONES GLOBALES EN TODO WP
 * Con persistencia mejorada y restauración desde cookies
 ***************************************************/

// Configurar tiempo de vida de sesión (30 días en segundos)
ini_set('session.gc_maxlifetime', 2592000); // 30 días
ini_set('session.cookie_lifetime', 2592000); // 30 días

function gofast_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar parámetros de sesión antes de iniciar
        session_set_cookie_params([
            'lifetime' => 2592000, // 30 días
            'path' => '/',
            'domain' => '',
            'secure' => false, // Cambiar a true si usas HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
    
    // Restaurar sesión desde cookie persistente si no hay sesión activa
    gofast_restore_session_from_cookie();
}
add_action('init', 'gofast_start_session', 1);

/**
 * Restaurar sesión desde cookie persistente
 * Esta función se ejecuta automáticamente al iniciar sesión
 */
function gofast_restore_session_from_cookie() {
    // Solo restaurar si no hay sesión activa
    if (!empty($_SESSION['gofast_user_id'])) {
        return; // Ya hay sesión activa
    }
    
    // Verificar si existe cookie persistente
    if (empty($_COOKIE['gofast_token'])) {
        return; // No hay cookie
    }
    
    global $wpdb;
    
    $token = sanitize_text_field($_COOKIE['gofast_token']);
    
    // Verificar si el campo remember_token existe
    $tabla_info = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
    if (empty($tabla_info)) {
        return; // Campo no existe
    }
    
    // Buscar usuario por token
    $usuario = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, rol, activo FROM usuarios_gofast WHERE remember_token = %s AND activo = 1 LIMIT 1",
            $token
        )
    );
    
    if ($usuario) {
        // Restaurar sesión
        $_SESSION['gofast_user_id']  = (int) $usuario->id;
        $_SESSION['gofast_user_rol'] = strtolower($usuario->rol ?: 'cliente');
    } else {
        // Token inválido, eliminar cookie
        setcookie("gofast_token", "", time() - 3600, "/", "", false, true);
    }
}

