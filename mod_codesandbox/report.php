<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); 
$attemptid = optional_param('attemptid', 0, PARAM_INT); 

list($course, $cm) = get_course_and_cm_from_cmid($id, 'codesandbox');
$codesandbox = $DB->get_record('codesandbox', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/codesandbox:viewreport', $context);

// ===== AGREGAR BLOQUE AUTOMÁTICAMENTE SI NO EXISTE =====
codesandbox_ensure_block_exists($course->id);
// ===== FIN BLOQUE AUTOMÁTICO =====

$PAGE->set_url('/mod/codesandbox/report.php', array('id' => $id));
$PAGE->set_context($context);
$PAGE->set_title(format_string($codesandbox->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');
$PAGE->activityheader->disable(); 

$maxGrade = (int)$codesandbox->grade;

if ($attemptid && ($data = data_submitted())) {
    require_capability('mod/codesandbox:grade', $context);
    confirm_sesskey();
    $update = (object)[
        'id' => $attemptid,
        'grade' => min(optional_param('grade', 0, PARAM_FLOAT), $maxGrade),
        'teachercomment' => optional_param('teachercomment', '', PARAM_TEXT),
        'timemodified' => time()
    ];
    $DB->update_record('codesandbox_attempts', $update);
    
    $attempt = $DB->get_record('codesandbox_attempts', ['id' => $attemptid], 'userid');
    codesandbox_update_grades($codesandbox, $attempt->userid);
    codesandbox_send_notification($codesandbox, 'graded', $attempt->userid, [
        'grade' => $update->grade,
        'comment' => $update->teachercomment
    ]);

    redirect(new moodle_url('/mod/codesandbox/report.php', ['id' => $id]), get_string('grade_saved', 'mod_codesandbox'));
}

echo $OUTPUT->header();

if ($attemptid) {
    $at = $DB->get_record('codesandbox_attempts', ['id' => $attemptid], '*', MUST_EXIST);
    $user = $DB->get_record('user', ['id' => $at->userid]);
    
    $js_params = [
        'containerId' => 'editor-review-container',
        'code' => $at->code ?? get_string('no_code_submitted', 'mod_codesandbox'),
        'language' => ($codesandbox->language == 'assembly') ? 'ini' : $codesandbox->language,
        'monacoloader' => (new moodle_url('/mod/codesandbox/assets/vs/loader.js'))->out(false),
        'monacobase' => (new moodle_url('/mod/codesandbox/assets/vs'))->out(false)
    ];
    $PAGE->requires->js_call_amd('mod_codesandbox/editor_review', 'init', [$js_params]);
    
    $strGradeLimit = get_string('grade_limit', 'mod_codesandbox', $maxGrade);
    echo $OUTPUT->heading(get_string('grade_submission', 'mod_codesandbox', fullname($user)));

    echo '
    <div class="row">
        <div class="col-md-8">
            <h5 class="mb-2"><i class="fa fa-code"></i> '.get_string('student_code', 'mod_codesandbox').'</h5>
            <div id="editor-review-container" style="height: 450px; border: 1px solid #ccc;"></div>
            
            <h5 class="mt-4 mb-2"><i class="fa fa-terminal"></i> '.get_string('output_results', 'mod_codesandbox').'</h5>
            <div class="p-3 rounded mb-3" style="background-color: #1e1e1e; color: #f0f0f0; border: 1px solid #333; min-height: 80px; font-family: monospace;">
                <pre class="mb-0" style="color: inherit; background: transparent; border: none;">'.s($at->stdout ?? get_string('no_output_recorded', 'mod_codesandbox')).'</pre>
            </div>
            
            '.(!empty($at->stderr) ? '
            <h5 class="mt-4 mb-2"><i class="fa fa-exclamation-triangle"></i> Errores de Ejecución</h5>
            <div class="p-3 rounded mb-3" style="background-color: #440000; color: #ffcccc; border: 1px solid #990000; font-family: monospace;">
                <pre class="mb-0" style="color: inherit; background: transparent; border: none;">'.s($at->stderr).'</pre>
            </div>
            ' : '').'
            
            '.($at->exitcode !== null ? '
            <div class="mb-3 badge badge-secondary p-2" style="font-size: 0.9rem;">
                Código de salida: <strong>'.s($at->exitcode).'</strong>
            </div>
            ' : '').'
            
            '.(!empty($at->inputdata) ? '
            <h5 class="mt-4 mb-2"><i class="fa fa-keyboard-o"></i> Entradas Proporcionadas (Inputs)</h5>
            <div class="p-3 rounded border bg-light" style="font-family: monospace; color: #333;">
                <pre class="mb-0" style="background: transparent; border: none; padding: 0;">'.s(trim($at->inputdata)).'</pre>
            </div>
            ' : '').'
        </div>
        <div class="col-md-4">
            <div class="card p-3 shadow-sm border-primary">
                <form method="post">
                    <input type="hidden" name="sesskey" value="'.sesskey().'">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">'.$strGradeLimit.'</label>
                        <input type="number" name="grade" class="form-control" value="'.$at->grade.'" step="0.01" max="'.$maxGrade.'">
                    </div>
                    <textarea name="teachercomment" class="form-control mb-3" rows="10">'.s($at->teachercomment).'</textarea>
                    <button type="submit" class="btn btn-primary w-100">'.get_string('save_grade', 'mod_codesandbox').'</button>
                    <a href="report.php?id='.$id.'" class="btn btn-link w-100">'.get_string('btn_cancel', 'mod_codesandbox').'</a>
                </form>
            </div>
        </div>
    </div>';
} else {
    echo $OUTPUT->heading(get_string('submissions_header', 'mod_codesandbox'));
    $attempts = $DB->get_records('codesandbox_attempts', ['codesandboxid' => $codesandbox->id, 'status' => 'submitted'], 'timecreated DESC');

    if (!$attempts) {
        echo $OUTPUT->notification(get_string('no_submissions', 'mod_codesandbox'), 'info');
    } else {
        $table = new html_table();
        $table->head = [
            get_string('table_student', 'mod_codesandbox'), 
            get_string('table_date', 'mod_codesandbox'), 
            get_string('grade_header', 'mod_codesandbox'),
            get_string('table_actions', 'mod_codesandbox')
        ];
        foreach ($attempts as $at) {
            $user = $DB->get_record('user', ['id' => $at->userid]);
            $gradeDisplay = "<strong>" . ($at->grade ?? '--') . " / {$maxGrade}</strong>";
            $url = new moodle_url('/mod/codesandbox/report.php', ['id' => $id, 'attemptid' => $at->id]);
            $btn = $OUTPUT->action_link($url, get_string('btn_review', 'mod_codesandbox'), null, ['class' => 'btn btn-sm btn-primary']);
            $table->data[] = [fullname($user), userdate($at->timecreated), $gradeDisplay, $btn];
        }
        echo html_writer::table($table);
    }
}
echo $OUTPUT->footer();