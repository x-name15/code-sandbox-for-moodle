<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026021308;        // Versión actual (AñoMesDíaSegundos)
$plugin->requires  = 2022111800;        // Requiere Moodle 4.1+
$plugin->component = 'block_codesandbox'; 
$plugin->release   = '1.0.0';
$plugin->dependencies = array('mod_codesandbox' => 2026021200);