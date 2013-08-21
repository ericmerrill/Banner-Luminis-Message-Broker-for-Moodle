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


/*
 *  ERROR CODES
 *  0 - Unknown Error
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



$config = enrol_lmb_get_config();

$enrol = new enrol_lmb_plugin();
$enrol->open_log_file();
if (!isset($config->disablesecurity) || (!$config->disablesecurity)) {
    if ($config->lmbusername || $config->lmbpasswd) {
        $badauth = true;
        $realm = "LMB Interface";
    
        if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
    
            $digest = $_SERVER['PHP_AUTH_DIGEST'];
            preg_match_all('@(username|nonce|uri|nc|cnonce|qop|response)'.
                            '=[\'"]?([^\'",]+)@', $digest, $t);
            $data = array_combine($t[1], $t[2]);
    
            $baddigest = true;
    
            if ($data && count($data) == 7) {
                if ($data['username'] == $config->lmbusername) {
                    $a1 = md5($data['username'] . ':' . $realm . ':' . $config->lmbpasswd);
                    $a2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
                    $valid_response = md5($a1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$a2);
    
                    if ($valid_response == $data['response']) {
                        $badauth = false;
                    }
                }
            }
    
        } else if (isset($_SERVER['PHP_AUTH_USER'])) {
            if ((addslashes($_SERVER['PHP_AUTH_USER']) == $config->lmbusername)
                    && (addslashes($_SERVER['PHP_AUTH_PW']) == $config->lmbpasswd)) {
                $badauth = false;
            }
        }
    
        if ($badauth) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="'.$realm.
                   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
    
            $enrol->log_line('Unauthenticated LMB Connection');
    
            die('This is an authenticated service');
        }
    
    } else {
        $enrol->log_line('Endpoint security not configured.');

        die();
    }
}

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
    print '2 - POST data not received';
    exit;
}



// Place the XML if not set to 'Never'.
if ($config->storexml != 'never') {
    $xmlstorage = new stdClass();
    $xmlstorage->headers = addslashes($headers);
    $xmlstorage->timereceived = time();

    $xmlstorage->xml = addslashes($xml);
    $xmlstorage->id = $DB->insert_record('enrol_lmb_raw_xml', $xmlstorage, true);

}


$errornum = 0;
$errormessage = '';

$enrol->silent = true;
$result = $enrol->process_xml_line_error($xml, $errornum, $errormessage);


// If we have a good result, update the processed flag.
if ($result) {
    print '1 - '.$errormessage;
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
} else {
    print $errornum.' - '.$errormessage;
}

