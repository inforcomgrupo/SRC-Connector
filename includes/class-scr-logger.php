<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_Logger {

    public static function guardar( array $data ): void {
        global $wpdb;
        $tabla = $wpdb->prefix . SCR_LOGS_TABLE;

        $wpdb->insert(
            $tabla,
            [
                'form_id'     => $data['form_id']    ?? '',
                'form_plugin' => $data['form_plugin'] ?? '',
                'form_nombre' => $data['form_nombre'] ?? '',
                'estado'      => $data['estado']      ?? 'error',
                'mensaje'     => mb_substr( $data['mensaje'] ?? '', 0, 1000 ),
                'payload'     => $data['payload']     ?? '{}',
                'respuesta'   => $data['respuesta']   ?? '{}',
                'fecha'       => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    // Alias usado por class-scr-hooks.php
    public static function log( string $form_nombre, string $plugin, string $estado, string $mensaje, array $payload, array $respuesta ): void {
        self::guardar([
            'form_id'     => '',
            'form_plugin' => $plugin,
            'form_nombre' => $form_nombre,
            'estado'      => $estado,
            'mensaje'     => $mensaje,
            'payload'     => wp_json_encode( $payload ),
            'respuesta'   => wp_json_encode( $respuesta ),
        ]);
    }

    public static function obtener( int $pagina = 1, int $por_pagina = 50, string $estado = '', string $form = '' ): array {
        global $wpdb;
        $tabla  = $wpdb->prefix . SCR_LOGS_TABLE;
        $offset = ( $pagina - 1 ) * $por_pagina;

        $where  = '1=1';
        $params = [];

        if ( $estado !== '' ) {
            $where   .= ' AND estado = %s';
            $params[] = $estado;
        }
        if ( $form !== '' ) {
            $where   .= ' AND (form_nombre LIKE %s OR form_id LIKE %s)';
            $params[] = '%' . $wpdb->esc_like( $form ) . '%';
            $params[] = '%' . $wpdb->esc_like( $form ) . '%';
        }

        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE {$where}", ...$params )
                : "SELECT COUNT(*) FROM {$tabla}"
        );

        $sql = $params
            ? $wpdb->prepare( "SELECT * FROM {$tabla} WHERE {$where} ORDER BY fecha DESC LIMIT %d OFFSET %d", array_merge( $params, [ $por_pagina, $offset ] ) )
            : $wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY fecha DESC LIMIT %d OFFSET %d", $por_pagina, $offset );

        $logs = $wpdb->get_results( $sql, ARRAY_A ) ?: [];

        return [
            'rows'    => $logs,    // ← CORREGIDO: era 'logs', el JS espera 'rows'
            'total'   => $total,
            'paginas' => (int) ceil( $total / $por_pagina ),
            'pagina'  => $pagina,
        ];
    }

    public static function limpiar( int $dias = 30 ): void {
        global $wpdb;
        $tabla = $wpdb->prefix . SCR_LOGS_TABLE;
        $fecha = date( 'Y-m-d H:i:s', strtotime( "-{$dias} days" ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$tabla} WHERE fecha < %s", $fecha ) );
    }
}
