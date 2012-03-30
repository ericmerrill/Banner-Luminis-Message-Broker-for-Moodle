<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_url('/enrol/lmb/importnow.php');

$nav[0] = array('name' => 'Admin', 'link' => '../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../'.$CFG->admin.'/settings.php?section=enrolsettingslmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => 'tools/index.php', 'type' => '');
$nav[3] = array('name' => 'XML File Process', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: ".get_string('enrolments', 'enrol'), $SITE->fullname,
              build_navigation($nav));

require_once('lib.php');

$force = optional_param('force', 0, PARAM_INT);

echo $OUTPUT->box_start();


$enrol = new enrol_lmb_plugin();

print("<pre>");

$enrol->log_line("The import log will appear below.");

$enrol->process_file(NULL, $force);

print("</pre>");


echo $OUTPUT->box_end();

echo $OUTPUT->footer();

exit;
?>