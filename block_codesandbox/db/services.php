<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'block_codesandbox_save_notes' => array(
        'classname'   => 'block_codesandbox_external',
        'methodname'  => 'save_notes',
        'description' => 'Guarda asíncronamente las notas del scratchpad vinculadas al CMID.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/codesandbox:view'
    ),
);