<?php

//TODO2 reset enrolments works all wrong

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID));

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

/// get language strings
$str = get_strings(array('enrolments', 'users', 'administration', 'settings'));
$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/enrol_config.php?enrol=lmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => '');
$nav[3] = array('name' => 'Reprocess Enrolments', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: $str->enrolments", $SITE->fullname,
              build_navigation($nav));
              
require_once('../enrollib.php');


@set_time_limit(0);

echo $OUTPUT->box_start();
$sem = optional_param("sem");


if ($sem) {
    enrol_lmb_reset_all_term_enrolments($sem);
} else {
    $terms = enrol_lmb_make_terms_menu_array();
    
	?>
	
	<form action="" METHOD="post">
	
	Semester ID:<?php choose_from_menu($terms, 'sem'); ?><br>
	<input TYPE=SUBMIT VALUE=Restore>
	</form>
	
<?
}


echo $OUTPUT->box_end();


echo $OUTPUT->footer();

exit;
?>