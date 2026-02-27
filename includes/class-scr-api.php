<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_API {

    private $api_url;
    private $api_key;
    private $api_secret;

    public function __construct() {
        $opts             = get_option( SCR_OPTION_KEY, [] );
        $this->api_url    = trailingslashit( $opts['api_url']    ?? '' );
        $this->api_key    = $opts['api_key']    ?? '';
        $this->api_secret = $opts['api_secret'] ?? '';
    }

    public function enviar( array $datos ): array {

        if ( ! $this->api_url || ! $this->api_key || ! $this->api_secret ) {
            return [ 'success' => false, 'message' => 'SCR Connector: API no configurada.' ];
        }

        $endpoint  = $this->api_url . 'includes/ajax/api_registrar.php';
        $timestamp = time();
        $nonce     = wp_generate_password( 16, false );
        $body      = wp_json_encode( $datos );

        $mensaje = $this->api_key . $timestamp . $nonce . $body;
        $firma   = hash_hmac( 'sha256', $mensaje, $this->api_secret );

        $respuesta = wp_remote_post( $endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-SCR-Key'       => $this->api_key,
                'X-SCR-Timestamp' => $timestamp,
                'X-SCR-Nonce'     => $nonce,
                'X-SCR-Signature' => $firma,
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $respuesta ) ) {
            return [ 'success' => false, 'message' => $respuesta->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $respuesta );
        $json = json_decode( wp_remote_retrieve_body( $respuesta ), true );

        if ( $code === 200 && isset( $json['success'] ) && $json['success'] ) {
            return [ 'success' => true, 'message' => 'Registro enviado correctamente.', 'id' => $json['id'] ?? null ];
        }

        $msg = $json['message'] ?? "HTTP {$code}";
        return [ 'success' => false, 'message' => $msg ];
    }

    public function ping(): array {
        if ( ! $this->api_url || ! $this->api_key || ! $this->api_secret ) {
            return [ 'success' => false, 'message' => 'Faltan credenciales.' ];
        }

        $endpoint  = $this->api_url . 'includes/ajax/api_registrar.php';
        $timestamp = time();
        $nonce     = wp_generate_password( 16, false );
        $body      = wp_json_encode( [ '__ping' => true ] );
        $mensaje   = $this->api_key . $timestamp . $nonce . $body;
        $firma     = hash_hmac( 'sha256', $mensaje, $this->api_secret );

        $respuesta = wp_remote_post( $endpoint, [
            'timeout' => 10,
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-SCR-Key'       => $this->api_key,
                'X-SCR-Timestamp' => $timestamp,
                'X-SCR-Nonce'     => $nonce,
                'X-SCR-Signature' => $firma,
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $respuesta ) ) {
            return [ 'success' => false, 'message' => $respuesta->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $respuesta );
        $json = json_decode( wp_remote_retrieve_body( $respuesta ), true );

        if ( $code === 200 && ( isset( $json['pong'] ) || isset( $json['success'] ) ) ) {
            return [ 'success' => true, 'message' => 'Conexión exitosa con el Sistema.' ];
        }
        return [ 'success' => false, 'message' => "Error HTTP {$code}: " . ( $json['message'] ?? '' ) ];
    }
}
