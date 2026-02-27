<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_Hooks {

    private $settings;
    private $mapeos;

    public function __construct() {
        $opts           = get_option( SCR_OPTION_KEY, [] );
        $this->settings = $opts;
        $this->mapeos   = $opts['mapeos'] ?? [];
    }

    public function registrar() {
        add_action( 'wpcf7_mail_sent',                [ $this, 'cf7_enviado' ] );
        add_action( 'gform_after_submission',          [ $this, 'gf_enviado' ],          10, 2 );
        add_action( 'wpforms_process_complete',        [ $this, 'wpforms_enviado' ],      10, 4 );
        add_action( 'elementor_pro/forms/new_record',  [ $this, 'elementor_enviado' ],    10, 2 );
        add_action( 'ninja_forms_after_submission',    [ $this, 'ninjaforms_enviado' ] );
    }

    public function cf7_enviado( $contact_form ) {
        $form_id = (string) $contact_form->id();
        $mapeo   = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return;
        $data = $submission->get_posted_data();

        $this->procesar( $form_id, 'cf7', $contact_form->title(), $data, $mapeo );
    }

    public function gf_enviado( $entry, $form ) {
        $form_id = (string) $form['id'];
        $mapeo   = $this->obtener_mapeo( $form_id );
        if ( ! $mapeo ) return;

        $data = [];
        foreach ( $form['fields'] as $field ) {
            $value = rgar( $entry, (string) $field->id );
            $label = sanitize_title( $field->label );
            $data[ $label ] = $value;
        }
        $this->procesar( $form_id, 'gravity_forms', $form['title'], $data, $mapeo );
    }

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

    public function elementor_enviado( $record, $ajax_handler ) {
        $form_id   = $record->get_form_settings( 'id' );
        $form_name = $record->get_form_settings( 'form_name' ) ?: $form_id;
        $mapeo     = $this->obtener_mapeo( (string) $form_id );
        if ( ! $mapeo ) return;

        $data = [];
        $raw  = $record->get( 'fields' );
        foreach ( $raw as $field_id => $field ) {
            $data[ sanitize_title( $field['title'] ?? $field_id ) ] = $field['value'] ?? '';
        }
        $this->procesar( (string) $form_id, 'elementor', $form_name, $data, $mapeo );
    }

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

    private function procesar( $form_id, $plugin, $form_nombre, array $raw_data, array $mapeo ) {
        $payload       = [];
        $campos_usados = [];

        foreach ( $mapeo['campos'] ?? [] as $campo_sistema => $campo_form ) {
            if ( ! $campo_form ) continue;
            $valor = $this->extraer_valor( $raw_data, $campo_form );
            if ( $valor !== null ) {
                $payload[ $campo_sistema ] = sanitize_text_field( $valor );
                $campos_usados[]           = $campo_form;
            }
        }

        $campos_extra = [];
        foreach ( $raw_data as $clave => $valor ) {
            if ( strpos( $clave, '_wpcf7' ) === 0 ) continue;
            if ( strpos( $clave, '_wp' ) === 0 ) continue;
            if ( in_array( $clave, $campos_usados, true ) ) continue;
            if ( is_array( $valor ) ) $valor = implode( ', ', $valor );
            $campos_extra[ sanitize_text_field( $clave ) ] = sanitize_textarea_field( (string) $valor );
        }

        $payload['formulario_id'] = sanitize_text_field( $form_id );
        $payload['web']           = sanitize_url( home_url() );
        $payload['ip']            = sanitize_text_field( $this->obtener_ip() );
        $payload['campos_extra']  = $campos_extra;

        $api       = new SCR_API();
        $resultado = $api->enviar( $payload );

        $estado  = $resultado['success'] ? 'exito' : 'error';
        $mensaje = $resultado['message'];
        SCR_Logger::log( $form_nombre . ' (ID:' . $form_id . ')', $plugin, $estado, $mensaje, $payload, $resultado );
    }

    private function obtener_mapeo( string $form_id ): ?array {
        foreach ( $this->mapeos as $m ) {
            if ( (string) $m['form_id'] === $form_id && ! empty( $m['activo'] ) ) {
                return $m;
            }
        }
        return null;
    }

    private function extraer_valor( array $data, string $campo ) {
        if ( isset( $data[ $campo ] ) ) {
            $val = $data[ $campo ];
            return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
        }
        foreach ( $data as $k => $v ) {
            if ( strtolower( $k ) === strtolower( $campo ) ) {
                return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
            }
        }
        return null;
    }

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
