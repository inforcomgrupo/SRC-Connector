<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_Handler {

    public function registrar() {
        add_action( 'wpcf7_mail_sent',           [ $this, 'cf7_enviado' ] );
        add_action( 'gform_after_submission',     [ $this, 'gf_enviado' ],        10, 2 );
        add_action( 'wpforms_process_complete',   [ $this, 'wpforms_enviado' ],   10, 4 );
        add_action( 'elementor_pro/forms/new_record', [ $this, 'elementor_enviado' ], 10, 2 );
        add_action( 'ninja_forms_after_submission',   [ $this, 'ninja_enviado' ] );
    }

    // ─────────────────────────────────────────────
    // RESOLUCIÓN MULTI-NOMBRE
    // Permite mapear "campo1, campo2" → busca el primero que tenga valor
    // ─────────────────────────────────────────────
    private function resolverCampo( string $mapNombre, callable $getCampo ): ?string {
        // Si no hay coma, búsqueda simple
        if ( strpos( $mapNombre, ',' ) === false ) {
            return $getCampo( trim( $mapNombre ) );
        }

        // Múltiples nombres: busca el primero con valor
        $nombres = array_map( 'trim', explode( ',', $mapNombre ) );
        foreach ( $nombres as $nombre ) {
            if ( $nombre === '' ) continue;
            $val = $getCampo( $nombre );
            if ( $val !== null && $val !== '' ) {
                return $val;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────
    // WEB automático desde HTTP_REFERER o site_url
    // ─────────────────────────────────────────────
    private function obtenerWeb(): string {
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $url = filter_var( $_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL );
            if ( $url ) return $url;
        }
        return get_site_url();
    }

    // ─────────────────────────────────────────────
    // CONSTRUIR PAYLOAD
    // ─────────────────────────────────────────────
    private function construirPayload( array $mapeo, callable $getCampo ): array {
        $payload = [];

        $camposBase = [
            'nombre','apellidos','telefono','correo','asesor','delegado',
            'curso','pais','ciudad','moneda','metodo_pago','ip',
            'fecha','hora','categoria','file_url','formulario_id',
        ];

        foreach ( $camposBase as $campo ) {
            if ( ! empty( $mapeo['campos'][ $campo ] ) ) {
                // Soporta múltiples nombres separados por coma
                $val = $this->resolverCampo( $mapeo['campos'][ $campo ], $getCampo );
                if ( $val !== null && $val !== '' ) {
                    $payload[ $campo ] = sanitize_text_field( $val );
                }
            }
        }

        // web: SIEMPRE automático
        $payload['web'] = $this->obtenerWeb();

        return $payload;
    }

    // ─────────────────────────────────────────────
    // CONTACT FORM 7
    // ─────────────────────────────────────────────
    public function cf7_enviado( $contact_form ) {
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return;

        $form_id = (string) $contact_form->id();
        $mapeo   = $this->buscarMapeo( 'cf7', $form_id );
        if ( ! $mapeo ) return;

        $posted = $submission->get_posted_data();

        $payload = $this->construirPayload( $mapeo, function( $nombre ) use ( $posted ) {
            return isset( $posted[ $nombre ] ) ? (string) $posted[ $nombre ] : null;
        });

        $this->enviarAlSistema( $payload, $mapeo, 'cf7', $form_id );
    }

    // ─────────────────────────────────────────────
    // GRAVITY FORMS
    // ─────────────────────────────────────────────
    public function gf_enviado( $entry, $form ) {
        $form_id = (string) $form['id'];
        $mapeo   = $this->buscarMapeo( 'gravity_forms', $form_id );
        if ( ! $mapeo ) return;

        $campos_por_nombre = [];
        foreach ( $form['fields'] as $field ) {
            if ( ! empty( $field->inputName ) ) {
                $campos_por_nombre[ $field->inputName ] = rgar( $entry, $field->id );
            }
            $label = strtolower( str_replace( ' ', '_', $field->label ) );
            $campos_por_nombre[ $label ] = rgar( $entry, $field->id );
        }

        $payload = $this->construirPayload( $mapeo, function( $nombre ) use ( $campos_por_nombre ) {
            return $campos_por_nombre[ $nombre ] ?? null;
        });

        $this->enviarAlSistema( $payload, $mapeo, 'gravity_forms', $form_id );
    }

    // ─────────────────────────────────────────────
    // WPFORMS
    // ─────────────────────────────────────────────
    public function wpforms_enviado( $fields, $entry, $form_data, $entry_id ) {
        $form_id = (string) $form_data['id'];
        $mapeo   = $this->buscarMapeo( 'wpforms', $form_id );
        if ( ! $mapeo ) return;

        $campos_por_nombre = [];
        foreach ( $fields as $field ) {
            if ( ! empty( $field['name'] ) ) {
                $campos_por_nombre[ $field['name'] ] = $field['value'] ?? '';
            }
        }

        $payload = $this->construirPayload( $mapeo, function( $nombre ) use ( $campos_por_nombre ) {
            return $campos_por_nombre[ $nombre ] ?? null;
        });

        $this->enviarAlSistema( $payload, $mapeo, 'wpforms', $form_id );
    }

    // ─────────────────────────────────────────────
    // ELEMENTOR FORMS
    // ─────────────────────────────────────────────
    public function elementor_enviado( $record, $ajax_handler ) {
        $form_name = $record->get_form_settings( 'form_name' );
        $form_id   = sanitize_title( $form_name );
        $mapeo     = $this->buscarMapeo( 'elementor', $form_id );
        if ( ! $mapeo ) return;

        $raw_fields        = $record->get( 'fields' );
        $campos_por_nombre = [];
        foreach ( $raw_fields as $id => $field ) {
            $campos_por_nombre[ $id ] = $field['value'] ?? '';
        }

        $payload = $this->construirPayload( $mapeo, function( $nombre ) use ( $campos_por_nombre ) {
            return $campos_por_nombre[ $nombre ] ?? null;
        });

        $this->enviarAlSistema( $payload, $mapeo, 'elementor', $form_id );
    }

    // ─────────────────────────────────────────────
    // NINJA FORMS
    // ─────────────────────────────────────────────
    public function ninja_enviado( $form_data ) {
        $form_id = (string) $form_data['form_id'];
        $mapeo   = $this->buscarMapeo( 'ninja_forms', $form_id );
        if ( ! $mapeo ) return;

        $campos_por_nombre = [];
        foreach ( $form_data['fields'] as $field ) {
            $campos_por_nombre[ $field['key'] ] = $field['value'] ?? '';
        }

        $payload = $this->construirPayload( $mapeo, function( $nombre ) use ( $campos_por_nombre ) {
            return $campos_por_nombre[ $nombre ] ?? null;
        });

        $this->enviarAlSistema( $payload, $mapeo, 'ninja_forms', $form_id );
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ───────────────────────���─────────────────────

    private function buscarMapeo( string $plugin, string $form_id ): ?array {
        $opts   = get_option( SCR_OPTION_KEY, [] );
        $mapeos = $opts['mapeos'] ?? [];

        foreach ( $mapeos as $mapeo ) {
            if (
                ! empty( $mapeo['activo'] ) &&
                ( $mapeo['form_plugin'] ?? '' ) === $plugin &&
                (string) ( $mapeo['form_id'] ?? '' ) === $form_id
            ) {
                return $mapeo;
            }
        }
        return null;
    }

    private function enviarAlSistema( array $payload, array $mapeo, string $plugin, string $form_id ): void {
        $api = new SCR_API();
        $res = $api->enviar( $payload );

        SCR_Logger::guardar([
            'form_id'     => $form_id,
            'form_plugin' => $plugin,
            'form_nombre' => $mapeo['nombre'] ?? '',
            'estado'      => $res['success'] ? 'exito' : 'error',
            'mensaje'     => $res['message']  ?? '',
            'payload'     => wp_json_encode( $payload ),
            'respuesta'   => wp_json_encode( $res ),
        ]);
    }
}