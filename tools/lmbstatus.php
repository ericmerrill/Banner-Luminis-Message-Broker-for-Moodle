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

admin_externalpage_setup('enroltoollmbstatus');

echo $OUTPUT->header();

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

