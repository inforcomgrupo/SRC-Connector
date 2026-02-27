<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCR_Admin {

    public function registrar() {
        add_action( 'admin_menu',                     [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts',          [ $this, 'assets' ] );
        add_action( 'wp_ajax_scr_guardar_config',     [ $this, 'ajax_guardar_config' ] );
        add_action( 'wp_ajax_scr_ping',               [ $this, 'ajax_ping' ] );
        add_action( 'wp_ajax_scr_obtener_logs',       [ $this, 'ajax_obtener_logs' ] );
        add_action( 'wp_ajax_scr_limpiar_logs',       [ $this, 'ajax_limpiar_logs' ] );
        add_action( 'wp_ajax_scr_reenviar_log',       [ $this, 'ajax_reenviar_log' ] );
        add_action( 'wp_ajax_scr_obtener_mapeos',     [ $this, 'ajax_obtener_mapeos' ] );
        add_action( 'wp_ajax_scr_guardar_mapeo',      [ $this, 'ajax_guardar_mapeo' ] );
        add_action( 'wp_ajax_scr_eliminar_mapeo',     [ $this, 'ajax_eliminar_mapeo' ] );
    }

    public function menu() {
        add_menu_page(
            'SCR Connector', 'SCR Connector', 'manage_options',
            'scr-connector', [ $this, 'pagina_config' ],
            'dashicons-share-alt2', 80
        );
        add_submenu_page( 'scr-connector', 'Configuración',     'Configuración',     'manage_options', 'scr-connector', [ $this, 'pagina_config' ] );
        add_submenu_page( 'scr-connector', 'Mapeo Formularios', 'Mapeo Formularios', 'manage_options', 'scr-mapeos',    [ $this, 'pagina_mapeos' ] );
        add_submenu_page( 'scr-connector', 'Logs de Envío',     'Logs de Envío',     'manage_options', 'scr-logs',      [ $this, 'pagina_logs' ] );
    }

    public function assets( $hook ) {
        $slugs = [ 'scr-connector', 'scr-mapeos', 'scr-logs' ];
        $cargar = false;
        foreach ( $slugs as $slug ) {
            if ( strpos( $hook, $slug ) !== false ) { $cargar = true; break; }
        }
        if ( ! $cargar ) return;

        wp_enqueue_style(  'scr-admin-css', SCR_PLUGIN_URL . 'assets/css/scr-admin.css', [], SCR_VERSION );
        wp_enqueue_script( 'scr-admin-js',  SCR_PLUGIN_URL . 'assets/js/scr-admin.js',  [ 'jquery' ], SCR_VERSION, true );
        wp_localize_script( 'scr-admin-js', 'SCR', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'scr_nonce' ),
        ] );
    }

    // ─────────────────────────────────────────────
    // PÁGINAS
    // ─────────────────────────────────────────────

    public function pagina_config() {
        $opts = get_option( SCR_OPTION_KEY, [] );
        ?>
        <div class="wrap scr-wrap">
            <h1><span class="dashicons dashicons-share-alt2"></span> SCR Connector — Configuración</h1>
            <div class="scr-card">
                <h2>🔗 Conexión con el Sistema</h2>
                <p class="scr-desc">Ingresa los datos que encontrarás en <strong>Opciones de Sistema → API Keys</strong>.</p>
                <table class="form-table scr-form-table">
                    <tr>
                        <th><label for="scr_api_url">URL del Sistema</label></th>
                        <td>
                            <input type="url" id="scr_api_url" class="regular-text" value="<?php echo esc_attr( $opts['api_url'] ?? '' ); ?>" placeholder="https://tudominio.com/sistema/">
                            <p class="description">URL completa donde está instalado el Sistema (con barra final).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="scr_api_key">API Key</label></th>
                        <td><input type="text" id="scr_api_key" class="regular-text scr-mono" value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="scr_api_secret">API Secret</label></th>
                        <td>
                            <div class="scr-secret-wrap">
                                <input type="password" id="scr_api_secret" class="regular-text scr-mono" value="<?php echo esc_attr( $opts['api_secret'] ?? '' ); ?>">
                                <button type="button" class="button scr-toggle-secret" data-target="scr_api_secret">👁</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="scr_logs_dias">Retención de Logs</label></th>
                        <td>
                            <select id="scr_logs_dias">
                                <?php foreach ( [7,15,30,60,90] as $d ): ?>
                                    <option value="<?php echo $d; ?>" <?php selected( $opts['logs_dias'] ?? 30, $d ); ?>><?php echo $d; ?> días</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <div class="scr-actions">
                    <button id="scr_btn_guardar" class="button button-primary">💾 Guardar Configuración</button>
                    <button id="scr_btn_ping"    class="button button-secondary">🔌 Probar Conexión</button>
                    <span id="scr_ping_result" class="scr-ping-result"></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function pagina_mapeos() {
        ?>
        <div class="wrap scr-wrap">
            <h1><span class="dashicons dashicons-list-view"></span> SCR Connector — Mapeo de Formularios</h1>

            <div class="scr-card">
                <h2>➕ Agregar / Editar Mapeo</h2>
                <p class="scr-desc">Escribe el <strong>nombre exacto del campo</strong> (atributo <code>name</code> del input) tal como aparece en tu formulario.</p>

                <div class="scr-mapeo-form">
                    <div class="scr-row">
                        <div class="scr-col">
                            <label>Plugin de Formulario</label>
                            <select id="scr_form_plugin">
                                <option value="cf7">Contact Form 7</option>
                                <option value="gravity_forms">Gravity Forms</option>
                                <option value="wpforms">WPForms</option>
                                <option value="elementor">Elementor Forms</option>
                                <option value="ninja_forms">Ninja Forms</option>
                            </select>
                        </div>
                        <div class="scr-col">
                            <label>ID del Formulario</label>
                            <input type="text" id="scr_form_id" class="regular-text" placeholder="Ej: 123">
                        </div>
                        <div class="scr-col">
                            <label>Nombre (referencia)</label>
                            <input type="text" id="scr_form_nombre" class="regular-text" placeholder="Ej: Formulario Maestría">
                        </div>
                        <div class="scr-col scr-col-toggle">
                            <label>Activo</label>
                            <label class="scr-toggle">
                                <input type="checkbox" id="scr_form_activo" checked>
                                <span class="scr-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="scr-mapeo-campos">
                        <h3>🗺️ Mapeo de Campos</h3>
                        <p class="scr-desc">Escribe el nombre exacto del campo de tu formulario para cada columna del Sistema.</p>
                        <div class="scr-mapeo-grid" id="scr_mapeo_grid">
                            <?php
                            $campos = [
                                'nombre'        => [ 'Nombre',          'Ej: your-name' ],
                                'apellidos'     => [ 'Apellidos',       'Ej: your-apellidos' ],
                                'telefono'      => [ 'Teléfono',        'Ej: your-phone' ],
                                'correo'        => [ 'Correo',          'Ej: your-email' ],
                                'asesor'        => [ 'Asesor',          'Ej: asesor' ],
                                'delegado'      => [ 'Delegado',        'Ej: delegado' ],
                                'curso'         => [ 'Curso',           'Ej: curso' ],
                                'pais'          => [ 'País',            'Ej: pais' ],
                                'ciudad'        => [ 'Ciudad',          'Ej: ciudad' ],
                                'moneda'        => [ 'Moneda',          'Ej: moneda' ],
                                'metodo_pago'   => [ 'Método de Pago',  'Ej: metodo-pago' ],
                                'ip'            => [ 'IP',              'Ej: ip-usuario' ],
                                'fecha'         => [ 'Fecha',           'Ej: fecha-envio' ],
                                'hora'          => [ 'Hora',            'Ej: hora-envio' ],
                                'categoria'     => [ 'Categoría',       'Ej: categoria' ],
                                'file_url'      => [ 'File / URL',      'Ej: archivo-url' ],
                                'formulario_id' => [ 'ID (Formulario)', 'Ej: nombre-del-form' ],
                            ];
                            foreach ( $campos as $key => [ $label, $placeholder ] ): ?>
                                <div class="scr-mapeo-item">
                                    <label>
                                        <?php echo esc_html( $label ); ?>
                                        <span class="scr-sistema">→ <?php echo esc_html( $key ); ?></span>
                                    </label>
                                    <input
                                        type="text"
                                        class="scr-campo-form"
                                        data-campo="<?php echo esc_attr( $key ); ?>"
                                        placeholder="<?php echo esc_attr( $placeholder ); ?>"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="scr-auto-campos">
                            <h4>⚙️ Campos generados automáticamente</h4>
                            <p class="scr-desc">Estos campos los rellena el plugin — <strong>no necesitas mapearlos</strong>.</p>
                            <div class="scr-auto-grid">
                                <div class="scr-auto-item"><span class="scr-sistema">web</span> URL de la página del formulario</div>
                                <div class="scr-auto-item"><span class="scr-sistema">ip</span> IP del visitante (si no la mapeas)</div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="scr_mapeo_edit_index" value="">
                    <div class="scr-actions">
                        <button id="scr_btn_guardar_mapeo" class="button button-primary">💾 Guardar Mapeo</button>
                        <button id="scr_btn_cancelar_mapeo" class="button" style="display:none;">✖ Cancelar</button>
                    </div>
                </div>
            </div>

            <div class="scr-card">
                <h2>📋 Formularios Configurados</h2>
                <div id="scr_mapeos_lista"><em>Cargando...</em></div>
            </div>
        </div>
        <?php
    }

    public function pagina_logs() {
        ?>
        <div class="wrap scr-wrap">
            <h1><span class="dashicons dashicons-list-view"></span> SCR Connector — Logs de Envío</h1>

            <div class="scr-card">
                <h2>🔍 Filtros</h2>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Estado</label>
                        <select id="scr_log_filtro_estado" style="width:140px;">
                            <option value="">Todos</option>
                            <option value="exito">✅ Éxito</option>
                            <option value="error">❌ Error</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Formulario</label>
                        <input type="text" id="scr_log_filtro_form" class="regular-text" placeholder="Nombre o ID" style="width:220px;">
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button id="scr_btn_buscar_logs" class="button button-primary">🔍 Buscar</button>
                        <button id="scr_btn_limpiar_logs" class="button button-secondary">🗑 Limpiar Antiguos</button>
                    </div>
                </div>
            </div>

            <div class="scr-card">
                <div id="scr_logs_tabla"><em>Cargando logs...</em></div>
                <div id="scr_logs_paginacion" style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;"></div>
            </div>
        </div>
        <style>
            #scr_logs_tabla .scr-log-exito { color:#059669; font-weight:600; }
            #scr_logs_tabla .scr-log-error { color:#dc2626; font-weight:600; }
            .scr-pag-btn.activa { background:#2271b1 !important; color:#fff !important; border-color:#2271b1 !important; }
            .scr-detail-btn { color:#2271b1; cursor:pointer; text-decoration:underline; font-size:11px; }
        </style>
        <?php
    }

    // ─────────────────────────────────────────────
    // AJAX
    // ─────────────────────────────────────────────

    public function ajax_guardar_config() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $opts = get_option( SCR_OPTION_KEY, [] );
        $opts['api_url']    = trailingslashit( sanitize_url( $_POST['api_url'] ?? '' ) );
        $opts['api_key']    = sanitize_text_field( $_POST['api_key']    ?? '' );
        $opts['api_secret'] = sanitize_text_field( $_POST['api_secret'] ?? '' );
        $opts['logs_dias']  = (int) ( $_POST['logs_dias'] ?? 30 );
        update_option( SCR_OPTION_KEY, $opts );
        wp_send_json_success( 'Configuración guardada.' );
    }

    public function ajax_ping() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $api = new SCR_API();
        $res = $api->ping();
        if ( $res['success'] ) wp_send_json_success( $res['message'] );
        else wp_send_json_error( $res['message'] );
    }

    public function ajax_obtener_logs() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $pagina = (int) ( $_POST['pagina'] ?? 1 );
        $estado = sanitize_text_field( $_POST['estado'] ?? '' );
        $form   = sanitize_text_field( $_POST['form']   ?? '' );
        $result = SCR_Logger::obtener( $pagina, 50, $estado, $form );
        wp_send_json_success( $result );
    }

    public function ajax_limpiar_logs() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $opts = get_option( SCR_OPTION_KEY, [] );
        SCR_Logger::limpiar( $opts['logs_dias'] ?? 30 );
        wp_send_json_success( 'Logs antiguos eliminados.' );
    }

    public function ajax_reenviar_log() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        global $wpdb;
        $id    = (int) ( $_POST['log_id'] ?? 0 );
        $tabla = $wpdb->prefix . SCR_LOGS_TABLE;
        $log   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d", $id ) );
        if ( ! $log ) wp_send_json_error( 'Log no encontrado.' );
        $payload = json_decode( $log->payload, true );
        $api     = new SCR_API();
        $res     = $api->enviar( $payload );
        $estado  = $res['success'] ? 'exito' : 'error';
        $wpdb->update( $tabla, [ 'estado' => $estado, 'mensaje' => $res['message'] ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
        if ( $res['success'] ) wp_send_json_success( 'Reenviado correctamente.' );
        else wp_send_json_error( $res['message'] );
    }

    public function ajax_obtener_mapeos() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $opts = get_option( SCR_OPTION_KEY, [] );
        wp_send_json_success( $opts['mapeos'] ?? [] );
    }

    public function ajax_guardar_mapeo() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );

        $opts   = get_option( SCR_OPTION_KEY, [] );
        $mapeos = $opts['mapeos'] ?? [];

        $campos_raw = $_POST['campos'] ?? [];
        $campos     = [];
        foreach ( $campos_raw as $k => $v ) {
            $ck = sanitize_key( $k );
            $cv = sanitize_text_field( $v );
            if ( $cv !== '' ) $campos[ $ck ] = $cv;
        }

        $nuevo = [
            'form_id'     => sanitize_text_field( $_POST['form_id']     ?? '' ),
            'form_plugin' => sanitize_text_field( $_POST['form_plugin'] ?? 'cf7' ),
            'nombre'      => sanitize_text_field( $_POST['nombre']      ?? '' ),
            'activo'      => ( isset( $_POST['activo'] ) && intval( $_POST['activo'] ) === 1 ), // ← CORREGIDO
            'campos'      => $campos,
        ];

        if ( empty( $nuevo['form_id'] ) ) {
            wp_send_json_error( 'El ID del formulario no puede estar vacío.' );
        }

        $index = $_POST['edit_index'] ?? '';
        if ( $index !== '' && isset( $mapeos[ (int) $index ] ) ) {
            $mapeos[ (int) $index ] = $nuevo;
            $msg = 'Mapeo actualizado.';
        } else {
            $mapeos[] = $nuevo;
            $msg = 'Mapeo guardado.';
        }

        $opts['mapeos'] = array_values( $mapeos );
        update_option( SCR_OPTION_KEY, $opts );
        wp_send_json_success( $msg );
    }

    public function ajax_eliminar_mapeo() {
        check_ajax_referer( 'scr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );
        $opts   = get_option( SCR_OPTION_KEY, [] );
        $mapeos = $opts['mapeos'] ?? [];
        $index  = (int) ( $_POST['index'] ?? -1 );
        if ( isset( $mapeos[ $index ] ) ) {
            array_splice( $mapeos, $index, 1 );
            $opts['mapeos'] = array_values( $mapeos );
            update_option( SCR_OPTION_KEY, $opts );
            wp_send_json_success( 'Mapeo eliminado.' );
        }
        wp_send_json_error( 'Mapeo no encontrado.' );
    }
}
