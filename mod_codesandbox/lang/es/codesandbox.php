<?php
$string['pluginname'] = 'Code Sandbox';
$string['modulename'] = 'Code Sandbox';
$string['modulenameplural'] = 'Code Sandboxes';
$string['modulename_help'] = 'Un entorno interactivo y seguro de ejecución de código mediante Docker y RabbitMQ.';
$string['codesandboxname'] = 'Nombre del Sandbox';
$string['pluginadministration'] = 'Administración de Code Sandbox';

// Navegación y Vista de Profesor
$string['teacher_view_submissions'] = 'Ver Entregas de Alumnos';
$string['submissions_header'] = 'Entregas recibidas';
$string['no_submissions'] = 'No hay entregas enviadas todavía.';
$string['btn_back_editor'] = 'Volver al Editor';

// Interfaz del IDE
$string['status_not_submitted'] = 'No entregado';
$string['status_submitted'] = 'Entregado';
$string['btn_run_label'] = 'Probar';
$string['btn_run_title'] = 'Ejecutar código en terminal';
$string['btn_submit_label'] = 'Entregar';
$string['btn_submit_title'] = 'Enviar tarea al profesor';
$string['terminal_header'] = 'CONSOLA / SALIDA';
$string['console_inputs'] = 'Entradas';
$string['console_inputs_placeholder'] = 'Ingresa las entradas aquí (una por línea)';
$string['console_inputs_help'] = 'Proporciona entradas para tu código (un valor por línea).';
$string['btn_clear'] = 'Limpiar';
$string['terminal_cleaned'] = '> Limpio.';
$string['console_ready'] = '> Listo para programar en <strong>{$a}</strong>.';

// Columnas de tabla
$string['table_student'] = 'Alumno';
$string['table_date'] = 'Fecha de Entrega';
$string['table_actions'] = 'Acciones';
$string['btn_review'] = 'Revisar Código';
$string['btn_cancel'] = 'Cancelar';

// Mensajes JS y Restricciones
$string['msg_confirm_submit'] = "Al entregar no podrás realizar más cambios.\n¿Estás seguro de que deseas enviar tu tarea?";
$string['msg_submitted_success'] = 'Tarea entregada correctamente';
$string['msg_running'] = 'Ejecutando código...';
$string['msg_sending'] = 'Enviando tarea...';
$string['msg_err_server'] = 'Error del servidor. Por favor, inténtalo de nuevo.';
$string['msg_err_conn'] = 'Error de conexión:';
$string['already_submitted'] = 'Ya has realizado una entrega definitiva. El editor está en modo lectura.';
$string['deadline_passed'] = 'La fecha límite de entrega ha pasado.';

// Ajustes de Formulario
$string['sandbox_settings'] = 'Configuración del Sandbox'; 
$string['maxgrade'] = 'Calificación máxima';
$string['maxgrade_help'] = 'Establece la calificación máxima para esta actividad. Este valor se usará en el libro de calificaciones.';
$string['duedate'] = 'Fecha límite';
$string['duedate_info'] = 'Fecha límite: {$a}';
$string['no_duedate'] = 'Sin fecha límite';
$string['instructions'] = 'Instrucciones';
$string['instructions_help'] = 'Escribe aquí detalladamente qué es lo que el alumno debe lograr.';
$string['select_language'] = 'Lenguaje de programación';
$string['select_language_placeholder'] = 'Seleccione un lenguaje...';

// Errores
$string['error_noinstructions'] = 'Debes proporcionar las instrucciones del ejercicio.';
$string['error_nodefaultlang'] = 'Debes seleccionar un lenguaje de programación.';
$string['error_nograde'] = 'Debes definir una calificación máxima.';

// Calificación
$string['grade_submission'] = 'Calificar Entrega: {$a}';
$string['grade_limit'] = 'Calificación (0-{$a})';
$string['grade_header'] = 'Nota';
$string['teacher_comment'] = 'Comentarios del Profesor';
$string['save_grade'] = 'Guardar Nota';
$string['grade_saved'] = 'La calificación se ha guardado correctamente';
$string['student_code'] = 'Código del Alumno';
$string['output_results'] = 'Resultados de Ejecución';
$string['no_code_submitted'] = '// No hay código disponible';
$string['no_output_recorded'] = '> No hay salida registrada';

// Capacidades (permisos)
$string['codesandbox:view'] = 'Ver Code Sandbox';
$string['codesandbox:viewreport'] = 'Ver reportes de entregas';
$string['codesandbox:grade'] = 'Calificar entregas';

// Configuración RabbitMQ
$string['rabbitmq_settings'] = 'Configuración de RabbitMQ';
$string['rabbitmq_settings_desc'] = 'Parámetros de conexión al servidor de cola de mensajes';
$string['rabbitmq_host'] = 'Host de RabbitMQ';
$string['rabbitmq_port'] = 'Puerto de RabbitMQ';
$string['rabbitmq_user'] = 'Usuario de RabbitMQ';
$string['rabbitmq_password'] = 'Contraseña de RabbitMQ';

// Configuración del Worker
$string['worker_settings'] = 'Configuración del Worker';
$string['worker_timeout'] = 'Tiempo de espera de ejecución (segundos)';
$string['rabbitmq_queue'] = 'Nombre de la cola RabbitMQ';

// Mensajes de cola
$string['queue_unavailable'] = 'El servicio de ejecución no está disponible temporalmente. Inténtalo más tarde.';
$string['job_queued'] = 'Tu código ha sido encolado para ejecución.';

// Mensajes de Polling/Async
$string['msg_job_queued'] = 'Job en cola: {$a}...';
$string['msg_waiting_results'] = 'Esperando resultados...';
$string['msg_timeout'] = 'TIMEOUT: El job tardó demasiado. Revisa más tarde.';
$string['msg_no_output'] = '(Sin salida)';
$string['msg_stderr_label'] = '[STDERR]';
$string['msg_exitcode_label'] = '[Código de salida: {$a}]';

// Notificaciones
$string['notification_graded_subject'] = 'Tu tarea "{$a}" ha sido calificada';
$string['notification_graded_message'] = 'Tu actividad ha sido calificada con: {$a->grade} / {$a->maxgrade}. Comentarios del profesor: {$a->comment}';
$string['notification_graded_message_html'] = '<p>Tu actividad <strong>{$a->activityname}</strong> ha sido calificada.</p><p>Nota: <strong>{$a->grade} / {$a->maxgrade}</strong></p><p>Comentario:</p><blockquote>{$a->comment}</blockquote>';
$string['notification_graded_small'] = 'Tu actividad {$a} ha sido calificada.';

$string['notification_assignment_subject'] = 'Nueva tarea: {$a}';
$string['notification_assignment_message'] = 'Se ha asignado un nuevo Sandbox de programación.';
$string['notification_completed_subject'] = 'Entrega recibida en {$a}';
$string['notification_completed_message'] = '{$a} ha completado su sandbox.';
$string['notification_updated_subject'] = 'Actualizada: {$a}';
$string['notification_updated_message'] = 'La actividad ha sido actualizada. Cambios: {$a}';
$string['notification_change_duedate'] = 'fecha límite';
$string['notification_change_instructions'] = 'instrucciones';
$string['notification_change_grade'] = 'calificación máxima';
$string['notification_change_general'] = 'configuración general';

// Calificación Automática
$string['autograding_header'] = 'Calificación Automática (Opcional)';
$string['autograding_enable'] = 'Activar Autograding';
$string['autograding_enable_desc'] = 'Comparar salida con casos de prueba';
$string['testcases'] = 'Casos de Prueba (JSON)';
$string['testcases_help'] = 'Define casos de prueba en formato JSON. Ejemplo: [{"input": "2\\n2", "output": "4"}]';
$string['autograding_results'] = 'Resultados de Autograding';
$string['tests_passed'] = 'Tests Aprobados: {$a}';
$string['auto_graded'] = 'Calificado automáticamente';
$string['test_case_passed'] = 'Aprobado';
$string['test_case_failed'] = 'Fallido';
$string['expected_output'] = 'Salida Esperada';
$string['actual_output'] = 'Salida Obtenida';