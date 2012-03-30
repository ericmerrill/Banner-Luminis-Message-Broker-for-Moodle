<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_url('/enrol/lmb/tools/extractprocess.php');
require_once('../lib.php');



$config = enrol_lmb_get_config();

$term = NULL;
$matches = array();

$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/settings.php?section=enrolsettingslmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => '');
$nav[3] = array('name' => 'XML Folder Process', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: ".get_string('enrolments', 'enrol'), $SITE->fullname,
              build_navigation($nav));

echo $OUTPUT->box_start();

//Check to see if a folder is set in config
if (!isset($config->bannerxmlfolder) || !$config->bannerxmlfolder) {
    die();
}

$enrol = new enrol_lmb_plugin();

print("<pre>");

$enrol->process_folder(NULL);

print("</pre>");

echo $OUTPUT->box_end();

echo $OUTPUT->footer();

?>