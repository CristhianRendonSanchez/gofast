<?php
/***************************************************
 * GOFAST – SHORTCODE AUTH (LOGIN + REGISTRO BONITOS)
 ***************************************************/
function gofast_auth_shortcode() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Si ya está logueado → no permitir volver al login
    if (!empty($_SESSION['gofast_user_id'])) {
        wp_redirect(home_url('/'));
        exit;
    }

    $modo = !empty($_GET['registro']) ? 'registro' : 'login';

    // Recuperar error
    $error = '';
    if (!empty($_SESSION['gofast_auth_error'])) {
        $error = $_SESSION['gofast_auth_error'];
        unset($_SESSION['gofast_auth_error']);
    }

    ob_start();
?>
<div class="gofast-auth-container">

    <!-- Mensaje de error -->
    <?php if ($error): ?>
        <div class="gofast-alert-error">⚠️ <?= esc_html($error) ?></div>
    <?php endif; ?>

    <!-- Aviso cookies (solo si ya aceptó antes) -->
    <div class="gofast-cookie-box" id="cookieBox" style="display:none;">
        🍪 Usamos cookies para recordar tu sesión por 30 días.  
        <button onclick="gofastAcceptCookies()" class="gofast-cookie-btn">Aceptar</button>
    </div>

    <!-- LOGIN -->
    <?php if ($modo === 'login'): ?>

        <h2 class="gofast-auth-title">Iniciar sesión</h2>

        <form method="post" action="" id="gofast-login-form">
            <!-- Campo hidden para indicar si aceptó cookies -->
            <input type="hidden" name="gofast_cookies_accepted" id="gofast-cookies-accepted" value="0">
            <!-- Campo hidden para identificar formulario de login -->
            <input type="hidden" name="gofast_login_form" value="1">
            <!-- Campo honeypot anti-bot (debe estar vacío) -->
            <input type="text" name="gofast_extra_field" value="" style="display:none !important;" autocomplete="off" tabindex="-1">
            
            <label>Email o WhatsApp</label>
            <input type="text" name="user" required>

            <label>Contraseña</label>
            <div class="gofast-password-wrapper">
                <input type="password" name="password" id="login_pass" required>
                <button type="button" class="gofast-eye-btn"
                        onclick="gofastTogglePassword('login_pass', this)">
                    <svg viewBox="0 0 24 24" class="gofast-eye-icon">
                        <path d="M12 4.5C7 4.5 2.7 8 1 12c1.7 4 6 7.5 11 7.5s9.3-3.5 11-7.5c-1.7-4-6-7.5-11-7.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                </button>
            </div>

            <div class="gofast-remember-row">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Mantener sesión iniciada 30 días</label>
            </div>

            <button type="submit" name="gofast_login" class="gofast-btn-action">
                🚀 Ingresar
            </button>
        </form>

        <p class="gofast-auth-footer">
            <a href="<?= esc_url(home_url('/recuperar-password')) ?>" class="gofast-forgot-link">¿Olvidaste tu contraseña?</a>
        </p>

        <p class="gofast-auth-footer">
            ¿No tienes cuenta?
            <a href="<?= esc_url(home_url('/auth/?registro=1')) ?>">Regístrate aquí</a>
        </p>

    <!-- REGISTRO -->
    <?php else: ?>

        <h2 class="gofast-auth-title">Crear cuenta</h2>

        <form method="post" action="" id="gofast-registro-form">
            <!-- Campo hidden para indicar si aceptó cookies -->
            <input type="hidden" name="gofast_cookies_accepted" id="gofast-cookies-accepted-reg" value="0">
            <!-- Campo honeypot anti-bot (debe estar vacío) -->
            <input type="text" name="gofast_extra_field" value="" style="display:none !important;" autocomplete="off" tabindex="-1">

            <label>Nombre completo</label>
            <input type="text" name="nombre" required>

            <label>WhatsApp</label>
            <input type="text" name="telefono" required placeholder="Ej: 3001234567" pattern="[0-9]{10,}" title="Mínimo 10 dígitos">

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Contraseña</label>
            <div class="gofast-password-wrapper">
                <input type="password" name="password" id="reg_pass1" required minlength="6" placeholder="Mínimo 6 caracteres">
                <button type="button" class="gofast-eye-btn"
                        onclick="gofastTogglePassword('reg_pass1', this)">
                    <svg viewBox="0 0 24 24" class="gofast-eye-icon">
                        <path d="M12 4.5C7 4.5 2.7 8 1 12c1.7 4 6 7.5 11 7.5s9.3-3.5 11-7.5c-1.7-4-6-7.5-11-7.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                </button>
            </div>

            <label>Repite la contraseña</label>
            <div class="gofast-password-wrapper">
                <input type="password" name="password2" id="reg_pass2" required minlength="6" placeholder="Mínimo 6 caracteres">
                <button type="button" class="gofast-eye-btn"
                        onclick="gofastTogglePassword('reg_pass2', this)">
                    <svg viewBox="0 0 24 24" class="gofast-eye-icon">
                        <path d="M12 4.5C7 4.5 2.7 8 1 12c1.7 4 6 7.5 11 7.5s9.3-3.5 11-7.5c-1.7-4-6-7.5-11-7.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                </button>
            </div>

            <button type="submit" name="gofast_registro" class="gofast-btn-action">
                ✅ Crear cuenta
            </button>
        </form>

        <p class="gofast-auth-footer">
            ¿Ya tienes cuenta?
            <a href="<?= esc_url(home_url('/auth')) ?>">Inicia sesión</a>
        </p>

    <?php endif; ?>
</div>

<!-- JS -->
<script>
/* Mostrar / ocultar contraseña */
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

/* Banner cookies (solo si ya aceptó antes) */
document.addEventListener("DOMContentLoaded", () => {
    const preference = localStorage.getItem("gofast_cookies_preference");
    if (preference === "accepted" && !localStorage.getItem("gofast_cookie_ok")) {
        localStorage.setItem("gofast_cookie_ok", "1");
    }
});

// Función para actualizar campo hidden cuando se aceptan cookies
function gofastAcceptCookies() {
    localStorage.setItem("gofast_cookies_preference", "accepted");
    localStorage.setItem("gofast_cookie_ok", "1");
    document.getElementById("cookieBox").style.display = "none";
    
    // Actualizar campos hidden en formularios
    const acceptedInput = document.getElementById("gofast-cookies-accepted");
    const acceptedInputReg = document.getElementById("gofast-cookies-accepted-reg");
    if (acceptedInput) acceptedInput.value = "1";
    if (acceptedInputReg) acceptedInputReg.value = "1";
}

// Verificar preferencia de cookies antes de enviar formularios
document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById("gofast-login-form");
    const registroForm = document.getElementById("gofast-registro-form");
    
    function updateCookiePreference() {
        const preference = localStorage.getItem("gofast_cookies_preference");
        const accepted = (preference === "accepted") ? "1" : "0";
        
        const acceptedInput = document.getElementById("gofast-cookies-accepted");
        const acceptedInputReg = document.getElementById("gofast-cookies-accepted-reg");
        if (acceptedInput) acceptedInput.value = accepted;
        if (acceptedInputReg) acceptedInputReg.value = accepted;
    }
    
    // Actualizar al cargar
    updateCookiePreference();
    
    // Actualizar cuando cambia la preferencia
    window.addEventListener("storage", updateCookiePreference);
    
    // Actualizar antes de enviar formularios
    if (loginForm) {
        loginForm.addEventListener("submit", updateCookiePreference);
    }
    if (registroForm) {
        registroForm.addEventListener("submit", updateCookiePreference);
    }
});
</script>

<!-- CSS -->
<style>
.gofast-auth-container{
    max-width:420px;
    margin:30px auto;
    background:#fff;
    padding:28px;
    border-radius:12px;
    color:#000;
    border:1px solid #eee;
}
.gofast-auth-title{
    font-size:26px;
    font-weight:700;
    margin-bottom:18px;
}
.gofast-alert-error{
    background:#ffe5e5;
    border-left:5px solid #d60000;
    padding:10px 14px;
    margin-bottom:18px;
    border-radius:8px;
    color:#700;
    font-weight:600;
}
.gofast-cookie-box{
    background:#fff8d1;
    padding:10px;
    border-radius:8px;
    border-left:4px solid #f4c524;
    margin-bottom:15px;
    font-size:14px;
}
.gofast-cookie-btn{
    background:#f4c524;
    border:0;
    padding:6px 12px;
    border-radius:6px;
    cursor:pointer;
    font-weight:700;
    margin-top:6px;
}
label{
    font-weight:600;
    margin-top:12px;
    display:block;
}
input[type="text"],
input[type="email"],
input[type="password"]{
    width:100%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:15px;
    margin-top:4px;
    margin-bottom:10px;
}
.gofast-password-wrapper{
    position:relative;
}
.gofast-eye-btn{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    background:transparent;
    border:none;
    cursor:pointer;
    padding:4px;
}
.gofast-eye-icon{
    width:22px;
    height:22px;
    fill:#333;
    transition:opacity .15s;
}
.gofast-remember-row{
    display:flex;
    align-items:center;
    gap:6px;
    margin:10px 0 18px;
}
.gofast-btn-action{
    width:100%;
    padding:14px;
    background:#F4C524;
    border:0;
    border-radius:8px;
    font-weight:700;
    font-size:18px;
    cursor:pointer;
}
.gofast-btn-action:hover{
    background:#e6b91d;
}
.gofast-auth-footer{
    text-align:center;
    margin-top:18px;
}
.gofast-auth-footer a{
    color:#0057ff;
    font-weight:600;
}
.gofast-forgot-link{
    display:block;
    margin-bottom:8px;
    font-size:14px;
}
</style>

<?php
return ob_get_clean();
}
add_shortcode('gofast_auth', 'gofast_auth_shortcode');

