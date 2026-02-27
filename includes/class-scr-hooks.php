<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_Hooks {

    private $settings;
    private $mapeos;   // array de mapeos por form_id

    public function __construct() {
        $opts          = get_option( SCR_OPTION_KEY, [] );
        $this->settings = $opts;
        $this->mapeos   = $opts['mapeos'] ?? [];
    }

    public function registrar() {
        // ── Contact Form 7 ──
        add_action( 'wpcf7_mail_sent',             [ $this, 'cf7_enviado' ] );

        // ── Gravity Forms ──
        add_action( 'gform_after_submission',       [ $this, 'gf_enviado' ], 10, 2 );

        // ── WPForms ──
        add_action( 'wpforms_process_complete',     [ $this, 'wpforms_enviado' ], 10, 4 );

        // ── Elementor Forms ──
        add_action( 'elementor_pro/forms/new_record', [ $this, 'elementor_enviado' ], 10, 2 );

        // ── Ninja Forms ──
        add_action( 'ninja_forms_after_submission', [ $this, 'ninjaforms_enviado' ] );
    }

    // ─────────────────────────────────────────────────────────
    // CONTACT FORM 7
    // ─────────────────────────────────────────────────────────
    public function cf7_enviado( $contact_form ) {
        $form_id  = (string) $contact_form->id();
        $mapeo    = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return;
        $data = $submission->get_posted_data();

        $this->procesar( $form_id, 'cf7', $contact_form->title(), $data, $mapeo );
    }

    // ─────────────────────────────────────────────────────────
    // GRAVITY FORMS
    // ─────────────────────────────────────────────────────────
    public function gf_enviado( $entry, $form ) {
        $form_id = (string) $form['id'];
        $mapeo   = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        // Aplanar campos de Gravity Forms
        $data = [];
        foreach ( $form['fields'] as $field ) {
            $value = rgar( $entry, (string) $field->id );
            $label = sanitize_title( $field->label );
            $data[ $label ] = $value;
        }
        $this->procesar( $form_id, 'gravity_forms', $form['title'], $data, $mapeo );
    }

    // ─────────────────────────────────────────────────────────
    // WPFORMS
    // ─────────────────────────────────────────────────────────
    public function wpforms_enviado( $fields, $entry, $form_data, $entry_id ) {
        $form_id = (string) $form_data['id'];
        $mapeo   = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        $data = [];
        foreach ( $fields as $field ) {
            $label = sanitize_title( $field['name'] ?? 'campo_' . $field['id'] );
            $data[ $label ] = $field['value'] ?? '';
        }
        $this->procesar( $form_id, 'wpforms', $form_data['settings']['form_title'] ?? $form_id, $data, $mapeo );
    }

    // ────────────────────────────────────────────────────��────
    // ELEMENTOR FORMS
    // ─────────────────────────────────────────────────────────
    public function elementor_enviado( $record, $ajax_handler ) {
        $form_id   = $record->get_form_settings( 'id' );
        $form_name = $record->get_form_settings( 'form_name' ) ?: $form_id;
        $mapeo     = $this->obtener_mapeo( (string) $form_id );
        if ( ! $mapeo ) return;

        $data  = [];
        $raw   = $record->get( 'fields' );
        foreach ( $raw as $field_id => $field ) {
            $data[ sanitize_title( $field['title'] ?? $field_id ) ] = $field['value'] ?? '';
        }
        $this->procesar( (string) $form_id, 'elementor', $form_name, $data, $mapeo );
    }

    // ─────────────────────────────────────────────────────────
    // NINJA FORMS
    // ─────────────────────────────────────────────────────────
    public function ninjaforms_enviado( $form_data ) {
        $form_id = (string) ( $form_data['form_id'] ?? '' );
        $mapeo   = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        $data = [];
        foreach ( $form_data['fields'] as $field ) {
            $key = sanitize_title( $field['settings']['label'] ?? $field['id'] );
            $data[ $key ] = $field['value'] ?? '';
        }
        $this->procesar( $form_id, 'ninja_forms', $form_data['settings']['title'] ?? $form_id, $data, $mapeo );
    }

    // ─────────────────────────────────────────────────────────
    // PROCESO CENTRAL
    // ─────────────────────────────────────────────────────────
    private function procesar( $form_id, $plugin, $form_nombre, array $raw_data, array $mapeo ) {

        // 1. Campos estándar del sistema
        $campos_sistema = [
            'nombre', 'apellidos', 'telefono', 'correo', 'asesor', 'delegado',
            'curso', 'pais', 'ciudad', 'moneda', 'metodo_pago',
            'categoria', 'file_url',
        ];

        $payload       = [];
        $campos_usados = [];

        // Mapear campos configurados
        foreach ( $mapeo['campos'] ?? [] as $campo_sistema => $campo_form ) {
            if ( ! $campo_form ) continue;
            $valor = $this->extraer_valor( $raw_data, $campo_form );
            if ( $valor !== null ) {
                $payload[ $campo_sistema ] = sanitize_text_field( $valor );
                $campos_usados[]           = $campo_form;
            }
        }

        // 2. Campos extra: todo lo que NO fue mapeado
        $campos_extra = [];
        foreach ( $raw_data as $clave => $valor ) {
            // Omitir campos internos de CF7 / WP
            if ( strpos( $clave, '_wpcf7' ) === 0 ) continue;
            if ( strpos( $clave, '_wp' ) === 0 ) continue;
            if ( in_array( $clave, $campos_usados, true ) ) continue;
            if ( is_array( $valor ) ) $valor = implode( ', ', $valor );
            $campos_extra[ sanitize_text_field( $clave ) ] = sanitize_textarea_field( (string) $valor );
        }

        // 3. Agregar campos automáticos
        $payload['formulario_id'] = sanitize_text_field( $form_id );
        $payload['web']           = sanitize_url( home_url() );
        $payload['ip']            = sanitize_text_field( $this->obtener_ip() );
        $payload['campos_extra']  = $campos_extra;

        // 4. Enviar a la API
        $api      = new SCR_API();
        $resultado = $api->enviar( $payload );

        // 5. Log
        $estado  = $resultado['success'] ? 'exito' : 'error';
        $mensaje = $resultado['message'];
        SCR_Logger::log( $form_nombre . ' (ID:' . $form_id . ')', $plugin, $estado, $mensaje, $payload, $resultado );
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene el mapeo para un form_id dado.
     * Devuelve null si el formulario no está configurado o está deshabilitado.
     */
    private function obtener_mapeo( string $form_id ): ?array {
        foreach ( $this->mapeos as $m ) {
            if ( (string) $m['form_id'] === $form_id && ! empty( $m['activo'] ) ) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Extrae un valor de $raw_data buscando por nombre exacto o similar.
     */
    private function extraer_valor( array $data, string $campo ) {
        if ( isset( $data[ $campo ] ) ) {
            $val = $data[ $campo ];
            return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
        }
        // Búsqueda insensible
        foreach ( $data as $k => $v ) {
            if ( strtolower( $k ) === strtolower( $campo ) ) {
                return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
            }
        }
        return null;
    }

    /**
     * Obtiene la IP real del visitante.
     */
    private function obtener_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
            }
        }
        return '';
    }
}