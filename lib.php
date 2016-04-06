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
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/gradelib.php');




class enrol_lmb_plugin extends enrol_plugin {

    private $log;

    public $silent = false;
    public $islmb = false;

    public $processid = 0;
    private $terms = array();

    private $customfields = array();

    private $xmlcache = '';

    private $catcache = array();


    public function __construct() {
        $this->catcache['term'] = array();
        $this->catcache['termdept'] = array();
        $this->catcache['dept'] = array();
    }

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

            $this->log_line('Found file '.$filename);
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
                            $this->log_line($percent.'% complete');
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

            $this->log_line('Process has completed. Time taken: '.$timeelapsed.' seconds.');

            if ($comp) {
                $this->process_extract_drops();
            }

        } else { // End of if (file_exists).
            $this->log_line('File not found: '.$filename);
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


    /**
     * Process the group tag. Sends the tag onto the appropriate tag processor
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool the status as returned from the tag processor
     */
    public function process_group_tag($tagcontents) {
        if (preg_match('{<group>.*?<grouptype>.*?<typevalue.*?\>(.+?)</typevalue>.*?</grouptype>.*?</group>}is',
                $tagcontents, $matches)) {
            switch (trim($matches[1])) {
                case 'Term':
                    return $this->process_term_tag($tagcontents);
                    break;

                case 'CourseSection':
                    return $this->process_course_section_tag($tagcontents);
                    break;

                case 'CrossListedSection':
                    return $this->process_crosslisted_group_tag($tagcontents);
                    break;

            }
        }
    }

    /**
     * Process the course section group tag. Defines a course in Moodle.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool the status of the processing. false if there was no error
     */
    public function process_course_section_tag($tagcontents) {
        global $DB;

        if (!$this->get_config('parsecoursexml')) {
            $this->log_line('Course:skipping.');
            return true;
        }

        $course = new stdClass();

        $status = true;
        $deleted = false;
        $logline = 'Course:';

        // Sourcedid Source.
        if (preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $course->sourcedidsource = trim($matches[1]);
        }

        // Sourcedid Id.
        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $course->sourcedid = trim($matches[1]);

            $parts = explode('.', $course->sourcedid);
            $course->coursenumber = $parts[0];
            $course->term = $parts[1];

            $logline .= $course->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }

        $ttid = $course->term;
        if (!enrol_lmb_term_allowed($ttid)) {
            if (!$this->get_config('logerrors')) {
                $this->log_line("Skipping course message from term {$ttid} due to filter.");
            }
            return true;
        }

        if (preg_match('{<description>.*?<long>(.+?)</long>.*?</description>}is', $tagcontents, $matches)) {
            $course->longtitle = trim($matches[1]);

            $parts = explode('-', $course->longtitle);

            $course->rubric = $parts[0].'-'.$parts[1];
            $course->dept = $parts[0];
            $course->num = $parts[1];
            $course->section = $parts[2];
        }

        if (preg_match('{<description>.*?<full>(.+?)</full>.*?</description>}is', $tagcontents, $matches)) {
            $course->fulltitle = trim($matches[1]);
        }

        if (preg_match('{<timeframe>.*?<begin.*?\>(.+?)</begin>.*?</timeframe>}is', $tagcontents, $matches)) {
            $date = explode('-', trim($matches[1]));

            $course->startdate = make_timestamp($date[0], $date[1], $date[2]);
        }

        if (preg_match('{<timeframe>.*?<end.*?\>(.+?)</end>.*?</timeframe>}is', $tagcontents, $matches)) {
            $date = explode('-', trim($matches[1]));

            $course->enddate = make_timestamp($date[0], $date[1], $date[2]);
        }

        if (preg_match('{<org>.*?<orgunit>(.+?)</orgunit>.*?</org>}is', $tagcontents, $matches)) {
            $course->depttitle = trim($matches[1]);
        } else {
            $course->depttitle = '';
            $logline .= 'org/orgunit not defined:';
        }

        $cat = new stdClass();

        $cat->id = $this->get_category_id($course->term, $course->depttitle, $course->dept, $logline, $status);

        // Do the LMB tables check/update!

        $course->timemodified = time();

        if ($oldcourse = $DB->get_record('enrol_lmb_courses', array('sourcedid' => $course->sourcedid))) {
            $course->id = $oldcourse->id;
            if (enrol_lmb_compare_objects($course, $oldcourse)) {
                if ($DB->update_record('enrol_lmb_courses', $course)) {
                    $logline .= 'updated enrol_lmb_courses:';
                } else {
                    $logline .= 'failed to update enrol_lmb_courses:';
                    $status = false;
                }
            } else {
                $logline .= 'no lmb changes to make:';
            }
        } else {
            if ($course->id = $DB->insert_record('enrol_lmb_courses', $course, true)) {
                $logline .= 'inserted into enrol_lmb_courses:';
            } else {
                $logline .= 'failed to insert into enrol_lmb_courses:';
                $status = false;
            }
        }

        // Do the Course check/update!

        $moodlecourse = new stdClass();

        $moodlecourse->idnumber = $course->sourcedid;
        $moodlecourse->timemodified = time();

        if ($status && ($currentcourse = $DB->get_record('course', array('idnumber' => $moodlecourse->idnumber)))) {
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
                $logline .= 'updated course:';

            } else {
                $logline .= 'no changes to make:';
            }

        } else if ($status) {
            // If it's a new course.
            $this->create_shell_course($course->sourcedid, enrol_lmb_expand_course_title($course, $this->get_config('coursetitle')),
                                        enrol_lmb_expand_course_title($course, $this->get_config('courseshorttitle')), $cat->id,
                                        $logline, $status, false, $course->startdate, $course->enddate);
        }

        if ($status) {
            // TODO make optional.
            $tmpstatus = enrol_lmb_restore_users_to_course($course->sourcedid);
            if (!$tmpstatus) {
                $logline .= 'error restoring some enrolments:';
            }
        }

        if ($status && !$deleted) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }

        return $status;

    } // End process_group_tag().


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

        // Set some preferences.
        $moodlecourseconfig = get_config('moodlecourse');
        if ($this->get_config('usemoodlecoursesettings') && ($moodlecourseconfig)) {
            $logline .= 'Using default Moodle settings:';
            foreach ($moodlecourseconfig as $key => $value) {
                $moodlecourse->$key = $value;
            }
        } else {
            $logline .= 'Using hard-coded settings:';
            $moodlecourse->format               = 'topics';
            $moodlecourse->numsections          = 6;
            $moodlecourse->hiddensections       = 0;
            $moodlecourse->newsitems            = 3;
            $moodlecourse->showgrades           = 1;
            $moodlecourse->showreports          = 1;
        }

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
                $logline .= 'created course:';
            } else {
                $logline .= 'error adding course:';
                $status = false;
                return false;
            }
        } catch (Exception $e) {
            $logline .= 'exception - '.$e->getMessage().':';
            $status = false;
            return false;
        }

        $this->add_instance($moodlecourse);

        return $moodlecourse->id;
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

        $cat = new stdClass();

        if (($this->get_config('cattype') == 'deptcode') || ($this->get_config('cattype') == 'termdeptcode')) {
            $depttitle = $deptcode;
        }

        switch ($this->get_config('cattype')) {
            case 'term':
                $cat->id = $this->get_term_category_id($term, $logline, $status);

                break;

            case 'deptcode':
            case 'dept':
                if (isset($this->catcache['dept'][$depttitle])) {
                    return $this->catcache['dept'][$depttitle];
                }
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('dept' => $depttitle, 'cattype' => 'dept'))) {
                    if ($DB->record_exists('course_categories', array('id' => $lmbcat->categoryid))) {
                        $this->catcache['dept'][$depttitle] = $lmbcat->categoryid;
                        return $lmbcat->categoryid;
                    } else {
                        $DB->delete_records('enrol_lmb_categories', array('dept' => $depttitle, 'cattype' => 'dept'));
                    }
                }

                $cat->name = $depttitle;
                if ($this->get_config('cathidden')) {
                    $cat->visible = 0;
                } else {
                    $cat->visible = 1;
                }
                $cat->sortorder = 999;
                if ($cat->id = $DB->insert_record('course_categories', $cat, true)) {
                    $lmbcat = new stdClass();
                    $lmbcat->categoryid = $cat->id;
                    $lmbcat->cattype = 'dept';
                    $lmbcat->dept = $depttitle;

                    $cat->context = context_coursecat::instance($cat->id);
                    $cat->context->mark_dirty();
                    fix_course_sortorder();
                    if (!$DB->insert_record('enrol_lmb_categories', $lmbcat)) {
                        $logline .= "error saving category to enrol_lmb_categories:";
                    }
                    $this->catcache['dept'][$depttitle] = $lmbcat->categoryid;
                    $logline .= 'Created new (hidden) category:';
                } else {
                    $logline .= 'error creating category:';
                    $status = false;
                }

                break;

            case 'termdeptcode':
            case 'termdept':
                $key = $term.'termdept'.$depttitle;
                if (isset($this->catcache['termdept'][$key])) {
                    return $this->catcache['termdept'][$key];
                }

                $params = array('termsourcedid' => $term, 'dept' => $depttitle, 'cattype' => 'termdept');
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', $params)) {
                    if ($DB->record_exists('course_categories', array('id' => $lmbcat->categoryid))) {
                        $this->catcache['termdept'][$key] = $lmbcat->categoryid;
                        return $lmbcat->categoryid;
                    } else {
                        $DB->delete_records('enrol_lmb_categories', $params);
                    }
                }
                if ($termid = $this->get_term_category_id($term, $logline, $status)) {
                    $cat->name = $depttitle;
                    if ($this->get_config('cathidden')) {
                        $cat->visible = 0;
                    } else {
                        $cat->visible = 1;
                    }
                    $cat->parent = $termid;
                    $cat->sortorder = 999;
                    if ($cat->id = $DB->insert_record('course_categories', $cat, true)) {
                        $lmbcat = new stdClass();
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
                        $this->catcache['termdept'][$key] = $lmbcat->categoryid;
                        $logline .= 'Created new category:';
                    } else {
                        $logline .= 'error creating category:';
                        $status = false;
                    }
                } else {
                    $logline .= 'error creating category:';
                    $status = false;
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

        if (isset($this->catcache['term'][$term])) {
            return $this->catcache['term'][$term];
        }

        if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('termsourcedid' => $term, 'cattype' => 'term'))) {
            if ($DB->record_exists('course_categories', array('id' => $lmbcat->categoryid))) {
                $this->catcache['term'][$term] = $lmbcat->categoryid;
                return $lmbcat->categoryid;
            } else {
                $DB->delete_records('enrol_lmb_categories', array('termsourcedid' => $term, 'cattype' => 'term'));
            }
        }

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
                $lmbcat = new stdClass();
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
                $logline .= 'Created new category:';
                $this->catcache['term'][$term] = $cat->id;

                return $cat->id;
            } else {
                $logline .= 'error creating category:';
                $status = false;
            }
        } else {
            $logline .= "term does not exist, error creating category:";
            $status = false;
        }

        return false;
    }



    /**
     * Processes tag for the definition of a crosslisting group.
     * Currently does nothing. See process_crosslist_membership_tag_error()
     *
     * @param string $tagcontents the xml/ims idnumber for the term
     * @return bool success or failure of the processing
     */
    public function process_crosslisted_group_tag($tagcontents) {
        if ((!$this->get_config('parsexlsxml')) || (!$this->get_config('parsecoursexml'))) {
            $this->log_line('Crosslist Group:skipping.');
            return true;
        }

        $status = true;
        $deleted = false;
        $logline = 'Crosslist Group:';

        unset($xlist);

        // TODO remove this?

        if ($status && !$deleted) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }

        return $status;
    }


    /**
     * Used to call process_crosslist_membership_tag_error() without passing
     * error variables. See process_crosslist_membership_tag_error()
     *
     * @param string $tagcontent the xml contents to process
     * @return bool success or failure of the processing
     */
    public function process_crosslist_membership_tag($tagcontents) {
        $errorcode = 0;
        $errormessage = '';

        return $this->process_crosslist_membership_tag_error($tagcontents, $errorcode, $errormessage);
    }


    /**
     * Processes tags that join courses to a crosslist. Creates the crosslist
     * if it doesn't exist yet and updates name as needed.
     *
     * @param string $tagconents the xml contents to process
     * @param string $errorcode an error code number
     * @param string $errormessage an error message
     * @return bool success or failure of the processing
     */
    public function process_crosslist_membership_tag_error($tagcontents, &$errorcode, &$errormessage) {
        global $DB, $CFG;

        if ((!$this->get_config('parsexlsxml')) || (!$this->get_config('parsecoursexml'))) {
            $this->log_line('Crosslist Group:skipping.');
            return true;
        }

        $status = true;
        $deleted = false;
        $logline = 'Crosslist membership:';

        $xlists = array();

        $crosssourcedidsource = false;
        $term = false;

        if (preg_match('{<sourcedid>(.+?)</sourcedid>}is', $tagcontents, $matches)) {
            $source = $matches[1];

            if (preg_match('{<source>(.+?)</source>}is', $source, $matches)) {
                $crosssourcedidsource = trim($matches[1]);
            }
            if ($crosssourcedidsource == 'Plugin Internal') {
                if (preg_match('{<id>(.+?)</id>}is', $source, $matches)) {
                    $crosslistsourcedid = trim($matches[1]);
                    $logline .= $crosslistsourcedid.':';
                } else {
                    $crosslistsourcedid = '';
                    $internalid = enrol_lmb_create_new_crosslistid();
                    $logline .= 'generated:'.$internalid.':';
                }

            } else if (preg_match('{<id>(.+?)</id>}is', $source, $matches)) {
                $crosslistsourcedid = trim($matches[1]);
                $logline .= $crosslistsourcedid.':';

                if (preg_match('{XLS.+([0-9]{6})$}is', $crosslistsourcedid, $matches)) {
                    $term = trim($matches[1]);
                }
            }
        }

        if (preg_match('{<type>(.+?)</type>}is', $tagcontents, $matches)) {
            $type = $matches[1];
        } else {
            $type = $this->get_config('xlstype');
        }

        if (preg_match_all('{<member>(.+?)</member>}is', $tagcontents, $matches)) {

            $members = $matches[1];
            foreach ($members as $member) {
                unset($xlist);
                $xlist = new stdClass();

                if (preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $member, $matches)) {
                    $xlist->coursesourcedidsource = trim($matches[1]);
                }

                if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $member, $matches)) {
                    $xlist->coursesourcedid = trim($matches[1]);
                    $logline .= $xlist->coursesourcedid.':';
                }

                $recstatus = ($this->get_recstatus($member, 'role'));

                if ($recstatus == 3) {
                    $xlist->status = 0;
                } else {
                    if (preg_match('{<role.*?\>.*?<status>(.+?)</status>.*?</role>}is', $member, $matches)) {
                        $xlist->status = trim($matches[1]);
                    }
                }

                $parts = explode('.', $xlist->coursesourcedid);
                if (!$term) {
                    $term = $parts[1];
                }

                if ($crosslistsourcedid == '') {
                    $crosslistsourcedid = $internalid.$term;
                }

                if ($crosssourcedidsource) {
                    $xlist->crosssourcedidsource = $crosssourcedidsource;
                }
                if ($crosslistsourcedid) {
                    $xlist->crosslistsourcedid = $crosslistsourcedid;
                }

                $xlist->timemodified = time();

                array_push($xlists, $xlist);

            }
        } else {
            $logline .= 'no members found:';
            $errormessage = 'No member courses found';
            $errorcode = 5;
            $status = false;
        }

        $ttid = $term;
        if (!enrol_lmb_term_allowed($ttid)) {
            if (!$this->get_config('logerrors')) {
                $this->log_line("Skipping crosslist message from term {$ttid} due to filter.");
            }
            return true;
        }

        $params = array('crosslistsourcedid' => $crosslistsourcedid);
        if ($status && $existing_xlists = $DB->get_records('enrol_lmb_crosslists', $params)) {
            foreach ($existing_xlists as $existing_xlist) {
                if (isset($existing_type)) {
                    if ($existing_type != $existing_xlist->type) {
                        $logline .= 'type mismatch with existing members:';
                        $errormessage = 'Other existing members of this xlist are of a different type';
                        $errorcode = 4;
                        $status = false;
                    }
                } else {
                    $existing_type = $existing_xlist->type;
                }
            }
        }

        if ($status && isset($existing_type) && $existing_type != $type) {
            $logline .= 'xlist type '.$existing_type.' already in use:';
            $type = $existing_type;
        }

        foreach ($xlists as $xlist) {
            $params = array('status' => 1, 'coursesourcedid' => $xlist->coursesourcedid, 'type' => 'merge');
            if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', $params)) {
                if (($oldxlist->crosslistsourcedid != $xlist->crosslistsourcedid) ) {
                    $logline .= $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid.':';
                    $errorcode = 3;
                    $errormessage = $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid;
                    $status = false;
                }
            }
            if ($status && $type == 'merge') {
                $params = array('coursesourcedid' => $xlist->coursesourcedid, 'type' => 'meta');
                if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', $params)) {
                    $logline .= $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid.':';
                    $errormessage = $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid;
                    $errorcode = 3;
                    $status = false;
                }
            }
        }

        $newxlists = array();

        if ($status) {
            foreach ($xlists as $xlist) {
                $xlist->type = $type;
                $params = array('crosslistsourcedid' => $xlist->crosslistsourcedid, 'coursesourcedid' => $xlist->coursesourcedid);
                if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', $params)) {
                    $xlist->id = $oldxlist->id;
                    if (enrol_lmb_compare_objects($xlist, $oldxlist)) {
                        if ($DB->update_record('enrol_lmb_crosslists', $xlist)) {
                            $xlist->newrecord = 1;
                            $logline .= 'lmb updated:';
                        } else {
                            $status = false;
                            $logline .= 'failed to update lmb:';
                            $errormessage = 'Failed when updating LMB record';
                            $errorcode = 6;
                        }
                    } else {
                        $logline .= 'no lmb changes to make:';
                    }
                } else {
                    if ($xlist->id = $DB->insert_record('enrol_lmb_crosslists', $xlist, true)) {
                        $logline .= 'lmb inserted:';

                        $xlist->newrecord = 1;
                    } else {
                        $status = false;
                        $logline .= 'failed to insert lmb:';
                        $errormessage = 'Failed when inserting LMB record';
                        $errorcode = 6;
                    }
                }

                array_push($newxlists, $xlist);
            }
        }

        $xlists = $newxlists;

        $catid = $this->get_category_id($term, 'Crosslisted', 'XLS', $logline, $status);

        if ($type == 'meta') {
            $meta = true;
        } else {
            $meta = false;
        }

        // Course cant be merged multiple times?
        if ($status) {
            foreach ($xlists as $xlist) {
                $substatus = true;
                // Setup the course.
                unset($moodlecourse);
                $moodlecourse = new stdClass();

                $enddate = $this->get_crosslist_endtime($xlist->crosslistsourcedid);
                $params = array('idnumber' => $xlist->crosslistsourcedid);
                if ($substatus && !$moodlecourse->id = $DB->get_field('course', 'id', $params)) {
                    $starttime = $this->get_crosslist_starttime($xlist->crosslistsourcedid);
                    $moodlecourse->id = $this->create_shell_course($xlist->crosslistsourcedid, 'Crosslisted Course',
                                                $xlist->crosslistsourcedid, $catid,
                                                $logline, $substatus, $meta, $starttime, $enddate);
                }

                if ($substatus && $moodlecourse->id) {
                    $moodlecourse->fullname = $this->expand_crosslist_title($xlist->crosslistsourcedid,
                            $this->get_config('xlstitle'), $this->get_config('xlstitlerepeat'),
                            $this->get_config('xlstitledivider'));

                    $moodlecourse->shortname = $this->expand_crosslist_title($xlist->crosslistsourcedid,
                            $this->get_config('xlsshorttitle'), $this->get_config('xlsshorttitlerepeat'),
                            $this->get_config('xlsshorttitledivider'));

                    // TODO We should recompute the hidden status if this changes.
                    $moodlecourse->startdate = $this->get_crosslist_starttime($xlist->crosslistsourcedid);

                    if ($this->get_config('forcecomputesections') && $this->get_config('computesections')) {
                        $moodlecourseconfig = get_config('moodlecourse');

                        $length = $enddate - $moodlecourse->startdate;

                        $length = ceil(($length/(24*3600)/7));

                        if (($length > 0) && ($length <= $moodlecourseconfig->maxsections)) {
                            $moodlecourse->numsections = $length;
                        }
                    }

                    if ($DB->update_record('course', $moodlecourse)) {
                        $logline .= 'set course name:';
                    } else {
                        $logline .= 'error setting course name:';
                        $errormessage = 'Failed when updating course record';
                        $errorcode = 7;
                        $substatus = false;
                    }
                } else if ($substatus) {
                    $logline .= 'no course id for name update:';
                    $errormessage = 'No moodle course found';
                    $errorcode = 8;
                    $substatus = false;
                }

                if (($substatus) && $meta) {
                    if ($addid = $DB->get_field('course', 'id', array('idnumber' => $xlist->coursesourcedid))) {
                        if ($xlist->status) {
                            if (!$this->add_to_metacourse($moodlecourse->id, $addid)) {
                                $logline .= 'could not join course to meta course:';
                                $substatus = false;
                                $errormessage = 'Error adding course '.$xlist->coursesourcedid.' to metacourse';
                                $errorcode = 9;
                            }
                        } else {
                            if (!$this->remove_from_metacourse($moodlecourse->id, $addid)) {
                                $logline .= 'could not unjoin course from meta course:';
                                $substatus = false;
                                $errormessage = 'Error removing course '.$xlist->coursesourcedid.' from metacourse';
                                $errorcode = 9;
                            }
                        }
                    }

                }

                if ($substatus && !$meta && isset($xlist->newrecord) && $xlist->newrecord) {
                    $count = 0;
                    // TODO - removing at some future version.
                    if ($CFG->version >= 2013111800) {
                        $courseid = $DB->get_field('course', 'id', array('idnumber' => $xlist->coursesourcedid));
                        $coursemodinfo = course_modinfo::instance($courseid, -1);

                        $instances = $coursemodinfo->get_instances();
                        if (!empty($instances)) {
                            foreach($instances as $instance) {
                                $count += count($instance);
                            }
                        }
                    } else {
                        if (!$modinfo = $DB->get_field('course', 'modinfo', array('idnumber' => $xlist->coursesourcedid))) {
                            $modinfo = '';
                        }

                        $modinfo = unserialize($modinfo);
                        $count = count($modinfo);
                    }

                    if ($count <= 1) {
                        enrol_lmb_drop_all_users($xlist->coursesourcedid, 2, true);
                    }

                    $substatus = $substatus && enrol_lmb_drop_all_users($xlist->coursesourcedid, 1, true);
                    if (!$substatus) {
                        $errormessage = 'Error removing students from course '.$xlist->coursesourcedid;
                        $errorcode = 10;
                    }

                    $substatus = $substatus && enrol_lmb_restore_users_to_course($xlist->coursesourcedid);
                    if (!$substatus) {
                        $errormessage .= 'Error adding students to course '.$xlist->crosslistsourcedid;
                        $errorcode = 10;
                    }
                }

                // Uncrosslist a course.
                if ($substatus && !$meta && ($xlist->status == 0)) {
                    $logline .= 'removing from crosslist:';
                    // Restore users to individual course.
                    enrol_lmb_restore_users_to_course($xlist->coursesourcedid);

                    // Drop users from crosslist.
                    enrol_lmb_drop_crosslist_users($xlist);

                    $droppedusers = true;
                }
                $status = $status && $substatus;
            } // Close foreach loop.
        } // Close status check.

        if (isset($droppedusers) && $droppedusers) {
            $params = array('status' => 1, 'crosslistsourcedid' => $crosslistsourcedid);
            if ($allxlists = $DB->get_records('enrol_lmb_crosslists', $params)) {
                foreach ($allxlists as $xlist) {
                    enrol_lmb_restore_users_to_course($xlist->coursesourcedid);
                }
            }
        }

        if ($status && !$deleted) {
            $errormessage = $crosslistsourcedid;
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }

        return $status;
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

        return substr($title, 0, 254);
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
     * Processes a given person tag, updating or creating a moodle user as
     * needed.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success of failure of processing the tag
     */
    public function process_person_tag($tagcontents) {
        global $CFG, $DB;

        if (!$this->get_config('parsepersonxml')) {
            $this->log_line('Person:skipping.');
            return true;
        }

        $status = true;
        $deleted = false;
        $logline = 'Person:';

        $person = new stdClass();

        // Sourcedid Source.
        if (preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $person->sourcedidsource = trim($matches[1]);
        }

        // Sourcedid Id.
        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $person->sourcedid = trim($matches[1]);
            $logline .= $person->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }

        $recordsctid = $this->get_config('recordsctid');
        if (!empty($recordsctid)) {
            if (preg_match('{<userid.+?useridtype *= *"SCTID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                $person->sctid = trim($matches[1]);
            }
        }

        // Full Name.
        if (preg_match('{<name>.*?<fn>(.+?)</fn>.*?</name>}is', $tagcontents, $matches)) {
            $person->fullname = trim($matches[1]);
        }

        // Nickname.
        if (preg_match('{<name>.*?<nickname>(.+?)</nickname>.*?</name>}is', $tagcontents, $matches)) {
            $person->nickname = trim($matches[1]);
        }

        // Given Name.
        if (preg_match('{<name>.*?<n>.*?<given>(.+?)</given>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
            $person->givenname = trim($matches[1]);
        }

        // Family Name.
        if (preg_match('{<name>.*?<n>.*?<family>(.+?)</family>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
            $person->familyname = trim($matches[1]);
        }

        // Email.
        if (preg_match('{<email>(.*?)</email>}is', $tagcontents, $matches)) {
            $person->email = trim($matches[1]);
        }

        // Telephone.
        if (preg_match('{<tel.*?\>(.+?)</tel>}is', $tagcontents, $matches)) {
            $person->telephone = trim($matches[1]);
        }

        // Street.
        if (preg_match('{<adr>.*?<street>(.+?)</street>.*?</adr>}is', $tagcontents, $matches)) {
            $person->street = trim($matches[1]);
        }

        // Locality.
        if (preg_match('{<adr>.*?<locality>(.+?)</locality>.*?</adr>}is', $tagcontents, $matches)) {
            $person->locality = trim($matches[1]);
        }

        // Region.
        if (preg_match('{<adr>.*?<region>(.+?)</region>.*?</adr>}is', $tagcontents, $matches)) {
            $person->region = trim($matches[1]);
        }

        // Country.
        if (preg_match('{<adr>.*?<country>(.+?)</country>.*?</adr>}is', $tagcontents, $matches)) {
            $person->country = trim($matches[1]);
        }

        // Academice major.
        $exp = '{<extension>.*?<luminisperson>.*?<academicmajor>(.+?)</academicmajor>.*?</luminisperson>.*?</extension>}is';
        if (preg_match($exp, $tagcontents, $matches)) {
            $person->academicmajor = trim($matches[1]);
        }

        $person->username = '';

        // Select the userid.
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

            case "loginid":
                if (preg_match('{<userid.+?useridtype *= *"Logon ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                    $person->username = trim($matches[1]);
                }
                break;

            case "sctid":
                if (preg_match('{<userid.+?useridtype *= *"SCTID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                    $person->username = trim($matches[1]);
                }
                break;

            case "emailid":
                if (preg_match('{<userid.+?useridtype *= *"Email ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                    $person->username = trim($matches[1]);
                }
                break;

            case "other":
                $exp = '{<userid.+?useridtype *= *"'.$this->get_config('useridtypeother').'".*?\>(.+?)</userid>}is';
                if (preg_match($exp, $tagcontents, $matches)) {
                    $person->username = trim($matches[1]);
                }
                break;

            default:
                $status = false;
                $logline .= 'bad enrol_lmb_usernamesource setting:';

        }

        if ($this->get_config('sourcedidfallback') && trim($person->username)=='') {
            // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied...
            // ...NB We don't use an "else if" because the tag may be supplied-but-empty.
            $person->username = $person->sourcedid.'';
        }

        if (!isset($person->username) || (trim($person->username)=='')) {
            if (!$this->get_config('createusersemaildomain')) {
                $status = false;
            }
            $logline .= 'no username:';
        }

        $person->auth = $this->get_config('auth');

        // Custom field mapping.
        if ($this->get_config('customfield1mapping')) {
            switch($this->get_config('customfield1source')) {
                case "loginid":
                    if (preg_match('{<userid.+?useridtype *= *"Logon ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                        $person->customfield1 = trim($matches[1]);
                    }
                    break;
                case "sctid":
                    if (preg_match('{<userid.+?useridtype *= *"SCTID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                        $person->customfield1 = trim($matches[1]);
                    }
                    break;
                case "emailid":
                    if (preg_match('{<userid.+?useridtype *= *"Email ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)) {
                        $person->customfield1 = trim($matches[1]);
                    }
                    break;
            }
        }

        // Select the password.
        switch ($this->get_config('passwordnamesource')) {
            case "none":
                break;

            case "loginid":
                $exp = '{<userid.+?useridtype *= *"Logon ID".+?password *= *"(.*?)">.*?</userid>}is';
                if (preg_match($exp, $tagcontents, $matches)) {
                    $person->password = trim($matches[1]);
                }
                break;

            case "sctid":
                $exp = '{<userid.+?useridtype *= *"SCTID".+?password *= *"(.*?)">.*?</userid>}is';
                if (preg_match($exp, $tagcontents, $matches)) {
                    $person->password = trim($matches[1]);
                }
                break;

            case "emailid":
                $exp = '{<userid.+?useridtype *= *"Email ID".+?password *= *"(.*?)">.+?</userid>}is';
                if (preg_match($exp, $tagcontents, $matches)) {
                    $person->password = trim($matches[1]);
                }
                break;

            case "other":
                $exp = '{<userid.+?useridtype *= *"'.$this->get_config('useridtypeother')
                        .'".+?password *= *"(.*?)">.+?</userid>}is';
                if (preg_match($exp, $tagcontents, $matches)) {
                    $person->password = trim($matches[1]);
                }
                break;

            default:
                $logline .= 'bad enrol_lmb_passwordnamesource setting:';
                $status = false;

        }

        $recstatus = ($this->get_recstatus($tagcontents, 'person'));

        $lmbperson = new stdClass();

        if (isset($person->sourcedid)) {
            $lmbperson->sourcedid = $person->sourcedid;
        }
        if (isset($person->sctid)) {
            $lmbperson->sctid = $person->sctid;
        } else {
            $lmbperson->sctid = null;
        }
        if (isset($person->sourcedidsource)) {
            $lmbperson->sourcedidsource = $person->sourcedidsource;
        }
        if (isset($person->fullname)) {
            $lmbperson->fullname = $person->fullname;
        }
        if (isset($person->nickname)) {
            $lmbperson->nickname = $person->nickname;
        }
        if (isset($person->familyname)) {
            $lmbperson->familyname = $person->familyname;
        }
        if (isset($person->givenname)) {
            $lmbperson->givenname = $person->givenname;
        }
        if (isset($person->email)) {
            $lmbperson->email = $person->email;
        }
        if (isset($person->username)) {
            $lmbperson->username = $person->username;
        }
        if (isset($person->telephone)) {
            $lmbperson->telephone = $person->telephone;
        }
        if (isset($person->street)) {
            $lmbperson->adrstreet = $person->street;
        }
        if (isset($person->locality)) {
            $lmbperson->locality = $person->locality;
        }
        if (isset($person->region)) {
            $lmbperson->region = $person->region;
        }
        if (isset($person->country)) {
            $lmbperson->country = $person->country;
        }
        if (isset($person->academicmajor)) {
            $lmbperson->academicmajor = $person->academicmajor;
        }
        if (isset($person->customfield1)) {
            $lmbperson->customfield1 = $person->customfield1;
        } else {
            $lmbperson->customfield1 = null;
        }
        $lmbperson->recstatus = $recstatus;

        $lmbperson->timemodified = time();

        $lmbpersonslash = $lmbperson;

        // Check to see if we have an existing record for this person.
        if ($oldlmbperson = $DB->get_record('enrol_lmb_people', array('sourcedid' => $lmbperson->sourcedid))) {
            $lmbpersonslash->id = $oldlmbperson->id;
            if (enrol_lmb_compare_objects($lmbpersonslash, $oldlmbperson)) {
                if (!$DB->update_record('enrol_lmb_people', $lmbpersonslash)) {
                    $logline .= 'error updating enrol_lmb_people:';
                    $status = false;
                } else {
                    $logline .= 'updated lmb table:';
                }
            } else {
                $logline .= 'no lmb changes to make:';
            }
        } else {
            if (!$DB->insert_record('enrol_lmb_people', $lmbpersonslash)) {
                $logline .= 'error inserting enrol_lmb_people:';
                $status = false;
            } else {
                $logline .= 'inserted into lmb table:';
            }
        }

        $emailallow = true;
        if ($this->get_config('createusersemaildomain')) {

            if (isset($lmbperson->email) && ($lmbperson->email) && ($domain = explode('@', $lmbperson->email))
                    && (count($domain) > 1)) {

                $domain = trim($domain[1]);

                if (isset($CFG->ignoredomaincase) && $CFG->ignoredomaincase) {
                    $matchappend = 'i';
                } else {
                    $matchappend = '';
                }

                if (!preg_match('/^'.trim($this->get_config('createusersemaildomain')).'$/'.$matchappend, $domain)) {
                    $logline .= 'no in domain email:';
                    $emailallow = false;
                    if (!$this->get_config('donterroremail')) {
                        $status = false;
                    }
                }
            } else {
                $logline .= 'no in domain email:';
                $emailallow = false;
                if (!$this->get_config('donterroremail')) {
                    $status = false;
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

        if (isset($lmbperson->email)) {
            if ($emailallow && $lmbperson->recstatus != 3 && trim($lmbperson->username) != '') {
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
                        if (!empty($lmbperson->telephone)) {
                            $moodleuser->phone1 = $lmbperson->telephone;
                        } else {
                            $moodleuser->phone1 = '';
                        }
                    }

                    if ($this->get_config('includeaddress') && $this->get_config('forceaddress')) {
                        if (!empty($lmbperson->adrstreet)) {
                            $moodleuser->address = $lmbperson->adrstreet;
                        } else {
                            $moodleuser->address = '';
                        }
                    }

                    if ($this->get_config('includecity') && $this->get_config('forcecity')) {
                        if ($this->get_config('defaultcity') == 'standardxml') {
                            if ($lmbperson->locality) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = $this->get_config('standardcity');
                            }
                        } else if ($this->get_config('defaultcity') == 'xml') {
                            if (!empty($lmbperson->locality)) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = '';
                            }
                        } else if ($this->get_config('defaultcity') == 'standard') {
                            $moodleuser->city = $this->get_config('standardcity');
                        }
                    }

                    if (enrol_lmb_compare_objects($moodleuser, $oldmoodleuser) || ($this->get_config('customfield1mapping')
                            && ($this->compare_custom_mapping($moodleuser->id, $lmbperson->customfield1,
                            $this->get_config('customfield1mapping'))))) {

                        if ((strcasecmp($oldmoodleuser->username, $moodleuser->username) !== 0)
                                && ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username)))) {
                            $logline .= 'username collision while trying to update:';
                            $status = false;
                        } else {
                            if ($DB->update_record('user', $moodleuser)) {
                                $logline .= 'updated user:';
                                // Update custom fields.
                                if ($this->get_config('customfield1mapping')) {
                                    $this->update_custom_mapping($moodleuser->id, $lmbperson->customfield1,
                                        $this->get_config('customfield1mapping'));
                                }
                            } else {
                                $logline .= 'failed to update user:';
                                $status = false;
                            }
                        }
                    } else {
                        $logline .= 'no changes to make:';
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
                        if (!empty($lmbperson->telephone)) {
                            $moodleuser->phone1 = $lmbperson->telephone;
                        } else {
                            $moodleuser->phone1 = '';
                        }
                    } else {
                        $moodleuser->phone1 = '';
                    }

                    if ($this->get_config('includeaddress')) {
                        if (!empty($lmbperson->adrstreet)) {
                            $moodleuser->address = $lmbperson->adrstreet;
                        } else {
                            $moodleuser->address = '';
                        }
                    } else {
                        $moodleuser->address = '';
                    }

                    if ($this->get_config('includecity')) {
                        if ($this->get_config('defaultcity') == 'standardxml') {
                            if ($lmbperson->locality) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = $this->get_config('standardcity');
                            }
                        } else if ($this->get_config('defaultcity') == 'xml') {
                            if (!empty($lmbperson->locality)) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = '';
                            }
                        } else if ($this->get_config('defaultcity') == 'standard') {
                            $moodleuser->city = $this->get_config('standardcity');
                        }
                    } else {
                        $moodleuser->city = '';
                    }

                    $moodleuser->country = $CFG->country;

                    if ($this->get_config('createnewusers')) {
                        if ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username))) {
                            $logline .= 'username collision, could not create user:';
                            $status = false;
                        } else {
                            if ($id = $DB->insert_record('user', $moodleuser, true)) {
                                $logline .= "created new user:";
                                if (isset($lmbperson->customfield1)) {
                                    $this->update_custom_mapping($id, $lmbperson->customfield1,
                                            $this->get_config('customfield1mapping'));
                                }
                                $moodleuser->id = $id;
                                $newuser = true;

                                $status = $status && $this->restore_user_enrolments($lmbperson->sourcedid);

                            } else {
                                $logline .= 'failed to insert new user:';
                                $status = false;
                            }
                        }
                    } else {
                        $logline .= 'did not create new user:';
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
                                        $logline .= 'error setting password:';
                                        $status = false;
                                    }
                                }
                            }
                        }
                    }
                }

            } else if ($this->get_config('imsdeleteusers') && ($lmbperson->recstatus == 3)
                    && ($moodleuser = $DB->get_record('user', array('idnumber' => $lmbperson->idnumber)))) {
                if (delete_user($moodleuser)) {
                    $logline .= 'deleted user:';
                    $deleted = true;
                } else {
                    $logline .= 'failed to delete user:';
                    $status = false;
                }
            }
        } else {
            $logline .= 'no email address found:';
            if (!$this->get_config('donterroremail')) {
                $status = false;
            }
        }

        if ($status && !$deleted) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else if ($deleted) {
            $this->log_line($logline.'complete');
        } else {
            $this->log_line($logline.'error');
        }

        return $status;

    } // End process_person_tag().


    /**
     * Processes a given term tag. Basically just inserting the info
     * in a lmb internal table for future use.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success of failure of processing the tag
     */
    public function process_term_tag($tagcontents) {
        global $DB;

        $status = true;
        $logline = 'Term:';

        $term = new stdClass();

        // Sourcedid Source.
        if (preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $term->sourcedidsource = trim($matches[1]);
        }

        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $term->sourcedid = trim($matches[1]);
            $logline .= $term->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }

        $ttid = $term->sourcedid;
        if (!enrol_lmb_term_allowed($ttid)) {
            if (!$this->get_config('logerrors')) {
                $this->log_line("Skipping term message from term {$ttid} due to filter.");
            }
            return true;
        }

        if (preg_match('{<description>.*?<long>(.+?)</long>.*?</description>}is', $tagcontents, $matches)) {
            $term->title = trim($matches[1]);
        } else {
            $logline .= "title not found:";
            $status = false;
        }

        if (preg_match('{<timeframe>.*?<begin.*?\>(.+?)</begin>.*?</timeframe>}is', $tagcontents, $matches)) {
            $date = explode('-', trim($matches[1]));

            $term->starttime = make_timestamp($date[0], $date[1], $date[2]);
        }

        if (preg_match('{<timeframe>.*?<end.*?\>(.+?)</end>.*?</timeframe>}is', $tagcontents, $matches)) {
            $date = explode('-', trim($matches[1]));

            $term->endtime = make_timestamp($date[0], $date[1], $date[2]);
        }

        $term->timemodified = time();

        if ($oldterm = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $term->sourcedid))) {
            $term->id = $oldterm->id;

            if ($id = $DB->update_record('enrol_lmb_terms', $term)) {
                $logline .= 'updated term:';
            } else {
                $logline .= 'failed to update term:';
                $status = false;
            }
        } else {
            if ($id = $DB->insert_record('enrol_lmb_terms', $term, true)) {
                $logline .= 'create term:';
                $term->id = $id;
            } else {
                $logline .= 'create to update term:';
                $status = false;
            }
        }

        if ($status) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }

        return $status;

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

        if (preg_match('{<sourcedid>.*?<id>XLS(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            return $this->process_crosslist_membership_tag_error($tagcontents, $errorcode, $errormessage);
        } else if (preg_match('{<sourcedid>.*?<source>Plugin Internal</source>.*?</sourcedid>}is', $tagcontents, $matches)) {
            return $this->process_crosslist_membership_tag_error($tagcontents, $errorcode, $errormessage);
        } else {
            return $this->process_person_membership_tag($tagcontents);
        }

    }


    /**
     * Process a tag that is a membership of a person
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success or failure of the processing
     */
    public function process_person_membership_tag($tagcontents) {
        global $DB;

        if ((!$this->get_config('parsepersonxml')) || (!$this->get_config('parsecoursexml'))
                || (!$this->get_config('parsepersonxml'))) {
            $this->log_line('Enrolment:skipping.');
            return true;
        }

        $status = true;
        $logline = 'Enrolment:';
        $enrolment = new stdClass();

        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $enrolment->coursesourcedid = trim($matches[1]);

            if (preg_match('{.....\.(.+?)$}is', $enrolment->coursesourcedid, $matches)) {
                $enrolment->term = trim($matches[1]);
                $ttid = $enrolment->term;
                if (!enrol_lmb_term_allowed($ttid)) {
                    if (!$this->get_config('logerrors')) {
                        $this->log_line("Skipping enrol message from term {$ttid} due to filter.");
                    }
                    return true;
                }
                if (!isset($this->terms[$enrolment->term])) {
                    $this->terms[$enrolment->term] = 0;
                }
                $this->terms[$enrolment->term]++;
            }

            $logline .= 'course id '.$enrolment->coursesourcedid.':';
        } else {
            $logline .= 'course id not found:';
            $status = false;
        }

        if (preg_match('{<member>(.*?)</member>}is', $tagcontents, $membermatches)) {

            $member = $membermatches[1];

            if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $member, $matches)) {
                $enrolment->personsourcedid = trim($matches[1]);
                $logline .= 'person id '.$enrolment->personsourcedid.':';
            } else {
                $logline .= 'person id not found:';
                $status = false;
            }

            if (preg_match('{<role.*?roletype *= *"(.*?)".*?\>.*?</role>}is', $member, $matches)) {
                $enrolment->role = (int)trim($matches[1]);
            } else {
                $logline .= 'person role not found:';
                $status = false;
            }

            if (preg_match('{<timeframe>.*?<begin.*?restrict *= *"(.*?)".*?\>.*?</begin>.*?</timeframe>}is', $member, $matches)) {
                $enrolment->beginrestrict = (int)trim($matches[1]);
            }

            if (preg_match('{<timeframe>.*?<begin.*?restrict *= *".*?".*?\>(.*?)</begin>.*?</timeframe>}is', $member, $matches)) {
                $date = explode('-', trim($matches[1]));

                $enrolment->beginrestricttime = make_timestamp($date[0], $date[1], $date[2]);
            }

            if (preg_match('{<timeframe>.*?<end.*?restrict *= *"(.*?)".*?\>.*?</end>.*?</timeframe>}is', $member, $matches)) {
                $enrolment->endrestrict = (int)trim($matches[1]);
            }

            if (preg_match('{<timeframe>.*?<end.*?restrict *= *".*?".*?\>(.*?)</end>.*?</timeframe>}is', $member, $matches)) {
                $date = explode('-', trim($matches[1]));

                $enrolment->endrestricttime = make_timestamp($date[0], $date[1], $date[2], 23, 59, 59);
            }

            if (preg_match('{<interimresult.*?\>.*?<mode>(.+?)</mode>.*?</interimresult>}is', $member, $matches)) {
                $enrolment->midtermgrademode = trim($matches[1]);
            }

            if (preg_match('{<finalresult.*?\>.*?<mode>(.+?)</mode>.*?</finalresult>}is', $member, $matches)) {
                $enrolment->finalgrademode = trim($matches[1]);
            }

            if (preg_match('{<extension.*?\>.*?<gradable>(.+?)</gradable>.*?</extension>}is', $member, $matches)) {
                $enrolment->gradable = (int)trim($matches[1]);
            } else {
                // Per e-learn docs, if ommited, then membership is gradable.
                if (isset($enrolment->midtermgrademode) || isset($enrolment->finalgrademode)) {
                    $enrolment->gradable = 1;
                }
            }

            $recstatus = ($this->get_recstatus($member, 'role'));
            if ($recstatus==3) {
                $enrolment->status = 0;
            }

            if (preg_match('{<role.*?\>.*?<status>(.+?)</status>.*?</role>}is', $member, $matches)) {
                $enrolment->status = trim($matches[1]);
            } else {
                $logline .= 'person status not found:';
                $status = false;
            }

            if ($this->processid) {
                $enrolment->extractstatus = $this->processid;
            }
        } else {
            $logline .= 'member not found:';
            $status = false;
        }

        $enrolment->timemodified = time();

        if ($status) {
            $params = array('coursesourcedid' => $enrolment->coursesourcedid, 'personsourcedid' => $enrolment->personsourcedid);
            if ($oldenrolment = $DB->get_record('enrol_lmb_enrolments', $params)) {
                $enrolment->id = $oldenrolment->id;
                $enrolment->succeeded = $oldenrolment->succeeded;

                if (enrol_lmb_compare_objects($enrolment, $oldenrolment)) {
                    if (!$DB->update_record('enrol_lmb_enrolments', $enrolment)) {
                        $logline .= 'error updating in enrol_lmb_enrolments:';
                        $status = false;
                    } else {
                        $logline .= 'lmb updated:';
                    }
                } else {
                    $logline .= 'no lmb changes to make:';
                }

            } else {
                if (!$enrolment->id = $DB->insert_record('enrol_lmb_enrolments', $enrolment, true)) {
                    $logline .= 'error inserting into enrol_lmb_enrolments:';
                    $status = false;
                } else {
                    $logline .= 'lmb inserted:';
                }
            }
        }

        $status = $status && $this->process_enrolment_log($enrolment, $logline);

        if ($status) {
            if (!$this->get_config('logerrors')) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }

        return $status;
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
                        $this->email_luminis_error($difftime, trim($email));
                    }
                }
            } else {
                // If longer then grace.
                if (($this->get_config('nonbizgrace')) && ($difftime > ($this->get_config('nonbizgrace') * 60))) {
                    $this->log_line('Last luminis message received '.floor($difftime/60).' minutes ago.');

                    $emails = explode(',', $this->get_config('emails'));

                    foreach ($emails as $email) {
                        $this->email_luminis_error($difftime, trim($email));
                    }
                }
            }
        }

    }


    /**
     * Email a luminis downtime message to the provided email
     *
     * @param int $timeelapsed the number of seconds since the last message was received
     * @param string $emailaddress the email address to send the message to
     * @return bool success of failure of the email send
     */
    public function email_luminis_error($timeelapsed, $emailaddress) {
        $fromuser = get_admin();

        $touser = new stdClass();
        $touser->id = $fromuser->id;
        $touser->email = $emailaddress;

        $time = format_time($timeelapsed);

        $messagetext = get_string('nomessagefull', 'enrol_lmb', $time);
        $subject = get_string("nomessage", "enrol_lmb");

        return email_to_user($touser, $fromuser, $subject, $messagetext);
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

            $sqlparams = array('processid' => $this->processid, 'termid' => $termid);

            $enrols = $DB->get_recordset_select('enrol_lmb_enrolments',
                    'extractstatus < :processid AND term = :termid', $sqlparams, 'coursesourcedid ASC');
            $count = $DB->count_records_select('enrol_lmb_enrolments',
                    'extractstatus < :processid AND term = :termid', $sqlparams);

            if ($enrols->valid()) {
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
                        $enrolup = new stdClass();
                        $enrolup->id = $enrol->id;
                        $enrolup->timemodified = time();
                        $enrolup->status = 0;
                        $enrolup->succeeded = 0;

                        if (enrol_lmb_compare_objects($enrolup, $enrol)) {
                            $DB->update_record('enrol_lmb_enrolments', $enrolup);
                        }

                        if ($courseid) {
                            if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                                if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                                    if (!$this->lmb_unassign_role_log($roleid, $courseid, $userid, $logline)) {
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
            $enrols->close();
            $this->log_line('Completed with term '.$termid);
        }

        return $status;
    }

    /**
     * Processes an enrol object, executing the associated assign or
     * unassign and update the lmb entry for success or failure
     *
     * @param object $enrol an enrol object representing a record in enrol_lmb_enrolments
     * @param string $logline passed logline object to append log entries to
     * @return bool success or failure of the role assignments
     */ // TODO2.
    public function process_enrolment_log($enrol, &$logline) {
        global $DB;
        $status = true;

        $newcoursedid = enrol_lmb_get_course_id($enrol->coursesourcedid);

        $params = array('status' => 1, 'coursesourcedid' => $enrol->coursesourcedid);
        if ($this->get_config('xlsmergegroups') && $xlist = $DB->get_record('enrol_lmb_crosslists', $params)) {
            $groupid = enrol_lmb_get_crosslist_groupid($enrol->coursesourcedid, $xlist->crosslistsourcedid);
        } else {
            $groupid = false;
        }

        $enrolup = new stdClass();
        $enrolup->id = $enrol->id;

        if ($newcoursedid) {
            if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                    if ($enrol->status) {
                        if (isset($enrol->beginrestrict) && $enrol->beginrestrict) {
                            $beginrestricttime = $enrol->beginrestricttime;
                        } else {
                            $beginrestricttime = 0;
                        }
                        if (isset($enrol->endrestrict) && $enrol->endrestrict) {
                            $endrestricttime = $enrol->endrestricttime;
                        } else {
                            $endrestricttime = 0;
                        }
                        $status = $this->lmb_assign_role_log($roleid, $newcoursedid, $userid, $logline, $beginrestricttime, $endrestricttime);
                        if ($status && $groupid && !groups_is_member($groupid, $userid)) {
                            global $CFG;
                            require_once($CFG->dirroot.'/group/lib.php');
                            groups_add_member($groupid, $userid);
                            $logline .= 'added user to group:';
                        }
                    } else {
                        $status = $this->lmb_unassign_role_log($roleid, $newcoursedid, $userid, $logline);
                        if ($status && $groupid && groups_is_member($groupid, $userid)) {
                            global $CFG;
                            require_once($CFG->dirroot.'/group/lib.php');
                            groups_remove_member($groupid, $userid);
                            $logline .= 'removed user from group:';
                        }
                    }
                } else {
                    $logline .= 'roleid not found:';
                    $status = false;
                }
            } else {
                $logline .= 'user not found:';
                $status = false;
            }
        } else {
            $logline .= 'course not found:';
            $status = false;
        }

        if ($status) {
            $enrolup->succeeded = 1;
        } else {
            $enrolup->succeeded = 0;
        }

        if (enrol_lmb_compare_objects($enrolup, $enrol)) {
            if (!$DB->update_record('enrol_lmb_enrolments', $enrolup)) {
                $logline .= 'error updating in enrol_lmb_enrolments:';
                $status = false;
            } else {
                $logline .= 'lmb updated:';
            }
        }

        unset($enrolup);

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
            foreach ($enrols as $enrol) {
                $logline = '';
                $status = $this->process_enrolment_log($enrol, $logline) && $status;
            }

        }

        return $status;
    }


    /**
     * Assigns a moodle role to a user in the provided course
     *
     * @param int $roleid id of the moodle role to assign
     * @param int $courseid id of the course to assign
     * @param int $userid id of the moodle user
     * @param string $logline passed logline object to append log entries to
     * @param int $restrictstart Start date of the enrolment
     * @param int $restrictend End date of the enrolment
     * @return bool success or failure of the role assignment
     */
    public function lmb_assign_role_log($roleid, $courseid, $userid, &$logline, $restrictstart = 0, $restrictend = 0) {
        if (!$courseid) {
            $logline .= 'missing courseid:';
        }

        if ($instance = $this->get_instance($courseid)) {
            if ($this->get_config('recovergrades')) {
                $wasenrolled = is_enrolled(context_course::instance($courseid), $userid);
            }

            // TODO catch exceptions thrown.
            if ($this->get_config('recovergrades') && !$wasenrolled) {
                $logline .= 'recovering grades:';
                $recover = true;
            } else {
                $recover = false;
            }

            if ($this->get_config('userestrictdates')) {
                if ((($restrictstart === 0) && ($restrictend === 0)) || (($restrictstart < time()) && (($restrictend === 0) || (time() < $restrictend)))) {
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
            $logline .= 'enrolled:';
            return true;
        } else {
            $logline .= 'course lmb instance not found:';
            return false;
        }
    }


    /**
     * Unassigns a moodle role to a user in the provided course
     *
     * @param int $roleid id of the moodle role to unassign
     * @param int $courseid id of the course to unassign
     * @param int $userid id of the moodle user
     * @param string $logline passed logline object to append log entries to
     * @return bool success or failure of the role assignment
     */
    public function lmb_unassign_role_log($roleid, $courseid, $userid, &$logline) {
        if (!$courseid) {
            $logline .= 'missing courseid:';
            return false;
        }

        if (enrol_lmb_check_enrolled_in_xls_merged($userid, $courseid)) {
            $logline .= 'xls still enroled:';
            return true;
        }

        if ($instance = $this->get_instance($courseid)) {
            // TODO catch exceptions thrown.
            if ($this->get_config('disableenrol')) {
                $this->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            } else {
                $this->unenrol_user($instance, $userid, $roleid);
            }
            $logline .= 'unenrolled:';
            return true;
        } else {
            $logline .= 'course lmb instance not found:';
            return false;
        }
    }


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

} // End of class.

