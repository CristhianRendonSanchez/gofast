<?php

/**
 * show_admin_bar
 */
add_filter('show_admin_bar', function() {
    return false;
}, 99999);

add_action('init', function () {
    // Evita que WordPress agregue el espacio superior para la admin bar
    remove_action('wp_head', '_admin_bar_bump_cb');

    // Elimina cualquier instancia del admin bar si se carga por error
    if (is_admin_bar_showing()) {
        global $wp_admin_bar;
        if (is_object($wp_admin_bar)) {
            $wp_admin_bar->remove_node('site-name');
            $wp_admin_bar->remove_node('edit');
            $wp_admin_bar->remove_node('dashboard');
        }
    }
});
