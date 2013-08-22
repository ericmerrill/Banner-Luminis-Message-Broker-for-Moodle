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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

require_once('lib.php');

admin_externalpage_setup('enroltoolimportfile');

echo $OUTPUT->header();

$force = optional_param('force', 0, PARAM_INT);

echo $OUTPUT->box_start();


$enrol = new enrol_lmb_plugin();

print("<pre>");

$enrol->log_line("The import log will appear below.");

$enrol->process_file(null, $force);

print("</pre>");


echo $OUTPUT->box_end();

echo $OUTPUT->footer();

exit;
