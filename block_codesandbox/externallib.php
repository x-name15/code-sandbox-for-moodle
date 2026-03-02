<?php
require_once($CFG->libdir . "/externallib.php");

class block_codesandbox_external extends external_api {

    public static function save_notes_parameters() {
        return new external_function_parameters(array(
            'cmid' => new external_value(PARAM_INT, 'ID del módulo'),
            'note_text' => new external_value(PARAM_RAW, 'Contenido de la nota')
        ));
    }

    public static function save_notes($cmid, $note_text) {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_notes_parameters(), array(
            'cmid' => $cmid,
            'note_text' => $note_text
        ));

        $context = context_module::instance($params['cmid']);
        self::validate_context($context);

        $record = $DB->get_record('block_codesandbox_notes', array(
            'userid' => $USER->id, 
            'cmid' => $params['cmid']
        ));

        if ($record) {
            $record->note_text = $params['note_text'];
            $record->timemodified = time();
            $DB->update_record('block_codesandbox_notes', $record);
        } else {
            $newrecord = new stdClass();
            $newrecord->userid = $USER->id;
            $newrecord->cmid = $params['cmid'];
            $newrecord->note_text = $params['note_text'];
            $newrecord->timemodified = time();
            $DB->insert_record('block_codesandbox_notes', $newrecord);
        }

        return true;
    }

    public static function save_notes_returns() {
        return new external_value(PARAM_BOOL, 'True si se guardó correctamente');
    }
}