<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID));



$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));


$nav = array();
$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/enrol_config.php?enrol=lmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => 'title');

$str = get_strings(array('enrolments', 'users', 'administration', 'settings'));
print_header("$SITE->shortname: $str->enrolments", $SITE->fullname,
              build_navigation($nav));

echo $OUTPUT->box_start();

print '<a href="../importnow.php">Import XML File Now</a> - Import the contents of the XML File now<br>';
print '<a href="extractprocess.php">Import XML Folder Now</a> - Import the contents of the XML Folder now<br>';
print '<a href="lmbstatus.php">LMB Status</a> - Information about the last time the LMB interface has received a message<br>';
print '<a href="reprocessenrolments.php">Reprocess Enrolments</a> - Reprocess enrolments that failed to process<br>';
print '<a href="prunelmbtables.php">Prune LMB Tables</a> - Remove records for a semester from Banner/LMB Module tables.<br>';

echo $OUTPUT->box_end();


echo $OUTPUT->footer();


?>