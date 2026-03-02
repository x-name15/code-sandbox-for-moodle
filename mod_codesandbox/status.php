<?php
/**
 * Endpoint para consultar el estado de un job
 * El frontend hace polling a este endpoint mientras espera resultados
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

$job_id = required_param('job_id', PARAM_ALPHANUMEXT);

$attempt = $DB->get_record('codesandbox_attempts', ['job_id' => $job_id]);

if (!$attempt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Job not found'
    ]);
    exit;
}

$codesandbox = $DB->get_record('codesandbox', ['id' => $attempt->codesandboxid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('codesandbox', $codesandbox->id);
$context = context_module::instance($cm->id);

if ($attempt->userid != $USER->id && !has_capability('mod/codesandbox:viewreport', $context)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Permission denied'
    ]);
    exit;
}

$response = [
    'status' => $attempt->status,
    'job_id' => $attempt->job_id
];

if (in_array($attempt->status, ['completed', 'failed', 'submitted'])) {
    $response['stdout'] = $attempt->stdout;
    $response['stderr'] = $attempt->stderr;
    $response['exitcode'] = $attempt->exitcode;
    $response['execution_time'] = $attempt->execution_time;
}

echo json_encode($response);
