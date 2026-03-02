<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Declara qué características soporta el módulo
 */
function codesandbox_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        default: return null;
    }
}

/**
 * Asegura que el bloque codesandbox exista en el curso
 * Se llama automáticamente:
 * - Cuando se crea una actividad codesandbox
 * - Cuando un usuario entra a view.php
 * - Cuando un profesor entra a report.php
 * 
 * @param int $courseid ID del curso
 * @return bool true si existe o fue creado, false si falló
 */
function codesandbox_ensure_block_exists($courseid) {
    global $DB;
    
    if (!$courseid) {
        return false;
    }
    
    try {
        $context = context_course::instance($courseid);
        
        // 1. Verificar que el bloque esté instalado en Moodle
        $blockrecord = $DB->get_record('block', ['name' => 'codesandbox']);
        if (!$blockrecord) {
            error_log("[CodeSandbox] Block 'codesandbox' not installed in Moodle. Skipping block creation.");
            return false;
        }
        
        // 2. Verificar si el bloque ya existe en el curso
        $existing = $DB->get_record('block_instances', [
            'blockname' => 'codesandbox',
            'parentcontextid' => $context->id
        ]);
        
        if ($existing) {
            return true;  // Ya existe
        }
        
        // 3. Bloque NO existe - CREAR
        // Encontrar el siguiente peso disponible
        $maxweight = $DB->get_field_sql(
            "SELECT MAX(defaultweight) FROM {block_instances} 
             WHERE parentcontextid = ? AND defaultregion = 'side-post'",
            [$context->id]
        );
        $newweight = (is_null($maxweight) ? 0 : $maxweight + 1);
        
        $blockinstance = new stdClass();
        $blockinstance->blockname = 'codesandbox';
        $blockinstance->parentcontextid = $context->id;
        $blockinstance->showinsubcontexts = 0;
        $blockinstance->requiredbytheme = 0;
        $blockinstance->pagetypepattern = 'course-view-*';
        $blockinstance->subpagepattern = null;
        $blockinstance->defaultregion = 'side-post';
        $blockinstance->defaultweight = $newweight;
        $blockinstance->configdata = '';
        $blockinstance->timecreated = time();
        $blockinstance->timemodified = time();
        
        $blockid = $DB->insert_record('block_instances', $blockinstance);
        
        error_log("[CodeSandbox] Block created in course {$courseid} (ID: {$blockid}, weight: {$newweight})");
        return true;
        
    } catch (Exception $e) {
        error_log("[CodeSandbox] Error ensuring block exists: " . $e->getMessage());
        return false;
    }
}

function codesandbox_add_instance($codesandbox) {
    global $DB, $CFG;
    $codesandbox->timecreated = time();
    
    // Normalizar campos de autograding
    if (!isset($codesandbox->use_autograding)) {
        $codesandbox->use_autograding = 0;
    }
    if (!isset($codesandbox->testcases)) {
        $codesandbox->testcases = '';
    }
    
    $id = $DB->insert_record('codesandbox', $codesandbox);
    $codesandbox->id = $id;
    codesandbox_grade_item_update($codesandbox);
    
    // Asegurar que el bloque existe en el curso
    codesandbox_ensure_block_exists($codesandbox->course);

    codesandbox_send_notification($codesandbox, 'assignment');

    return $id;
}

function codesandbox_update_instance($codesandbox) {
    global $DB;
    $codesandbox->timemodified = time();
    $codesandbox->id = $codesandbox->instance;
    
    // Obtener valores anteriores para detectar cambios importantes
    $old = $DB->get_record('codesandbox', ['id' => $codesandbox->id]);
    
    // Normalizar campos de autograding
    if (!isset($codesandbox->use_autograding)) {
        $codesandbox->use_autograding = 0;
    }
    if (!isset($codesandbox->testcases)) {
        $codesandbox->testcases = '';
    }
    
    $result = $DB->update_record('codesandbox', $codesandbox);
    codesandbox_grade_item_update($codesandbox);
    
    // Detectar cambios importantes y notificar
    if ($old) {
        $changes = [];
        
        // Detectar cambio de fecha límite
        if ($old->duedate != $codesandbox->duedate) {
            $changes[] = 'duedate';
        }
        
        // Detectar cambio de instrucciones
        if ($old->intro != $codesandbox->intro) {
            $changes[] = 'instructions';
        }
        
        // Detectar cambio de calificación máxima
        if ($old->grade != $codesandbox->grade) {
            $changes[] = 'grade';
        }
        
        // Si hay cambios importantes, notificar a los estudiantes
        if (!empty($changes)) {
            $codesandbox->changes = $changes; // Pasar info de cambios
            codesandbox_send_notification($codesandbox, 'updated');
        }
    }
    
    return $result;
}

function codesandbox_delete_instance($id) {
    global $DB;
    if (!$codesandbox = $DB->get_record('codesandbox', array('id' => $id))) return false;
    $DB->delete_records('codesandbox_attempts', array('codesandboxid' => $codesandbox->id));
    codesandbox_grade_item_delete($codesandbox);
    $DB->delete_records('codesandbox', array('id' => $codesandbox->id));
    return true;
}

function codesandbox_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $codesandboxnode = null) {
    global $PAGE;
    $context = context_module::instance($PAGE->cm->id);
    if (has_capability('mod/codesandbox:viewreport', $context)) {
        $url = new moodle_url('/mod/codesandbox/report.php', array('id' => $PAGE->cm->id));
        $node = navigation_node::create(
            get_string('teacher_view_submissions', 'mod_codesandbox'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'codesandbox_submissions',
            new pix_icon('i/report', '')
        );
        if ($codesandboxnode) {
            $codesandboxnode->add_node($node);
        }
    }
}

/**
 * Actualiza el item de calificación en el libro de calificaciones
 * 
 * @param object $codesandbox Instancia del módulo codesandbox
 * @param mixed $grades Calificaciones opcionales para actualizar
 * @return int GRADE_UPDATE_OK o GRADE_UPDATE_ITEM
 */
function codesandbox_grade_item_update($codesandbox, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = array('itemname' => $codesandbox->name);
    
    if (isset($codesandbox->grade) && $codesandbox->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $codesandbox->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/codesandbox', $codesandbox->course, 'mod', 'codesandbox', 
                       $codesandbox->id, 0, $grades, $params);
}

/**
 * Actualiza las calificaciones de los estudiantes en el libro de calificaciones
 * 
 * @param object $codesandbox Instancia del módulo codesandbox
 * @param int $userid Usuario específico o 0 para todos
 * @param bool $nullifnone Si es true, devuelve null si no hay calificación
 * @return bool Success
 */
function codesandbox_update_grades($codesandbox, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($codesandbox->grade == 0) {
        return codesandbox_grade_item_update($codesandbox);
    }

    if ($userid) {
        $attempt = $DB->get_record_sql(
            "SELECT * FROM {codesandbox_attempts} 
             WHERE codesandboxid = ? AND userid = ? AND status = 'submitted'
             ORDER BY timecreated DESC LIMIT 1",
            array($codesandbox->id, $userid)
        );
        
        if ($attempt && $attempt->grade !== null) {
            $grades = new stdClass();
            $grades->userid = $userid;
            $grades->rawgrade = $attempt->grade;
            $grades->dategraded = $attempt->timemodified;
            $grades->datesubmitted = $attempt->timecreated;
        } else {
            $grades = new stdClass();
            $grades->userid = $userid;
            $grades->rawgrade = null;
        }
        
        return codesandbox_grade_item_update($codesandbox, $grades);
    } else {
        $sql = "SELECT a.userid, a.grade, a.timemodified, a.timecreated
                FROM {codesandbox_attempts} a
                INNER JOIN (
                    SELECT userid, MAX(timecreated) as maxtime
                    FROM {codesandbox_attempts}
                    WHERE codesandboxid = ? AND status = 'submitted'
                    GROUP BY userid
                ) b ON a.userid = b.userid AND a.timecreated = b.maxtime
                WHERE a.codesandboxid = ?";
        
        $attempts = $DB->get_records_sql($sql, array($codesandbox->id, $codesandbox->id));
        
        $grades = array();
        foreach ($attempts as $attempt) {
            $grade = new stdClass();
            $grade->userid = $attempt->userid;
            $grade->rawgrade = $attempt->grade;
            $grade->dategraded = $attempt->timemodified;
            $grade->datesubmitted = $attempt->timecreated;
            $grades[] = $grade;
        }
        
        return codesandbox_grade_item_update($codesandbox, $grades);
    }
}

/**
 * Elimina toda la información de calificaciones del libro de calificaciones
 * 
 * @param object $codesandbox Instancia del módulo codesandbox
 * @return bool Success
 */
function codesandbox_grade_item_delete($codesandbox) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    
    return grade_update('mod/codesandbox', $codesandbox->course, 'mod', 'codesandbox',
                       $codesandbox->id, 0, null, array('deleted' => 1));
}

/**
 * Envía datos de ejecución al bloque Companion.
 */
function codesandbox_update_block_data($cmid, $userid, $code, $status, $runtime) {
    global $DB;

    $snap = $DB->get_record('block_codesandbox_snap', ['userid' => $userid, 'cmid' => $cmid]);
    $snapdata = (object)[
        'userid' => $userid,
        'cmid' => $cmid,
        'code_preview' => mb_substr($code, 0, 150),
        'status' => $status,
        'runtime_ms' => $runtime,
        'timestamp' => time()
    ];
    if ($snap) { $snapdata->id = $snap->id; $DB->update_record('block_codesandbox_snap', $snapdata); }
    else { $DB->insert_record('block_codesandbox_snap', $snapdata); }

    $histdata = (object)[
        'userid' => $userid,
        'cmid' => $cmid,
        'status' => $status,
        'runtime_ms' => $runtime,
        'timestamp' => time()
    ];
    $DB->insert_record('block_codesandbox_hist', $histdata);
}



function codesandbox_send_notification($instance, $type, $student_id = null, $extra_details = []) {
    global $DB, $USER;

    $message = new \core\message\message();
    $message->component = 'mod_codesandbox';
    $message->name = 'submission';
    $message->userfrom = $USER; 
    
    if ($type === 'assignment') {
        $users = get_enrolled_users(context_course::instance($instance->course));
        foreach ($users as $user) {
            $message->userto = $user;
            $message->subject = get_string('notification_assignment_subject', 'mod_codesandbox', $instance->name);
            $message->fullmessage = get_string('notification_assignment_message', 'mod_codesandbox');
            $message->notification = 1;
            message_send($message);
        }
    } else if ($type === 'completed') {
        $teachers = get_users_by_capability(context_module::instance($instance->id), 'mod/codesandbox:grade');
        foreach ($teachers as $teacher) {
            $message->userto = $teacher;
            $message->subject = get_string('notification_completed_subject', 'mod_codesandbox', $instance->name);
            $message->fullmessage = get_string('notification_completed_message', 'mod_codesandbox', fullname($USER));
            $message->notification = 1;
            message_send($message);
        }
    } else if ($type === 'updated') {
        // Notificar sobre cambios importantes en la actividad
        $users = get_enrolled_users(context_course::instance($instance->course));
        
        // Construir mensaje con los cambios
        $changes_text = [];
        if (isset($instance->changes)) {
            foreach ($instance->changes as $change) {
                $changes_text[] = get_string('notification_change_' . $change, 'mod_codesandbox');
            }
        }
        $changes_summary = !empty($changes_text) ? implode(', ', $changes_text) : get_string('notification_change_general', 'mod_codesandbox');
        
        foreach ($users as $user) {
            $message->userto = $user;
            $message->subject = get_string('notification_updated_subject', 'mod_codesandbox', $instance->name);
            $message->fullmessage = get_string('notification_updated_message', 'mod_codesandbox', $changes_summary);
            $message->notification = 1;
            message_send($message);
        }
    } else if ($type === 'graded') {
        // Notificar al estudiante sobre su calificación
        if (!$student_id) return;
        
        $student = $DB->get_record('user', ['id' => $student_id]);
        if (!$student) return;

        $message->userto = $student;
        $message->subject = get_string('notification_graded_subject', 'mod_codesandbox', $instance->name);
        
        $a = new stdClass();
        $a->activityname = $instance->name;
        $a->grade = $extra_details['grade'] ?? '-';
        $a->maxgrade = $instance->grade;
        $a->comment = strip_tags($extra_details['comment'] ?? '');

        $message->fullmessage = get_string('notification_graded_message', 'mod_codesandbox', $a);
        
        // Versión HTML más rica para email/moodle
        $a->comment = $extra_details['comment'] ?? ''; // Permitir HTML en el email
        $message->fullmessagehtml = get_string('notification_graded_message_html', 'mod_codesandbox', $a);
        $message->smallmessage = get_string('notification_graded_small', 'mod_codesandbox', $instance->name);
        
        $message->notification = 1;
        $message->contexturl = (new moodle_url('/mod/codesandbox/view.php', ['id' => $instance->id]))->out(false);
        $message->contexturlname = $instance->name;

        message_send($message);
    }
}