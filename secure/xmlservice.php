<?php
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


$headers = print_r(getallheaders(), true);
//$xml = $HTTP_RAW_POST_DATA;
$xml = file_get_contents('php://input');

// ERROR CODES
/*
1 - Succeeded
2 - XML Not Received
3 - Course in crosslist
4 - Crosslist type mismatch
5 - No member courses found in xml
6 - LMB database error
7 - Course DB error
8 - Moodle course id error
9 - Metacourse update error
10 - Error adding or removing course memebers

*/

set_config('silent', 1, 'enrol_lmb');


//Dont proceed if there is no xml present
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
$result = $enrol->process_xml_line_error($xml, $errornum, $errormessage);

set_config('silent', 0, 'enrol_lmb');

if ($result) {
    //print '1 - received'.$time."\n"; 
    print '1 - '.$errormessage;
} else {
    print $errornum.' - '.$errormessage;
}
//print '1 - received'.$time."\n"; 
//print $result;

?>