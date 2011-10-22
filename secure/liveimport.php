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


$config = enrol_lmb_get_config();

$enrol = new enrol_lmb_plugin();
$enrol->open_log_file();
$enrol->islmb = true;

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
                $A1 = md5($data['username'] . ':' . $realm . ':' . $config->lmbpasswd);
                $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
                $valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);
                
                if ($valid_response == $data['response']) {
                    $badauth = false;
                }
            }
        }

    } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
        if ((addslashes($_SERVER['PHP_AUTH_USER']) == $config->lmbusername) && (addslashes($_SERVER['PHP_AUTH_PW']) == $config->lmbpasswd)) {
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

}

//This allows sites w/o PHP Apache Module to get header info.
if (!function_exists('getallheaders'))
{
    function getallheaders() {
       foreach ($_SERVER as $name => $value) {
           if (substr($name, 0, 5) == 'HTTP_') {
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
           }
       }
       return $headers;
    }
}



$headers = print_r(getallheaders(), true);
//$xml = $HTTP_RAW_POST_DATA;
$xml = file_get_contents('php://input');

//print $headers;


//Dont proceed if there is no xml present
if (!$xml) {
	exit;
}


$xmlstorage = new stdClass();
$xmlstorage->headers = addslashes($headers);
$xmlstorage->timereceived = time();

//Place the XML if not set to 'Never'
if ($config->storexml != 'never') {
    $xmlstorage->xml = addslashes($xml);
}

set_config('lastlmbmessagetime', time(), 'enrol/lmb');

$xmlstorage->id = $DB->insert_record('lmb_raw_xml', $xmlstorage, true);


$result = $enrol->process_xml_line($xml);

//If we have a good result, update the processed flag
if ($result) {
    $xmlupdate = new stdClass();
    
    $xmlupdate->id = $xmlstorage->id;
    $xmlupdate->processed = 1;
    
    //If we only store on error, then remove the XML from the table.
    if ($config->storexml == 'onerror') {
        $xmlupdate->xml = ''; //Can we set this to NULL? Update record doesn't seem to support it.
    }
    
    $DB->update_record('lmb_raw_xml', $xmlupdate);
}    

?>