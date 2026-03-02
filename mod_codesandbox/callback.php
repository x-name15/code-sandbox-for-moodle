<?php
/**
 * Callback endpoint - recibe resultados del Worker
 * Este endpoint es llamado por judgeman.py cuando termina de ejecutar un job
 * 
 * SEGURIDAD: Valida token basado en job_id + secretsaltmain
 */
define('NO_MOODLE_COOKIES', true); // No requiere sesión de usuario
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => s('Invalid JSON payload')]));
}

$required = ['job_id', 'token', 'status'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        die(json_encode(['error' => s("Missing required field: " . $field)]));
    }
}

$job_id = $data['job_id'];
$token = $data['token'];
$status = $data['status']; 
$stdout = $data['stdout'] ?? '';
$stderr = $data['stderr'] ?? '';
$exitcode = $data['exitcode'] ?? null;
$execution_time = $data['execution_time'] ?? null;
$action = $data['action'] ?? 'run';
// $inputdata = $data['inputdata'] ?? '';  // ← Capturar inputdata del callback

$expected_token = hash('sha256', $job_id . $CFG->secretsaltmain);
if (!hash_equals($expected_token, $token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid security token']));
}

$attempt = $DB->get_record('codesandbox_attempts', ['job_id' => $job_id]);
if (!$attempt) {
    http_response_code(404);
    die(json_encode(['error' => 'Job not found']));
}

$update = new stdClass();
$update->id = $attempt->id;
$update->stdout = $stdout;
$update->stderr = $stderr;
$update->exitcode = $exitcode;
$update->execution_time = $execution_time;
// $update->inputdata = $inputdata;  // ← Evitar sobrescribir con vacío si el worker no lo devuelve
$update->timemodified = time();

if ($status === 'completed') {
    if ($action === 'submit') {
        $update->status = 'submitted';
    } else {
        $update->status = 'completed';
    }
} else {
    $update->status = 'failed';
}

$DB->update_record('codesandbox_attempts', $update);

// ===== NOTIFICACIÓN AL PROFESOR =====
if ($action === 'submit' && $update->status === 'submitted') {
    require_once(__DIR__ . '/lib.php');
    
    // Obtener la instancia completa del sandbox
    $codesandbox = $DB->get_record('codesandbox', ['id' => $attempt->codesandboxid]);
    
    if ($codesandbox) {
        // Obtener el módulo para las notificaciones
        $cm = $DB->get_record_sql(
            "SELECT cm.* 
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             WHERE m.name = 'codesandbox' AND cm.instance = ?",
            [$codesandbox->id]
        );
        
        if ($cm) {
            $codesandbox->id = $cm->id; // Necesita el cmid para las capabilities
            codesandbox_send_notification($codesandbox, 'completed');
            error_log("[CodeSandbox Callback] Notification sent to teachers for job $job_id");
        }
    }
}
// ===== FIN NOTIFICACIÓN =====

// ===== AUTOGRADING: Calificación Automática =====
if ($action === 'submit' && $status === 'completed') {
    require_once(__DIR__ . '/classes/grader.php');
    
    // Obtener la configuración del sandbox
    $codesandbox = $DB->get_record('codesandbox', ['id' => $attempt->codesandboxid]);
    
    if ($codesandbox && $codesandbox->use_autograding && !empty($codesandbox->testcases)) {
        // Ejecutar casos de prueba
        $grading_result = \mod_codesandbox\grader::run_test_cases($codesandbox->testcases, $stdout);
        
        if ($grading_result['valid'] === true && isset($grading_result['results'])) {
            // Calcular calificación automática
            $auto_grade = \mod_codesandbox\grader::calculate_final_grade(
                $grading_result['results'], 
                $codesandbox->grade
            );
            
            // Guardar calificación automática y resultados detallados
            $grade_update = new stdClass();
            $grade_update->id = $attempt->id;
            $grade_update->grade = $auto_grade;
            $grade_update->teachercomment = \mod_codesandbox\grader::generate_summary($grading_result['results']) . 
                                           " (Autograding)";
            $grade_update->autograding_results = json_encode($grading_result['results'], JSON_PRETTY_PRINT);
            $DB->update_record('codesandbox_attempts', $grade_update);
            
            // Actualizar el gradebook de Moodle
            $gradeitem = [
                'userid' => $attempt->userid,
                'rawgrade' => $auto_grade,
                'dategraded' => time(),
                'datesubmitted' => time()
            ];
            
            require_once(__DIR__ . '/lib.php');
            codesandbox_grade_item_update($codesandbox, $gradeitem);
            
            error_log("[CodeSandbox Autograding] Job $job_id - Auto grade: $auto_grade/{$codesandbox->grade}");
        } else {
            error_log("[CodeSandbox Autograding] Job $job_id - Error: " . ($grading_result['error'] ?? 'Unknown'));
        }
    }
}
// ===== FIN AUTOGRADING =====

// ===== ACTUALIZAR TABLAS DEL BLOQUE =====
$cm = $DB->get_record_sql(
    "SELECT cm.id as cmid, cs.id as instanceid
     FROM {course_modules} cm
     JOIN {modules} m ON m.id = cm.module
     JOIN {codesandbox} cs ON cs.id = cm.instance
     WHERE m.name = 'codesandbox' AND cs.id = ?",
    [$attempt->codesandboxid]
);

if ($cm) {
    $runtime_ms = ($execution_time !== null) ? round($execution_time * 1000) : 0;
    $final_status = ($update->status === 'completed' || $update->status === 'submitted') ? 'success' : 'error';
    
    $code_lines = explode("\n", $attempt->code);
    $preview_lines = array_slice($code_lines, 0, 10);
    $code_preview = implode("\n", $preview_lines);
    if (count($code_lines) > 10) {
        $code_preview .= "\n... (" . (count($code_lines) - 10) . " more lines)";
    }
    
    $snapshot = $DB->get_record('block_codesandbox_snap', [
        'userid' => $attempt->userid,
        'cmid' => $cm->cmid
    ]);
    
    if ($snapshot) {
        $snapshot->code_preview = $code_preview;
        $snapshot->status = $final_status;
        $snapshot->runtime_ms = $runtime_ms;
        $snapshot->timestamp = time();
        $DB->update_record('block_codesandbox_snap', $snapshot);
    } else {
        $snapshot = new stdClass();
        $snapshot->userid = $attempt->userid;
        $snapshot->cmid = $cm->cmid;
        $snapshot->code_preview = $code_preview;
        $snapshot->status = $final_status;
        $snapshot->runtime_ms = $runtime_ms;
        $snapshot->timestamp = time();
        $DB->insert_record('block_codesandbox_snap', $snapshot);
    }
    
    $history = new stdClass();
    $history->userid = $attempt->userid;
    $history->cmid = $cm->cmid;
    $history->status = $final_status;
    $history->timestamp = time();
    $history->runtime_ms = $runtime_ms;
    $DB->insert_record('block_codesandbox_hist', $history);
    
    error_log("[CodeSandbox Callback] Block tables updated for user {$attempt->userid}, cmid {$cm->cmid}");
}
// ===== FIN ACTUALIZACIÓN BLOQUE =====

error_log("[CodeSandbox Callback] Job $job_id completed with status: {$update->status}, exit code: $exitcode");

echo json_encode([
    'success' => true,
    'job_id' => $job_id,
    'attempt_id' => $attempt->id,
    'final_status' => $update->status
]);
