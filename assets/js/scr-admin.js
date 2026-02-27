/* SCR Connector — Admin JS */
jQuery(function($){
    'use strict';

    var paginaActual = 1;

    /* ── TOGGLE SECRET ── */
    $(document).on('click', '.scr-toggle-secret', function(){
        var target = $('#' + $(this).data('target'));
        target.attr('type', target.attr('type') === 'password' ? 'text' : 'password');
    });

    /* ──────────────────────────────────────────
       CONFIGURACIÓN
    ────────────────────────────────────────── */
    $('#scr_btn_guardar').on('click', function(){
        var $btn = $(this).prop('disabled', true).text('Guardando...');
        $.post(SCR.ajax_url, {
            action:     'scr_guardar_config',
            nonce:      SCR.nonce,
            api_url:    $('#scr_api_url').val(),
            api_key:    $('#scr_api_key').val(),
            api_secret: $('#scr_api_secret').val(),
            logs_dias:  $('#scr_logs_dias').val(),
        }, function(res){
            $btn.prop('disabled', false).text('💾 Guardar Configuración');
            scrToast(res.success ? res.data : res.data, res.success ? 'ok' : 'err');
        });
    });

    $('#scr_btn_ping').on('click', function(){
        var $r = $('#scr_ping_result').removeClass('ok err').text('⏳ Probando...');
        $.post(SCR.ajax_url, { action: 'scr_ping', nonce: SCR.nonce }, function(res){
            $r.addClass(res.success ? 'ok' : 'err').text(res.success ? '✅ ' + res.data : '❌ ' + res.data);
        });
    });

    /* ──────────────────────────────────────────
       MAPEOS
    ────────────────────────────────────────── */
    function cargarMapeos(){
        $.post(SCR.ajax_url, { action: 'scr_obtener_mapeos', nonce: SCR.nonce }, function(res){
            if (!res.success) return;
            renderMapeos(res.data);
        });
    }

    function renderMapeos(mapeos){
        var $cont = $('#scr_mapeos_lista');
        if (!mapeos || !mapeos.length) {
            $cont.html('<p><em>No hay formularios configurados aún.</em></p>');
            return;
        }
        var html = '<table class="scr-tabla"><thead><tr>'
            + '<th>Plugin</th><th>ID Form</th><th>Nombre</th>'
            + '<th>Estado</th><th>Campos Mapeados</th><th>Acciones</th>'
            + '</tr></thead><tbody>';
        $.each(mapeos, function(i, m){
            var activo  = m.activo ? '<span class="scr-badge scr-badge-on">Activo</span>' : '<span class="scr-badge scr-badge-off">Inactivo</span>';
            var plugin  = '<span class="scr-badge scr-badge-plugin">' + escHtml(m.form_plugin) + '</span>';
            var nCampos = Object.values(m.campos || {}).filter(function(v){ return v; }).length;
            html += '<tr>'
                + '<td>' + plugin + '</td>'
                + '<td><code>' + escHtml(m.form_id) + '</code></td>'
                + '<td>' + escHtml(m.nombre) + '</td>'
                + '<td>' + activo + '</td>'
                + '<td>' + nCampos + ' campos</td>'
                + '<td>'
                + '<button class="button scr-btn-xs scr-btn-editar" data-index="' + i + '">✏️ Editar</button> '
                + '<button class="button button-link-delete scr-btn-xs scr-btn-eliminar" data-index="' + i + '">🗑 Eliminar</button>'
                + '</td></tr>';
        });
        html += '</tbody></table>';
        $cont.html(html);
    }

    /* Guardar mapeo */
    $('#scr_btn_guardar_mapeo').on('click', function(){
        var campos = {};
        $('.scr-campo-form').each(function(){
            campos[$(this).data('campo')] = $(this).val().trim();
        });

        $.post(SCR.ajax_url, {
            action:      'scr_guardar_mapeo',
            nonce:       SCR.nonce,
            form_plugin: $('#scr_form_plugin').val(),
            form_id:     $('#scr_form_id').val().trim(),
            nombre:      $('#scr_form_nombre').val().trim(),
            activo:      $('#scr_form_activo').is(':checked') ? 1 : 0,
            campos:      campos,
            edit_index:  $('#scr_mapeo_edit_index').val(),
        }, function(res){
            scrToast(res.success ? res.data : res.data, res.success ? 'ok' : 'err');
            if (res.success) {
                limpiarFormMapeo();
                cargarMapeos();
            }
        });
    });

    /* Editar */
    $(document).on('click', '.scr-btn-editar', function(){
        var index = $(this).data('index');
        $.post(SCR.ajax_url, { action: 'scr_obtener_mapeos', nonce: SCR.nonce }, function(res){
            if (!res.success) return;
            var m = res.data[index];
            if (!m) return;
            $('#scr_form_plugin').val(m.form_plugin);
            $('#scr_form_id').val(m.form_id);
            $('#scr_form_nombre').val(m.nombre);
            $('#scr_form_activo').prop('checked', m.activo);
            $('#scr_mapeo_edit_index').val(index);
            $.each(m.campos || {}, function(campo, valor){
                $('.scr-campo-form[data-campo="' + campo + '"]').val(valor);
            });
            $('#scr_btn_cancelar_mapeo').show();
            $('html, body').animate({ scrollTop: 0 }, 400);
        });
    });

    /* Cancelar edición */
    $('#scr_btn_cancelar_mapeo').on('click', function(){
        limpiarFormMapeo();
    });

    /* Eliminar */
    $(document).on('click', '.scr-btn-eliminar', function(){
        if (!confirm('¿Eliminar este mapeo?')) return;
        var index = $(this).data('index');
        $.post(SCR.ajax_url, { action: 'scr_eliminar_mapeo', nonce: SCR.nonce, index: index }, function(res){
            scrToast(res.success ? res.data : res.data, res.success ? 'ok' : 'err');
            if (res.success) cargarMapeos();
        });
    });

    function limpiarFormMapeo(){
        $('#scr_form_id, #scr_form_nombre').val('');
        $('#scr_form_plugin').val('cf7');
        $('#scr_form_activo').prop('checked', true);
        $('#scr_mapeo_edit_index').val('');
        $('.scr-campo-form').val('');
        $('#scr_btn_cancelar_mapeo').hide();
    }

    /* ──────────────────────────────────────────
       LOGS
    ────────────────────────────────────────── */
    function cargarLogs(pagina){
        pagina = pagina || 1;
        paginaActual = pagina;
        var $cont = $('#scr_logs_tabla').html('<em>Cargando...</em>');

        $.post(SCR.ajax_url, {
            action:  'scr_obtener_logs',
            nonce:   SCR.nonce,
            pagina:  pagina,
            estado:  $('#scr_log_filtro_estado').val(),
            form:    $('#scr_log_filtro_form').val(),
        }, function(res){
            if (!res.success) { $cont.html('<p>Error cargando logs.</p>'); return; }
            renderLogs(res.data);
        });
    }

    function renderLogs(data){
        var rows = data.rows || [];
        if (!rows.length) {
            $('#scr_logs_tabla').html('<p><em>No hay logs registrados.</em></p>');
            $('#scr_logs_paginacion').html('');
            return;
        }
        var html = '<table class="scr-tabla"><thead><tr>'
            + '<th>#</th><th>Fecha</th><th>Formulario</th><th>Plugin</th>'
            + '<th>Estado</th><th>Mensaje</th><th>Acciones</th>'
            + '</tr></thead><tbody>';
        $.each(rows, function(_, r){
            var estadoClass = r.estado === 'exito' ? 'scr-log-exito' : 'scr-log-error';
            var estadoIcon  = r.estado === 'exito' ? '✅' : '❌';
            html += '<tr>'
                + '<td>' + r.id + '</td>'
                + '<td style="white-space:nowrap;font-size:11px;">' + escHtml(r.created_at) + '</td>'
                + '<td>' + escHtml(r.formulario) + '</td>'
                + '<td><span class="scr-badge scr-badge-plugin">' + escHtml(r.form_plugin) + '</span></td>'
                + '<td class="' + estadoClass + '">' + estadoIcon + ' ' + escHtml(r.estado) + '</td>'
                + '<td style="font-size:11px;">' + escHtml(r.mensaje || '') + '</td>'
                + '<td style="white-space:nowrap;">'
                + '<span class="scr-detail-btn" data-payload=\'' + escAttr(r.payload) + '\' data-respuesta=\'' + escAttr(r.respuesta) + '\'>Ver detalle</span>'
                + (r.estado !== 'exito' ? ' | <button class="button scr-btn-xs scr-btn-reenviar" data-id="' + r.id + '">🔄 Reenviar</button>' : '')
                + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#scr_logs_tabla').html(html);

        // Paginación
        var total   = data.total || 0;
        var paginas = Math.ceil(total / 50);
        var pHtml   = '';
        for (var p = 1; p <= paginas; p++) {
            pHtml += '<button class="button scr-pag-btn' + (p === paginaActual ? ' activa' : '') + '" data-p="' + p + '">' + p + '</button>';
        }
        $('#scr_logs_paginacion').html(pHtml + '<span style="font-size:11px;color:#6b7280;margin-left:8px;">Total: ' + total + '</span>');
    }

    $(document).on('click', '.scr-pag-btn', function(){
        cargarLogs( $(this).data('p') );
    });

    $('#scr_btn_buscar_logs').on('click', function(){ cargarLogs(1); });

    $('#scr_btn_limpiar_logs').on('click', function(){
        if (!confirm('¿Eliminar logs antiguos?')) return;
        $.post(SCR.ajax_url, { action: 'scr_limpiar_logs', nonce: SCR.nonce }, function(res){
            scrToast(res.success ? res.data : res.data, res.success ? 'ok' : 'err');
            if (res.success) cargarLogs(1);
        });
    });

    /* Reenviar */
    $(document).on('click', '.scr-btn-reenviar', function(){
        var $btn = $(this).prop('disabled', true).text('Reenviando...');
        $.post(SCR.ajax_url, { action: 'scr_reenviar_log', nonce: SCR.nonce, log_id: $(this).data('id') }, function(res){
            scrToast(res.success ? res.data : res.data, res.success ? 'ok' : 'err');
            $btn.prop('disabled', false).text('🔄 Reenviar');
            cargarLogs(paginaActual);
        });
    });

    /* Modal detalle */
    $('body').append('<div class="scr-modal-overlay" id="scrModalOverlay"><div class="scr-modal"><span class="scr-modal-close" id="scrModalClose">✕</span><h3>Detalle del Envío</h3><div id="scrModalBody"></div></div></div>');

    $(document).on('click', '.scr-detail-btn', function(){
        var payload   = $(this).data('payload');
        var respuesta = $(this).data('respuesta');
        var pJson = '{}', rJson = '{}';
        try { pJson = JSON.stringify(JSON.parse(payload   || '{}'), null, 2); } catch(e) { pJson = payload; }
        try { rJson = JSON.stringify(JSON.parse(respuesta || '{}'), null, 2); } catch(e) { rJson = respuesta; }
        $('#scrModalBody').html(
            '<strong>Payload enviado:</strong><pre>' + escHtml(pJson) + '</pre>'
            + '<strong>Respuesta del Sistema:</strong><pre>' + escHtml(rJson) + '</pre>'
        );
        $('#scrModalOverlay').addClass('active');
    });

    $(document).on('click', '#scrModalClose, #scrModalOverlay', function(e){
        if ($(e.target).is('#scrModalOverlay') || $(e.target).is('#scrModalClose')) {
            $('#scrModalOverlay').removeClass('active');
        }
    });

    /* ──────────────────────────────────────────
       HELPERS
    ────────────────────────────────────────── */
    function scrToast(msg, tipo){
        var color = tipo === 'ok' ? '#059669' : '#dc2626';
        var $t = $('<div style="position:fixed;bottom:30px;right:30px;background:' + color + ';color:#fff;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);">' + escHtml(msg) + '</div>');
        $('body').append($t);
        setTimeout(function(){ $t.fadeOut(300, function(){ $t.remove(); }); }, 3500);
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function escAttr(str) {
        return String(str || '').replace(/'/g, '&#39;');
    }

    /* ── Auto-carga por página ── */
    if ($('#scr_mapeos_lista').length) cargarMapeos();
    if ($('#scr_logs_tabla').length)   cargarLogs(1);
});