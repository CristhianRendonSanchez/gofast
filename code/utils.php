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

add_action('wp_footer', function() {
?>
<script>
document.addEventListener("input", function(e) {
    if (e.target.classList.contains("gofast-money")) {
        let raw = e.target.value.replace(/[^\d]/g, "");
        e.target.value = raw ? "$ " + Number(raw).toLocaleString("es-CO") : "";
    }
});
</script>
<?php
});

