<?php
// This file is part of the Banner/LMB plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

require_once('../lib.php');

admin_externalpage_setup('enroltoolprune');

echo $OUTPUT->header();

@set_time_limit(0);

echo $OUTPUT->box_start();
$sem = optional_param("sem", false, PARAM_INT);
$conf = optional_param("conf", false, PARAM_ALPHA);
$years = optional_param("timeyears", 0, PARAM_INT);
$months = optional_param("timemonths", 0, PARAM_INT);
$days = optional_param("timedays", 0, PARAM_INT);
$hours = optional_param("timehours", 0, PARAM_INT);

$time = time()-(3600*24*365*3); // So if you just click threw, we will delete things 3yrs old.
$timesub = false;
if ($years && $months && $days) {
    $time = mktime($hours, 0, 0, $months, $days, $years);
    $timesub = true;
}


if ($conf === 'Yes') {
    $conf = true;
} else if ($conf === 'No') {
    $sem = null;
    $conf = null;
} else if ($conf) {
    $sem = null;
    $conf = null;
}

if ($sem && $conf) {
    $enrol = new enrol_lmb_plugin();
    $enrol->prune_tables($sem);
    print "Pruned ".$sem.'.';
} else if ($timesub && $conf) {
    $sqlparams = array('timereceived' => $time);
    $sql = "timereceived < :timereceived";

    $count = $DB->delete_records_select('enrol_lmb_raw_xml', $sql, $sqlparams);

    print "Deleted";
} else if ($timesub) {
    $sqlparams = array('timereceived' => $time);
    $sql = "timereceived < :timereceived";

    $count = $DB->count_records_select('enrol_lmb_raw_xml', $sql, $sqlparams);

    ?>

    <form action="" METHOD="post">
    Are you sure you want to prune the enrol_lmb_raw_xml table? This will remove the <?php echo $count; ?> records before .<br>
    <input type="hidden" name="timeyears" value="<?php print $years; ?>">
    <input type="hidden" name="timemonths" value="<?php print $months; ?>">
    <input type="hidden" name="timedays" value="<?php print $days; ?>">
    <input type="hidden" name="timehours" value="<?php print $hours; ?>">
    <input TYPE=SUBMIT VALUE=Yes name=conf><input TYPE=SUBMIT VALUE=No name=conf>
    </form>

    <?php

} else if ($sem) {
    $term = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $sem));
    ?>

    <form action="" METHOD="post">
    Are you sure you want to delete the semester
    <?php print $term->title.' ('.$term->sourcedid.')'; ?> from the Banner/LMB Module tables?<br>
    <input type="hidden" name="sem" value="<?php print $sem; ?>">
    <input TYPE=SUBMIT VALUE=Yes name=conf><input TYPE=SUBMIT VALUE=No name=conf>
    </form>

    <?php
} else {
    $terms = enrol_lmb_make_terms_menu_array();

    ?>

    <form action="" METHOD="post">
    This tool will delete records associated with a semester from the Banner/Luminis Message Broker module's internal tables.<br>
    <br>
    This will generally be used long after a semester has ended, and you would like to remove unneeded data from tables.<br>
    <br>
    This do not effect data in Banner or to users in Moodle -
    this only removed backend information used solely by this module.<br><br>
    Semester:<?php echo html_writer::select($terms, 'sem'); ?><br>
    <input TYPE=SUBMIT VALUE="Prune...">
    </form>

    <br>
    <br>
    <br>

    <form action="" METHOD="post">
    This tool will delete LMB XML messages that were recorded for logging purposes.<br>
    <br>
    This do not effect data in Banner or to users in Moodle -
    this only removed backend information used solely by this module or for trouble shooting.<br><br>
    Delete records earlier than:<?php

    echo html_writer::select_time('years', 'timeyears', $time) . 'y ';
    echo html_writer::select_time('months', 'timemonths', $time) . 'm ';
    echo html_writer::select_time('days', 'timedays', $time) . 'd ';
    echo html_writer::select_time('hours', 'timehours', $time) . 'h ';


    ?><br>
    <input TYPE=SUBMIT VALUE="Prune...">
    </form>

    <?php
}


echo $OUTPUT->box_end();


echo $OUTPUT->footer();

exit;
