<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_codesandbox_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026021401) {
        // Define field inputdata to be added to codesandbox_attempts.
        $table = new xmldb_table('codesandbox_attempts');
        // El campo inputdata es de tipo TEXT, despues del campo 'code'
        $field = new xmldb_field('inputdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'code');

        // Conditionally launch add field inputdata.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Codesandbox savepoint reached.
        upgrade_mod_savepoint(true, 2026021401, 'codesandbox');
    }

    return true;
}
