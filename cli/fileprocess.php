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
 * This is a support library for the enrol-lmb module and its tools
 *
 * @author Eric Merrill (merrill@oakland.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package enrol-lmb
 * Based on enrol_imsenterprise from Dan Stowell.
 */


define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/enrol/lmb/lib.php');


list($options, $unrecognized) = cli_get_params(array('force'=>false, 'filepath'=>null, 'silent'=>false, 'help'=>false),
                                               array('f'=>'filepath', 's'=>'silent'));


if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}


if ($options['help']) {
    $help =
    "LMB file import CLI tool.
Process a file using the LMB plugin.

Options:
-f, --filepath          Path to the file to process. Use config setting if not specified.
--force                 Skip file modification time checks.
-h, --help              Print out this help
-s, --silent            Don't print logging output to stdout.


Example:
\$sudo -u www-data /usr/bin/php enrol/lmb/cli/fileprocess.php
";

    echo $help;
    die;
}


$silent = (bool)$options['silent'];
$force = (bool)$options['force'];
$filepath = $options['filepath'];


$enrol = new enrol_lmb_plugin();

$enrol->silent = $silent;
$enrol->log_line("The import log will appear below.");
$enrol->process_file($filepath, $force);
