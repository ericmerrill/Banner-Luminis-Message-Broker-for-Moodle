<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID));

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

$nav = array();

require_once('../enrollib.php');

$str = get_strings(array('enrolments', 'users', 'administration', 'settings'));
$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/enrol_config.php?enrol=lmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => '');
$nav[3] = array('name' => 'LMB Status', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: $str->enrolments", $SITE->fullname,
              build_navigation($nav));

$config = enrol_lmb_get_config();


echo $OUTPUT->box_start();

print "Last message time: ";

if (isset($config->lastlmbmessagetime) && $config->lastlmbmessagetime) {
	print $config->lastlmbmessagetime." (".userdate($config->lastlmbmessagetime).")";
} else {
	print "This Moodle install has never received a message on its LMB interface.";
}

echo $OUTPUT->box_end();


echo $OUTPUT->footer();

exit;

?>