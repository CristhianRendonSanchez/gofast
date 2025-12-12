<?php
/***************************************************
 * GOFAST – RECUPERACIÓN DE CONTRASEÑA
 * Shortcode: [gofast_recuperar_password]
 * URL: /recuperar-password
 * 
 * Sistema seguro de recuperación de contraseña
 * - Genera tokens únicos con expiración (1 hora)
 * - Envía email con enlace seguro
 * - Permite resetear contraseña de forma segura
 ***************************************************/
function gofast_recuperar_password_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    $mensaje = '';
    $mensaje_tipo = ''; // 'success', 'error'
    $paso = 'solicitar'; // 'solicitar', 'resetear'

    // Si viene un token, mostrar formulario de reset
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $paso = 'resetear';
        $token = sanitize_text_field($_GET['token']);

        // Verificar si el token es válido
        $usuario = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, reset_token_expires 
             FROM usuarios_gofast 
             WHERE reset_token = %s 
             AND activo = 1 
             LIMIT 1",
            $token
        ));

        if (!$usuario) {
            $mensaje = 'El enlace de recuperación no es válido o ha expirado.';
            $mensaje_tipo = 'error';
            $paso = 'solicitar';
        } else {
            // Verificar si el token expiró
            $ahora = gofast_current_time('mysql');
            if ($usuario->reset_token_expires < $ahora) {
                $mensaje = 'El enlace de recuperación ha expirado. Por favor solicita uno nuevo.';
                $mensaje_tipo = 'error';
                $paso = 'solicitar';
                // Limpiar token expirado
                $wpdb->update(
                    'usuarios_gofast',
                    ['reset_token' => null, 'reset_token_expires' => null],
                    ['id' => $usuario->id],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
    }

    /*********************************************
     * FUNCIÓN PARA ENMASCARAR EMAIL
     *********************************************/
    function gofast_mask_email($email) {
        if (empty($email) || !is_email($email)) {
            return $email;
        }
        
        $parts = explode('@', $email);
        $username = $parts[0];
        $domain = $parts[1] ?? '';
        
        // Si el usuario tiene 2 caracteres o menos, mostrar solo el primero
        if (strlen($username) <= 2) {
            $masked_username = substr($username, 0, 1) . '***';
        } else {
            // Mostrar primeros 2 caracteres y enmascarar el resto
            $masked_username = substr($username, 0, 2) . str_repeat('*', min(4, strlen($username) - 2));
        }
        
        return $masked_username . '@' . $domain;
    }

    /*********************************************
     * PROCESAR SOLICITUD DE RECUPERACIÓN
     *********************************************/
    if (isset($_POST['gofast_solicitar_reset'])) {
        $usuario_input = trim($_POST['usuario'] ?? ''); // Email o teléfono

        if (empty($usuario_input)) {
            $mensaje = 'Por favor ingresa tu correo electrónico o número de teléfono.';
            $mensaje_tipo = 'error';
        } else {
            // Normalizar teléfono (solo dígitos)
            $tel_norm = preg_replace('/\D+/', '', $usuario_input);
            
            // Buscar usuario por email o teléfono
            $usuario = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nombre, email 
                 FROM usuarios_gofast 
                 WHERE activo = 1
                   AND (
                         email = %s
                      OR REPLACE(REPLACE(REPLACE(telefono, '+',''), ' ','') ,'-','') = %s
                   )
                 LIMIT 1",
                $usuario_input,
                $tel_norm
            ));

            if ($usuario) {
                // Verificar que tenga email (necesario para enviar recuperación)
                if (empty($usuario->email)) {
                    $mensaje = 'Tu cuenta no tiene un correo electrónico registrado. Contacta con soporte.';
                    $mensaje_tipo = 'error';
                } else {
                    // Generar token único y seguro
                    $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
                    $expira = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expira en 1 hora

                    // Guardar token en la base de datos
                    $wpdb->update(
                        'usuarios_gofast',
                        [
                            'reset_token' => $token,
                            'reset_token_expires' => $expira
                        ],
                        ['id' => $usuario->id],
                        ['%s', '%s'],
                        ['%d']
                    );

                    // Construir URL de recuperación
                    $reset_url = home_url('/recuperar-password?token=' . $token);

                // Enviar email con formato HTML
                $asunto = 'Recuperación de contraseña - GoFast';
                
                // Email en HTML para mejor presentación
                $mensaje_email_html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #F4C524; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #F4C524; color: #000; padding: 12px 24px; 
                  text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .button:hover { background: #e6b91d; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; color: #000;">🚀 GoFast</h1>
        </div>
        <div class="content">
            <h2>¡Hola ' . esc_html($usuario->nombre) . '!</h2>
            <p>Recibimos una solicitud para restablecer tu contraseña en GoFast.</p>
            <p>Para crear una nueva contraseña, haz clic en el siguiente botón (el enlace es válido por 1 hora):</p>
            <div style="text-align: center;">
                <a href="' . esc_url($reset_url) . '" class="button">Restablecer contraseña</a>
            </div>
            <p style="font-size: 12px; color: #666; word-break: break-all;">
                O copia y pega este enlace en tu navegador:<br>
                ' . esc_html($reset_url) . '
            </p>
            <div class="warning">
                <strong>⚠️ Importante:</strong> Si no solicitaste este cambio, puedes ignorar este correo. 
                Tu contraseña permanecerá sin cambios.
            </div>
            <p>¡Gracias por usar GoFast!</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        </div>
    </div>
</body>
</html>
                ';

                // Enviar email usando wp_mail de WordPress
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $enviado = wp_mail(
                    $usuario->email,
                    $asunto,
                    $mensaje_email_html,
                    $headers
                );

                    if ($enviado) {
                        $email_mask = gofast_mask_email($usuario->email);
                        $mensaje = 'Se ha enviado un correo a ' . esc_html($email_mask) . ' con las instrucciones para recuperar tu contraseña. Revisa tu bandeja de entrada (y spam).';
                        $mensaje_tipo = 'success';
                    } else {
                        $mensaje = 'Hubo un error al enviar el correo. Por favor intenta más tarde.';
                        $mensaje_tipo = 'error';
                    }
                }
            } else {
                // Por seguridad, no revelar si el email/teléfono existe o no
                $mensaje = 'Si el correo o teléfono existe en nuestro sistema, recibirás un enlace para recuperar tu contraseña.';
                $mensaje_tipo = 'success';
            }
        }
    }

    /*********************************************
     * PROCESAR RESET DE CONTRASEÑA
     *********************************************/
    if (isset($_POST['gofast_reset_password'])) {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (empty($token) || empty($password) || empty($password2)) {
            $mensaje = 'Todos los campos son obligatorios.';
            $mensaje_tipo = 'error';
            $paso = 'resetear';
        } elseif ($password !== $password2) {
            $mensaje = 'Las contraseñas no coinciden.';
            $mensaje_tipo = 'error';
            $paso = 'resetear';
        } elseif (strlen($password) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
            $mensaje_tipo = 'error';
            $paso = 'resetear';
        } else {
            // Buscar usuario por token
            $usuario = $wpdb->get_row($wpdb->prepare(
                "SELECT id, reset_token_expires 
                 FROM usuarios_gofast 
                 WHERE reset_token = %s 
                 AND activo = 1 
                 LIMIT 1",
                $token
            ));

            if (!$usuario) {
                $mensaje = 'El token no es válido.';
                $mensaje_tipo = 'error';
                $paso = 'solicitar';
            } else {
                // Verificar expiración
                $ahora = gofast_current_time('mysql');
                if ($usuario->reset_token_expires < $ahora) {
                    $mensaje = 'El enlace ha expirado. Por favor solicita uno nuevo.';
                    $mensaje_tipo = 'error';
                    $paso = 'solicitar';
                } else {
                    // Actualizar contraseña
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $actualizado = $wpdb->update(
                        'usuarios_gofast',
                        [
                            'password_hash' => $hash,
                            'reset_token' => null,
                            'reset_token_expires' => null
                        ],
                        ['id' => $usuario->id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );

                    if ($actualizado !== false) {
                        $mensaje = '¡Tu contraseña ha sido actualizada exitosamente! Ya puedes iniciar sesión.';
                        $mensaje_tipo = 'success';
                        $paso = 'solicitar';
                    } else {
                        $mensaje = 'Hubo un error al actualizar la contraseña. Por favor intenta de nuevo.';
                        $mensaje_tipo = 'error';
                        $paso = 'resetear';
                    }
                }
            }
        }
    }

    ob_start();
    ?>

    <div class="gofast-recuperar-container">
        <h2 class="gofast-recuperar-title">
            <?= $paso === 'resetear' ? '🔒 Restablecer contraseña' : '🔑 Recuperar contraseña' ?>
        </h2>

        <?php if ($mensaje): ?>
            <div class="gofast-alert <?= $mensaje_tipo === 'success' ? 'gofast-alert-success' : 'gofast-alert-error' ?>">
                <?php if ($mensaje_tipo === 'success'): ?>✅<?php else: ?>⚠️<?php endif; ?>
                <?= esc_html($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($paso === 'solicitar'): ?>
            <!-- Formulario para solicitar recuperación -->
            <form method="post" action="" class="gofast-recuperar-form">
                <p class="gofast-recuperar-descripcion">
                    Ingresa tu correo electrónico o número de teléfono y te enviaremos un enlace a tu correo para restablecer tu contraseña.
                </p>

                <label>Correo electrónico o Teléfono</label>
                <input type="text" name="usuario" required 
                       placeholder="tu@correo.com o 3001234567" 
                       value="<?= ($mensaje_tipo === 'success') ? '' : (isset($_POST['usuario']) ? esc_attr($_POST['usuario']) : '') ?>">

                <button type="submit" name="gofast_solicitar_reset" class="gofast-btn-action">
                    📧 Enviar enlace de recuperación
                </button>

                <p class="gofast-recuperar-footer">
                    <a href="<?= esc_url(home_url('/auth')) ?>">← Volver al inicio de sesión</a>
                </p>
            </form>

        <?php else: ?>
            <!-- Formulario para resetear contraseña -->
            <form method="post" action="" class="gofast-recuperar-form">
                <input type="hidden" name="token" value="<?= esc_attr($token) ?>">

                <p class="gofast-recuperar-descripcion">
                    Ingresa tu nueva contraseña (mínimo 6 caracteres).
                </p>

                <label>Nueva contraseña</label>
                <div class="gofast-password-wrapper">
                    <input type="password" name="password" id="reset_pass1" required 
                           minlength="6" placeholder="Mínimo 6 caracteres">
                    <button type="button" class="gofast-eye-btn"
                            onclick="gofastTogglePassword('reset_pass1', this)">
                        <svg viewBox="0 0 24 24" class="gofast-eye-icon">
                            <path d="M12 4.5C7 4.5 2.7 8 1 12c1.7 4 6 7.5 11 7.5s9.3-3.5 11-7.5c-1.7-4-6-7.5-11-7.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                        </svg>
                    </button>
                </div>

                <label>Confirmar contraseña</label>
                <div class="gofast-password-wrapper">
                    <input type="password" name="password2" id="reset_pass2" required 
                           minlength="6" placeholder="Repite la contraseña">
                    <button type="button" class="gofast-eye-btn"
                            onclick="gofastTogglePassword('reset_pass2', this)">
                        <svg viewBox="0 0 24 24" class="gofast-eye-icon">
                            <path d="M12 4.5C7 4.5 2.7 8 1 12c1.7 4 6 7.5 11 7.5s9.3-3.5 11-7.5c-1.7-4-6-7.5-11-7.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                        </svg>
                    </button>
                </div>

                <button type="submit" name="gofast_reset_password" class="gofast-btn-action">
                    ✅ Actualizar contraseña
                </button>

                <p class="gofast-recuperar-footer">
                    <a href="<?= esc_url(home_url('/recuperar-password')) ?>">Solicitar nuevo enlace</a> |
                    <a href="<?= esc_url(home_url('/auth')) ?>">Volver al login</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript para mostrar/ocultar contraseña -->
    <script>
    function gofastTogglePassword(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector("svg");

        if (input.type === "password") {
            input.type = "text";
            icon.style.opacity = "0.4";
        } else {
            input.type = "password";
            icon.style.opacity = "1";
        }
    }
    </script>

    <style>
    .gofast-recuperar-container {
        max-width: 450px;
        margin: 40px auto;
        background: #fff;
        padding: 32px;
        border-radius: 12px;
        color: #000;
        border: 1px solid #eee;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        box-sizing: border-box;
    }
    .gofast-recuperar-title {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 20px;
        text-align: center;
    }
    .gofast-recuperar-descripcion {
        color: #666;
        margin-bottom: 20px;
        line-height: 1.6;
        text-align: center;
    }
    .gofast-alert {
        padding: 12px 16px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: 600;
    }
    .gofast-alert-success {
        background: #d4edda;
        border-left: 4px solid #28a745;
        color: #155724;
    }
    .gofast-alert-error {
        background: #ffe5e5;
        border-left: 4px solid #d60000;
        color: #700;
    }
    .gofast-recuperar-form label {
        font-weight: 600;
        margin-top: 16px;
        display: block;
        margin-bottom: 6px;
    }
    .gofast-recuperar-form input[type="text"],
    .gofast-recuperar-form input[type="email"],
    .gofast-recuperar-form input[type="password"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
        box-sizing: border-box;
        margin-bottom: 10px;
    }
    .gofast-password-wrapper {
        position: relative;
        margin-bottom: 10px;
    }
    .gofast-eye-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 4px;
    }
    .gofast-eye-icon {
        width: 22px;
        height: 22px;
        fill: #333;
        transition: opacity .15s;
    }
    .gofast-btn-action {
        width: 100%;
        padding: 14px;
        background: #F4C524;
        border: 0;
        border-radius: 8px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        margin-top: 20px;
    }
    .gofast-btn-action:hover {
        background: #e6b91d;
    }
    .gofast-recuperar-footer {
        text-align: center;
        margin-top: 20px;
        font-size: 14px;
    }
    .gofast-recuperar-footer a {
        color: #0057ff;
        font-weight: 600;
        text-decoration: none;
    }
    .gofast-recuperar-footer a:hover {
        text-decoration: underline;
    }
    </style>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_recuperar_password', 'gofast_recuperar_password_shortcode');

