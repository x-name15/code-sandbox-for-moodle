/* global monaco */
define(['jquery', 'core/notification'], function($, notification) {
    return {
        /**
         * Inicializa el entorno Monaco Editor.
         *
         * @param {object} params Parámetros pasados desde PHP (IDs, rutas, textos).
         */
        init: function(params) {
            var container = document.getElementById(params.containerId);
            var script = document.createElement('script');
            script.src = params.monacoloader;
            script.type = 'text/javascript';
            script.async = true;

            script.onload = function() {
                require.config({paths: {'vs': params.monacobase}});

                require(['vs/editor/editor.main'], function() {
                    $(container).empty();

                    var initialValue = params.code || ("// " + (params.msg_ready || "Write your code here...") + "\n");

                    var editor = monaco.editor.create(container, {
                        value: initialValue,
                        language: params.language,
                        theme: 'vs-dark',
                        automaticLayout: true,
                        minimap: {enabled: false},
                        readOnly: params.readOnly || false
                    });

                    var consoleOut = $('#console-output');
                    if (params.initialOutput) {
                        consoleOut.text(params.initialOutput);
                    }

                    $('#run-btn').off('click').on('click', function() {
                        var code = editor.getValue();
                        executeCode(code, params, 'run');
                    });

                    $('#submit-btn').off('click').on('click', function() {
                        if (confirm(params.msg_confirm)) {
                            var code = editor.getValue();
                            executeCode(code, params, 'submit');
                        }
                    });

                    // ===== PERSISTENCIA DE INPUTS =====
                    var inputs = $('#console-input');
                    var storageKey = 'moodle_codesandbox_inputs_' + params.cmid;
                    // Cargar valor guardado
                    var savedInputs = localStorage.getItem(storageKey);
                    if (savedInputs !== null) {
                        inputs.val(savedInputs);
                    }
                    // Guardar al cambiar
                    inputs.on('input propertychange', function() {
                        localStorage.setItem(storageKey, $(this).val());
                    });
                });
            };
            document.body.appendChild(script);

            /**
             * Ejecuta el código enviándolo al backend via AJAX.
             * Soporta polling asíncrono para esperar resultados del worker.
             *
             * @param {string} code El código fuente escrito por el alumno.
             * @param {object} params Parámetros de configuración (cmid, sesskey, mensajes).
             * @param {string} action La acción a realizar: 'run' (probar) o 'submit' (entregar).
             */
            function executeCode(code, params, action) {
                var btnRun = $('#run-btn');
                var btnSubmit = $('#submit-btn');
                var consoleOut = $('#console-output');
                var badge = $('#status-badge');
                var inputdata = $('#console-input').val();  // ← SIEMPRE capturar inputs
                var pollInterval = null;
                var pollCount = 0;
                var maxPolls = 30;

                btnRun.prop('disabled', true);
                btnSubmit.prop('disabled', true);

                if (action === 'run') {
                    consoleOut.text(params.msg_running);
                } else {
                    consoleOut.text(params.msg_sending);
                }

                $.ajax({
                    url: M.cfg.wwwroot + '/mod/codesandbox/run.php',
                    type: 'POST',
                    data: {
                        cmid: params.cmid,
                        code: code,
                        action: action,
                        inputdata: inputdata,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(res) {
                        try {
                            var data = (typeof res === 'string') ? JSON.parse(res) : res;

                            if (data.status === 'error') {
                                consoleOut.text("> ERROR: " + (data.message || params.msg_err_server));
                                notification.addNotification({
                                    message: data.message || params.msg_err_server,
                                    type: 'error'
                                });
                                btnRun.prop('disabled', false);
                                btnSubmit.prop('disabled', false);
                                return;
                            }

                            if (data.status === 'queued' && data.job_id) {
                                var jobIdShort = data.job_id.substring(0, 8);
                                consoleOut.text(params.msg_running + "\n" + params.msg_job_queued.replace('{$a}', jobIdShort));
                                startPolling(data.job_id, action);
                            } else if (data.output) {
                                consoleOut.text(data.output);
                                handleCompletion(action, data);
                            }

                        } catch (e) {
                            consoleOut.text(params.msg_err_server + "\n" + e.message);
                            btnRun.prop('disabled', false);
                            btnSubmit.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        consoleOut.text(params.msg_err_conn + " " + error);
                        btnRun.prop('disabled', false);
                        btnSubmit.prop('disabled', false);
                    }
                });

                /**
                 * Inicia el polling para consultar el estado del job.
                 *
                 * @param {string} jobId El identificador del job en ejecución.
                 * @param {string} actionType El tipo de acción: 'run' o 'submit'.
                 */
                function startPolling(jobId, actionType) {
                    pollInterval = setInterval(function() {
                        pollCount++;

                        var dots = '.'.repeat((pollCount % 3) + 1);
                        consoleOut.text(params.msg_running + dots + "\n" + params.msg_waiting_results);

                        if (pollCount >= maxPolls) {
                            clearInterval(pollInterval);
                            consoleOut.text("> " + params.msg_timeout);
                            btnRun.prop('disabled', false);
                            btnSubmit.prop('disabled', false);
                            return;
                        }

                        $.ajax({
                            url: M.cfg.wwwroot + '/mod/codesandbox/status.php',
                            type: 'GET',
                            data: { job_id: jobId   },
                            success: function(res) {
                                var statusData = (typeof res === 'string') ? JSON.parse(res) : res;

                                if (['completed', 'failed', 'submitted'].includes(statusData.status)) {
                                    clearInterval(pollInterval);

                                    var output = statusData.stdout || '';
                                    if (statusData.stderr) {
                                        output += "\n" + params.msg_stderr_label + "\n" + statusData.stderr;
                                    }
                                    if (statusData.exitcode !== null && statusData.exitcode !== 0) {
                                        output += "\n" + params.msg_exitcode_label.replace('{$a}', statusData.exitcode);
                                    }
                                    consoleOut.text(output || params.msg_no_output);

                                    handleCompletion(actionType, statusData);

                                    reloadBlockSidebar(actionType);
                                }
                            },
                            error: function() {
                                // Error handling for polling is not required
                            }
                        });
                    }, 500);
                }

                /**
                 * Maneja la finalización del job
                 *
                 * @param {string} actionType El tipo de acción: 'run' o 'submit'.
                 */
                function handleCompletion(actionType) {
                    if (actionType === 'submit') {
                        badge.removeClass('badge-warning').addClass('badge-success').text(params.status_submitted);
                        notification.addNotification({
                            message: params.msg_success,
                            type: 'success'
                        });
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        btnRun.prop('disabled', false);
                        btnSubmit.prop('disabled', false);
                    }
                }
                /**
                 * Recarga solo el bloque CodesAndbox en el sidebar.
                 * Si existe el bloque, hace un refresh silencioso de su contenido.
                 *
                 * @param {string} actionType El tipo de acción: 'run' o 'submit'.
                 */
                function reloadBlockSidebar(actionType) {
                    if (actionType === 'submit') {
                        return;
                    }

                    // Intentar recargar el bloque vía AJAX para no perder el estado de la consola
                    var blockElement = $('.block_codesandbox');
                    if (blockElement.length > 0) {
                        blockElement.load(window.location.href + " .block_codesandbox > *");
                    }
                }
            }
        }
    };
});