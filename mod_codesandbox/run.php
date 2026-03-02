<?php
/**
 * Endpoint para ejecutar código (run) o entregar (submit)
 * Envía el job a RabbitMQ para ejecución real en Docker
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/rabbitmq_client.php');

$cmid = required_param('cmid', PARAM_INT);
$code = required_param('code', PARAM_RAW);
$action = optional_param('action', 'run', PARAM_ALPHA); // 'run' o 'submit'
$inputdata = optional_param('inputdata', '', PARAM_RAW); // Inputs del usuario para testing

// 🔍 DEBUG: Log everything
error_log("========== CodeSandbox run.php DEBUG ==========");
error_log("Action: " . var_export($action, true));
error_log("Inputdata received (raw): " . var_export($inputdata, true));
error_log("Inputdata length: " . strlen($inputdata));
error_log("Inputdata empty?: " . ($inputdata === '' ? 'YES (empty string)' : 'NO (has content)'));
error_log("Inputdata null?: " . ($inputdata === null ? 'YES' : 'NO'));
error_log("=============================================");

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'codesandbox');
require_login($course, $cm);
require_capability('mod/codesandbox:view', context_module::instance($cm->id));
require_sesskey();

$codesandbox = $DB->get_record('codesandbox', array('id' => $cm->instance), '*', MUST_EXIST);

// Verificar si ya existe una entrega final
if ($action === 'submit') {
    $existing_submit = $DB->get_record('codesandbox_attempts', [
        'codesandboxid' => $cm->instance,
        'userid' => $USER->id,
        'status' => 'submitted'
    ]);
    
    if ($existing_submit) {
        echo json_encode([
            'status' => 'error',
            'message' => get_string('already_submitted', 'mod_codesandbox')
        ]);
        exit;
    }
}

$job_id = bin2hex(random_bytes(16));

// Lógica para manejar attempts existentes
if ($action === 'run') {
    // Para 'run': buscar y reutilizar un attempt que NO sea submitted
    $existing = $DB->get_record('codesandbox_attempts', [
        'codesandboxid' => $cm->instance,
        'userid' => $USER->id
    ], '*', IGNORE_MULTIPLE);
    
    // Solo reutilizar si existe Y no es submitted
    if ($existing && $existing->status !== 'submitted') {
        $attempt = $existing;
        $attempt->code = $code;
        $attempt->inputdata = $inputdata;
        $attempt->status = 'pending';
        $attempt->job_id = $job_id;
        $attempt->stdout = null; 
        $attempt->stderr = null;
        $attempt->exitcode = null;
        $attempt->timemodified = time();
        $DB->update_record('codesandbox_attempts', $attempt);
        $attemptid = $attempt->id;
    } else {
        // Crear nuevo si no existe o si el único que existe es submitted
        $attempt = new stdClass();
        $attempt->codesandboxid = $cm->instance;
        $attempt->userid = $USER->id;
        $attempt->code = $code;
        $attempt->inputdata = $inputdata;
        $attempt->status = 'pending';
        $attempt->job_id = $job_id;
        $attempt->timecreated = time();
        $attempt->timemodified = time();
        $attemptid = $DB->insert_record('codesandbox_attempts', $attempt);
        $attempt->id = $attemptid;
    }
} else {
    // Para 'submit': SIEMPRE crear un nuevo attempt
    $attempt = new stdClass();
    $attempt->codesandboxid = $cm->instance;
    $attempt->userid = $USER->id;
    $attempt->code = $code;
    $attempt->inputdata = $inputdata;
    $attempt->status = 'pending';  // El callback lo cambiará a 'submitted'
    $attempt->job_id = $job_id;
    $attempt->timecreated = time();
    $attempt->timemodified = time();
    $attemptid = $DB->insert_record('codesandbox_attempts', $attempt);
    $attempt->id = $attemptid;
}

$callback_url = (new moodle_url('/mod/codesandbox/callback.php'))->out(false);

$payload = [
    'job_id' => $job_id,
    'attempt_id' => $attemptid,
    'language' => $codesandbox->language,
    'code' => $code,
    'action' => $action,
    'inputdata' => $inputdata,  // ← Inputs del alumno (tanto para run como submit)
    'callback_url' => $callback_url,
    'callback_token' => hash('sha256', $job_id . $CFG->secretsaltmain) 
];

// 🔍 DEBUG: Log payload
error_log("========== CodeSandbox Payload to RabbitMQ ==========");
error_log("Payload action: " . var_export($payload['action'], true));
error_log("Payload inputdata: " . var_export($payload['inputdata'], true));
error_log("Payload inputdata length: " . strlen($payload['inputdata']));
error_log("====================================================");

$rabbitmq = new \mod_codesandbox\rabbitmq_client();
$result = $rabbitmq->publish_job($payload);

if (!$result['success']) {
    $DB->set_field('codesandbox_attempts', 'status', 'failed', ['id' => $attemptid]);
    $DB->set_field('codesandbox_attempts', 'stderr', 'Queue service unavailable: ' . $result['error'], ['id' => $attemptid]);
    
    echo json_encode([
        'status' => 'error',
        'message' => get_string('queue_unavailable', 'mod_codesandbox'),
        'debug' => $result['error']
    ]);
    exit;
}

$response = [
    'status' => 'queued',
    'job_id' => $job_id,
    'attempt_id' => $attemptid,
    'message' => get_string('job_queued', 'mod_codesandbox')
];

echo json_encode($response);