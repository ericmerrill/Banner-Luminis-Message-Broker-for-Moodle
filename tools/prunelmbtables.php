<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_login();
//require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM, SITEID));
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID));

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

/// get language strings
$str = get_strings(array('enrolments', 'users', 'administration', 'settings'));
$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/enrol_config.php?enrol=lmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => '');
$nav[3] = array('name' => 'Prune Banner LMB Module Tables', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: $str->enrolments", $SITE->fullname,
              build_navigation($nav));
              
require_once('../lib.php');


@set_time_limit(0);

echo $OUTPUT->box_start();
$sem = optional_param("sem", false, PARAM_INT);
$conf = optional_param("conf", false, PARAM_ALPHA);
$years = optional_param("years", 0, PARAM_INT);
$months = optional_param("months", 0, PARAM_INT);
$days = optional_param("days", 0, PARAM_INT);
$hours = optional_param("hours", 0, PARAM_INT);

$time = time()-(3600*24*365*3);
$timesub = false;
if ($years && $months && $days) {
    $time = mktime($hours, 0, 0, $months, $days, $years);
    $timesub = true;
    //[int hour [, int minute [, int second [, int month [, int day [, int year [, int is_dst]]]]]]])
}


if ($conf === 'Yes') {
    $conf = true;
} else if ($conf === 'No') {
    $sem = NULL;
    $conf = NULL;
} else if ($conf) {
    $sem = NULL;
    $conf = NULL;
}

if ($sem && $conf) {
    $enrol = new enrol_lmb_plugin();
    $enrol->prune_tables($sem);
    print "Pruned ".$sem.'.';
} else if ($timesub && $conf) {
    
} else if ($timesub) {
    $sqlparams = array('timereceived' => $time);
    $sql = "SELECT id, timereceived FROM {lmb_raw_xml} WHERE timereceived < :timereceived";
               
    $count = $DB->count_records_sql($sql, $sqlparams);
    
    ?>
	
	<form action="" METHOD="post">
	Are you sure you want to prune the lmb_raw_xml table? This will remove the <? echo $count; ?> records before .<br>
	<input type="hidden" name="years" value="<?php print $years; ?>">
	<input type="hidden" name="months" value="<?php print $months; ?>">
	<input type="hidden" name="days" value="<?php print $days; ?>">
	<input type="hidden" name="hours" value="<?php print $hours; ?>">
	<input TYPE=SUBMIT VALUE=Yes name=conf><input TYPE=SUBMIT VALUE=No name=conf>
	</form>
	
<?
    
} else if ($sem) {
    $term = get_record('lmb_terms', 'sourcedid', $sem);
    ?>
	
	<form action="" METHOD="post">
	Are you sure you want to delete the semester <?php print $term->title.' ('.$term->sourcedid.')'; ?> from the Banner/LMB Module tables?<br>
	<input type="hidden" name="sem" value="<?php print $sem; ?>">
	<input TYPE=SUBMIT VALUE=Yes name=conf><input TYPE=SUBMIT VALUE=No name=conf>
	</form>
	
<?
} else {
    $terms = enrol_lmb_make_terms_menu_array();
    
	?>
	
	<form action="" METHOD="post">
	This tool will delete records associated with a semester from the Banner/Luminis Message Broker module's internal tables.<br>
	<br>
	This will generally be used long after a semester has ended, and you would like to remove unneeded data from tables.<br>
	<br>
	This do not effect data in Banner or to users in Moodle - this only removed backend information used solely by this module.<br><br> 
	Semester:<?php echo html_writer::select($terms, 'sem'); ?><br>
	<input TYPE=SUBMIT VALUE="Prune...">
	</form>
	
	<br>
	<br>
	<br>
	
	<form action="" METHOD="post">
	This tool will delete LMB XML messages that were recorded for logging purposes.<br>
	<br>
	This do not effect data in Banner or to users in Moodle - this only removed backend information used solely by this module or for trouble shooting.<br><br> 
	Delete records earlier than:<?php 
	
	echo html_writer::select_time('years', 'timeyears', $time) . 'y ';
	echo html_writer::select_time('months', 'timemonths', $time) . 'm '; 
	echo html_writer::select_time('days', 'timedays', $time) . 'd '; 
	echo html_writer::select_time('hours', 'timehours', $time) . 'h '; 
	
	
	?><br>
	<input TYPE=SUBMIT VALUE="Prune...">
	</form>
	
    <?
}


echo $OUTPUT->box_end();


echo $OUTPUT->footer();

exit;
?>