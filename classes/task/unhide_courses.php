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

namespace enrol_lmb\task;

/**
 * Unhide LMB courses that are about to start.
 *
 * @package    enrol_lmb
 * @copyright  2026 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unhide_courses extends \core\task\scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_unhidecourses', 'enrol_lmb');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG;

        if (!enrol_is_enabled('lmb')) {
            return;
        }

        require_once($CFG->dirroot . '/enrol/lmb/lib.php');
        (new \enrol_lmb_plugin())->cron_unhide_courses();
    }
}
