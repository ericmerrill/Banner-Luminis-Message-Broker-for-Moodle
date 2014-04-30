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
 * This is an enrolment module for Moodle that parses Banner XML, either in a
 * bulk file, or indivdual chunks from Luminis Message Broker (not related
 * to Luminis Portal).
 *
 * @author Eric Merrill (merrill@oakland.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package enrol-lmb
 * Based on enrol_imsenterprise from Dan Stowell.
 */
require_once('enrollib.php');
require_once('logginglib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/gradelib.php');




class enrol_lmb_plugin extends enrol_plugin {

    private $log;
    private $logline = ''; // A line for the log.
    private $logerror = false; // Line contains an error if true.
    private $linestatus = true;
    private $logonlyerrors = true;

    public $silent = false;
    public $islmb = false;

    public $processid = 0;
    private $terms = array();

    private $customfields = array();

    private $loggers = array();



    // -----------------------------------------------------------------------------------------------------------------
    // Input Processing.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Used to call process_xml_line_error() without passing
     * error variables. See process_xml_line_error()
     *
     * @param string $xml the xml string to process
     * @return bool success or failure of the processing
     */
    public function process_xml_line($xml) {
        $errorcode = 0;
        $errormessage = '';

        return $this->process_xml_line_error($xml, $errorcode, $errormessage);
    }

    /**
     * Processes a single tag (membership, group, person, etc) of xml
     *
     * @param string $xml the xml string to process
     * @param string $errorcode an error code number
     * @param string $errormessage an error message
     * @return bool success or failure of the processing
     */
    public function process_xml_line_error($xml, &$errorcode, &$errormessage) {

        $this->open_log_file();

        $curline = $xml;
        $this->xmlcache .= $curline; // Add a line onto the XML cache.

        $status = false;

        if ($tagcontents = $this->full_tag_found_in_cache('group', $curline)) {
            $status = $this->process_group_tag($tagcontents);
            $this->remove_tag_from_cache('group');
        } else if ($tagcontents = $this->full_tag_found_in_cache('person', $curline)) {
            $status = $this->process_person_tag($tagcontents);
            $this->remove_tag_from_cache('person');
        } else if ($tagcontents = $this->full_tag_found_in_cache('membership', $curline)) {
            $status = $this->process_membership_tag_error($tagcontents, $errorcode, $errormessage);
            $this->remove_tag_from_cache('membership');
        } else if ($tagcontents = $this->full_tag_found_in_cache('comments', $curline)) {
            $this->remove_tag_from_cache('comments');
        } else if ($tagcontents = $this->full_tag_found_in_cache('properties', $curline)) {
            $status = $this->process_properties_tag($tagcontents);
            $this->remove_tag_from_cache('properties');
        }
        // TODO.
        /* else */

        return $status;
    }

    /**
     * Process an entire file of xml
     *
     * @param string $filename the file to process. use config location if unspecified
     * @param bool $force forse the processing to occur, even if already processing or there is no file time change.
     * @return bool success or failure of the processing
     */
    public function process_file($filename = null, $force = false, $folderprocess = false, $processid = null) {
        if (!$this->processid) {
            $this->processid = time();
        }

        if (!$folderprocess && ($this->get_config('processingfile') !== null) && $this->get_config('processingfile') && !$force) {
            return;
        }

        $comp = false;
        if (!$folderprocess && !$filename && ($this->get_config('bannerxmllocation') !== null)) {
            $filename = $this->get_config('bannerxmllocation');
            if ($this->get_config('bannerxmllocationcomp')) {
                $comp = true;
            }
        }

        $filetime = filemtime($filename);

        if (!$folderprocess && ($this->get_config('xmlfiletime') !== null)
                && ($this->get_config('xmlfiletime') >= $filetime) && !$force) {
            return;
        }

        $this->open_log_file();

        if ( file_exists($filename) ) {
            @set_time_limit(0);
            $starttime = time();

            $this->set_config('processingfile', $starttime);

            logger::log('Found file '.$filename, ENROL_LMB_LOG_INFO);
            $this->xmlcache = '';

            // The list of tags which should trigger action (even if only cache trimming).
            $listoftags = array('group', 'person', 'member', 'membership', 'comments', 'properties');
            // The <properties> tag is allowed to halt processing if we're demanding a matching target.
            $this->continueprocessing = true;

            if (($fh = fopen($filename, "r")) != false) {
                $stats = fstat($fh);
                $fsize = $stats['size'];
                $csize = 0;
                $percent = 0;

                $line = 0;
                while ((!feof($fh)) && $this->continueprocessing) {

                    $line++;
                    $curline = fgets($fh);
                    $csize += strlen($curline);
                    $this->xmlcache .= $curline; // Add a line onto the XML cache.

                    $cperc = (int)floor(($csize/$fsize)*100);
                    if ($cperc > $percent) {
                        $percent = $cperc;
                        if (($this->get_config('logpercent') !== null) && $this->get_config('logpercent')) {
                            logger::log($percent.'% complete', ENROL_LMB_LOG_INFO, true);
                        }
                    }

                    while (true) {
                        // If we've got a full tag (i.e. the most recent line has closed the tag) then process-it-and-forget-it.
                        // Must always make sure to remove tags from cache so they don't clog up our memory.
                        if ($tagcontents = $this->full_tag_found_in_cache('group', $curline)) {
                            $this->process_group_tag($tagcontents);
                            $this->remove_tag_from_cache('group');
                        } else if ($tagcontents = $this->full_tag_found_in_cache('person', $curline)) {
                            $this->process_person_tag($tagcontents);
                            $this->remove_tag_from_cache('person');
                        } else if ($tagcontents = $this->full_tag_found_in_cache('membership', $curline)) {
                            $this->process_membership_tag($tagcontents);
                            $this->remove_tag_from_cache('membership');
                        } else if ($tagcontents = $this->full_tag_found_in_cache('comments', $curline)) {
                            $this->remove_tag_from_cache('comments');
                        } else if ($tagcontents = $this->full_tag_found_in_cache('properties', $curline)) {
                            $this->process_properties_tag($tagcontents);
                            $this->remove_tag_from_cache('properties');
                        } else {
                            break;
                        }
                    } // End of while-tags-are-detected.
                } // End of while loop.
                fclose($fh);
            } // End of if (file_open) for first pass.

            fix_course_sortorder();

            $timeelapsed = time() - $starttime;

            $this->set_config('xmlfiletime', $filetime);
            $this->set_config('processingfile', 0);

            logger::log('Process has completed. Time taken: '.$timeelapsed.' seconds.', ENROL_LMB_LOG_INFO);

            if ($comp) {
                $this->process_extract_drops();
            }

        } else { // End of if (file_exists).
            logger::log('File not found: '.$filename, ENROL_LMB_LOG_WARN);
        }

    }

    /**
     * Process a folder of xml file(s)
     *
     * @param string $filename the file to process. use config location if unspecified
     * @param bool $force forse the processing to occur, even if already processing or there is no file time change.
     * @return bool success or failure of the processing
     */
    public function process_folder($folder = null, $term = null, $force = false) {
        $this->processid = time();

        if ($this->get_config('processingfolder') && !$force) {
            return;
        }

        if (!$folder && ($this->get_config('bannerxmlfolder') !== null)) {
            $folder = $this->get_config('bannerxmlfolder');
        }

        // Add a trailing slash if it isnt there.
        if (!preg_match('{.*/$}', $folder)) {
            $folder = $folder.'/';
        }

        // Open the folder and look through all the files.
        $startfile = false;
        $processingfile = false;
        $files = array();
        $matches = array();
        if (is_dir($folder) && $dh = opendir($folder)) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match('{.*xml}is', $file)) {
                    array_push($files, $folder.$file);
                }
                if (preg_match('{start.*}is', $file)) {
                    $startfile = $folder.$file;
                }
                if (preg_match('{processing.*}is', $file)) {
                    $processingfile = $folder.$file;
                }
                if (preg_match('{term(.+)}is', $file, $matches)) {
                    $term = trim($matches[1]);
                }
            }
        } else {
            return false;
        }

        if (count($files) == 0) {
            return false;
        }

        sort($files);

        if ($this->get_config('usestatusfiles')) {
            if (!$startfile) {
                return false;
            } else {
                $processfile = $folder.'processing';
                $donefile = $folder.'done';

                unlink($startfile);
                fclose(fopen($processfile, 'x'));
            }
        }

        foreach ($files as $file) {
            print $file;
            $this->process_file($file, true, true);
        }

        if ($this->get_config('bannerxmlfoldercomp')) {
            $this->process_extract_drops();
        }

        if ($this->get_config('usestatusfiles')) {
            unlink($processfile);
            fclose(fopen($donefile, 'x'));
        }
    }

    /**
     * Check if a complete tag is found in the cached data, which usually happens
     * when the end of the tag has only just been loaded into the cache.
     * Returns either false, or the contents of the tag (including start and end).
     *
     * @param string $tagname Name of tag to look for
     * @param string $latestline The very last line in the cache (used for speeding up the match)
     * @return string|bool the tag and contents or false
     */
    public function full_tag_found_in_cache($tagname, $latestline) {
        // Return entire element if found. Otherwise return false.
        if (strpos(strtolower($latestline), '</'.strtolower($tagname).'>')===false) {
            return false;
        } else if (preg_match('{(<'.$tagname.'\b.*?\>.*?</'.$tagname.'>)}is', $this->xmlcache, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * Remove complete tag from the cached data (including all its contents) - so
     * that the cache doesn't grow to unmanageable size
     *
     * @param string $tagname Name of tag to look for
     */
    public function remove_tag_from_cache($tagname) { // Trim the cache so we're not in danger of running out of memory.
        // "1" so that we replace only the FIRST instance.
        $this->xmlcache = trim(preg_replace('{<'.$tagname.'\b.*?\>.*?</'.$tagname.'>}is', '', $this->xmlcache, 1));
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Tag Controllers.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Processes a given person tag, updating or creating a moodle user as
     * needed.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success of failure of processing the tag
     */
    public function process_person_tag($tagcontents) {
        // TODO check for error.
        // TODO status flags?

        if (!$this->get_config('parsepersonxml')) {
            logger::log('Person messages disabled, skipping.', ENROL_LMB_LOG_INFO);
            return true;
        }

        //$this->append_log_line('Person');
        $xmlarray = enrol_lmb_xml_to_array($tagcontents);
        $lmbperson = $this->xml_to_person($xmlarray);

        $moodleuser = $this->person_to_moodleuser($lmbperson);

        $this->log_line_new();
        return $this->linestatus;
    }

    /**
     * Used to call process_membership_tag_error() without passing
     * error variables. See process_membership_tag_error()
     *
     * @param string $tagcontents the xml string to process
     * @return bool success or failure of the processing
     */
    public function process_membership_tag($tagcontents) {
        $errorcode = 0;
        $errormessage = '';

        return $this->process_membership_tag_error($tagcontents, $errorcode, $errormessage);
    }

    /**
     * Process the membership tag. Sends the tag onto the appropriate tag processor
     *
     * @param string $tagconents The raw contents of the XML element
     * @param string $errorcode an error code number
     * @param string $errormessage an error message
     * @return bool success or failure of the processing
     */
    public function process_membership_tag_error($tagcontents, &$errorcode, &$errormessage) {
        $xmlarray = enrol_lmb_xml_to_array($tagcontents);

        if (stripos($xmlarray['membership']['#']['sourcedid'][0]['#']['id'][0]['#'], 'XLS') === 0) {
            $memberships = $this->xml_to_xls_memberships($xmlarray);
            $this->xml_memberships_to_moodlecourse($memberships);
        } else if ($xmlarray['membership']['#']['sourcedid'][0]['#']['source'][0]['#'] === 'Plugin Internal') {
            $memberships = $this->xml_to_xls_memberships($xmlarray);
            $this->xml_memberships_to_moodlecourse($memberships);
        } else {
            $memberships = $this->xml_to_person_memberships($xmlarray);
            $this->person_memberships_to_enrolments($memberships);
        }

        $this->log_line_new();
        return $this->linestatus;

    }

    /**
     * Process the group tag. Sends the tag onto the appropriate tag processor
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool the status as returned from the tag processor
     */
    public function process_group_tag($tagcontents) {
        $xmlarray = enrol_lmb_xml_to_array($tagcontents);
        if (!is_array($xmlarray) || !isset($xmlarray['group']) || !isset($xmlarray['group']['#'])) {
            logger::log('Malformed group XML message.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }

        switch ($xmlarray['group']['#']['grouptype'][0]['#']['typevalue'][0]['#']) {
            case 'Term':
                $this->xml_to_term($xmlarray);
                break;

            case 'CourseSection':
                $course = $this->xml_to_course($xmlarray);
                $this->course_to_moodlecourse($course);
                break;

            case 'CrossListedSection':
                return $this->process_crosslisted_group_tag($xmlarray);
                break;

        }

        $this->log_line_new();
        return $this->linestatus;
    }

    /**
     * Processes tag for the definition of a crosslisting group.
     * Currently does nothing. See process_membership_tag_error()
     *
     * @param array $xmlarray XML array
     * @return bool success or failure of the processing
     */
    public function process_crosslisted_group_tag($xmlarray) {
        //$this->append_log_line('Crosslist Group');
        if ((!$this->get_config('parsexlsxml')) || (!$this->get_config('parsecoursexml'))) {
            logger::log('Crosslist messages disabled, skipping.', ENROL_LMB_LOG_INFO);
            return true;
        }

        return true;
    }

    /**
     * Process the properties tag. Currently unimplimented. Need to look into need.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success or failure
     */
    public function process_properties_tag($tagcontents) {

        return true;
    }


    // -----------------------------------------------------------------------------------------------------------------
    // New XML.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Process the provided XML person array into a internal person obeject.
     * Processed object is also stored.
     *
     * @param array $xmlarray The raw contents of the XML message in array form
     * @return stdClass A object representing a LMB Person
     */
    public function xml_to_person($xmlarray) {
        global $DB;
        $person = new stdClass();

        if (!is_array($xmlarray) || !isset($xmlarray['person']) || !isset($xmlarray['person']['#'])) {
            logger::log('Malformed person XML message.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }

        $xmlperson = $xmlarray['person']['#'];

        // Sourcedid Source.
        if (!isset($xmlperson['sourcedid'][0]['#']['source'][0]['#'])) {
            logger::log('Person sourcedid>source not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $person->sourcedidsource = $xmlperson['sourcedid'][0]['#']['source'][0]['#'];

        // Sourcedid Id.
        if (!isset($xmlperson['sourcedid'][0]['#']['id'][0]['#'])) {
            logger::log('Person sourcedid not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $person->sourcedid = $xmlperson['sourcedid'][0]['#']['id'][0]['#'];

        // Rec status.
        if (isset($xmlarray['person']['@']['recstatus'])) {
            $person->recstatus = $xmlarray['person']['@']['recstatus'];
        } else {
            $person->recstatus = '';
        }

        // Full Name.
        if (isset($xmlperson['name'][0]['#']['fn'][0]['#'])) {
            $person->fullname = $xmlperson['name'][0]['#']['fn'][0]['#'];
        } else {
            $person->fullname = null;
        }

        // Nickname.
        if (isset($xmlperson['name'][0]['#']['nickname'][0]['#'])) {
            $person->nickname = $xmlperson['name'][0]['#']['nickname'][0]['#'];
        } else {
            $person->nickname = null;
        }

        // Given Name.
        if (isset($xmlperson['name'][0]['#']['n'][0]['#']['given'][0]['#'])) {
            $person->givenname = $xmlperson['name'][0]['#']['n'][0]['#']['given'][0]['#'];
        } else {
            $person->givenname = null;
        }

        // Family Name.
        if (isset($xmlperson['name'][0]['#']['n'][0]['#']['family'][0]['#'])) {
            $person->familyname = $xmlperson['name'][0]['#']['n'][0]['#']['family'][0]['#'];
        } else {
            $person->familyname = null;
        }

        // Email.
        if (isset($xmlperson['email'][0]['#'])) {
            $person->email = $xmlperson['email'][0]['#'];
        } else {
            $person->email = null;
        }

        // Telephone.
        if (isset($xmlperson['tel'][0]['#'])) {
            $person->telephone = $xmlperson['tel'][0]['#'];
        } else {
            $person->telephone = null;
        }

        // Street.
        if (isset($xmlperson['adr'][0]['#']['street'][0]['#'])) {
            $person->adrstreet = $xmlperson['adr'][0]['#']['street'][0]['#'];
        } else {
            $person->adrstreet = null;
        }

        // Locality.
        if (isset($xmlperson['adr'][0]['#']['locality'][0]['#'])) {
            $person->locality = $xmlperson['adr'][0]['#']['locality'][0]['#'];
        } else {
            $person->locality = null;
        }

        // Region.
        if (isset($xmlperson['adr'][0]['#']['region'][0]['#'])) {
            $person->region = $xmlperson['adr'][0]['#']['region'][0]['#'];
        } else {
            $person->region = null;
        }

        // Country.
        if (isset($xmlperson['adr'][0]['#']['country'][0]['#'])) {
            $person->country = $xmlperson['adr'][0]['#']['country'][0]['#'];
        } else {
            $person->country = null;
        }

        // Academic Major.
        if (isset($xmlperson['extension'][0]['#']['luminisperson'][0]['#']['academicmajor'][0]['#'])) {
            $person->academicmajor = $xmlperson['extension'][0]['#']['luminisperson'][0]['#']['academicmajor'][0]['#'];
        } else {
            $person->academicmajor = null;
        }

        // Select the username.
        $person->username = '';
        switch ($this->get_config('usernamesource')) {
            case "email":
                if (isset($person->email)) {
                    $person->username = $person->email;
                }
                break;

            case "emailname":
                if (isset($person->email) && preg_match('{(.+?)@.*?}is', $person->email, $matches)) {
                    $person->username = trim($matches[1]);
                }
                break;

            case "other":
                $type = $this->get_config('useridtypeother');
            case "loginid":
                if (!isset($type)) {
                    $type = 'Logon ID';
                }
            case "sctid":
                if (!isset($type)) {
                    $type = 'SCTID';
                }
            case "emailid":
                if (!isset($type)) {
                    $type = 'Email ID';
                }

                if (isset($xmlperson['userid'])) {
                    foreach ($xmlperson['userid'] as $row) {
                        if (isset($row['@']['useridtype'])) {
                            if ($row['@']['useridtype'] === $type) {
                                $person->username = $row['#'];
                            }
                        }
                    }
                }

                break;

            default:
                logger::log('Bad enrol_lmb_usernamesource setting.', ENROL_LMB_LOG_WARN);

                //$this->linestatus = false; // TODO?

        }
        unset($type);

        if ($this->get_config('sourcedidfallback') && trim($person->username)=='') {
            // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied...
            // ...NB We don't use an "else if" because the tag may be supplied-but-empty.
            $person->username = $person->sourcedid.'';
        }

        // Custom field mapping.
        if ($this->get_config('customfield1mapping')) {

            switch($this->get_config('customfield1source')) {
                case "loginid":
                    if (!isset($type)) {
                        $type = 'Logon ID';
                    }
                case "sctid":
                    if (!isset($type)) {
                        $type = 'SCTID';
                    }
                case "emailid":
                    if (!isset($type)) {
                        $type = 'Email ID';
                    }

                    if (isset($xmlperson['userid'])) {
                        foreach ($xmlperson['userid'] as $row) {
                            if (isset($row['@']['useridtype'])) {
                                if ($row['@']['useridtype'] === $type) {
                                    $person->customfield1 = $row['#'];
                                }
                            }
                        }
                    }
                    break;

                default:
                    logger::log('Bad enrol_lmb_customfield1mapping setting.', ENROL_LMB_LOG_WARN);
            }
        }
        unset($type);

        if ($this->get_config('sourcedidfallback') && trim($person->username)=='') {
            // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied...
            $person->username = $person->sourcedid.'';
        } //TODO Duplicate - Remove.

        // Select the password.
        switch ($this->get_config('passwordnamesource')) {
            case "none":
                break;

            case "other":
                $type = $this->get_config('passworduseridtypeother');
            case "loginid":
                if (!isset($type)) {
                    $type = 'Logon ID';
                }
            case "sctid":
                if (!isset($type)) {
                    $type = 'SCTID';
                }
            case "emailid":
                if (!isset($type)) {
                    $type = 'Email ID';
                }

                if (isset($xmlperson['userid'])) {
                    foreach ($xmlperson['userid'] as $row) {
                        if (isset($row['@']['useridtype'])) {
                            if ($row['@']['useridtype'] === $type) {
                                $person->password = $row['@']['password'];
                            }
                        }
                    }
                }

                break;

            default:
                //$this->linestatus = false;
                logger::log('Bad enrol_lmb_passwordnamesource setting.', ENROL_LMB_LOG_WARN);

        }
        unset($type);

        $person->auth = $this->get_config('auth');

        $person->timemodified = time();

        $this->update_or_insert_lmb($person, 'enrol_lmb_people');

        return $person;
    }

    /**
     * Processes a given term tag. Basically just inserting the info
     * in a lmb internal table for future use.
     *
     * @param array $xmlarray The raw contents of the XML element in array format
     * @return stdClass The term object
     */
    public function xml_to_term($xmlarray) {
        global $DB;
        $term = new stdClass();

        $this->append_log_line('Term');

        if (!is_array($xmlarray) || !isset($xmlarray['group']) || !isset($xmlarray['group']['#'])) {
            logger::log('Malformed group XML message for term.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }

        $xmlgroup = $xmlarray['group']['#'];

        // Sourcedid Source.
        if (!isset($xmlgroup['sourcedid'][0]['#']['source'][0]['#'])) {
            logger::log('Term sourcedid>source not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $term->sourcedidsource = $xmlgroup['sourcedid'][0]['#']['source'][0]['#'];

        // Sourcedid.
        if (!isset($xmlgroup['sourcedid'][0]['#']['id'][0]['#'])) {
            logger::log('Term sourcedid not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $term->sourcedid = $xmlgroup['sourcedid'][0]['#']['id'][0]['#'];

        // Long Description.
        if (isset($xmlgroup['description'][0]['#']['long'][0]['#'])) {
            $term->title = $xmlgroup['description'][0]['#']['long'][0]['#'];
        } else {
            logger::log('Long description not found', ENROL_LMB_LOG_WARN);
            $this->linestatus = false;
        }

        // Timeframe begin.
        if (isset($xmlgroup['timeframe'][0]['#']['begin'][0]['#'])) {
            $date = explode('-', trim($xmlgroup['timeframe'][0]['#']['begin'][0]['#']));
            $term->starttime = make_timestamp($date[0], $date[1], $date[2]);
        }

        // Timeframe end.
        if (isset($xmlgroup['timeframe'][0]['#']['end'][0]['#'])) {
            $date = explode('-', trim($xmlgroup['timeframe'][0]['#']['begin'][0]['#']));
            $term->endtime = make_timestamp($date[0], $date[1], $date[2]);
        }

        $term->timemodified = time();

        $this->update_or_insert_lmb($term, 'enrol_lmb_terms');

        return $term;
    }

    public function xml_to_course($xmlarray) {
        global $DB;
        $course = new stdClass();

        //$this->append_log_line('Course');

        if (!is_array($xmlarray) || !isset($xmlarray['group']) || !isset($xmlarray['group']['#'])) {
            $this->log_error('Malformed group XML message for course.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }

        $xmlgroup = $xmlarray['group']['#'];

        // Sourcedid Source.
        if (!isset($xmlgroup['sourcedid'][0]['#']['source'][0]['#'])) {
            logger::log('Course sourcedid>source not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $course->sourcedidsource = $xmlgroup['sourcedid'][0]['#']['source'][0]['#'];

        // Sourcedid.
        if (!isset($xmlgroup['sourcedid'][0]['#']['id'][0]['#'])) {
            logger::log('Course sourcedid not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $course->sourcedid = $xmlgroup['sourcedid'][0]['#']['id'][0]['#'];

        // Course parts.
        $parts = explode('.', $course->sourcedid);
        $course->coursenumber = $parts[0];
        $course->term = $parts[1];

        // Course information.
        if (isset($xmlgroup['description'][0]['#']['long'][0]['#'])) {
            $course->longtitle = trim($xmlgroup['description'][0]['#']['long'][0]['#']);

            $parts = explode('-', $course->longtitle);

            $course->rubric = $parts[0].'-'.$parts[1];
            $course->dept = $parts[0];
            $course->num = $parts[1];
            $course->section = $parts[2];
        }

        // Full title.
        if (isset($xmlgroup['description'][0]['#']['full'][0]['#'])) {
            $course->fulltitle = trim($xmlgroup['description'][0]['#']['full'][0]['#']);
        }

        // Start date.
        if (isset($xmlgroup['timeframe'][0]['#']['begin'][0]['#'])) {
            $date = explode('-', trim($xmlgroup['timeframe'][0]['#']['begin'][0]['#']));

            $course->startdate = make_timestamp($date[0], $date[1], $date[2]);
        }

        // End date.
        if (isset($xmlgroup['timeframe'][0]['#']['end'][0]['#'])) {
            $date = explode('-', trim($xmlgroup['timeframe'][0]['#']['end'][0]['#']));

            $course->enddate = make_timestamp($date[0], $date[1], $date[2]);
        }

        // Org unit (department).
        if (isset($xmlgroup['org'][0]['#']['orgunit'][0]['#'])) {
            $course->depttitle = trim($xmlgroup['org'][0]['#']['orgunit'][0]['#']);
        } else {
            $course->depttitle = '';
            logger::log('Course '.$course->sourcedid.' org/orgunit not defined.', ENROL_LMB_LOG_NOTICE);
        }

        $course->timemodified = time();

        $this->update_or_insert_lmb($course, 'enrol_lmb_courses');

        return $course;
    }

    public function xml_to_person_memberships($xmlarray) {
        //$this->append_log_line('Enrolment');

        if ((!$this->get_config('parsepersonxml')) || (!$this->get_config('parsecoursexml'))
                || (!$this->get_config('parsepersonxml'))) {
            logger::log('Enrolments disabled, skipping.', ENROL_LMB_LOG_INFO);
            return array();
        }

        $output = array();
        $membership = new stdClass;

        $xmlmembership = $xmlarray['membership']['#'];

        // Sourcedid Source.
        /* TODO
        if (!isset($xmlmembership['sourcedid'][0]['#']['source'][0]['#'])) {
            $this->log_error('Sourcedid source not found');
            $this->linestatus = false;
            return false;
        }
        $membership->sourcedidsource = $xmlgroup['sourcedid'][0]['#']['source'][0]['#'];
        */

        // Sourcedid.
        if (!isset($xmlmembership['sourcedid'][0]['#']['id'][0]['#'])) {
            logger::log('Person enrolment parent sourcedid not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $membership->coursesourcedid = $xmlmembership['sourcedid'][0]['#']['id'][0]['#'];

        if (preg_match('{.....\.(.+?)$}is', $membership->coursesourcedid, $matches)) {
            $membership->term = trim($matches[1]);
        }

        foreach ($xmlmembership['member'] as $key => $member) {
            $output[$key] = clone $membership;
            $member = $member['#'];

            // Sourcedid.
            // Todo, shouldn't error out if one member fails.
            if (!isset($member['sourcedid'][0]['#']['id'][0]['#'])) {
                logger::log('Person enrolment child sourcedid not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->personsourcedid = $member['sourcedid'][0]['#']['id'][0]['#'];

            // Role.
            if (!isset($member['role'][0]['@']['roletype'])) {
                logger::log('Person enrolment role not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->role = (int)$member['role'][0]['@']['roletype'];

            // Status.
            if (!isset($member['role'][0]['#']['status'][0]['#'])) {
                logger::log('Person enrolment status not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->status = trim($member['role'][0]['#']['status'][0]['#']);

            // Rec Status.
            if (isset($member['role'][0]['@']['recstatus'])) {
                $recstatus = (int)trim($member['role'][0]['@']['recstatus']);
                if ($recstatus==3) {
                    $output[$key]->status = 0;
                }
            }

            // Being Restrict.
            if (isset($member['role'][0]['#']['timeframe'][0]['#']['begin'])) {
                $output[$key]->beginrestrict = (int)$member['role'][0]['#']['timeframe'][0]['#']['begin'][0]['@']['restrict'];

                $date = explode('-', trim($member['role'][0]['#']['timeframe'][0]['#']['begin'][0]['#']));
                $output[$key]->beginrestricttime = make_timestamp($date[0], $date[1], $date[2]);
            } else {
                $output[$key]->beginrestrict = 0;
                $output[$key]->beginrestricttime = 0;
            }

            // End Restrict.
            if (isset($member['role'][0]['#']['timeframe'][0]['#']['end'])) {
                $output[$key]->endrestrict = (int)$member['role'][0]['#']['timeframe'][0]['#']['end'][0]['@']['restrict'];

                $date = explode('-', trim($member['role'][0]['#']['timeframe'][0]['#']['end'][0]['#']));
                $output[$key]->endrestricttime = make_timestamp($date[0], $date[1], $date[2], 23, 59, 59);
            } else {
                $output[$key]->endrestrict = 0;
                $output[$key]->endrestricttime = 0;
            }

            // Interm Grade Type.
            if (isset($member['role'][0]['#']['interimresult'][0]['#']['mode'][0]['#'])) {
                $output[$key]->midtermgrademode = trim($member['role'][0]['#']['interimresult'][0]['#']['mode'][0]['#']);
            }

            // Final Grade Type.
            if (isset($member['role'][0]['#']['finalresult'][0]['#']['mode'][0]['#'])) {
                $output[$key]->finalgrademode = trim($member['role'][0]['#']['finalresult'][0]['#']['mode'][0]['#']);
            }

            // Gradable.
            if (isset($member['role'][0]['#']['extension'][0]['#']['extension'][0]['#'])) {
                $output[$key]->gradable = (int)trim($member['role'][0]['#']['extension'][0]['#']['extension'][0]['#']);
            } else {
                // Per e-learn docs, if ommited, then membership is gradable.
                if (isset($output[$key]->midtermgrademode) || isset($output[$key]->finalgrademode)) {
                    $output[$key]->gradable = 1;
                }
            }

            if ($this->processid) {
                $output[$key]->extractstatus = $this->processid;
            }

            // Tracking for enrolment percentages.
            if (!isset($this->terms[$output[$key]->term])) {
                $this->terms[$output[$key]->term] = 0;
            }
            $this->terms[$output[$key]->term]++;

            $this->update_or_insert_lmb($output[$key], 'enrol_lmb_enrolments');
        }

        return $output;
    }

    public function xml_to_xls_memberships($xmlarray) {
        global $DB;
        //$this->append_log_line('Crosslist Membership');

        if (!$this->get_config('parsexlsxml')) {
            $this->append_log_line('skipping.');
            return array();
        }

        $output = array();
        $membership = new stdClass;

        $xmlmembership = $xmlarray['membership']['#'];

        // Sourcedid Source.
        if (!isset($xmlmembership['sourcedid'][0]['#']['source'][0]['#'])) {
            logger::log('Crosslist membership sourcedid>source not found.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            return false;
        }
        $membership->crosssourcedidsource = $xmlmembership['sourcedid'][0]['#']['source'][0]['#'];

        if ($membership->crosssourcedidsource == 'Plugin Internal') {
            // Sourcedid.
            if (isset($xmlmembership['sourcedid'][0]['#']['id'][0]['#'])) {
                $membership->crosslistsourcedid = $xmlmembership['sourcedid'][0]['#']['id'][0]['#'];
            } else {
                $membership->crosslistsourcedid = enrol_lmb_create_new_crosslistid();
                $parts = explode('.', $xlist->coursesourcedid);
                $term = $parts[1];
                $membership->crosslistsourcedid .= $term;
            }
        } else {
            // Sourcedid.
            if (!isset($xmlmembership['sourcedid'][0]['#']['id'][0]['#'])) {
                logger::log('Crosslist membership sourcedid not found.', ENROL_LMB_LOG_FAIL);
                $this->linestatus = false;
                return false;
            }
            $membership->crosslistsourcedid = $xmlmembership['sourcedid'][0]['#']['id'][0]['#'];
        }

        // Get existing member types.
        $params = array('crosssourcedidsource' => $membership->crosssourcedidsource,
                'crosslistsourcedid' => $membership->crosslistsourcedid);
        if ($existing_members = $DB->get_records('enrol_lmb_crosslists', $params)) {
            $existing_member = reset($existing_members);
            $existing_type = $existing_member->type;
        }

        // Grouping Type.
        if (isset($xmlmembership['type'][0]['#']) &&
                (($xmlmembership['type'][0]['#'] == 'meta') || ($xmlmembership['type'][0]['#'] == 'merge'))) {
            $membership->type = $xmlmembership['type'][0]['#'];
            if (isset($existing_type) && ($existing_type != $membership->type)) {
                logger::log('Croslist membership type mismatch with existing members.', ENROL_LMB_LOG_FAIL);
                $this->line_status = false;
                $errormessage = 'Other existing members of this xlist are of a different type';
                $errorcode = 4;
                return false;
            }
        } else {
            if (isset($existing_type)) {
                $membership->type = $existing_type;
            } else {
                $membership->type = $this->get_config('xlstype');
            }
        }

        foreach ($xmlmembership['member'] as $key => $member) {
            $output[$key] = clone $membership;
            $member = $member['#'];

            // Sourcedid Source.
            // Todo, shouldn't error out if one member fails.
            if (!isset($member['sourcedid'][0]['#']['source'][0]['#'])) {
                logger::log('Crosslist '.$member->crosslistsourcedid.' member sourcedid>source not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->coursesourcedidsource = $member['sourcedid'][0]['#']['source'][0]['#'];

            // Sourcedid.
            // Todo, shouldn't error out if one member fails.
            if (!isset($member['sourcedid'][0]['#']['id'][0]['#'])) {
                logger::log('Crosslist '.$member->crosslistsourcedid.' member sourcedid not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->coursesourcedid = $member['sourcedid'][0]['#']['id'][0]['#'];

            // Status.
            if (!isset($member['role'][0]['#']['status'][0]['#'])) {
                logger::log('Crosslist '.$member->crosslistsourcedid.' member status not found.', ENROL_LMB_LOG_WARN);
                $this->linestatus = false;
                unset($output[$key]);
                continue;
            }
            $output[$key]->status = trim($member['role'][0]['#']['status'][0]['#']);

            // Rec Status.
            if (isset($member['role'][0]['@']['recstatus'])) {
                $recstatus = (int)trim($member['role'][0]['@']['recstatus']);
                if ($recstatus==3) {
                    $output[$key]->status = 0;
                }
            }

            // Check if in conflicting crosslist types.
            if ($output[$key]->type == 'merge') {
                // Merges can't conflict with any type.
                $params = array('status' => 1, 'coursesourcedid' => $output[$key]->coursesourcedid);
            } else {
                // Metas can only conflict with other metas (not merges).
                $params = array('status' => 1, 'coursesourcedid' => $output[$key]->coursesourcedid, 'type' => 'merge');
            }
            if ($existing_members = $DB->get_records('enrol_lmb_crosslists', $params)) {
                foreach ($existing_members as $existing_member) {
                    if ($existing_members->crosslistsourcedid != $output[$key]->crosslistsourcedid) {
                        $str = $output[$key]->coursesourcedid. ' already in xlist '.$existing_members->crosslistsourcedid;
                        logger::log($str, ENROL_LMB_LOG_WARN);
                        $this->linestatus = false;
                        unset($output[$key]);
                        continue 2;
                    }
                }
            }

            $params = array('coursesourcedidsource' => $output[$key]->coursesourcedidsource,
                    'coursesourcedid' => $output[$key]->coursesourcedid,
                    'crosssourcedidsource' => $output[$key]->crosssourcedidsource,
                    'crosslistsourcedid' => $output[$key]->crosslistsourcedid);
            if ($existing_member = $DB->get_record('enrol_lmb_crosslists', $params)) {
                if (enrol_lmb_compare_objects($output[$key], $existing_member)) {
                    $output[$key]->succeeded = 0;
                } else {
                    $output[$key]->succeeded = $existing_member->succeeded;
                }
            } else {
                $output[$key]->succeeded = 0;
            }

            $this->update_or_insert_lmb($output[$key], 'enrol_lmb_crosslists');
        }

        return $output;
    }


    // -----------------------------------------------------------------------------------------------------------------
    // New Moodle.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Process the course section group tag. Defines a course in Moodle.
     *
     * @param stdClass $class The LMB Class object
     * @return bool the status of the processing. False on error.
     */
    public function course_to_moodlecourse($course) {
        global $DB;

        $cat = new stdClass();

        $cat->id = $this->get_category_id($course->term, $course->depttitle, $course->dept, $logline, $status);

        // Do the Course check/update!

        $moodlecourse = new stdClass();

        $moodlecourse->idnumber = $course->sourcedid;
        $moodlecourse->timemodified = time();

        if ($currentcourse = $DB->get_record('course', array('idnumber' => $moodlecourse->idnumber))) {
            // If it's an existing course.
            $moodlecourse->id = $currentcourse->id;

            if ($this->get_config('forcetitle')) {
                $moodlecourse->fullname = enrol_lmb_expand_course_title($course, $this->get_config('coursetitle'));
            }

            if ($this->get_config('forceshorttitle')) {
                $moodlecourse->shortname = enrol_lmb_expand_course_title($course, $this->get_config('courseshorttitle'));
            }

            if ($this->get_config('forcecat')) {
                $moodlecourse->category = $cat->id;
            }

            $moodlecourse->startdate = $course->startdate;

            if ($this->get_config('forcecomputesections') && $this->get_config('computesections')) {
                $moodlecourseconfig = get_config('moodlecourse');

                $length = $course->enddate - $course->startdate;

                $length = ceil(($length/(24*3600)/7));

                if ($length < 1) {
                    $length = $moodlecourse->numsections;
                } else if ($length > $moodlecourseconfig->maxsections) {
                    $length = $moodlecourseconfig->maxsections;
                }

                $moodlecourse->numsections = $length;
            }

            $update = false;

            $update = enrol_lmb_compare_objects($moodlecourse, $currentcourse);

            if ($update) {
                update_course($moodlecourse);
                logger::log('Moodle course '.$course->sourcedid.' updated.', ENROL_LMB_LOG_UPDATE);
            } else {
                logger::log('Moodle course '.$course->sourcedid.' update not needed.', ENROL_LMB_LOG_INFO);
            }

        } else {
            // If it's a new course.
            $moodlecourse->id = $this->create_shell_course($course->sourcedid,
                                        enrol_lmb_expand_course_title($course, $this->get_config('coursetitle')),
                                        enrol_lmb_expand_course_title($course, $this->get_config('courseshorttitle')), $cat->id,
                                        $logline, $status, false, $course->startdate, $course->enddate);
        }

        // TODO make optional.
        $tmpstatus = enrol_lmb_restore_users_to_course($course->sourcedid);
        if (!$tmpstatus) {
            logger::log('Error restoring some enrolments.', ENROL_LMB_LOG_WARN); // TODOLog remove?
            $this->logerror = true;
            $this->linestatus = false;
        }

        /*
        if ($status && !$deleted) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }
        */
        if (isset($moodlecourse->id)) {
            return $DB->get_record('course', array('id' => $moodlecourse->id));
        } else {
            return false;
        }

    } // End process_group_tag().

    /**
     * Takes an internal LMB person object and converts it to a moodle user object,
     * inserting, updating, or deleting the user in moodle as needed.
     *
     * @param stdClass $lmbperson The LMB person element
     * @return stdClass A object representing a Moodle user
     */
    public function person_to_moodleuser($lmbperson) {
        global $DB, $CFG;
        $emailallow = true;

        $logprefix = 'Person '.$lmbperson->sourcedid.' - ';

        if (!isset($lmbperson->username) || (trim($lmbperson->username)=='')) {
            if (!$this->get_config('createusersemaildomain')) { // TODOLog?
                $this->linestatus = false;
            }
            logger::log($logprefix.'username not found.', ENROL_LMB_LOG_FAIL);

            return false;
        }

        if (!isset($lmbperson->email) || (trim($lmbperson->email)=='')) {
            $level = ENROL_LMB_LOG_FAIL;
            if (!$this->get_config('donterroremail')) {
                $level = ENROL_LMB_LOG_INFO;
                $this->linestatus = false;
            }
            logger::log($logprefix.'no email found.', $level);

            return false;
        }
// TODOLog.
        if ($this->get_config('createusersemaildomain')) {
            if ($domain = explode('@', $lmbperson->email) && (count($domain) > 1)) {
                $domain = trim($domain[1]);

                if (isset($CFG->ignoredomaincase) && $CFG->ignoredomaincase) {
                    $matchappend = 'i';
                } else {
                    $matchappend = '';
                }

                if (!preg_match('/^'.trim($this->get_config('createusersemaildomain')).'$/'.$matchappend, $domain)) {
                    $this->append_log_line('no in domain email');
                    $emailallow = false;
                    if (!$this->get_config('donterroremail')) {
                        $this->linestatus = false;
                    }
                }
            } else {
                $this->append_log_line('no in domain email');
                $emailallow = false;
                if (!$this->get_config('donterroremail')) {
                    $this->linestatus = false;
                }
            }
        }

        if ($this->get_config('nickname') && isset($lmbperson->nickname) && !empty($lmbperson->nickname)) {
            $pos = strrpos($lmbperson->nickname, ' '.$lmbperson->familyname);
            $firstname = $lmbperson->nickname;

            // Remove last name.
            if ($pos !== false) {
                $firstname = substr($firstname, 0, $pos);
            }

            if (empty($firstname) || ($firstname === false)) {
                $firstname = $lmbperson->givenname;
            }

        } else {
            $firstname = $lmbperson->givenname;
        }

        $newuser = false;

        if ($emailallow && $lmbperson->recstatus != 3) {
            $moodleuser = new stdClass();

            $moodleuser->idnumber = $lmbperson->sourcedid;

            if ($this->get_config('ignoreusernamecase')) {
                $moodleuser->username = strtolower($lmbperson->username);
            } else {
                $moodleuser->username = $lmbperson->username;
            }
            $moodleuser->auth = $this->get_config('auth');
            $moodleuser->timemodified = time();

            if (($oldmoodleuser = $DB->get_record('user', array('idnumber' => $moodleuser->idnumber)))
                    || (($this->get_config('consolidateusernames'))
                    && ($oldmoodleuser = $DB->get_record('user', array('username' => $moodleuser->username))))) {
                // If we have an existing user in moodle (using idnumber) or...
                // ...if we can match by username (but not idnumber) and the consolidation is on.

                if ($this->get_config('ignoreusernamecase')) {
                    $oldmoodleuser->username = strtolower($oldmoodleuser->username);
                }

                $moodleuser->id = $oldmoodleuser->id;

                if ($this->get_config('forcename')) {
                    $moodleuser->firstname = $firstname;
                    $moodleuser->lastname = $lmbperson->familyname;
                }

                if ($this->get_config('forceemail')) {
                    $moodleuser->email = $lmbperson->email;
                }

                if ($this->get_config('includetelephone') && $this->get_config('forcetelephone')) {
                    $moodleuser->phone1 = $lmbperson->telephone;
                }

                if ($this->get_config('includeaddress') && $this->get_config('forceaddress')) {
                    $moodleuser->address = $lmbperson->adrstreet;

                    if ($this->get_config('defaultcity') == 'standardxml') {
                        if ($lmbperson->locality) {
                            $moodleuser->city = $lmbperson->locality;
                        } else {
                            $moodleuser->city = $this->get_config('standardcity');
                        }
                    } else if ($this->get_config('defaultcity') == 'xml') {
                        $moodleuser->city = $lmbperson->locality;
                    } else if ($this->get_config('defaultcity') == 'standard') {
                        $moodleuser->city = $this->get_config('standardcity');
                    }
                } else {
                    $moodleuser->address = '';
                }

                if (enrol_lmb_compare_objects($moodleuser, $oldmoodleuser) || ($this->get_config('customfield1mapping')
                        && ($this->compare_custom_mapping($moodleuser->id, $lmbperson->customfield1,
                        $this->get_config('customfield1mapping'))))) {

                    if (($oldmoodleuser->username != $moodleuser->username)
                            && ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username)))) {
                        $log = 'Username collision while trying to update from '.$oldmoodleuser->username.' to '.$moodleuser->username.'.';
                        logger::log($log, ENROL_LMB_LOG_FAIL);
                        $this->linestatus = false;
                    } else {
                        if ($DB->update_record('user', $moodleuser)) {
                            logger::log($logprefix.'updated Moodle user.', ENROL_LMB_LOG_UPDATE);
                            // Update custom fields.
                            if ($this->get_config('customfield1mapping')) {
                                $this->update_custom_mapping($moodleuser->id, $lmbperson->customfield1,
                                    $this->get_config('customfield1mapping'));
                            }
                        } else {
                            logger::log('Person '.$lmbperson->sourcedid.' failed to update Moodle user.', ENROL_LMB_LOG_FAIL);
                            $this->linestatus = false;
                        }
                    }
                } else {
                    logger::log($logprefix.'no Moodle changes to make.', ENROL_LMB_LOG_INFO);
                }
            } else {
                // Set some default prefs.
                if (!isset($CFG->mnet_localhost_id)) {
                    include_once($CFG->dirroot . '/mnet/lib.php');
                    $env = new mnet_environment();
                    $env->init();
                    unset($env);
                }
                $moodleuser->mnethostid = $CFG->mnet_localhost_id;
                $moodleuser->confirmed = 1;

                // Add default site language.
                $moodleuser->lang = $CFG->lang;

                // The user appears to not exist at all yet.
                $moodleuser->firstname = $firstname;
                $moodleuser->lastname = $lmbperson->familyname;

                if ($this->get_config('ignoreemailcase')) {
                    $moodleuser->email = strtolower($lmbperson->email);
                } else {
                    $moodleuser->email = $lmbperson->email;
                }

                $moodleuser->auth = $this->get_config('auth');
                if ($this->get_config('includetelephone')) {
                    $moodleuser->phone1 = $lmbperson->telephone;
                }

                if ($this->get_config('includeaddress')) {
                    if (isset ($lmbperson->adrstreet)) {
                        $moodleuser->address = $lmbperson->adrstreet;
                    } else {
                        $moodleuser->address = '';
                    }

                    if ($this->get_config('defaultcity') == 'standardxml') {
                        if ($lmbperson->locality) {
                            $moodleuser->city = $lmbperson->locality;
                        } else {
                            $moodleuser->city = $this->get_config('standardcity');
                        }
                    } else if ($this->get_config('defaultcity') == 'xml') {
                        $moodleuser->city = $lmbperson->locality;
                    } else if ($this->get_config('defaultcity') == 'standard') {
                        $moodleuser->city = $this->get_config('standardcity');
                    }

                } else {
                    $moodleuser->address = '';
                }

                $moodleuser->country = $CFG->country;

                if ($this->get_config('createnewusers')) {
                    if ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username))) {
                        logger::log($logprefix.'username collision, could not create user', ENROL_LMB_LOG_FAIL);
                        $this->linestatus = false;
                    } else {
                        if ($id = $DB->insert_record('user', $moodleuser, true)) {
                            logger::log($logprefix.'created new Moodle user.', ENROL_LMB_LOG_UPDATE);
                            if (isset($lmbperson->customfield1)) {
                                $this->update_custom_mapping($id, $lmbperson->customfield1,
                                        $this->get_config('customfield1mapping'));
                            }
                            $moodleuser->id = $id;
                            $newuser = true;

                            $this->restore_user_enrolments($lmbperson->sourcedid);

                        } else {
                            logger::log($logprefix.'failed to insert new user.', ENROL_LMB_LOG_FAIL);
                            $this->linestatus = false;
                        }
                    }
                } else {
                    logger::log($logprefix.'did not create new user.', ENROL_LMB_LOG_INFO);
                    return false;
                }
            }

            if ($this->get_config('passwordnamesource') != 'none') {
                if ($this->get_config('forcepassword', true) || $newuser) {
                    if ($user = $DB->get_record('user', array('id' => $moodleuser->id))) {
                        $userauth = get_auth_plugin($user->auth);
                        if ($userauth->can_change_password() && (!$userauth->change_password_url())) {
                            // TODO2 - what happens if password is blank?
                            if (isset($person->password) && ($person->password != '')) {
                                if (!$userauth->user_update_password($user, $person->password)) {
                                    logger::log($logprefix.'error setting password.', ENROL_LMB_LOG_NOTICE);
                                    $this->linestatus = false;
                                }
                            }
                        }
                    }
                }
            }

        } else if (($this->get_config('imsdeleteusers')) && ($lmbperson->recstatus == 3)
                && ($moodleuser = $DB->get_record('user', array('idnumber' => $lmbperson->idnumber)))) {

            try {
                if (delete_user($moodleuser)) {
                    logger::log($logprefix.'deleted Moodle user.', ENROL_LMB_LOG_WARN);
                    $deleted = true;
                } else {
                    logger::log($logprefix.'failed to delete Moodle user.', ENROL_LMB_LOG_FAIL);
                    $this->linestatus = false;
                }
            } catch (Exception $e) {
                $this->append_log_ling($logprefix.'exception deleting user - '.$e->getMessage(), ENROL_LMB_LOG_FAIL);
                $this->linestatus = false;
            }

            $moodleuser = $deleteuser;
        }

        return $moodleuser;

    } // End process_person_tag().

    public function person_memberships_to_enrolments($input) {
        if (!is_array($input)) {
            $this->person_membership_to_enrolment($input);
            return;
        } else {
            $memberships = $input;
        }

        foreach ($memberships as $key => $member) {
            $this->person_membership_to_enrolment($member);
        }
    }

    public function person_membership_to_enrolment($member) {
        global $DB;
        $status = true;

        //$this->append_log_line($member->coursesourcedid);
        //$this->append_log_line($member->personsourcedid);
        $logprefix = 'Course:'.$member->coursesourcedid.' Person:'.$member->personsourcedid.' - ';

        $newcoursedid = enrol_lmb_get_course_id($member->coursesourcedid);

        $params = array('status' => 1, 'coursesourcedid' => $member->coursesourcedid);
        if ($this->get_config('xlsmergegroups') && $xlist = $DB->get_record('enrol_lmb_crosslists', $params)) {
            $groupid = enrol_lmb_get_crosslist_groupid($member->coursesourcedid, $xlist->crosslistsourcedid);
        } else {
            $groupid = false;
        }

        $enrolup = new object();
        $enrolup->id = $member->id;

        if ($newcoursedid) {
            if ($userid = $DB->get_field('user', 'id', array('idnumber' => $member->personsourcedid))) {
                if ($roleid = enrol_lmb_get_roleid($member->role)) {
                    if ($member->status) {
                        if (isset($member->beginrestrict) && $member->beginrestrict) {
                            $beginrestricttime = $member->beginrestricttime;
                        } else {
                            $beginrestricttime = 0;
                        }
                        if (isset($member->endrestrict) && $member->endrestrict) {
                            $endrestricttime = $member->endrestricttime;
                        } else {
                            $endrestricttime = 0;
                        }
                        $status = $this->lmb_assign_role($roleid, $newcoursedid, $userid,
                                $beginrestricttime, $endrestricttime);
                        if ($status && $groupid && !groups_is_member($groupid, $userid)) {
                            global $CFG;
                            require_once($CFG->dirroot.'/group/lib.php');
                            groups_add_member($groupid, $userid);
                            logger::log($logprefix.'added user to group.', ENROL_LMB_LOG_INFO);
                        }
                    } else {
                        $status = $this->lmb_unassign_role($roleid, $newcoursedid, $userid);
                        if ($status && $groupid && groups_is_member($groupid, $userid)) {
                            global $CFG;
                            require_once($CFG->dirroot.'/group/lib.php');
                            groups_remove_member($groupid, $userid);
                            logger::log($logprefix.'removed user from group.', ENROL_LMB_LOG_INFO);
                        }
                    }
                } else {
                    logger::log($logprefix.'roleid not found.', ENROL_LMB_LOG_FAIL);
                    $status = false;
                }
            } else {
                logger::log($logprefix.'user not found.', ENROL_LMB_LOG_FAIL);
                $status = false;
            }
        } else {
            logger::log($logprefix.'course not found.', ENROL_LMB_LOG_FAIL);
            $status = false;
        }

        if ($status) {
            $enrolup->succeeded = 1;
        } else {
            $this->linestatus = false;
            $enrolup->succeeded = 0;
        }

        $this->update_or_insert_lmb($enrolup, 'enrol_lmb_enrolments');
    }

    public function xml_memberships_to_moodlecourse($memberships) {
        if (!is_array($input)) {
            $this->xml_membership_to_moodlecourse($input);
            return;
        } else {
            $memberships = $input;
        }

        foreach ($memberships as $key => $member) {
            $this->xml_membership_to_moodlecourse($member);
        }
    }

    public function xml_membership_to_moodlecourse($membership) {
        // TODO succeeded shouldn't fail when just a user is missing.

        $logprefix = 'Crosslist:'.$membership->crosslistsourcedid.' Course:'.$membership->coursesourcedid.' - ';

        if (isset($membership->succeeded)) {
            $succeeded = $membership;
        } else {
            $succeeded = 0;
        }

        $status = true;

        if ($type == 'meta') {
            $meta = true;
        } else {
            $meta = false;
        }

        $moodlecourse = new stdClass();

        $enddate = $this->get_crosslist_endtime($membership->crosslistsourcedid);
        $starttime = $this->get_crosslist_starttime($membership->crosslistsourcedid);

        // Create a new course if it doesn't exist.
        $params = array('idnumber' => $membership->crosslistsourcedid);
        if (!$moodlecourse->id = $DB->get_field('course', 'id', $params)) {
            $moodlecourse->id = $this->create_shell_course($membership->crosslistsourcedid, 'Crosslisted Course',
                                        $membership->crosslistsourcedid, $catid,
                                        $logline, $substatus, $meta, $starttime, $enddate);
        }

        if (!$moodlecourse->id) {
            logger::log($logprefix.'could not find or create course.', ENROL_LMB_LOG_FAIL);
            $this->linestatus = false;
            $errormessage = 'No moodle course found'; // TODOLog Remove?
            $errorcode = 8;
            return false;
        }

        // TODO should we be forcing this?
        $moodlecourse->fullname = $this->expand_crosslist_title($membership->crosslistsourcedid,
                $this->get_config('xlstitle'), $this->get_config('xlstitlerepeat'),
                $this->get_config('xlstitledivider'));

        $moodlecourse->shortname = $this->expand_crosslist_title($membership->crosslistsourcedid,
                $this->get_config('xlsshorttitle'), $this->get_config('xlsshorttitlerepeat'),
                $this->get_config('xlsshorttitledivider'));

        $moodlecourse->startdate = $starttime;

        if ($this->get_config('forcecomputesections') && $this->get_config('computesections')) {
            $moodlecourseconfig = get_config('moodlecourse');

            $length = $enddate - $moodlecourse->startdate;

            $length = ceil(($length/(24*3600)/7));

            if (($length > 0) && ($length <= $moodlecourseconfig->maxsections)) {
                $moodlecourse->numsections = $length;
            }
        }

        if (!$DB->update_record('course', $moodlecourse)) {
            logger::log($logprefix.'Error setting course name', ENROL_LMB_LOG_WARN);
            $this->line_status = false;
            $errormessage = 'Failed when updating course record';
            $errorcode = 7;
        }

        if ($succeeded == 0) {
            if ($meta) {
                $succeeded = 1;
                $params = array('idnumber' => $membership->coursesourcedid);
                if ($childid = $DB->get_field('course', 'id', $params)) {
                    if ($xlist->status) {
                        if (!$this->add_to_metacourse($moodlecourse->id, $addid)) {
                            $succeeded = 0;
                            logger::log($logprefix.'could not join course to meta course.', ENROL_LMB_LOG_FAIL);
                            $this->line_status = false;
                            $status = false;
                            $errormessage = 'Error adding course '.$xlist->coursesourcedid.' to metacourse';
                            $errorcode = 9;
                        }
                    } else {
                        if (!$this->remove_from_metacourse($moodlecourse->id, $addid)) {
                            $succeeded = 0;
                            logger::log($logprefix.'could not unjoin course from meta course.', ENROL_LMB_LOG_FAIL);
                            $this->line_status = false;
                            $status = false;
                            $errormessage = 'Error removing course '.$xlist->coursesourcedid.' from metacourse';
                            $errorcode = 9;
                        }
                    }
                } else {
                    $succeeded = 0;
                    logger::log($logprefix.'could not find child course.', ENROL_LMB_LOG_FAIL);
                    $this->line_status = false;
                    $errormessage = 'Could not find child course';
                    $errorcode = 7;
                    $status = false;
                }
            } else {
                $succeeded = 0;
                if ($xlist->status == 1) {
                    if (!$modinfo = $DB->get_field('course', 'modinfo', array('idnumber' => $membership->coursesourcedid))) {
                        $modinfo = '';
                    }

                    $modinfo = unserialize($modinfo);

                    if (count($modinfo) <= 1) {
                        enrol_lmb_drop_all_users($membership->coursesourcedid, 2, true);
                    }

                    if (!enrol_lmb_drop_all_users($membership->coursesourcedid, 1, true)) {
                        logger::log($logprefix.'error dropping old users.', ENROL_LMB_LOG_WARN);
                        $this->line_status = false;
                        $succeeded = 0;
                        $status = false;
                        $errormessage = 'Error removing students from course '.$membership->coursesourcedid;
                        $errorcode = 10;
                    }

                    if (!enrol_lmb_restore_users_to_course($membership->coursesourcedid)) {
                        logger::log($logprefix.'error adding new users to crosslist.', ENROL_LMB_LOG_WARN);
                        $this->line_status = false;
                        $succeeded = 0;
                        $status = false;
                        $errormessage .= 'Error adding students to course '.$membership->crosslistsourcedid;
                        $errorcode = 10;
                    }
                } else {
                    // Restore users to individual course.
                    if (!enrol_lmb_restore_users_to_course($membership->coursesourcedid)) {
                        logger::log($logprefix.'error enrolling users in child course.', ENROL_LMB_LOG_WARN);
                        $this->line_status = false;
                        $succeeded = 0;
                        $status = false;
                    }

                    // Drop users from crosslist.
                    if (!enrol_lmb_drop_crosslist_users($membership)) {
                        logger::log($logprefix.'error dropping users from crosslist', ENROL_LMB_LOG_WARN);
                        $this->line_status = false;
                        $succeeded = 0;
                        $status = false;
                    }

                    $droppedusers = true;
                }
            }
        }

        if ($succeeded != $membership->succeeded) {
            $membershipup = new stdClass();
            $membershipup->id = $membership->id;
            $membershipup->succeeded = $succeeded;
            $this->update_or_insert_lmb($membershipup, 'enrol_lmb_crosslists');
        }

        return $status;
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Support Functions.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Create an empty moodle course
     *
     * @param string $idnumber the xml/ims idnumber for the new course
     * @param string $name the full name to set for the course
     * @param string $shortname the short name to set for the course
     * @param int $catid the moodle id of the category to put the course in
     * @param string $logline a passed logline variable to append to
     * @param bool $status a passed states variable
     * @param bool $meta create a meta course if true
     * @param int $startdate set the start date of the course in unix time format
     * @param int $enddate set the end date of the course in unix time format
     * @return int|bool the moodle id number of the course or false if there was an error
     */
    public function create_shell_course($idnumber, $name, $shortname, $catid, &$logline, &$status,
            $meta=false, $startdate = 0, $enddate = 0) {

        global $CFG, $DB;
        $status = true;

        $moodlecourse = new stdClass();

        $moodlecourse->idnumber = $idnumber;
        $moodlecourse->timemodified = time();

        $moodlecourse->shortname = $shortname;
        $moodlecourse->fullname = $name;

        $moodlecourse->startdate = $startdate;

        if ($this->get_config('coursehidden') == 'never') {
            $moodlecourse->visible = 1;
        } else if ($this->get_config('coursehidden') == 'cron') {
            $curtime = time();
            $todaytime = mktime(0, 0, 0, date('n', $curtime), date('j', $curtime), date('Y', $curtime));
            $time = $todaytime + ($this->get_config('cronunhidedays') * 86400);

            if ($startdate > $time) {
                $moodlecourse->visible = 0;
            } else {
                $moodlecourse->visible = 1;
            }
        } else if ($this->get_config('coursehidden') == 'always') {
            $moodlecourse->visible = 0;
        }

        $moodlecourse->timecreated = time();
        $moodlecourse->category = $catid;

        // Set some preferences.
        $moodlecourseconfig = get_config('moodlecourse');
        if ($this->get_config('usemoodlecoursesettings') && ($moodlecourseconfig)) {
            //$logline .= 'Using default Moodle settings:';
            $moodlecourse->format                   = $moodlecourseconfig->format;
            $moodlecourse->numsections              = $moodlecourseconfig->numsections;
            $moodlecourse->hiddensections           = $moodlecourseconfig->hiddensections;
            $moodlecourse->newsitems                = $moodlecourseconfig->newsitems;
            $moodlecourse->showgrades               = $moodlecourseconfig->showgrades;
            $moodlecourse->showreports              = $moodlecourseconfig->showreports;
            $moodlecourse->maxbytes                 = $moodlecourseconfig->maxbytes;
            $moodlecourse->groupmode                = $moodlecourseconfig->groupmode;
            $moodlecourse->groupmodeforce           = $moodlecourseconfig->groupmodeforce;
            $moodlecourse->lang                     = $moodlecourseconfig->lang;
            $moodlecourse->enablecompletion         = $moodlecourseconfig->enablecompletion;

        } else {
            //$logline .= 'Using hard-coded settings:';
            $moodlecourse->format               = 'topics';
            $moodlecourse->numsections          = 6;
            $moodlecourse->hiddensections       = 0;
            $moodlecourse->newsitems            = 3;
            $moodlecourse->showgrades           = 1;
            $moodlecourse->showreports          = 1;
        }

        if ($this->get_config('computesections')) {
            $length = $enddate - $startdate;

            $length = ceil(($length/(24*3600)/7));

            if ($length < 1) {
                $length = $moodlecourse->numsections;
            } else if ((isset($moodlecourseconfig->maxsections)) && ($length > $moodlecourseconfig->maxsections)) {
                $length = $moodlecourseconfig->maxsections;
            }

            $moodlecourse->numsections = $length;
        }

        try {
            if ($moodlecourse = create_course($moodlecourse)) {
                logger::log('Created Moodle course '.$moodlecourse->idnumber.'.', ENROL_LMB_LOG_UPDATE);
            } else {
                logger::log('Error creating course '.$moodlecourse->idnumber.'.', ENROL_LMB_LOG_FAIL);
                return false;
            }
        } catch (Exception $e) {
            logger::log('Exception thrown while creating course '.$moodlecourse->idnumber.'. '.$e->getMessage(), ENROL_LMB_LOG_FAIL);
            return false;
        }

        $this->add_instance($moodlecourse);

        return $moodlecourse->id;
    }

    /**
     * Adds a meta course enrolment method from a course
     *
     * @param int $parentid parent course id
     * @param int $childid child course id
     * @return bool result
     */
    public function add_to_metacourse($parentid, $childid) {
        global $DB;

        $params = array('courseid' => $parentid, 'customint1' => $childid, 'enrol' => 'meta');
        if (($enrols = $DB->get_record('enrol', $params, '*', IGNORE_MULTIPLE)) && (count($enrols) > 0)) {
            return true;
        }

        $parentcourse = $DB->get_record('course', array('id' => $parentid));
        $metaplugin = enrol_get_plugin('meta');
        $metaplugin->add_instance($parentcourse, array('customint1' => $childid));

        $this->meta_sync($parentid);

        return true;
    }

    /**
     * Removes a meta course enrolment method from a course
     *
     * @param int $parentid parent course id
     * @param int $childid child course id
     * @return bool result
     */
    public function remove_from_metacourse($parentid, $childid) {
        global $DB;

        $params = array('courseid' => $parentid, 'customint1' => $childid, 'enrol' => 'meta');
        $enrol = $DB->get_record('enrol', $params, '*', IGNORE_MULTIPLE);
        if (!$enrol) {
            return true;
        }

        $metaplugin = enrol_get_plugin('meta');

        $metaplugin->delete_instance($enrol);

        return true;
    }

    /**
     * Syncs meta enrolments between children and parent.
     *
     * @param int $parentid course id of the parent course to sync
     */
    public function meta_sync($parentid) {
        $metaplugin = enrol_get_plugin('meta');

        $course = new stdClass();
        $course->id = $parentid;

        $metaplugin->course_updated(false, $course, false);
    }

    /**
     * Provides the title for the given crosslist id based on the provided
     * definitions. See help documentation for definition formatting.
     *
     * @param string $idnumber the xml/ims id of the crosslist
     * @param string $titledef the definition of the title
     * @param string $repeatdef the definition of the repeater
     * @param string $dividerdef the definition of the divider
     * @return string the title
     */
    public function expand_crosslist_title($idnumber, $titledef, $repeatdef, $dividerdef) {
        global $DB;

        $title = $titledef;
        $repeat = '';

        if ($courseids = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $courses = array();

            foreach ($courseids as $courseid) {

                if ($course = $DB->get_record('enrol_lmb_courses', array('sourcedid' => $courseid->coursesourcedid))) {
                    array_push($courses, $course);

                }
            }

            if ($course = $courses[0]) {
                $title = enrol_lmb_expand_course_title($course, $title);

                $i = 1;
                $count = count($courses);
                foreach ($courses as $course) {

                    $repeat .= enrol_lmb_expand_course_title($course, $repeatdef);
                    if ($count != $i) {
                        $repeat .= $dividerdef;
                    }
                    $i++;
                }

                $title = str_replace('[REPEAT]', $repeat, $title);

            }
        }

        $title = str_replace('[XLSID]', $idnumber, $title);

        return $title;
    }

    /**
     * Determines the end time for a crosslisted course - the latest end date
     * of any child course
     *
     * @param string $idnumber the xml/ims id of the crosslist
     * @return int the end time in unit time format
     */
    public function get_crosslist_endtime($idnumber) {
        global $DB;

        if ($courseids = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $enddates = array();

            foreach ($courseids as $courseid) {
                if ($enddate = $DB->get_field('enrol_lmb_courses', 'enddate', array('sourcedid' => $courseid->coursesourcedid))) {
                    array_push($enddates, $enddate);

                }
            }

            if ($enddate = $enddates[0]) {
                rsort($enddates);

                return $enddates[0];
            }
        }
    }

    /**
     * Determines the start time for a crosslisted course - the earliest start date
     * of any child course
     *
     * @param string $idnumber the xml/ims id of the crosslist
     * @return int the start time in unit time format
     */
    public function get_crosslist_starttime($idnumber) {
        global $DB;

        if ($courseids = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $startdates = array();

            foreach ($courseids as $courseid) {
                $params = array('sourcedid' => $courseid->coursesourcedid);
                if ($startdate = $DB->get_field('enrol_lmb_courses', 'startdate', $params)) {
                    array_push($startdates, $startdate);
                }
            }

            if ($startdate = $startdates[0]) {
                sort($startdates);

                return $startdates[0];
            }
        }
        return 0;
    }

    /**
     * Get the moodle id of a desired category based on settings. Create it if
     * no matching category is found.
     *
     * @param string $term the xml/ims idnumber for the term
     * @param string $depttitle the title of the department the course is in
     * @param string $deptcode the code of the department
     * @param string $logline a passed logline variable to append to
     * @param bool $status a passed states variable
     * @return int|bool the moodle id number of the category or false if there was an error
     */
    public function get_category_id($term, $depttitle, $deptcode, &$logline, &$status) {
        global $DB;

        $cat = new Object();

        if (($this->get_config('cattype') == 'deptcode') || ($this->get_config('cattype') == 'termdeptcode')) {
            $depttitle = $deptcode;
        }

        switch ($this->get_config('cattype')) {
            case 'term':
                $cat->id = $this->get_term_category_id($term, $logline, $status);

                break;

            case 'deptcode':
            case 'dept':
                // TODO2 - Removed addslashes around depttitle, check.
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('dept' => $depttitle, 'cattype' => 'dept'))) {
                    $cat->id = $lmbcat->categoryid;
                } else {
                    $cat->name = $depttitle;
                    $cat->visible = 0;
                    $cat->sortorder = 999;
                    if ($cat->id = $DB->insert_record('course_categories', $cat, true)) {
                        $lmbcat = new Object();
                        $lmbcat->categoryid = $cat->id;
                        $lmbcat->cattype = 'dept';
                        $lmbcat->dept = $depttitle;

                        $cat->context = context_coursecat::instance($cat->id);
                        $cat->context->mark_dirty();
                        fix_course_sortorder();
                        if (!$DB->insert_record('enrol_lmb_categories', $lmbcat)) {
                            $logline .= "error saving category to enrol_lmb_categories:";
                        }
                        $logline .= 'Created new (hidden) category:';
                    } else {
                        $logline .= 'error creating category:';
                        $status = false;
                    }

                }

                break;

            case 'termdeptcode':
            case 'termdept':
                // TODO2 - Removed addslashes around depttitle, check.
                $params = array('termsourcedid' => $term, 'dept' => $depttitle, 'cattype' => 'termdept');
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', $params)) {
                    $cat->id = $lmbcat->categoryid;
                } else {
                    if ($termid = $this->get_term_category_id($term, $logline, $status)) {
                        $cat->name = $depttitle;
                        $cat->visible = 0;
                        $cat->parent = $termid;
                        $cat->sortorder = 999;
                        if ($cat->id = $DB->insert_record('course_categories', $cat, true)) {
                            $lmbcat = new Object();
                            $lmbcat->categoryid = $cat->id;
                            $lmbcat->cattype = 'termdept';
                            $lmbcat->termsourcedid = $term;
                            $lmbcat->dept = $depttitle;

                            $cat->context = context_coursecat::instance($cat->id);
                            $cat->context->mark_dirty();
                            fix_course_sortorder();
                            if (!$DB->insert_record('enrol_lmb_categories', $lmbcat, true)) {
                                $logline .= "error saving category to enrol_lmb_categories:";
                            }
                            $logline .= 'Created new (hidden) category:';
                        } else {
                            $logline .= 'error creating category:';
                            $status = false;
                        }
                    }
                }

                break;
            // TODO case 'deptterm':.

            case 'other':
                if ($this->get_config('catselect') > 0) {
                    $cat->id = $this->get_config('catselect');
                } else {
                    $logline .= "category not selected:";
                    $status = false;
                }
                break;

            default:
                $logline .= "category type config error:";
                $status = false;
        }

        return $cat->id;

    }

    /**
     * Get the moodle id of a desired term category. Create it if
     * no matching category is found.
     *
     * @param string $term the xml/ims idnumber for the term
     * @param string $logline a passed logline variable to append to
     * @param bool $status a passed states variable
     * @return int|bool the moodle id number of the category or false if there was an error
     */
    public function get_term_category_id($term, &$logline, &$status) {
        global $DB;

        if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('termsourcedid' => $term, 'cattype' => 'term'))) {
            return $lmbcat->categoryid;
        } else {
            if ($lmbterm = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $term))) {
                $cat = new stdClass();

                $cat->name = $lmbterm->title;
                if ($this->get_config('cathidden')) {
                    $cat->visible = 0;
                } else {
                    $cat->visible = 1;
                }

                $cat->sortorder = 999;
                if ($cat->id = $DB->insert_record('course_categories', $cat, true)) {
                    $lmbcat = new Object();
                    $lmbcat->categoryid = $cat->id;
                    $lmbcat->termsourcedid = $lmbterm->sourcedid;
                    $lmbcat->sourcedidsource = $lmbterm->sourcedidsource;
                    $lmbcat->cattype = 'term';

                    $cat->context = context_coursecat::instance($cat->id);
                    $cat->context->mark_dirty();
                    fix_course_sortorder();

                    if (!$DB->insert_record('enrol_lmb_categories', $lmbcat)) {
                        $logline .= "error saving category to enrol_lmb_categories:";
                    }
                    $logline .= 'Created new (hidden) category:';

                    return $cat->id;
                } else {
                    $logline .= 'error creating category:';
                    $status = false;
                }
            } else {
                $logline .= "term does not exist, error creating category:";
                $status = false;
            }
        }

        return false;
    }

    /**
     * Loads the custom user profile field from the database.
     * This is cached.
     * Returns stdClass on success or false on failure.
     *
     * @param string $shortname
     * @param boolean $flush
     * @return mixed
     */
    private function load_custom_mapping($shortname, $flush=false) {
        global $DB;
        if (!isset($this->customfields[$shortname]) || $flush) {
            $this->customfields[$shortname] = $DB->get_record('user_info_field', array('shortname' => $shortname));
        }
        return $this->customfields[$shortname];
    }

    /**
     * This is a stripped down version of edit_save_data() from /user/profile/lib.php
     *
     * @param int userid
     * @param string custom field value
     * @param string custom field shortname
     * @return void
     */
    private function update_custom_mapping($userid, $value, $mapping) {
        global $DB;

        $profile = $this->load_custom_mapping($mapping);
        if ($profile === false) {
            return;
        }

        $data = new stdClass();
        $data->userid  = $userid;
        $data->fieldid = $profile->id;
        if ($value) {
            $data->data = $value;
        } else {
            $data->data = '';
        }

        if ($dataid = $DB->get_field('user_info_data', 'id', array('userid' => $data->userid, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record('user_info_data', $data);
        } else {
            $DB->insert_record('user_info_data', $data);
        }
    }

    /**
     * Compares the custfield value with the one stored.
     *
     * @param int $userid The matching userid
     * @param string $value The new custom field value
     * @param string $mapping custom field shortname
     * @return bool True for mismatch, false for match
     */
    private function compare_custom_mapping($userid, $value, $mapping) {
        global $DB;

        $profile = $this->load_custom_mapping($mapping);
        if ($profile === false) {
            return false;
        }

        $data = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $profile->id));
        return (!($data == $value));
    }

    /**
     * Calls fix_course_sortorder() if course categories are getting to close.
     * We do this so that we aren't calling fix_course_sortorder() after each
     * course creation when doing a bulk import, which would greatly slow an import.
     */
    public function sort_if_needed() {
        global $DB;
        $sql = "SELECT MIN(sortorder) AS min,
                       MAX(sortorder) AS max,
                       COUNT(sortorder),
                       category FROM {course} GROUP BY category ORDER BY min";

        if ($cats = $DB->get_records_sql($sql)) {
            $count = count($cats);

            $next = array_shift($cats);
            for ($i = 0; $i < $count-1; $i++) {
                $curr = $next;
                $next = array_shift($cats);
                if ((($next->min) - ($curr->max)) < 100) {
                    print "sorting\n";
                    fix_course_sortorder();
                    return;
                }
            }
        }
    }

    /**
     * public function the returns the recstatus of a tag
     * 1=Add, 2=Update, 3=Delete, as specified by IMS, and we also use 0 to indicate "unspecified".
     *
     * @param string $tagdata the tag XML data
     * @param string $tagname the name of the tag we're interested in
     * @return int recstatus number
     */
    public function get_recstatus($tagdata, $tagname) {
        if (preg_match('{<'.$tagname.'\b[^>]*recstatus\s*=\s*["\'](\d)["\']}is', $tagdata, $matches)) {
            return intval($matches[1]);
        } else {
            return 0; // Unspecified.
        }
    }


    // -----------------------------------------------------------------------------------------------------------------
    // DB Helpers.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * 
     *
     * @param stdClass &$object The lmb object to work on
     * @param string $table The table to work on
     * @return bool the status of the processing. False on error.
     */
    public function update_or_insert_lmb(&$object, $table) {
        global $DB;
        $args = array();

        if (isset($object->id)) {
            $args['id'] = $object->id;
        } else {
            if ($table === 'enrol_lmb_enrolments') {
                $args['coursesourcedid'] = $object->coursesourcedid;
                $args['personsourcedid'] = $object->personsourcedid;
            } else if ($table === 'enrol_lmb_crosslists') {
                $args['coursesourcedidsource'] = $object->coursesourcedidsource;
                $args['coursesourcedid'] = $object->coursesourcedid;
                $args['crosssourcedidsource'] = $object->crosssourcedidsource;
                $args['crosslistsourcedid'] = $object->crosslistsourcedid;
            } else {
                $args['sourcedid'] = $object->sourcedid;
            }
        }

        if ($oldobject = $DB->get_record($table, $args)) {
            $object->id = $oldobject->id;
            if (enrol_lmb_compare_objects($object, $oldobject)) {
                if ($DB->update_record($table, $object)) {
                    logger::log('Updated '.$table.'.', ENROL_LMB_LOG_UPDATE);
                } else {
                    logger::log('Failed to update '.$table.'.', ENROL_LMB_LOG_FAIL);
                    $this->linestatus = false;
                    return false;
                }
            } else {
                logger::log('No LMB changes to make.', ENROL_LMB_LOG_INFO);
            }
        } else {
            if ($object->id = $DB->insert_record($table, $object, true)) {
                logger::log('Inserted into '.$table.'.', ENROL_LMB_LOG_UPDATE);
            } else {
                logger::log('Failed to insert into '.$table.'.', ENROL_LMB_LOG_FAIL);
                $this->linestatus = false;
                return false;
            }
        }

        return true;
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Logging.
    // -----------------------------------------------------------------------------------------------------------------
    public function append_log_line($string, $error = false) {
        $this->logline .= $string.':';
        if ($error == true) {
            $this->logerror = true;
        }
    }

    public function log_error($string) {
        $this->append_log_line($string);
        $this->logerror = true;
        $this->log_line_new();
    }

    public function log_line_new($end = false) {
        $message = '';

        if ($this->get_config('logerrors') && (!$this->logerror)) {
            $this->logline = '';
            $this->logerror = false;
            return;
        }

        if ($this->islmb) {
            $message = 'LMB Message:';
        }
        $message .= $this->logline;

        if ($end) {
            $message .= $end;
        }

        if ($this->logerror) {
            $message .= 'error';
        } else {
            $message .= 'complete';
        }
        if (!$this->silent) {
            mtrace($message);
        }

        if (isset($this->logfp) && $this->logfp) {
            fwrite($this->logfp, date('Y-m-d\TH:i:s - ') . $message . "\n");
        }

        $this->logline = '';
        $this->logerror = false;
    }

    public function get_line_status() {
        return $this->linestatus;
    }

    /**
     * Open the lof file and store the pointer in this object.
     */
    public function open_log_file () {
        $this->logfp = false; // File pointer for writing log data to.
        $path = $this->get_config('logtolocation');
        if (($path !== null) && (!empty($path))) {
            $this->logfp = fopen($this->get_config('logtolocation'), 'a');
        }
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Utility.
    // -----------------------------------------------------------------------------------------------------------------
    public function unhide_courses($lasttime, $curtime, $days = 0) {
        global $CFG, $DB;
        $daysec = $days * 86400;

        $this->open_log_file();

        $start = $lasttime + $daysec;
        $end = $curtime + $daysec;

        // Update normal courses.
        $sqlparams = array('start' => $start, 'end' => $end);
        $sql = 'UPDATE {course} SET visible=1 WHERE visible=0 AND idnumber IN (SELECT sourcedid FROM {enrol_lmb_courses} '
                .'WHERE startdate > :start AND startdate <= :end)';

        $this->log_line('cron unhide:'.$sql);
        $DB->execute($sql, $sqlparams);

        // Update crosslists.
        $sqlparams = array('start' => $start, 'end' => $end);
        $sql = 'UPDATE {course} SET visible=1 WHERE visible=0 AND idnumber IN (SELECT crosslistsourcedid FROM '
                .'{enrol_lmb_crosslists} WHERE coursesourcedid IN (SELECT sourcedid FROM {enrol_lmb_courses} '
                .'WHERE startdate > :start AND startdate <= :end))';

        $this->log_line('cron unhide:'.$sql);
        $DB->execute($sql, $sqlparams);
    }

    /**
     * Remove all entries for a term from various tables.
     *
     * @param string $term The term sourcedid
     * @return bool success or failure
     */
    public function prune_tables($term) {
        global $DB;

        $DB->delete_records('enrol_lmb_enrolments', array('term' => $term));
        $DB->delete_records('enrol_lmb_courses', array('term' => $term));

        $sqlparams = array('term' => '%'.$term);
        $DB->delete_records_select('enrol_lmb_crosslists', "coursesourcedid LIKE :term", $sqlparams);

        $DB->delete_records('enrol_lmb_terms', array('sourcedid' => $term));

        return true;
    }

    /**
     * Execute the check of the last time a message was received
     * on the liveimport interface (luminis message broker). If
     * last message time falls outside parameters, then email
     * error messages.
     */
    public function check_last_luminis_event() {
        global $CFG;

        $this->log_line("Checking LMB last message sent time.");

        if ($this->get_config('lastlmbmessagetime')) {
            $lasttime = $this->get_config('lastlmbmessagetime');

            $starttime = make_timestamp(date("Y"), date("m"), date("d"),
                    ( $this->get_config('startbiztimehr') ? $this->get_config('startbiztimehr') : 9),
                    $this->get_config('startbiztimemin'));
            $endtime = make_timestamp(date("Y"), date("m"), date("d"),
                    ( $this->get_config('endbiztimehr') ? $this->get_config('endbiztimehr') : 9),
                    $this->get_config('endbiztimemin'));

            $currenttime = time();

            $difftime = $currenttime - $lasttime;

            // If it's mon-fri, and inside of biz hours.
            if ((date("w") > 0) && (date("w") < 6) && ($currenttime > $starttime && $currenttime < $endtime)) {
                // If longer then grace.
                if (($this->get_config('bizgrace')) && ($difftime > ($this->get_config('bizgrace') * 60))) {
                    $this->log_line('Last luminis message received '.floor($difftime/60).' minutes ago.');
                    $emails = explode(',', $this->get_config('emails'));

                    foreach ($emails as $email) {
                        $this->email_luminis_error(floor($difftime/60), trim($email));
                    }
                }
            } else {
                // If longer then grace.
                if (($this->get_config('nonbizgrace')) && ($difftime > ($this->get_config('nonbizgrace') * 60))) {
                    $this->log_line('Last luminis message received '.floor($difftime/60).' minutes ago.');

                    $emails = explode(',', $this->get_config('emails'));

                    foreach ($emails as $email) {
                        $this->email_luminis_error(floor($difftime/60), trim($email));
                    }
                }
            }
        }

    }

    /**
     * Email a luminis downtime message to the provided email
     *
     * TODO - replace with mesaging system?
     *
     * @param int $minutes the number of minutes since the last message was received
     * @param string $emailaddress the email address to send the message to
     * @return bool success of failure of the email send
     */
    public function email_luminis_error($minutes, $emailaddress) {
        global $CFG, $FULLME;
        include_once($CFG->libdir .'/phpmailer/class.phpmailer.php');

        $messagetext = get_string('nomessagefull', 'enrol_lmb').$minutes.get_string('minutes');
        $subject = get_string("nomessage", "enrol_lmb");

        $mail = new phpmailer;

        $mail->Version = 'Moodle '. $CFG->version;           // Mailer version.
        $mail->PluginDir = $CFG->libdir .'/phpmailer/';      // Plugin directory (eg smtp plugin).

        if (current_language() != 'en') {
            $mail->CharSet = get_string('thischarset');
        }

        if ($CFG->smtphosts == 'qmail') {
            $mail->IsQmail();                              // Sse Qmail system.

        } else if (empty($CFG->smtphosts)) {
            $mail->IsMail();                               // Use PHP mail() = sendmail.

        } else {
            $mail->IsSMTP();                               // Use SMTP directly.
            if ($CFG->debug > 7) {
                echo '<pre>' . "\n";
                $mail->SMTPDebug = true;
            }
            $mail->Host = $CFG->smtphosts;               // Specify main and backup servers.

            if ($CFG->smtpuser) {                          // Use SMTP authentication.
                $mail->SMTPAuth = true;
                $mail->Username = $CFG->smtpuser;
                $mail->Password = $CFG->smtppass;
            }
        }

        $adminuser = get_admin();

        // Make up an email address for handling bounces.
        if (!empty($CFG->handlebounces)) {
            $modargs = 'B'.base64_encode(pack('V', $adminuser->id)).substr(md5($adminuser->email), 0, 16);
            $mail->Sender = generate_email_processing_address(0, $modargs);
        } else {
            $mail->Sender   = $adminuser->email;
        }

        $mail->From     = $CFG->noreplyaddress;
        if (empty($replyto)) {
            $mail->AddReplyTo($CFG->noreplyaddress, get_string('noreplyname'));
        }

        if (!empty($replyto)) {
            $mail->AddReplyTo($replyto);
        }

        $mail->Subject = $subject;

        $mail->AddAddress($emailaddress, "" );

        $mail->WordWrap = 79;                               // Set word wrap.

        $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";

        if ($mail->Send()) {
            set_send_count($adminuser);
            return true;
        } else {
            mtrace('ERROR: '. $mail->ErrorInfo);
            add_to_log(SITEID, 'library', 'mailer', $FULLME, 'ERROR: '. $mail->ErrorInfo);
            $this->log_line("Error emailing");
            return false;
        }
    }

    /**
     * Drops all enrolments that not been updated by this extract.
     * Since an extract is comprehensive, we track all enrolemnts that
     * were not updated by the extract import.
     * All the remaining ones are assumed to have dropped, and so we set their
     * status to 0, and unassign the role assignment from moodle.
     *
     * This should only ever be called at the end of an extract process, or
     * users may get un-intentionally unenrolled from courses
     *
     * @return bool success or failure of the drops
     */
    // TODO Convert logging.
    public function process_extract_drops() {
        global $CFG, $DB;
        $status = true;

        foreach ($this->terms as $termid => $count) {
            $this->log_line('Processing drops for term '.$termid);

            $sqlparams = array('term' => $termid, 'status' => 1);
            $termcnt = $DB->count_records('enrol_lmb_enrolments', $sqlparams);

            $sqlparams = array('processid' => $this->processid, 'termid' => $termid, 'status' => 1);
            $dropcnt = $DB->count_records_select('enrol_lmb_enrolments',
                    'extractstatus < :processid AND term = :termid AND status = :status', $sqlparams);

            $percent = (int)ceil(($dropcnt/$termcnt)*100);
            $this->log_line('Dropping '.$dropcnt.' out of '.$termcnt.' ('.$percent.'%) enrolments.');

            if ($percent > $this->get_config('dropprecentlimit')) {
                $this->log_line('Exceeds the drop percent limit, skipping term.');
                continue;
            }

            $sqlparams = array('extractstatus' => $this->processid, 'termid' => $termid);

            if ($enrols = $DB->get_records_select('enrol_lmb_enrolments',
                    'extractstatus < :extractstatus AND term = :termid', $sqlparams, 'coursesourcedid ASC')) {
                $count = count($enrols);
                $curr = 0;
                $percent = 0;
                $csourcedid = '';

                $this->log_line($count.' records to process');

                foreach ($enrols as $enrol) {
                    $logline = $enrol->coursesourcedid.':'.$enrol->personsourcedid.':';

                    $enrolstatus = true;

                    $cperc = (int)floor(($curr/$count)*100);
                    if ($cperc > $percent) {
                        $percent = $cperc;
                        if ($this->get_config('logpercent')) {
                            $this->log_line($percent.'% complete');
                        }
                    }
                    $curr++;

                    if ($csourcedid != $enrol->coursesourcedid) {
                        $csourcedid = $enrol->coursesourcedid;
                        $courseid = enrol_lmb_get_course_id($csourcedid);
                    }

                    if ($enrol->status || !$enrol->succeeded) {
                        $enrolup = new Object();
                        $enrolup->id = $enrol->id;
                        $enrolup->timemodified = time();
                        $enrolup->status = 0;
                        $enrolup->succeeded = 0;

                        if ($courseid) {
                            if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                                if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                                    if (!$this->lmb_unassign_role($roleid, $courseid, $userid)) {
                                        $logline .= 'could not drop user:';
                                        $enrolup->succeeded = 0;
                                        $enrolstatus = false;
                                    } else {
                                        $logline .= 'dropped:';
                                        $enrolup->succeeded = 1;
                                    }
                                } else {
                                    $enrolup->succeeded = 0;
                                    $logline .= 'roleid not found:';
                                    $enrolstatus = false;
                                }
                            } else {
                                $logline .= 'user not found:';
                            }
                        } else {
                            $logline .= 'role course not found:';
                        }

                        if (enrol_lmb_compare_objects($enrolup, $enrol)) {
                            if (!$DB->update_record('enrol_lmb_enrolments', $enrolup)) {
                                $logline .= 'error updating lmb:';
                                $enrolstatus = false;
                            } else {
                                $logline .= 'lmb updated:';
                            }
                        } else {
                            $logline .= 'no lmb changes to make:';
                        }

                        if ($enrolstatus) {
                            if (!$this->get_config('logerrors')) {
                                $this->log_line($logline.'complete');
                            }
                        } else {
                            $this->log_line($logline.'error');
                        }

                        unset($enrolup);
                        $logline = '';
                        $status = $status && $enrolstatus;
                    }

                }

            }

            $this->log_line('Completed with term '.$termid);
        }

        return $status;
    }

    /**
     * For a given person id number, run all enrol and unenrol records in
     * the local lmb database
     *
     * @param string $idnumber the ims/xml id of a person
     * @return bool success or failure of the enrolments
     */
    public function restore_user_enrolments($idnumber) {
        global $DB;

        $status = true;

        if ($enrols = $DB->get_records('enrol_lmb_enrolments', array('personsourcedid' => $idnumber))) {
            $this->person_memberships_to_enrolments($enrols);
        }

        return $status;
    }

    /**
     * Assigns a moodle role to a user in the provided course
     *
     * @param int $roleid id of the moodle role to assign
     * @param int $courseid id of the course to assign
     * @param int $userid id of the moodle user
     * @param int $restrictstart Start date of the enrolment
     * @param int $restrictend End date of the enrolment
     * @return bool success or failure of the role assignment
     */
    public function lmb_assign_role($roleid, $courseid, $userid, $restrictstart = 0, $restrictend = 0) {
        if (!$courseid) {
            $this->append_log_line('missing courseid');
            return false;
        }

        if ($instance = $this->get_instance($courseid)) {
            if ($this->get_config('recovergrades')) {
                $wasenrolled = is_enrolled(context_course::instance($courseid), $userid);
            }

            if ($this->get_config('recovergrades') && !$wasenrolled) {
                $this->append_log_line('recovering grades');
                $recover = true;
            } else {
                $recover = false;
            }

            if ($this->get_config('userestrictdates')) {
                if ((($restrictstart === 0) && ($restrictend === 0)) || (($restrictstart < time())
                        && (($restrictend === 0) || (time() < $restrictend)))) {
                    $userstatus = ENROL_USER_ACTIVE;
                } else {
                    $userstatus = ENROL_USER_SUSPENDED;
                }
            } else {
                $userstatus = ENROL_USER_ACTIVE;
                $restrictstart = 0;
                $restrictend = 0;
            }
            $this->enrol_user($instance, $userid, $roleid, $restrictstart, $restrictend, $userstatus, $recover);
            $this->append_log_line('enrolled');
            return true;
        } else {
            $this->append_log_line('course lmb instance not found');
            return false;
        }
    }

    /**
     * Unassigns a moodle role to a user in the provided course
     *
     * @param int $roleid id of the moodle role to unassign
     * @param int $courseid id of the course to unassign
     * @param int $userid id of the moodle user
     * @return bool success or failure of the role assignment
     */
    public function lmb_unassign_role($roleid, $courseid, $userid) {
        if (!$courseid) {
            $this->append_log_line('missing courseid');
            return false;
        }

        if (enrol_lmb_check_enrolled_in_xls_merged($userid, $courseid)) {
            $this->append_log_line('xls still enroled');
            return true;
        }

        if ($instance = $this->get_instance($courseid)) {
            if ($this->get_config('disableenrol')) {
                $this->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            } else {
                $this->unenrol_user($instance, $userid, $roleid);
            }
            $this->append_log_line('unenrolled');
            return true;
        } else {
            $this->append_log_line('course lmb instance not found');
            return false;
        }
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Class Functions.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Preform any cron tasks for the module.
     */
    public function cron() {
        // If enabled, before a LMB time check.
        if ($this->get_config('lmbcheck')) {
            $this->check_last_luminis_event();
        }

        if ($this->get_config('cronxmlfile')) {
            $this->process_file(null, false);
        }

        // TODO.
        if ($this->get_config('cronxmlfolder')) {
            $this->process_folder();
        }

        if ($this->get_config('nextunhiderun') === null) {
            $curtime = time();
            $endtoday = mktime(23, 59, 59, date('n', $curtime), date('j', $curtime), date('Y', $curtime));

            $this->set_config('nextunhiderun', $endtoday);
        }

        if ($this->get_config('cronunhidecourses') && (time() > $this->get_config('nextunhiderun'))) {
            if ($this->get_config('prevunhideendtime') === null) {
                $this->set_config('prevunhideendtime', (time() + ($this->get_config('cronunhidedays')*86400)));
            }

            $starttime = $this->get_config('prevunhideendtime');
            $curtime = time();
            $endtoday = mktime(23, 59, 59, date('n', $curtime), date('j', $curtime), date('Y', $curtime));

            $endtime = $endtoday + ($this->get_config('cronunhidedays')*86400);

            $this->unhide_courses($starttime, $endtime);

            $this->set_config('nextunhiderun', $endtoday);
            $this->set_config('prevunhideendtime', $endtime);
        }

    }

    /**
     * Does this plugin allow manual enrolments?
     *
     * @param stdClass $instance course enrol instance
     * All plugins allowing this must implement 'enrol/xxx:enrol' capability
     *
     * @return bool - true means user with 'enrol/xxx:enrol' may enrol others freely,
     *                false means nobody may add more enrolments manually
     */
    public function allow_enrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow manual unenrolment of all users?
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol others freely,
     *                false means nobody may touch user_enrolments
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * This is useful especially for synchronisation plugins that
     * do suspend instead of full unenrolment.
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table, specifies user
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user,
     *                false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        return $this->allow_unenrol($instance);
    }

    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * All plugins allowing this must implement 'enrol/xxx:manage' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means it is possible to change enrol period and status in user_enrolments table
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        return array();
        /*
        // TODO for mangage.
        global $OUTPUT;

        if ($instance->enrol !== 'lmb') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/lmb:manage', $context)) {
            $managelink = new moodle_url("/enrol/lmb/manage.php", array('enrolid'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($managelink, new pix_icon('i/users', get_string('enrolusers', 'enrol_manual'),
                    'core', array('class'=>'iconsmall')));
        }

        return $icons;
        */
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        return false;
    }// TODO - make option?

    /**
     * Returns enrolment instance in given course.
     * @param int $courseid
     * @return object of enrol instances, or false
     */
    public function get_instance($courseid) {
        global $DB;

        $instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'lmb'));

        // TODO add option to disable this.
        if (!$instance) {
            if ($course = $DB->get_record('course', array('id' => $courseid))) {
                $this->add_instance($course);
                $instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'lmb'));
            }
        }

        return $instance;
    }

    public function log($line, $level, $force = false) {
        if (!$this->silent) {
            mtrace($line);
        }

        if (isset($this->logfp) && $this->logfp) {
            fwrite($this->logfp, date('Y-m-d\TH:i:s - ') . $level. ':' . $line . "\n");
        }
    }

/*    public function log($line, $level, $key = false, $force = false) {
        if (!isset($this->loggers[$key])) {
            $this->loggers[$key] = new enrol_lmb_log_record();
        }

        if (!$this->silent) {
            mtrace($line);
        }

        if (isset($this->logfp) && $this->logfp) {
            fwrite($this->logfp, date('Y-m-d\TH:i:s - ') . $level. ':' . $line . "\n");
        }
    }*/


    // -----------------------------------------------------------------------------------------------------------------
    // Old.
    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Write the provided string out to the logfile and to the screen.
     * New line will be added after line.
     *
     * @param string $string Text to write
     */
    public function log_line($string) {
        $message = '';

        if ($this->islmb) {
            $message = 'LMB Message:';
        }

        if (!$this->silent) {
            mtrace($string);
        }

        if (isset($this->logfp) && $this->logfp) {
            fwrite($this->logfp, date('Y-m-d\TH:i:s - ') . $message . $string . "\n");
        }
    }

} // End of class.
