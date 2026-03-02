<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_codesandbox_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('codesandboxname', 'mod_codesandbox'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'introeditor', get_string('instructions', 'mod_codesandbox'), 
            array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true));
        $mform->setType('introeditor', PARAM_RAW);
        $mform->addRule('introeditor', get_string('error_noinstructions', 'mod_codesandbox'), 'required', null, 'client');

        $mform->addElement('header', 'sandbox_config', get_string('sandbox_settings', 'mod_codesandbox'));

        $languages = array(
            ''           => get_string('select_language_placeholder', 'mod_codesandbox'), 
            'python'     => 'Python 3',
            'javascript' => 'Node.js',
            'java'       => 'Java',
            'c'          => 'C (GCC)',
            'cpp'        => 'C++',
            'assembly'   => 'Assembly (NASM)',
            'php'        => 'PHP CLI'
        );
        $mform->addElement('select', 'language', get_string('select_language', 'mod_codesandbox'), $languages);
        $mform->addRule('language', get_string('error_nodefaultlang', 'mod_codesandbox'), 'required', null, 'client');

        $mform->addElement('text', 'grade', get_string('maxgrade', 'mod_codesandbox'), array('size'=>'3'));
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', get_string('error_nograde', 'mod_codesandbox'), 'required', null, 'client');
        $mform->addHelpButton('grade', 'maxgrade', 'mod_codesandbox');

        $mform->addElement('header', 'autograding_hdr', get_string('autograding_header', 'mod_codesandbox'));
        $mform->addElement('advcheckbox', 'use_autograding', get_string('autograding_enable', 'mod_codesandbox'), get_string('autograding_enable_desc', 'mod_codesandbox'));
        // Usamos un área de texto para JSON por ahora (más rápido de implementar)
        // Formato: [{"input": "2\n2", "output": "4"}]
        $mform->addElement('textarea', 'testcases', get_string('testcases', 'mod_codesandbox'), ['rows' => 5, 'cols' => 60]);
        $mform->addHelpButton('testcases', 'testcases', 'mod_codesandbox');
        $mform->disabledIf('testcases', 'use_autograding', 'notchecked');

        // Fecha Límite Obligatoria
        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'mod_codesandbox'));
        $mform->addRule('duedate', null, 'required', null, 'client');

        // Elementos estándar (sin notificación de actualización, usamos nuestra propia lógica)
        $this->standard_coursemodule_elements(['sendnotification' => false]);
        $this->add_action_buttons();
    }

    /**
     * Preprocesar datos antes de mostrar el formulario de edición
     * Esto asegura que los valores guardados se carguen correctamente
     */
    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);
        
        if (isset($default_values['grade'])) {
            $default_values['grade'] = (int)$default_values['grade'];
        }
        
        if (isset($default_values['duedate'])) {
            $default_values['duedate'] = (int)$default_values['duedate'];
        }
        
        // Cargar valores de autograding
        if (isset($default_values['use_autograding'])) {
            $default_values['use_autograding'] = (int)$default_values['use_autograding'];
        } else {
            $default_values['use_autograding'] = 0;
        }
        
        if (isset($default_values['testcases'])) {
            $default_values['testcases'] = $default_values['testcases'];
        } else {
            $default_values['testcases'] = '';
        }
    }
}