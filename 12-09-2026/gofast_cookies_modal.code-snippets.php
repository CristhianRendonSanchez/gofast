<?php

/**
 * gofast_cookies_modal
 */
/***************************************************
 * GOFAST – MODAL DE COOKIES (Aceptar/Rechazar)
 * Aparece automáticamente si no se ha aceptado antes
 ***************************************************/

function gofast_cookies_modal() {
    // Solo mostrar si no se ha aceptado/rechazado antes
    ob_start();
    ?>
    
<div id="gofast-cookies-modal" style="display:none;">
    <div class="gofast-cookies-overlay"></div>
    <div class="gofast-cookies-modal-content">
        <div class="gofast-cookies-header">
            <h3>🍪 Política de Cookies</h3>
        </div>
        
        <div class="gofast-cookies-body">
            <p>
                Utilizamos cookies para mejorar tu experiencia en nuestro sitio web. 
                Las cookies nos permiten recordar tu sesión por <strong>30 días</strong> para que 
                no tengas que iniciar sesión cada vez que nos visites.
            </p>
            
            <div class="gofast-cookies-info">
                <h4>¿Qué cookies utilizamos?</h4>
                <ul>
                    <li><strong>Cookie de sesión persistente</strong>: Recuerda tu sesión por 30 días</li>
                    <li><strong>Cookie de preferencias</strong>: Guarda tu elección sobre las cookies</li>
                </ul>
            </div>
            
            <p class="gofast-cookies-note">
                Puedes cambiar tu preferencia en cualquier momento. 
                Si rechazas las cookies, tendrás que iniciar sesión cada vez que visites el sitio.
            </p>
        </div>
        
        <div class="gofast-cookies-actions">
            <button type="button" class="gofast-cookies-btn-accept" onclick="gofastAcceptCookies()">
                ✅ Aceptar todas las cookies
            </button>
            <button type="button" class="gofast-cookies-btn-reject" onclick="gofastRejectCookies()">
                ❌ Rechazar cookies
            </button>
        </div>
    </div>
</div>

<style>
/* Overlay oscuro de fondo */
.gofast-cookies-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 999998;
    backdrop-filter: blur(4px);
}

/* Contenedor del modal */
.gofast-cookies-modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    z-index: 999999;
    animation: gofastModalSlideIn 0.3s ease-out;
}

@keyframes gofastModalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

/* Header del modal */
.gofast-cookies-header {
    padding: 24px 24px 16px;
    border-bottom: 2px solid #f0f0f0;
}

.gofast-cookies-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

/* Body del modal */
.gofast-cookies-body {
    padding: 20px 24px;
    color: #555;
    line-height: 1.6;
}

.gofast-cookies-body p {
    margin: 0 0 16px 0;
    font-size: 15px;
}

.gofast-cookies-body strong {
    color: #F4C524;
    font-weight: 600;
}

.gofast-cookies-info {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    margin: 16px 0;
}

.gofast-cookies-info h4 {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #333;
}

.gofast-cookies-info ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
}

.gofast-cookies-info li {
    margin: 8px 0;
    font-size: 14px;
}

.gofast-cookies-note {
    font-size: 13px;
    color: #777;
    font-style: italic;
    margin-top: 16px;
}

/* Acciones del modal */
.gofast-cookies-actions {
    padding: 16px 24px 24px;
    display: flex;
    gap: 12px;
    border-top: 2px solid #f0f0f0;
}

.gofast-cookies-btn-accept,
.gofast-cookies-btn-reject {
    flex: 1;
    padding: 14px 20px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.gofast-cookies-btn-accept {
    background: #F4C524;
    color: #000;
}

.gofast-cookies-btn-accept:hover {
    background: #e6b91d;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(244, 197, 36, 0.3);
}

.gofast-cookies-btn-reject {
    background: #f0f0f0;
    color: #666;
}

.gofast-cookies-btn-reject:hover {
    background: #e0e0e0;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 600px) {
    .gofast-cookies-modal-content {
        width: 95%;
        max-height: 85vh;
    }
    
    .gofast-cookies-header h3 {
        font-size: 20px;
    }
    
    .gofast-cookies-actions {
        flex-direction: column;
    }
    
    .gofast-cookies-btn-accept,
    .gofast-cookies-btn-reject {
        width: 100%;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    // Verificar si ya se ha aceptado/rechazado
    const cookiePreference = localStorage.getItem('gofast_cookies_preference');
    
    // Si no hay preferencia guardada, mostrar modal
    if (!cookiePreference) {
        const modal = document.getElementById('gofast-cookies-modal');
        if (modal) {
            // Mostrar modal después de un pequeño delay para mejor UX
            setTimeout(function() {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevenir scroll
            }, 500);
        }
    }
    
    // Función para aceptar cookies
    window.gofastAcceptCookies = function() {
        localStorage.setItem('gofast_cookies_preference', 'accepted');
        localStorage.setItem('gofast_cookie_ok', '1'); // Compatibilidad con código existente
        
        // Cerrar modal
        const modal = document.getElementById('gofast-cookies-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restaurar scroll
        }
        
        // Si el usuario ya está logueado, intentar crear cookie ahora
        // (para usuarios que se registraron antes de aceptar cookies)
        setTimeout(function() {
            if (typeof gofastCreateCookieIfLoggedIn === 'function') {
                gofastCreateCookieIfLoggedIn();
            }
        }, 100);
        
        // Mostrar mensaje de confirmación
        console.log('✅ Cookies aceptadas. Tu sesión se recordará por 30 días.');
    };
    
    // Función para rechazar cookies
    window.gofastRejectCookies = function() {
        localStorage.setItem('gofast_cookies_preference', 'rejected');
        
        // Eliminar cookie persistente si existe
        document.cookie = "gofast_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        
        // Cerrar modal
        const modal = document.getElementById('gofast-cookies-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restaurar scroll
        }
        
        // Mostrar mensaje
        console.log('❌ Cookies rechazadas. Tu sesión no se recordará.');
        
        // Opcional: Mostrar notificación al usuario
        alert('Has rechazado las cookies. Tendrás que iniciar sesión cada vez que visites el sitio.');
    };
    
    // Función para crear cookie si el usuario ya está logueado
    // (se llama después de aceptar cookies)
    window.gofastCreateCookieIfLoggedIn = function() {
        // Si el usuario ya está logueado, recargar para crear cookie
        // El servidor detectará que aceptó cookies y creará la cookie
        if (document.body.classList.contains('gofast-user-logged-in') || 
            document.querySelector('.gofast-welcome')) {
            // Usuario está logueado, recargar para crear cookie
            window.location.reload();
        }
    };
    
    // Agregar clase al body si el usuario está logueado (para detectar)
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si hay indicadores de usuario logueado
        const welcomeMsg = document.querySelector('.gofast-welcome');
        const logoutLink = document.querySelector('a[href*="gofast_logout"]');
        if (welcomeMsg || logoutLink) {
            document.body.classList.add('gofast-user-logged-in');
        }
    });
    
    // Cerrar modal al hacer clic en el overlay (opcional)
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('gofast-cookies-modal');
        const overlay = modal ? modal.querySelector('.gofast-cookies-overlay') : null;
        
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                // No cerrar al hacer clic en overlay (forzar decisión)
                // Si quieres permitir cerrar, descomenta:
                // gofastRejectCookies();
            });
        }
    });
})();
</script>

    <?php
    return ob_get_clean();
}

// Agregar el modal al footer de todas las páginas
add_action('wp_footer', function() {
    echo gofast_cookies_modal();
}, 999);
