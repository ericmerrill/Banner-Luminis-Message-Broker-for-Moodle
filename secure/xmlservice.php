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


$headers = serialize(getallheaders());
$xml = file_get_contents('php://input');

/*
 *  ERROR CODES
 *  1 - Succeeded
 *  2 - XML Not Received
 *  3 - Course in crosslist
 *  4 - Crosslist type mismatch
 *  5 - No member courses found in xml
 *  6 - LMB database error
 *  7 - Course DB error
 *  8 - Moodle course id error
 *  9 - Metacourse update error
 *  10 - Error adding or removing course memebers
*/



// Dont proceed if there is no xml present.
if (!$xml) {
    print '2 - POST data not received';
    exit;
}


$xmlstorage = new stdClass();
$xmlstorage->headers = $headers;
$xmlstorage->timereceived = time();
$xmlstorage->xml = $xml;
$xmlstorage->id = insert_record('enrol_lmb_raw_xml_test', $xmlstorage);

$errornum = 0;
$errormessage = '';

$enrol = new enrol_lmb_plugin();
$enrol->silent = true;
$result = $enrol->process_xml_line_error($xml, $errornum, $errormessage);


if ($result) {
    print '1 - '.$errormessage;
} else {
    print $errornum.' - '.$errormessage;
}

