<?php
/***************************************************
 * GOFAST – UTILIDADES JAVASCRIPT
 * Formato de dinero en campos con clase gofast-money
 ***************************************************/
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

