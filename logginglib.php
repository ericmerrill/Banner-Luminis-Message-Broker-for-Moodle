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

/**
 * This provides logging services for enrol_lmb
 *
 * @author Eric Merrill (merrill@oakland.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package enrol-lmb
 * Based on enrol_imsenterprise from Dan Stowell.
 */
const ENROL_LMB_LOG_NONE    = 0;
const ENROL_LMB_LOG_INFO    = 1;
const ENROL_LMB_LOG_UPDATE  = 2;
const ENROL_LMB_LOG_NOTICE  = 3;
const ENROL_LMB_LOG_WARN    = 4;
const ENROL_LMB_LOG_FAIL    = 5;
const ENROL_LMB_LOG_FATAL   = 6;


class enrol_lmb_logging {


} // End of class.


class enrol_lmb_log_record {
    private $currentlevel = ENROL_LMB_LOG_NONE;

    //$message = '';

    // Append a level and possibly a new level.
    public function append_message($msg, $lvl = ENROL_LMB_LOG_NONE) {
        $this->set_minimum_level($lvl);
        $this->message .= $msg;
    }

    // Set the level to at least the passed value;
    public function set_minimum_level($lvl) {
        if ($lvl > $this->currentlevel) {
            $this->currentlevel = $lvl;
        }
    }


    public function write_to_log() {

    }
}
