<?php
/**
 * Plugin Name: SCR Connector
 * Description: Conecta formularios WordPress con el Sistema de Control de Registros
 * Version:     1.0.1
 * Author:      Inforcom Grupo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SCR_VERSION',    '1.0.1' );
define( 'SCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCR_OPTION_KEY', 'scr_connector_options' );
define( 'SCR_LOGS_TABLE', 'scr_logs' );

// ── AUTOLOAD ──
require_once SCR_PLUGIN_DIR . 'includes/class-scr-api.php';
require_once SCR_PLUGIN_DIR . 'includes/class-scr-logger.php';
require_once SCR_PLUGIN_DIR . 'includes/class-scr-hooks.php';   // ← CORREGIDO
require_once SCR_PLUGIN_DIR . 'includes/class-scr-admin.php';

// ── ACTIVACIÓN: crear tabla de logs ──
register_activation_hook( __FILE__, 'scr_activar' );
function scr_activar() {
    global $wpdb;
    $tabla   = $wpdb->prefix . SCR_LOGS_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$tabla} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        form_id     VARCHAR(100)    NOT NULL DEFAULT '',
        form_plugin VARCHAR(50)     NOT NULL DEFAULT '',
        form_nombre VARCHAR(200)    NOT NULL DEFAULT '',
        estado      VARCHAR(20)     NOT NULL DEFAULT 'error',
        mensaje     VARCHAR(1000)   NOT NULL DEFAULT '',
        payload     LONGTEXT        NOT NULL,
        respuesta   LONGTEXT        NOT NULL,
        fecha       DATETIME        NOT NULL,
        PRIMARY KEY (id),
        KEY idx_estado (estado),
        KEY idx_fecha  (fecha)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// ── INIT ──
add_action( 'plugins_loaded', function() {
    ( new SCR_Admin() )->registrar();
    ( new SCR_Hooks() )->registrar();   // ← CORREGIDO
});
