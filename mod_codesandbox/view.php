<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); 
list($course, $cm) = get_course_and_cm_from_cmid($id, 'codesandbox');
$codesandbox = $DB->get_record('codesandbox', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, $cm);
$PAGE->set_cm($cm, $course);
$PAGE->set_url('/mod/codesandbox/view.php', array('id' => $id));
$context = context_module::instance($cm->id);
require_capability('mod/codesandbox:view', $context);

// ===== AGREGAR BLOQUE AUTOMÁTICAMENTE SI NO EXISTE =====
codesandbox_ensure_block_exists($course->id);
// ===== FIN BLOQUE AUTOMÁTICO =====

$extensions = [
    'python' => 'py', 'javascript' => 'js', 'java' => 'java', 
    'c' => 'c', 'cpp' => 'cpp', 'assembly' => 'asm', 'php' => 'php'
];
$ext = $extensions[$codesandbox->language] ?? 'txt';
$fullFileName = "main." . $ext;

$last_attempt = $DB->get_record_sql("SELECT * FROM {codesandbox_attempts} 
    WHERE codesandboxid = ? AND userid = ? 
    ORDER BY timecreated DESC LIMIT 1", [$codesandbox->id, $USER->id]);

$isSubmitted = ($last_attempt && $last_attempt->status == 'submitted');
$isPastDue = ($codesandbox->duedate > 0 && time() > $codesandbox->duedate);
$isReadOnly = ($isSubmitted || $isPastDue);

$PAGE->set_url('/mod/codesandbox/view.php', array('id' => $id));
$PAGE->set_title(format_string($codesandbox->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse'); 
$PAGE->activityheader->set_description(''); 
$PAGE->requires->css('/mod/codesandbox/styles.css');
$langName = strtoupper($codesandbox->language);

$js_params = [
    'cmid' => $cm->id,
    'language' => ($codesandbox->language == 'assembly') ? 'ini' : $codesandbox->language,
    'monacoloader' => (new moodle_url('/mod/codesandbox/assets/vs/loader.js'))->out(false),
    'monacobase'   => (new moodle_url('/mod/codesandbox/assets/vs'))->out(false),
    'containerId'  => 'editor-container',
    'readOnly'     => $isReadOnly,
    'code'         => $last_attempt ? $last_attempt->code : "",
    'initialOutput' => $last_attempt && $last_attempt->stdout ? $last_attempt->stdout : "",
    'msg_confirm'  => get_string('msg_confirm_submit', 'mod_codesandbox'),
    'msg_ready'    => strip_tags(get_string('console_ready', 'mod_codesandbox', $langName)),
    'msg_success'  => get_string('msg_submitted_success', 'mod_codesandbox'),
    'msg_running'  => get_string('msg_running', 'mod_codesandbox'),
    'msg_sending'  => get_string('msg_sending', 'mod_codesandbox'),
    'msg_err_server' => get_string('msg_err_server', 'mod_codesandbox'),
    'msg_err_conn'   => get_string('msg_err_conn', 'mod_codesandbox'),
    'status_submitted' => get_string('status_submitted', 'mod_codesandbox'),
    'msg_job_queued' => get_string('msg_job_queued', 'mod_codesandbox', ''),
    'msg_waiting_results' => get_string('msg_waiting_results', 'mod_codesandbox'),
    'msg_timeout' => get_string('msg_timeout', 'mod_codesandbox'),
    'msg_no_output' => get_string('msg_no_output', 'mod_codesandbox'),
    'msg_stderr_label' => get_string('msg_stderr_label', 'mod_codesandbox'),
    'msg_exitcode_label' => get_string('msg_exitcode_label', 'mod_codesandbox', '')
];
$PAGE->requires->js_call_amd('mod_codesandbox/editor', 'init', [$js_params]);

// Force cache buster for development
echo '<script>
require.config({
    paths: {
        "mod_codesandbox/editor": M.cfg.wwwroot + "/mod/codesandbox/amd/build/editor.min.js?" + new Date().getTime()
    }
});
</script>';

echo $OUTPUT->header();

if ($codesandbox->duedate > 0) {
    $duedatetext = userdate($codesandbox->duedate, get_string('strftimedatetime', 'langconfig'));
    $duedateclass = ($isPastDue) ? 'alert-danger' : 'alert-info';
    echo '<div class="alert '.$duedateclass.' mb-3">';
    echo '<i class="fa fa-clock-o"></i> ' . get_string('duedate_info', 'mod_codesandbox', $duedatetext);
    echo '</div>';
}

if (!empty($codesandbox->intro)) {
    echo '<div class="instructions-box mb-3 p-3 bg-light border rounded shadow-sm" style="border-left: 5px solid #007bff !important;">';
    echo '<h5 class="mt-0 text-primary"><i class="fa fa-info-circle"></i> ' . get_string('instructions', 'mod_codesandbox') . '</h5>';
    echo format_module_intro('codesandbox', $codesandbox, $cm->id);
    echo '</div>';
}

if ($isSubmitted) echo $OUTPUT->notification(get_string('already_submitted', 'mod_codesandbox'), 'warning');
else if ($isPastDue) echo $OUTPUT->notification(get_string('deadline_passed', 'mod_codesandbox'), 'error');
if (optional_param('runtest', 0, PARAM_INT)) {
    codesandbox_update_block_data($cm->id, $USER->id, 'print("Test de flujo OK")', 'success', 120);
}

echo '
<div class="codesandbox-ide-wrapper">
    <div class="ide-toolbar">
        <div class="file-tab"><i class="fa fa-file-code-o"></i> ' . $fullFileName . '</div>
        <div class="toolbar-actions">
            <span id="status-badge" class="badge '.($isSubmitted ? 'badge-success' : 'badge-warning').' mr-2">'.($isSubmitted ? get_string('status_submitted', 'mod_codesandbox') : get_string('status_not_submitted', 'mod_codesandbox')).'</span>
            <button id="run-btn" class="btn btn-secondary btn-sm"><i class="fa fa-play"></i> '.get_string('btn_run_label', 'mod_codesandbox').'</button>';
            if (!$isReadOnly) {
                echo '<button id="submit-btn" class="btn btn-primary btn-sm ml-2"><i class="fa fa-paper-plane"></i> '.get_string('btn_submit_label', 'mod_codesandbox').'</button>';
            }
echo '  </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-8">
            <div id="editor-container" style="height: 500px; border: 1px solid #333;"></div>
        </div>
        <div class="col-md-4 pl-3 pr-3">
            <div class="border-bottom pb-2 mb-3">
                <h6 class="text-uppercase text-secondary mb-0" style="font-size: 0.85em; letter-spacing: 0.5px;">
                    <i class="fa fa-keyboard-o"></i> '.get_string('console_inputs', 'mod_codesandbox', '').'
                </h6>
            </div>
            <textarea id="console-input" class="form-control" rows="10" placeholder="'.get_string('console_inputs_placeholder', 'mod_codesandbox', '').'" style="font-family: monospace; font-size: 1em;"></textarea>
            <small class="text-muted d-block mt-2" style="font-size: 0.95em; line-height: 1.5;">'.get_string('console_inputs_help', 'mod_codesandbox', '').'</small>
        </div>
    </div>
    <div class="ide-terminal-bar"><span><i class="fa fa-terminal"></i> '.get_string('terminal_header', 'mod_codesandbox').'</span></div>
    <div id="console-output" class="ide-console">
        <span class="text-muted">' . ($last_attempt ? s($last_attempt->stdout) : get_string('console_ready', 'mod_codesandbox', $langName)) . '</span>
    </div>
</div>';

echo $OUTPUT->footer();