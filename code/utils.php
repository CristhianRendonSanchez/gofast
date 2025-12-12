<?php
/***************************************************
 * GOFAST – UTILIDADES
 * Formato de dinero en campos con clase gofast-money
 * Funciones helper para fechas en zona horaria de Colombia
 ***************************************************/

// Configurar zona horaria de Colombia globalmente
if (!function_exists('gofast_set_timezone')) {
    function gofast_set_timezone() {
        date_default_timezone_set('America/Bogota');
    }
    gofast_set_timezone();
}

/**
 * Obtener fecha/hora actual en zona horaria de Colombia
 * @param string $format Formato de fecha (por defecto 'Y-m-d H:i:s')
 * @return string Fecha formateada en zona horaria de Colombia
 */
if (!function_exists('gofast_date')) {
    function gofast_date($format = 'Y-m-d H:i:s') {
        $timezone = new DateTimeZone('America/Bogota');
        $datetime = new DateTime('now', $timezone);
        return $datetime->format($format);
    }
}

/**
 * Obtener fecha actual en formato Y-m-d (zona horaria de Colombia)
 * @return string Fecha en formato Y-m-d
 */
if (!function_exists('gofast_date_today')) {
    function gofast_date_today() {
        return gofast_date('Y-m-d');
    }
}

/**
 * Obtener fecha/hora actual en formato MySQL (zona horaria de Colombia)
 * @return string Fecha en formato MySQL (Y-m-d H:i:s)
 */
if (!function_exists('gofast_date_mysql')) {
    function gofast_date_mysql() {
        return gofast_date('Y-m-d H:i:s');
    }
}

/**
 * Formatear fecha con strtotime respetando zona horaria de Colombia
 * @param string $date Fecha a formatear
 * @param string $format Formato de salida (por defecto 'Y-m-d H:i:s')
 * @return string Fecha formateada
 */
if (!function_exists('gofast_date_format')) {
    function gofast_date_format($date, $format = 'Y-m-d H:i:s') {
        $timezone = new DateTimeZone('America/Bogota');
        $datetime = new DateTime($date, $timezone);
        return $datetime->format($format);
    }
}

/**
 * Obtener fecha/hora actual en zona horaria de Colombia (equivalente a current_time de WordPress)
 * @param string $type Tipo de formato ('mysql', 'timestamp', o formato de fecha como 'Y-m-d')
 * @return string|int Fecha formateada o timestamp
 */
if (!function_exists('gofast_current_time')) {
    function gofast_current_time($type = 'mysql') {
        $timezone = new DateTimeZone('America/Bogota');
        $datetime = new DateTime('now', $timezone);
        
        if ($type === 'mysql') {
            return $datetime->format('Y-m-d H:i:s');
        } elseif ($type === 'timestamp') {
            return $datetime->getTimestamp();
        } else {
            // Cualquier otro formato de fecha
            return $datetime->format($type);
        }
    }
}

/**
 * Limpiar y validar número de WhatsApp
 * @param mixed $whatsapp Valor del WhatsApp (puede ser string o int)
 * @return string WhatsApp limpio o cadena vacía si es inválido
 */
if (!function_exists('gofast_clean_whatsapp')) {
    function gofast_clean_whatsapp($whatsapp) {
        // Convertir a string y limpiar
        $whatsapp = (string) $whatsapp;
        // Remover todo excepto números
        $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
        // Valores problemáticos comunes (límites de INT)
        $valores_invalidos = ['2147483647', '0', '2147483648', '-2147483648'];
        if (empty($whatsapp) || in_array($whatsapp, $valores_invalidos)) {
            return '';
        }
        return $whatsapp;
    }
}

// Protección global para toggleOtro - se ejecuta inmediatamente
add_action('wp_head', function() {
?>
<script>
// Protección global para toggleOtro - previene errores en páginas donde los elementos no existen
// Se ejecuta inmediatamente para proteger antes de que otros scripts se carguen
(function() {
    // Crear una función segura por defecto
    if (typeof window.toggleOtro === 'undefined') {
        window.toggleOtro = function() {
            // Función vacía segura - no hacer nada si los elementos no existen
            return;
        };
    }
    
    // Interceptar setTimeout para proteger llamadas a toggleOtro
    const originalSetTimeout = window.setTimeout;
    window.setTimeout = function(func, delay) {
        if (typeof func === 'function') {
            const funcStr = func.toString();
            if (funcStr.includes('toggleOtro')) {
                // Verificar si los elementos existen antes de ejecutar
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                const inputOtro = document.getElementById("tipo_otro");
                if (!tipoSelect || !wrapperOtro || !inputOtro) {
                    // No ejecutar si los elementos no existen
                    return null;
                }
            }
        }
        return originalSetTimeout.apply(this, arguments);
    };
})();
</script>
<?php
}, 1);

add_action('wp_footer', function() {
?>
<script>
// Proteger toggleOtro nuevamente en el footer por si se redefine después
(function() {
    if (typeof window.toggleOtro === 'function') {
        const originalToggleOtro = window.toggleOtro;
        window.toggleOtro = function() {
            try {
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                const inputOtro = document.getElementById("tipo_otro");
                
                // Solo ejecutar si todos los elementos existen
                if (tipoSelect && wrapperOtro && inputOtro) {
                    return originalToggleOtro.apply(this, arguments);
                }
            } catch(e) {
                // Silenciar cualquier error
                return;
            }
        };
    }
})();

document.addEventListener("input", function(e) {
    if (e.target.classList.contains("gofast-money")) {
        let raw = e.target.value.replace(/[^\d]/g, "");
        e.target.value = raw ? "$ " + Number(raw).toLocaleString("es-CO") : "";
    }
});
</script>
<?php
});

