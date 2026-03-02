<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    // 1. Capacidad básica para ENTRAR a la actividad (Alumno, Profe, Admin)
    'mod/codesandbox:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest'          => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW
        )
    ),

    // 2. Capacidad para ver el listado de entregas (Profe, Creador y Gestor/Admin)
    'mod/codesandbox:viewreport' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW
        )
    ),

    // 3. Capacidad para poner notas (SOLO Profesor y Creador)
    // Nota: Aquí quitamos al 'manager' según pediste antes.
    'mod/codesandbox:grade' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW
        )
    ),
);