<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version      = 2016021500;           // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires     = 2013110500;           // Requires this Moodle version
$plugin->component    = 'filter_quiz_chart';  // Full name of the plugin (used for diagnostics)
$plugin->dependencies = array(
                            'mod_quiz' => ANY_VERSION,
                            'quiz_overview' => ANY_VERSION,
                        );
$plugin->maturity     = MATURITY_BETA;
$plugin->release      = 'for moodle v3.x';
