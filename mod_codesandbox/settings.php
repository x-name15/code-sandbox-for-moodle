<?php
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('mod_codesandbox/rabbitmq_settings', 
        get_string('rabbitmq_settings', 'mod_codesandbox'), 
        get_string('rabbitmq_settings_desc', 'mod_codesandbox')));

    $settings->add(new admin_setting_configtext('mod_codesandbox/rabbitmq_host', 
        get_string('rabbitmq_host', 'mod_codesandbox'), '', 'localhost', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_codesandbox/rabbitmq_port', 
        get_string('rabbitmq_port', 'mod_codesandbox'), '', '5672', PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_codesandbox/rabbitmq_user', 
        get_string('rabbitmq_user', 'mod_codesandbox'), '', 'moodle', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_codesandbox/rabbitmq_queue', 
        get_string('rabbitmq_queue', 'mod_codesandbox'), '', 'moodle_code_jobs', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('mod_codesandbox/rabbitmq_pass', 
        get_string('rabbitmq_password', 'mod_codesandbox'), '', 'securepass'));

    // Sección: Límites del Worker
    $settings->add(new admin_setting_heading('mod_codesandbox/worker_settings', 
        get_string('worker_settings', 'mod_codesandbox'), ''));

    $settings->add(new admin_setting_configtext('mod_codesandbox/timeout', 
        get_string('worker_timeout', 'mod_codesandbox'), '', '5', PARAM_INT));
}