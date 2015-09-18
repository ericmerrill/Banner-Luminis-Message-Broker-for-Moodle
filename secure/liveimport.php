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
 * This file receives individual XML message from Luminis Message Broker,
 * stores some info in the database, and passes it on to the module to be
 * processed.
 *
 * @author Eric Merrill (merrill@oakland.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package enrol_lmb
 * Based on enrol_imsenterprise from Dan Stowell.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('../lib.php');


$config = enrol_lmb_get_config();

$enrol = new enrol_lmb_plugin();
$enrol->open_log_file();
$enrol->islmb = true;

enrol_lmb_authenticate_http($enrol);

// This allows sites w/o PHP Apache Module to get header info.
if (!function_exists('getallheaders')) {
    function getallheaders() {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

$headers = serialize(getallheaders());
$xml = file_get_contents('php://input');


// Dont proceed if there is no xml present.
if (!$xml) {
    header("HTTP/1.0 400 Bad Request");
    header("Status: 400 Bad Request");
    exit;
}


set_config('lastlmbmessagetime', time(), 'enrol_lmb');

// Place the XML if not set to 'Never'.
if ($config->storexml != 'never') {
    $xmlstorage = new stdClass();
    $xmlstorage->headers = addslashes($headers);
    $xmlstorage->timereceived = time();

    $xmlstorage->xml = addslashes($xml);
    $xmlstorage->id = $DB->insert_record('enrol_lmb_raw_xml', $xmlstorage, true);

}


$result = $enrol->process_xml_line($xml);

// If we have a good result, update the processed flag.
if ($result) {
    switch ($config->storexml) {
        case "always":
            $xmlupdate = new stdClass();

            $xmlupdate->id = $xmlstorage->id;
            $xmlupdate->processed = 1;
            $DB->update_record('enrol_lmb_raw_xml', $xmlupdate);
            break;
        case "onerror":
            // Delete the good record.
            $DB->delete_records('enrol_lmb_raw_xml', array('id' => $xmlstorage->id));
            break;

        default:
            break;
    }
}

