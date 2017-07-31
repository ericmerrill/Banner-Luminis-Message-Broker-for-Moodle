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

require_once('../enrollib.php');
require_once('../lib.php');

admin_externalpage_setup('enrolbackfillenddates');

echo $OUTPUT->header();

@set_time_limit(0);

echo $OUTPUT->box_start();
$sem = optional_param("sem", false, PARAM_INT);


if ($sem) {
    enrol_lmb_backfill_end_dates($sem);
} else {
    $terms = enrol_lmb_make_terms_menu_array();

    ?>

    <form action="" METHOD="post">
    Backfills the end dates of courses already added to the system.<br>
    Semester ID:<?php print html_writer::select($terms, 'sem'); ?><br>
    <input TYPE=SUBMIT VALUE=Backfill>
    </form>

    <?php
}


echo $OUTPUT->box_end();


echo $OUTPUT->footer();

exit;
