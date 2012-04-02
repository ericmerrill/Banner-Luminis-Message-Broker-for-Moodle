<?php
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




class enrol_lmb_plugin extends enrol_plugin {

    var $log;    

    // The "roles" hard-coded in the Banner XML specification are:
    var $imsroles = array(
    '01'=>'Learner',
    '02'=>'Instructor',
    );

    var $configcache;//Use this!!
    
    var $islmb = false;
    
    var $processid = 0;
    var $terms = array();

    
    /**
    * This public function is only used when first setting up the plugin, to 
    * decide which role assignments to recommend by default.
    * For example, IMS role '01' is 'Learner', so may map to 'student' in Moodle.
    * 
    * @param int $imscode This is the XML code for the role type
    * @return int the moodle role id
    */
    public function determine_default_rolemapping($imscode) {
        global $DB;
        
        switch($imscode) {
            case '01':
                $shortname = 'student';
                break;
            case '02':
                $shortname = 'editingteacher';
                break;
            default:
                return 0; // Zero for no match
        }
        return $DB->get_field('role', 'id', array('shortname' => $shortname));
    }
    
    
    
    /**
     * Preform any cron tasks for the module.
     */
    public function cron() {
        $config = $this->get_config();

        // If enabled, before a LMB time check
        if ($config->performlmbcheck) {
            $this->check_last_luminis_event();
        }
        
        if ($config->cronxmlfile) {
            $this->process_xml_file(NULL, false);
        }
        
        if ($config->cronxmlfolder) {
        
        }
        
        
        if (!isset($config->nextunhiderun)) {
        	$curtime = time();
	    	$endtoday = mktime(23, 59, 59, date('n', $curtime), date('j', $curtime), date('Y', $curtime), 0);
	    	
	    	$config->nextunhiderun = $endtoday;
	    	set_config('nextunhiderun', $endtoday, 'enrol_lmb');
        }
        
        if ($config->cronunhidecourses && (time() > $config->nextunhiderun)) {
        	if (!isset($config->prevunhideendtime)) {
        		$config->prevunhideendtime = (time() + ($config->cronunhidedays*86400));
        	}
        	
        	$starttime = $config->prevunhideendtime;
        	$curtime = time();
        	$endtoday = mktime(23, 59, 59, date('n', $curtime), date('j', $curtime), date('Y', $curtime), 0);
        	
        	$endtime = $endtoday + ($config->cronunhidedays*86400);
        	
        	$this->unhide_courses($starttime, $endtime);
        	
        	set_config('nextunhiderun', $endtoday, 'enrol_lmb');
        	set_config('prevunhideendtime', $endtime, 'enrol_lmb');
        }
        
    }
    
    
    public function unhide_courses($lasttime, $curtime, $days = 0) {
        global $CFG, $DB;
        $daysec = $days * 86400;
        
        $this->open_log_file();
        
        $start = $lasttime + $daysec;
        $end = $curtime + $daysec;
        
        
        //Update normal courses
        $sqlparams = array('start' => $start, 'end' => $end);
        $sql = 'UPDATE {course} SET visible=1 WHERE visible=0 AND idnumber IN (SELECT sourcedid FROM {enrol_lmb_courses} WHERE startdate > :start AND startdate <= :end)';
               
        $this->log_line('cron unhide:'.$sql);
        $DB->execute($sql, $sqlparams);
        
        
        //Update crosslists
        $sqlparams = array('start' => $start, 'end' => $end);
        $sql = 'UPDATE {course} SET visible=1 WHERE visible=0 AND idnumber IN (SELECT crosslistsourcedid FROM {enrol_lmb_crosslists} WHERE coursesourcedid IN (SELECT sourcedid FROM {enrol_lmb_courses} WHERE startdate > :start AND startdate <= :end))';

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
        $this->xmlcache .= $curline; // Add a line onto the XML cache
        
        $status = false;
        
    
        if($tagcontents = $this->full_tag_found_in_cache('group', $curline)){
            $status = $this->process_group_tag($tagcontents);
            $this->remove_tag_from_cache('group');
        } else if ($tagcontents = $this->full_tag_found_in_cache('person', $curline)){
            $status = $this->process_person_tag($tagcontents);
            $this->remove_tag_from_cache('person');
        } else if ($tagcontents = $this->full_tag_found_in_cache('membership', $curline)){
            $status = $this->process_membership_tag_error($tagcontents, $errorcode, $errormessage);
            $this->remove_tag_from_cache('membership');
        } else if ($tagcontents = $this->full_tag_found_in_cache('comments', $curline)){
            $this->remove_tag_from_cache('comments');
        } else if ($tagcontents = $this->full_tag_found_in_cache('properties', $curline)){
            $status = $this->process_properties_tag($tagcontents);
            $this->remove_tag_from_cache('properties');
        }else{
    
        }
        
        return $status;
    }
    
    
    /**
     * Process an entire file of xml 
     * 
     * @param string $filename the file to process. use config location if unspecified
     * @param bool $force forse the processing to occur, even if already processing or there is no file time change.
     * @return bool success or failure of the processing
     */
    public function process_file($filename = NULL, $force = false, $folderprocess = false, $processid = NULL) {
        $config = $this->get_config();
    
        if (!$this->processid) {
            $this->processid = time();
        }
    
        if (!$folderprocess && isset($config->processingfile) && $config->processingfile && !$force) {
            return;
        }
        
        
        $comp = false;
        if (!$folderprocess && !$filename && isset($config->bannerxmllocation)) {
            $filename = $config->bannerxmllocation;
            if ($config->bannerxmllocationcomp) {
                $comp = true;
            }
        }
    
        $filetime = filemtime($filename);
        
        if (!$folderprocess && isset($config->xmlfiletime) && ($config->xmlfiletime >= $filetime) && !$force) {
            return;
        }
    
        $this->open_log_file();
    
    
    
        if ( file_exists($filename) ) {
            @set_time_limit(0);
            $starttime = time(); 
            
            set_config('processingfile', $starttime, 'enrol_lmb');
            
    
            $this->log_line('Found file '.$filename);
            $this->xmlcache = '';
    
    
    
            // The list of tags which should trigger action (even if only cache trimming)
            $listoftags = array('group', 'person', 'member', 'membership', 'comments', 'properties'); 
            $this->continueprocessing = true; // The <properties> tag is allowed to halt processing if we're demanding a matching target
            
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
                    $this->xmlcache .= $curline; // Add a line onto the XML cache
                    
                    $cperc = (int)floor(($csize/$fsize)*100);
                    /*if ($line % 1000 == 0) {
                        $this->log_line($csize.':'.$fsize.':'.$cperc);
                    }*/
                    if ($cperc > $percent) {
                        $percent = $cperc;
                        $this->log_line($percent.'% complete');
                    }
                    
                    while(true){
                      // If we've got a full tag (i.e. the most recent line has closed the tag) then process-it-and-forget-it.
                      // Must always make sure to remove tags from cache so they don't clog up our memory
                      if($tagcontents = $this->full_tag_found_in_cache('group', $curline)){
                          $this->process_group_tag($tagcontents);
                          $this->remove_tag_from_cache('group');
                      } else if ($tagcontents = $this->full_tag_found_in_cache('person', $curline)){
                          $this->process_person_tag($tagcontents);
                          $this->remove_tag_from_cache('person');
                      } else if ($tagcontents = $this->full_tag_found_in_cache('membership', $curline)){
                          $this->process_membership_tag($tagcontents);
                          $this->remove_tag_from_cache('membership');
                      } else if ($tagcontents = $this->full_tag_found_in_cache('comments', $curline)){
                          $this->remove_tag_from_cache('comments');
                      } else if ($tagcontents = $this->full_tag_found_in_cache('properties', $curline)){
                          $this->process_properties_tag($tagcontents);
                          $this->remove_tag_from_cache('properties');
                      }else{
                    break;
                  }
                } // End of while-tags-are-detected
                } // end of while loop
                fclose($fh);
            } // end of if(file_open) for first pass
            
    
            fix_course_sortorder();
            
            $timeelapsed = time() - $starttime;
            
            set_config('xmlfiletime', $filetime, 'enrol_lmb');
            set_config('processingfile', 0, 'enrol_lmb');
            
            $this->log_line('Process has completed. Time taken: '.$timeelapsed.' seconds.');
    
            if ($comp) {
                $this->process_extract_drops();
            }
    
        }else{ // end of if(file_exists)
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
    public function process_folder($folder = NULL, $term = NULL, $force = false) {
        $config = $this->get_config();
        $this->processid = time();
        
        if (isset($config->processingfolder) && $config->processingfolder && !$force) {
            return;
        }
    
        if (!$folder && isset($config->bannerxmlfolder)) {
            $folder = $config->bannerxmlfolder;
        }
        
        //Add a trailing slash if it isnt there
        if (!preg_match('{.*/$}', $folder)) {
            $folder = $folder.'/';
        }
    
        //Open the folder and look through all the files
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
        
        if (isset($config->usestatusfiles) && $config->usestatusfiles) {
            if (!$startfile) {
                return false;
            } else {
                $processfile = $folder.'processing';
                $donefile = $folder.'done';
                
                unlink($startfile);
                fclose(fopen($processfile, 'x'));
            }
        } else {
            //Folder time?
            //Find most recently modified xml time?
        }
        
        
        foreach ($files as $file) {
            print $file;
            $this->process_file($file, true, true);
        }
        
        if ($config->bannerxmlfoldercomp) {
            $this->process_extract_drops();
        }
        
        if (isset($config->usestatusfiles) && $config->usestatusfiles) {
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
        if(strpos(strtolower($latestline), '</'.strtolower($tagname).'>')===false){
            return false;
        } else if (preg_match('{(<'.$tagname.'\b.*?\>.*?</'.$tagname.'>)}is', $this->xmlcache, $matches)){
            return $matches[1];
        }else{
            return false;
        }
    }
    
    
    
    /**
     * Remove complete tag from the cached data (including all its contents) - so
     * that the cache doesn't grow to unmanageable size
     * 
     * @param string $tagname Name of tag to look for
    */
    public function remove_tag_from_cache($tagname){ // Trim the cache so we're not in danger of running out of memory.
        $this->xmlcache = trim(preg_replace('{<'.$tagname.'\b.*?\>.*?</'.$tagname.'>}is', '', $this->xmlcache, 1)); // "1" so that we replace only the FIRST instance
    }
    
    
    /**
     * public function the returns the recstatus of a tag
     * 1=Add, 2=Update, 3=Delete, as specified by IMS, and we also use 0 to indicate "unspecified".
     *
     * @param string $tagdata the tag XML data
     * @param string $tagname the name of the tag we're interested in
     * @return int recstatus number
     */
    public function get_recstatus($tagdata, $tagname){
        if(preg_match('{<'.$tagname.'\b[^>]*recstatus\s*=\s*["\'](\d)["\']}is', $tagdata, $matches)){
            return intval($matches[1]);
        }else{
            return 0; // Unspecified
        }
    }
    
    
    /**
     * Process the group tag. Sends the tag onto the appropriate tag processor 
     * 
     * @param string $tagconents The raw contents of the XML element
     * @return bool the status as returned from the tag processor
     */
    public function process_group_tag($tagcontents){
        if(preg_match('{<group>.*?<grouptype>.*?<typevalue.*?\>(.+?)</typevalue>.*?</grouptype>.*?</group>}is', $tagcontents, $matches)){
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
    public function process_course_section_tag($tagcontents){
        global $DB;
        
        $config = $this->get_config();
        
        if (!$config->parsecoursexml) {
        	$this->log_line('Course:skipping.');
        	return true;
        }
        
        unset($course);
        
        
        $status = true;
        $deleted = false;
        $logline = 'Course:';
        
        //Sourcedid Source
        if(preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)){
            $course->sourcedidsource = trim($matches[1]);
        }
        
        //Sourcedid Id
        if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)){
            $course->sourcedid = trim($matches[1]);
            
            $parts = explode('.', $course->sourcedid);
            $course->coursenumber = $parts[0];
            $course->term = $parts[1];
            
            $logline .= $course->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }
        
        /*if(preg_match('{<description>.*?<short>(.+?)</short>.*?</description>}is', $tagcontents, $matches)){
            $course->coursenumber = trim($matches[1]);
        }*/
        
        if(preg_match('{<description>.*?<long>(.+?)</long>.*?</description>}is', $tagcontents, $matches)){
            $course->longtitle = trim($matches[1]);
            
            $parts = explode('-', $course->longtitle);
            
            $course->rubric = $parts[0].'-'.$parts[1];
            $course->dept = $parts[0];
            $course->num = $parts[1];
            $course->section = $parts[2];
        }
        
        if(preg_match('{<description>.*?<full>(.+?)</full>.*?</description>}is', $tagcontents, $matches)){
            $course->fulltitle = trim($matches[1]);
        }
        
        if(preg_match('{<timeframe>.*?<begin.*?\>(.+?)</begin>.*?</timeframe>}is', $tagcontents, $matches)){
            $date = explode('-', trim($matches[1]));
            
            $course->startdate = make_timestamp($date[0],$date[1],$date[2]);
        }
        
        if(preg_match('{<timeframe>.*?<end.*?\>(.+?)</end>.*?</timeframe>}is', $tagcontents, $matches)){
            $date = explode('-', trim($matches[1]));
            
            $course->enddate = make_timestamp($date[0],$date[1],$date[2]);
        }
        
        if(preg_match('{<org>.*?<orgunit>(.+?)</orgunit>.*?</org>}is', $tagcontents, $matches)){
            $course->depttitle = trim($matches[1]);
        } else {
            $course->depttitle = '';
            $logline .= 'org/orgunit not defined:';
        }
        
        
        
        $cat->id = $this->get_category_id($course->term, $course->depttitle, $course->dept, $logline, $status);
        
    
        
        
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
        
        
        
        
        
        unset($moodlecourse);
        
        $moodlecourse->idnumber = $course->sourcedid;
        $moodlecourse->timemodified = time();
        
        if ($status && ($currentcourse = $DB->get_record('course', array('idnumber' => $moodlecourse->idnumber)))) {
            //If it's an existing course
            
            $moodlecourse->id = $currentcourse->id;
            
            if ($config->forcetitle) {
                $moodlecourse->fullname = enrol_lmb_expand_course_title($course, $config->coursetitle);
            }
            
            if ($config->forceshorttitle) {
                $moodlecourse->shortname = enrol_lmb_expand_course_title($course, $config->courseshorttitle);
            }
            
            if ($config->forcecat) {
                //cat work
                $moodlecourse->category = $cat->id;
            }
            
            $moodlecourse->startdate = $course->startdate;
            
            if ($config->forcecomputesections && $config->computesections) {
                $moodlecourseconfig = get_config('moodlecourse');
            
                $length = $course->enddate - $course->startdate;
                
                $length = ceil(($length/(24*3600)/7));
                
                if ($length < 1) {
                    $length = $moodlecourse->numsections;
                } elseif ($length > $moodlecourseconfig->maxsections) {
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
            //If it's a new course
            
            $this->create_shell_course($course->sourcedid, enrol_lmb_expand_course_title($course, $config->coursetitle),
                                        enrol_lmb_expand_course_title($course, $config->courseshorttitle), $cat->id, 
                                        $logline, $status, false, $course->startdate, $course->enddate);
            
    
            
        }
        
        
        
        if ($status) {
            //TODO make optional        
            $tmpstatus = enrol_lmb_restore_users_to_course($course->sourcedid);
            if (!$tmpstatus) {
                $logline .= 'error restoring some enrolments:';
            }
        }
        
        
        if ($status && !$deleted) {
            if (!$config->logerrors) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }
    
        return $status;
        
    
    } // End process_group_tag()
    
    
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
    public function create_shell_course($idnumber, $name, $shortname, $catid, &$logline, &$status, $meta=false, $startdate = 0, $enddate = 0) {
        $config = $this->get_config();
        global $CFG, $DB;
        $status = true;
        
        
        
        unset($moodlecourse);
        
        $moodlecourse->idnumber = $idnumber;
        $moodlecourse->timemodified = time();
        
        $moodlecourse->shortname = $shortname;
        $moodlecourse->fullname = $name;
                
        $moodlecourse->startdate = $startdate;
        
                
        if ($config->coursehidden == 'never') {
            $moodlecourse->visible = 1;
        } else if ($config->coursehidden == 'cron') {
            $curtime = time();
            $todaytime = mktime(0, 0, 0, date('n', $curtime), date('j', $curtime), date('Y', $curtime), 0);
            $time = $todaytime + ($config->cronunhidedays * 86400);
            
            if ($startdate > $time) {
                $moodlecourse->visible = 0;
            } else {
                $moodlecourse->visible = 1;
            }
        } else if ($config->coursehidden == 'always') {
            $moodlecourse->visible = 0;
        }
        
        $moodlecourse->timecreated = time();
        $moodlecourse->category = $catid;
        
        //##### Set some preferences
        if ($config->usemoodlecoursesettings && ($moodlecourseconfig = get_config('moodlecourse'))) {
            $logline .= 'Using default Moodle settings:';
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
            $moodlecourse->completionstartonenrol   = $moodlecourseconfig->completionstartonenrol;
            
        } else {
            $logline .= 'Using hard-coded settings:';
            $moodlecourse->format               = 'topics';
            $moodlecourse->numsections          = 6;
            $moodlecourse->hiddensections       = 0;
            $moodlecourse->newsitems            = 3;
            $moodlecourse->showgrades           = 1;
            $moodlecourse->showreports          = 1;
        }
        
        if ($config->computesections) {
            $length = $enddate - $startdate;
            
            $length = ceil(($length/(24*3600)/7));
            
            if ($length < 1) {
                $length = $moodlecourse->numsections;
            } elseif ($length > $moodlecourseconfig->maxsections) {
                $length = $moodlecourseconfig->maxsections;
            }
            
            $moodlecourse->numsections = $length;
        }
        
        
        if ($moodlecourse = create_course($moodlecourse)) {
            $logline .= 'created course:';
            
            
        } else {
            $logline .= 'error adding course:';
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
    }//TODO - make option?


    
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
        
        $config = $this->get_config();
        
        $cat = new Object();
        
        if (($config->cattype == 'deptcode') || ($config->cattype == 'termdeptcode')) {
            $depttitle = $deptcode;
        }
        
        switch ($config->cattype) {
            case 'term':
                $cat->id = $this->get_term_category_id($term, $logline, $status);

                break;
            
            case 'deptcode':
            case 'dept':
                //TODO2 - Removed addslashes around depttitle, check
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('dept' => $depttitle, 'cattype' => 'dept'))) {
                    $cat->id = $lmbcat->categoryid;
                } else {
                    $cat->name = $depttitle;
                    $cat->visible = 0;
                    $cat->sortorder = 999;
                    if($cat->id = $DB->insert_record('course_categories', $cat, true)){
                        $lmbcat = new Object();
                        $lmbcat->categoryid = $cat->id;
                        //$lmbcat->termsourcedid = $lmbterm->sourcedid;
                        //$lmbcat->sourcedidsource = $lmbterm->sourcedidsource;
                        $lmbcat->cattype = 'dept';
                        $lmbcat->dept = $depttitle;
                        
                        
                        $cat->context = get_context_instance(CONTEXT_COURSECAT, $cat->id);
                        mark_context_dirty($cat->context->path);
                        fix_course_sortorder();
                        if (!$DB->insert_record('enrol_lmb_categories', $lmbcat)) {
                            $logline .= "error saving category to enrol_lmb_categories:";
                        }
                        $logline .= 'Created new (hidden) category:';
                    }else{
                        $logline .= 'error creating category:';
                        $status = false;
                    }
                    
                }

                break;
            
            case 'termdeptcode':
            case 'termdept':
                //TODO2 - Removed addslashes around depttitle, check
                if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('termsourcedid' => $term, 'dept' => $depttitle, 'cattype' => 'termdept'))) {
                    $cat->id = $lmbcat->categoryid;
                } else {
                    if ($termid = $this->get_term_category_id($term, $logline, $status)) {
                        $cat->name = $depttitle;
                        $cat->visible = 0;
                        $cat->parent = $termid;
                        $cat->sortorder = 999;
                        if($cat->id = $DB->insert_record('course_categories', $cat, true)){
                            $lmbcat = new Object();
                            $lmbcat->categoryid = $cat->id;
                            $lmbcat->cattype = 'termdept';
                            $lmbcat->termsourcedid = $term;
                            $lmbcat->dept = $depttitle;
                            
                            $cat->context = get_context_instance(CONTEXT_COURSECAT, $cat->id);
                            mark_context_dirty($cat->context->path);
                            fix_course_sortorder();
                            if (!$DB->insert_record('enrol_lmb_categories', $lmbcat, true)) {
                                $logline .= "error saving category to enrol_lmb_categories:";
                            }
                            $logline .= 'Created new (hidden) category:';
                        }else{
                            $logline .= 'error creating category:';
                            $status = false;
                        }
                    }
                }
                
                                
                break;
                
                
            //case 'deptterm':
                
                
            case 'other':
                if ($config->catselect > 0) {
                    $cat->id = $config->catselect;
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
        
        $config = $this->get_config();
        
        if ($lmbcat = $DB->get_record('enrol_lmb_categories', array('termsourcedid' => $term, 'cattype' => 'term'))) {
            return $lmbcat->categoryid;
        } else {
            if ($lmbterm = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $term))) {
                $cat->name = $lmbterm->title;
                if ($config->cathidden) {
                    $cat->visible = 0;
                } else {
                    $cat->visible = 1;
                }
                
                $cat->sortorder = 999;
                if($cat->id = $DB->insert_record('course_categories', $cat, true)){
                    $lmbcat = new Object();
                    $lmbcat->categoryid = $cat->id;
                    $lmbcat->termsourcedid = $lmbterm->sourcedid;
                    $lmbcat->sourcedidsource = $lmbterm->sourcedidsource;
                    $lmbcat->cattype = 'term';
                    
                    $cat->context = get_context_instance(CONTEXT_COURSECAT, $cat->id);
                    mark_context_dirty($cat->context->path);
                    fix_course_sortorder();
                    
                    if (!$DB->insert_record('enrol_lmb_categories', $lmbcat)) {
                        $logline .= "error saving category to enrol_lmb_categories:";
                    }
                    $logline .= 'Created new (hidden) category:';
                    
                    return $cat->id;
                }else{
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
     * Processes tag for the definition of a crosslisting group.
     * Currently does nothing. See process_crosslist_membership_tag_error()
     *
     * @param string $tagcontents the xml/ims idnumber for the term
     * @return bool success or failure of the processing
     */
    public function process_crosslisted_group_tag($tagcontents) {
        $config = $this->get_config();
    
    	if ((!$config->parsexlsxml) || (!$config->parsecoursexml)) {
        	$this->log_line('Crosslist Group:skipping.');
        	return true;
        }
    
        $status = true;
        $deleted = false;
        $logline = 'Crosslist Group:';
        
        unset($xlist);
        
    
        
        //TODO remove this?
        
        
        if ($status && !$deleted) {
            if (!$config->logerrors) {
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
        global $DB;
        
        $config = $this->get_config();
        
        if ((!$config->parsexlsxml) || (!$config->processcoursexml)) {
        	$this->log_line('Crosslist Group:skipping.');
        	return true;
        }
    
        $status = true;
        $deleted = false;
        $logline = 'Crosslist membership:';
        
        $xlists = array();
        
        if(preg_match('{<sourcedid>(.+?)</sourcedid>}is', $tagcontents, $matches)){
            $source = $matches[1];
    
        
            if(preg_match('{<source>(.+?)</source>}is', $source, $matches)){
                $crosssourcedidsource = trim($matches[1]);
            }
            if ($crosssourcedidsource == 'Plugin Internal') {
                if (preg_match('{<id>(.+?)</id>}is', $source, $matches)){
                    $crosslistsourcedid = trim($matches[1]);
                    $logline .= $crosslistsourcedid.':';
                } else {
                    $crosslistsourcedid = '';
                    $internalid = enrol_lmb_create_new_crosslistid();
                    $logline .= 'generated:'.$internalid.':';
                }
                
        
            } else if (preg_match('{<id>(.+?)</id>}is', $source, $matches)){
                $crosslistsourcedid = trim($matches[1]);
                $logline .= $crosslistsourcedid.':';
                
                /*if(preg_match('{XLS..?(.{6})$}is', $xlist->crosslistsourcedid, $matches)){
                    $term = trim($matches[1]);
                }*/
            }
        }
        
        if(preg_match('{<type>(.+?)</type>}is', $tagcontents, $matches)){
            $type = $matches[1];
        } else {
            $type = $config->xlstype;
        }
        
        if(preg_match_all('{<member>(.+?)</member>}is', $tagcontents, $matches)){
    
            $members = $matches[1];
            foreach ($members as $member) {
                unset($xlist);
    
                
                if(preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $member, $matches)){
                    $xlist->coursesourcedidsource = trim($matches[1]);
                }
                
                if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $member, $matches)){
                    $xlist->coursesourcedid = trim($matches[1]);
                    $logline .= $xlist->coursesourcedid.':';
                }
                
                $recstatus = ($this->get_recstatus($member, 'role'));
                
                if ($recstatus == 3) {
                    $xlist->status = 0;
                } else {
                    if(preg_match('{<role.*?\>.*?<status>(.+?)</status>.*?</role>}is', $member, $matches)){
                        $xlist->status = trim($matches[1]);
                    }
                }
                
                
                $parts = explode('.', $xlist->coursesourcedid);
                $term = $parts[1];
    
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
    
        
        if ($status && $existing_xlists = $DB->get_records('enrol_lmb_crosslists', array('crosslistsourcedid' => $crosslistsourcedid))) {
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
            if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', array('status' => 1, 'coursesourcedid' => $xlist->coursesourcedid, 'type' => 'merge'))) {
                if (($oldxlist->crosslistsourcedid != $xlist->crosslistsourcedid) ) {
                    $logline .= $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid.':';
                    $errorcode = 3;
                    $errormessage = $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid;
                    $status = false;
                }
            }
            if ($status && $type == 'merge') {
                if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', array('coursesourcedid' => $xlist->coursesourcedid, 'type' => 'meta'))) {
                    $logline .= $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid.':';
                    $errormessage = $xlist->coursesourcedid.' is already in xlist '.$oldxlist->crosslistsourcedid;
                    $errorcode = 3;
                    $status = false;
                }
            }
        }
    
        if ($status) {
            $newxlists = array();
            foreach ($xlists as $xlist) {
                $xlist->type = $type;
                if ($oldxlist = $DB->get_record('enrol_lmb_crosslists', array('crosslistsourcedid' => $xlist->crosslistsourcedid, 'coursesourcedid' => $xlist->coursesourcedid))) {
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
    
    
        //course cant be merged multiple times?
        if ($status) {
            foreach ($xlists as $xlist) {
                //Setup the course
                unset($moodlecourse);
    
                $enddate = $this->get_crosslist_endtime($xlist->crosslistsourcedid);
                if ($status && !$moodlecourse->id = $DB->get_field('course', 'id', array('idnumber' => $xlist->crosslistsourcedid))) {
                    $starttime = $this->get_crosslist_starttime($xlist->crosslistsourcedid);
                    $moodlecourse->id = $this->create_shell_course($xlist->crosslistsourcedid, 'Crosslisted Course',
                                                $xlist->crosslistsourcedid, $catid, 
                                                $logline, $status, $meta, $starttime, $enddate);
                }
                
                if ($status && $moodlecourse->id) {
                    $moodlecourse->fullname = $this->expand_crosslist_title($xlist->crosslistsourcedid, $config->xlstitle, $config->xlstitlerepeat, $config->xlstitledivider);
                    $moodlecourse->shortname = $this->expand_crosslist_title($xlist->crosslistsourcedid, $config->xlsshorttitle, $config->xlsshorttitlerepeat, $config->xlsshorttitledivider);
                    
                    $moodlecourse->startdate = $this->get_crosslist_starttime($xlist->crosslistsourcedid);//TODO We should recompute the hidden status if this changes
                    
                    
                    
                    
                    if ($config->forcecomputesections && $config->computesections) {
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
                        $status = false;
                    }
                } else if ($status) {
                    $logline .= 'no course id for name update:';
                    $errormessage = 'No moodle course found';
                    $errorcode = 8;
                    $status = false;
                }
                
            
                if (($status) && $meta) {
                    if ($addid = $DB->get_field('course', 'id', array('idnumber' => $xlist->coursesourcedid))) {
                        if ($xlist->status) {
                            if (!$this->add_to_metacourse($moodlecourse->id,$addid)) {
                                $logline .= 'could not join course to meta course:';
                                $status = false;
                                $errormessage = 'Error adding course '.$xlist->coursesourcedid.' to metacourse';
                                $errorcode = 9;
                            }
                        } else {
                            if (!$this->remove_from_metacourse($moodlecourse->id,$addid)) {
                                $logline .= 'could not unjoin course from meta course:';
                                $status = false;
                                $errormessage = 'Error removing course '.$xlist->coursesourcedid.' from metacourse';
                                $errorcode = 9;
                            }
                        }
                    }
            
                }
    
                if ($status && !$meta && isset($xlist->newrecord) && $xlist->newrecord) {
                    if (!$modinfo = $DB->get_field('course', 'modinfo', array('idnumber' => $xlist->coursesourcedid))) {
                        $modinfo = '';
                    }
                    
                    $modinfo = unserialize($modinfo);

                    if (count($modinfo) <= 1) {
                        enrol_lmb_drop_all_users($xlist->coursesourcedid, 2, TRUE);
                    }
    
                    $status = $status && enrol_lmb_drop_all_users($xlist->coursesourcedid, 1, TRUE);
                    if (!$status) {
                        $errormessage = 'Error removing students from course '.$xlist->coursesourcedid;
                        $errorcode = 10;
                    }
                    
                    $status = $status && enrol_lmb_restore_users_to_course($xlist->coursesourcedid);
                    if (!$status) {
                        $errormessage .= 'Error adding students to course '.$xlist->crosslistsourcedid;
                        $errorcode = 10;
                    }
                }
                
                //Uncrosslist a course
                if ($status && !$meta && ($xlist->status == 0)) {
                    $logline .= 'removing from crosslist:';
                    //Restore users to individual course
                    enrol_lmb_restore_users_to_course($xlist->coursesourcedid);
                    
                    //Drop users from crosslist
                    enrol_lmb_drop_crosslist_users($xlist);
                    
                    $droppedUsers = true;
                }
                
            }//Close foreach loop
        }//Close status check
    
        if (isset($droppedUsers) && $droppedUsers) {
            if ($allxlists = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $crosslistsourcedid))) {
                foreach ($allxlists as $xlist) {
                    enrol_lmb_restore_users_to_course($xlist->coursesourcedid);
                }
            }
        }
        
        if ($status && !$deleted) {
            $errormessage = $crosslistsourcedid;
            if (!$config->logerrors) {
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
        
        if (($enrols = $DB->get_record('enrol', array('courseid' => $parentid, 'customint1' => $childid, 'enrol' => 'meta'), '*', IGNORE_MULTIPLE)) && (count($enrols) > 0)) {
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
        
        $enrol = $DB->get_record('enrol', array('courseid' => $parentid, 'customint1' => $childid, 'enrol' => 'meta'), '*', IGNORE_MULTIPLE);
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
        
        if ($courseIds = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $courses = array();
            
            foreach ($courseIds as $courseId) {
            
                if ($course = $DB->get_record('enrol_lmb_courses', array('sourcedid' => $courseId->coursesourcedid))) {
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
        
        if ($courseIds = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $enddates = array();
            
            foreach ($courseIds as $courseId) {
                if ($enddate = $DB->get_field('enrol_lmb_courses', 'enddate', array('sourcedid' => $courseId->coursesourcedid))) {
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
        
        if ($courseIds = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'crosslistsourcedid' => $idnumber))) {
            $startdates = array();
            
            foreach ($courseIds as $courseId) {
                if ($startdate = $DB->get_field('enrol_lmb_courses', 'startdate', array('sourcedid' => $courseId->coursesourcedid))) {
                    array_push($startdates, $startdate);
                    
                }
            }
            
            if ($startdate = $startdates[0]) {
                sort($startdates);
                
                return $startdates[0];
            }
        }
    }
    
    
    
    /**
     * Processes a given person tag, updating or creating a moodle user as
     * needed.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success of failure of processing the tag
     */
    public function process_person_tag($tagcontents){
        global $CFG, $DB;
        $config = $this->get_config();
        
        if (!$config->parsepersonxml) {
        	$this->log_line('Person:skipping.');
        	return true;
        }
        
        $status = true;
        $deleted = false;
        $logline = 'Person:';
        
        //Sourcedid Source
        if(preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)){
            $person->sourcedidsource = trim($matches[1]);
        }
        
        //Sourcedid Id
        if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)){
            $person->sourcedid = trim($matches[1]);
            $logline .= $person->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }
        
        //Full Name
        if(preg_match('{<name>.*?<fn>(.+?)</fn>.*?</name>}is', $tagcontents, $matches)){
            $person->fullname = trim($matches[1]);
        }
        
        //Full Name
        if(preg_match('{<name>.*?<nickname>(.+?)</nickname>.*?</name>}is', $tagcontents, $matches)){
            $person->nickname = trim($matches[1]);
        }
        
        //Given Name
        if(preg_match('{<name>.*?<n>.*?<given>(.+?)</given>.*?</n>.*?</name>}is', $tagcontents, $matches)){
            $person->givenname = trim($matches[1]);
        }
        
        //Family Name
        if(preg_match('{<name>.*?<n>.*?<family>(.+?)</family>.*?</n>.*?</name>}is', $tagcontents, $matches)){
            $person->familyname = trim($matches[1]);
        }
        
        //Email
        if(preg_match('{<email>(.*?)</email>}is', $tagcontents, $matches)){
            $person->email = trim($matches[1]);
        }
        
        //Telephone
        if(preg_match('{<tel.*?\>(.+?)</tel>}is', $tagcontents, $matches)){
            $person->telephone = trim($matches[1]);
        }
        
        //Street
        if(preg_match('{<adr>.*?<street>(.+?)</street>.*?</adr>}is', $tagcontents, $matches)){
            $person->street = trim($matches[1]);
        }
        
        //Locality
        if(preg_match('{<adr>.*?<locality>(.+?)</locality>.*?</adr>}is', $tagcontents, $matches)){
            $person->locality = trim($matches[1]);
        }
        
        //Region
        if(preg_match('{<adr>.*?<region>(.+?)</region>.*?</adr>}is', $tagcontents, $matches)){
            $person->region = trim($matches[1]);
        }
        
        //Country
        if(preg_match('{<adr>.*?<country>(.+?)</country>.*?</adr>}is', $tagcontents, $matches)){
            $person->country = trim($matches[1]);
        }
        
        //Academice major
        if(preg_match('{<extension>.*?<luminisperson>.*?<academicmajor>(.+?)</academicmajor>.*?</luminisperson>.*?</extension>}is', $tagcontents, $matches)){
            $person->academicmajor = trim($matches[1]);
        }
        
        $person->username = '';
        
        //Select the userid
        switch ($config->usernamesource) {
        case "email":
            $person->username = $person->email;
            break;
        
        case "emailname":
            if(isset($person->email) && preg_match('{(.+?)@.*?}is', $person->email, $matches)){
                $person->username = trim($matches[1]);
            }
            break;
        
        case "loginid":
            if(preg_match('{<userid.+?useridtype *= *"Logon ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)){
                $person->username = trim($matches[1]);
            }
            break;
        
        case "sctid":
            if(preg_match('{<userid.+?useridtype *= *"SCTID".*?\>(.+?)</userid>}is', $tagcontents, $matches)){
                $person->username = trim($matches[1]);
            }
            break;
        
        case "emailid":
            if(preg_match('{<userid.+?useridtype *= *"Email ID".*?\>(.+?)</userid>}is', $tagcontents, $matches)){
                $person->username = trim($matches[1]);
            }
            break;
        
        case "other":
            if(preg_match('{<userid.+?useridtype *= *"'.$config->useridtypeother.'".*?\>(.+?)</userid>}is', $tagcontents, $matches)){
                $person->username = trim($matches[1]);
            }
            break;
        
        default:
            $status = false;
            $logline .= 'bad enrol_lmb_usernamesource setting:';
        
        }
        
        if($config->sourcedidfallback && trim($person->username)==''){
          // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied
          // NB We don't use an "else if" because the tag may be supplied-but-empty
            $person->username = $person->sourcedid.'';
        }
        
        
        if(!isset($person->username) || (trim($person->username)=='')) {
            if (!$config->createusersemaildomain) {
                $status = false;
            }
            $logline .= 'no username:';
        }
    
        $person->auth = $config->auth;
        
        //Select the password
        switch ($config->passwordnamesource) {
        case "none":
            break;
        
        case "loginid":
            if(preg_match('{<userid.+?useridtype *= *"Logon ID".+?password *= *"(.*?)">.*?</userid>}is', $tagcontents, $matches)){
                $person->password = trim($matches[1]);
            }
            break;
        
        case "sctid":
            if(preg_match('{<userid.+?useridtype *= *"SCTID".+?password *= *"(.*?)">.*?</userid>}is', $tagcontents, $matches)){
                $person->password = trim($matches[1]);
            }
            break;
        
        case "emailid":
            if(preg_match('{<userid.+?useridtype *= *"Email ID".+?password *= *"(.*?)">.+?</userid>}is', $tagcontents, $matches)){
                $person->password = trim($matches[1]);
            }
            break;
        
        case "other":
            if(preg_match('{<userid.+?useridtype *= *"'.$config->useridtypeother.'".+?password *= *"(.*?)">.+?</userid>}is', $tagcontents, $matches)){
                $person->password = trim($matches[1]);
            }
            break;
        
        default:
            $logline .= 'bad enrol_lmb_passwordnamesource setting:';
            $status = false;
        
        }
        
    
    
        $recstatus = ($this->get_recstatus($tagcontents, 'person'));
    
    
        unset($lmbperson);
        
        if (isset($person->sourcedid)) {
            $lmbperson->sourcedid = $person->sourcedid;
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
        $lmbperson->recstatus = $recstatus;
    
        $lmbperson->timemodified = time();
        
        $lmbpersonslash = $lmbperson;
    
        //Check to see if we have an existing record for this person
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
        if ($config->createusersemaildomain) {
            
            if (isset($lmbperson->email) && ($lmbperson->email) && ($domain = explode('@', $lmbperson->email)) && (count($domain) > 1)) {
                $domain = trim($domain[1]);
    
                if (!preg_match('/^'.trim($config->createusersemaildomain).'$/', $domain)) {
                    $logline .= 'no in domain email:';
                    $emailallow = false;
                    if (!$config->donterroremail) {
                        $status = false;
                    }
                }
            } else {
                $logline .= 'no in domain email:';
                $emailallow = false;
                if (!$config->donterroremail) {
                    $status = false;
                }
            }
    
        }
        
        
        if ($config->nickname && isset($lmbperson->nickname) && !empty($lmbperson->nickname)) {
            $pos = strrpos($lmbperson->nickname, ' '.$lmbperson->familyname);
            $firstname = $lmbperson->nickname;
            
            //remove last name
            if ($pos !== FALSE) {
                $firstname = substr($firstname, 0, $pos);
            }
            
            if (empty($firstname) || ($firstname === FALSE)) {
                $firstname = $lmbperson->givenname;
            }
            
        } else {
            $firstname = $lmbperson->givenname;
        }
    
        if (isset($lmbperson->email)) {
            if ($emailallow && $lmbperson->recstatus != 3 && trim($lmbperson->username) != '') {
                unset($moodleuser);
                
                $moodleuser->idnumber = $lmbperson->sourcedid;
                
                if ($config->ignoreusernamecase) {
                    $moodleuser->username = strtolower($lmbperson->username);
                } else {
                    $moodleuser->username = $lmbperson->username;
                }
                $moodleuser->auth = $config->auth;
                $moodleuser->timemodified = time();
                
                if (($oldmoodleuser = $DB->get_record('user', array('idnumber' => $moodleuser->idnumber))) || (($config->consolidateusernames) && ($oldmoodleuser = $DB->get_record('user', array('username' => $moodleuser->username))))) {
                    //If we have an existing user in moodle (using idnumber) or 
                    //if we can match by username (but not idnumber) and the consolidation is on
                    
                    if ($config->ignoreusernamecase) {
                        $oldmoodleuser->username = strtolower($oldmoodleuser->username);
                    }
                    
                    $moodleuser->id = $oldmoodleuser->id;
                    
                    if ($config->forcename) {
                        $moodleuser->firstname = $firstname;
                        $moodleuser->lastname = $lmbperson->familyname;
                    }
                    
                    if ($config->forceemail) {
                        $moodleuser->email = $lmbperson->email;
                    }
                    
                    if ($config->includetelephone && $config->forcetelephone) {
                        $moodleuser->phone1 = $lmbperson->telephone;
                    }
                    
                    if ($config->includeaddress && $config->forceaddress) {
                        $moodleuser->address = $lmbperson->adrstreet;
                        
                        if ($config->defaultcity == 'standardxml') {
                            if ($lmbperson->locality) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = $config->standardcity;
                            }
                        } else if ($config->defaultcity == 'xml') {
                            $moodleuser->city = $lmbperson->locality;
                        } else if ($config->defaultcity == 'standard') {
                            $moodleuser->city = $config->standardcity;
                        }
                    } else {
                        $moodleuser->address = '';
                    }
                    
                    
                    if (enrol_lmb_compare_objects($moodleuser, $oldmoodleuser)) {
                        if(($oldmoodleuser->username != $moodleuser->username) && ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username)))) {
                            $logline .= 'username collision while trying to update:';
                            $status = false;
                        } else {
                            if($id = $DB->update_record('user', $moodleuser)){
                                $logline .= 'updated user:';
                            } else {
                                $logline .= 'failed to update user:';
                                $status = false;
                            }
                        }
                    } else {
                        $logline .= 'no changes to make:';
                    }
                } else {
                    //#########Set some default prefs
                    if (!isset($CFG->mnet_localhost_id)) {
                        include_once $CFG->dirroot . '/mnet/lib.php';
                        $env = new mnet_environment();
                        $env->init();
                        unset($env);
                    }
                    $moodleuser->mnethostid = $CFG->mnet_localhost_id;
                    $moodleuser->confirmed = 1;
                    
                    //The user appears to not exist at all yet
                    $moodleuser->firstname = $firstname;
                    $moodleuser->lastname = $lmbperson->familyname;
                    $moodleuser->email = $lmbperson->email;
                    $moodleuser->auth = $config->auth;
                    if ($config->includetelephone) {
                        $moodleuser->phone1 = $lmbperson->telephone;
                    }
                    
                    if ($config->includeaddress) {
                        if (isset ($lmbperson->adrstreet)) {
                            $moodleuser->address = $lmbperson->adrstreet;
                        } else {
                            $moodleuser->address = '';
                        }
                        
                        
                        if ($config->defaultcity == 'standardxml') {
                            if ($lmbperson->locality) {
                                $moodleuser->city = $lmbperson->locality;
                            } else {
                                $moodleuser->city = $config->standardcity;
                            }
                        } else if ($config->defaultcity == 'xml') {
                            $moodleuser->city = $lmbperson->locality;
                        } else if ($config->defaultcity == 'standard') {
                            $moodleuser->city = $config->standardcity;
                        }
            
                    } else {
                        $moodleuser->address = '';
                    }
                    
                    $moodleuser->country = $CFG->country;
                    
                    if ($config->createnewusers) {
                        if ($collisionid = $DB->get_field('user', 'id', array('username' => $moodleuser->username))) {
                            $logline .= 'username collision, could not create user:';
                            $status = false;
                        } else {
                            if($id = $DB->insert_record('user', $moodleuser, true)){
                                $logline .= "created new user:";
                                $moodleuser->id = $id;
                                
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
                
                
                
                if ($config->passwordnamesource != 'none') {
                    if ($user = $DB->get_record('user', array('id' => $moodleuser->id))) {
                        $userauth = get_auth_plugin($user->auth);
                        if ($userauth->can_change_password() && (!$userauth->change_password_url())) {
                            //TODO2 if (!$userauth->user_update_password(addslashes_recursive($user), $person->password)) {
                            //TODO2 - what happens if password is blank
                            if (isset($person->password) && ($person->password != '')) {
                                if (!$userauth->user_update_password($user, $person->password)) {
                                    $logline .= 'error setting password:';
                                    $status = false;
                                }
                            }
                        }
                    }
                }
                
            } else if (($config->imsdeleteusers) && ($lmbperson->recstatus == 3) && ($moodleuser = $DB->get_record('user', array('idnumber' => $lmbperson->idnumber)))) {
                $deleteuser = new object();
                $deleteuser->id           = $moodleuser->id;
                $deleteuser->deleted      = 1;
                //TODO2 $deleteuser->username     = addslashes("$moodleuser->email.".time());  // Remember it just in case
                $deleteuser->username     = "$moodleuser->email.".time();  // Remember it just in case
                $deleteuser->email        = '';               // Clear this field to free it up
                $deleteuser->idnumber     = '';               // Clear this field to free it up
                $deleteuser->timemodified = time();
                
                if($id = $DB->update_record('user', $deleteuser)) {
                    $logline .= 'deleted user:';
                    $deleted = true;
                } else {
                    $logline .= 'failed to delete user:';
                    $status = false;
                }
            }
        } else {
            $logline .= 'no email address found:';
            if (!$config->donterroremail) {
                $status = false;
            }
        }
    
        if ($status && !$deleted) {
            if (!$config->logerrors) {
                $this->log_line($logline.'complete');
            }
        } else if ($deleted) {
            $this->log_line($logline.'complete');
        } else {
            $this->log_line($logline.'error');
        }
    
        return $status;
        
    } // End process_person_tag()
    
    
    /**
     * Processes a given term tag. Basically just inserting the info
     * in a lmb internal table for future use.
     *
     * @param string $tagconents The raw contents of the XML element
     * @return bool success of failure of processing the tag
     */
    public function process_term_tag($tagcontents){
        global $DB;
        
        $config = $this->get_config();
        
        $status = true;
        $logline = 'Term:';
        
        //Sourcedid Source
        if(preg_match('{<sourcedid>.*?<source>(.+?)</source>.*?</sourcedid>}is', $tagcontents, $matches)){
            $term->sourcedidsource = trim($matches[1]);
        }
        
        if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)){
            $term->sourcedid = trim($matches[1]);
            $logline .= $term->sourcedid.':';
        } else {
            $this->log_line($logline."sourcedid not found!");
            return false;
        }
        
        if(preg_match('{<description>.*?<long>(.+?)</long>.*?</description>}is', $tagcontents, $matches)){
            $term->title = trim($matches[1]);
        } else {
            $logline .= "title not found:";
            $status = false;
        }
        
        
        if(preg_match('{<timeframe>.*?<begin.*?\>(.+?)</begin>.*?</timeframe>}is', $tagcontents, $matches)){
            $date = explode('-', trim($matches[1]));
            
            $term->starttime = make_timestamp($date[0],$date[1],$date[2]);
        }
        
        if(preg_match('{<timeframe>.*?<end.*?\>(.+?)</end>.*?</timeframe>}is', $tagcontents, $matches)){
            $date = explode('-', trim($matches[1]));
            
            $term->endtime = make_timestamp($date[0],$date[1],$date[2]);
        }
        
        $term->timemodified = time();
        
        
        if ($oldterm = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $term->sourcedid))) {
            $term->id = $oldterm->id;
            
            if($id = $DB->update_record('enrol_lmb_terms', $term)){
                $logline .= 'updated term:';
            } else {
                $logline .= 'failed to update term:';
                $status = false;
            }
        } else {
            if($id = $DB->insert_record('enrol_lmb_terms', $term, true)){
                $logline .= 'create term:';
                $term->id = $id;
            } else {
                $logline .= 'create to update term:';
                $status = false;
            }
        }
        
        
        
        
        
        if ($status) {
            if (!$config->logerrors) {
                $this->log_line($logline.'complete');
            }
        } else {
            $this->log_line($logline.'error');
        }
        
        return $status;
        
    } // End process_term_tag()
    
    
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
        
        if(preg_match('{<sourcedid>.*?<id>XLS(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)){
            return $this->process_crosslist_membership_tag_error($tagcontents, $errorcode, $errormessage);
        } else if(preg_match('{<sourcedid>.*?<source>Plugin Internal</source>.*?</sourcedid>}is', $tagcontents, $matches)){
            return $this->process_crosslist_membership_tag_error($tagcontents, $errorcode, $errormessage);
        } else {
            return $this->process_person_membership_tag($tagcontents);
        }
        
    } // End process_membership_tag()
    
    
    /**
     * Process a tag that is a membership of a person 
     * 
     * @param string $tagconents The raw contents of the XML element
     * @return bool success or failure of the processing
     */
    public function process_person_membership_tag($tagcontents) {
        global $DB;
        
        $config = $this->get_config();
        
        if ((!$config->parsepersonxml) || (!$config->parsecoursexml) || (!$config->parsepersonxml)) {
        	$this->log_line('Enrolment:skipping.');
        	return true;
        }
        
        $status = true;
        $logline = 'Enrolment:';
        unset($enrolment);
        
        if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)){
            $enrolment->coursesourcedid = trim($matches[1]);
            
            if(preg_match('{.....\.(.+?)$}is', $enrolment->coursesourcedid, $matches)){
                $enrolment->term = trim($matches[1]);
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
        
        if(preg_match('{<member>(.*?)</member>}is', $tagcontents, $membermatches)) {
        
            $member = $membermatches[1];
    
            if(preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $member, $matches)){
                $enrolment->personsourcedid = trim($matches[1]);
                $logline .= 'person id '.$enrolment->personsourcedid.':';
            } else {
                $logline .= 'person id not found:';
                $status = false;
            }
            
            if(preg_match('{<role.*?roletype *= *"(.*?)".*?\>.*?</role>}is', $member, $matches)){
                $enrolment->role = (int)trim($matches[1]);
            } else {
                $logline .= 'person role not found:';
                $status = false;
            }
            
    
    
            if(preg_match('{<interimresult.*?\>.*?<mode>(.+?)</mode>.*?</interimresult>}is', $member, $matches)){
                $enrolment->midtermgrademode = trim($matches[1]);
            }
            
            if(preg_match('{<finalresult.*?\>.*?<mode>(.+?)</mode>.*?</finalresult>}is', $member, $matches)){
                $enrolment->finalgrademode = trim($matches[1]);
            }
            
            if(preg_match('{<extension.*?\>.*?<gradable>(.+?)</gradable>.*?</extension>}is', $member, $matches)){
                $enrolment->gradable = (int)trim($matches[1]);
            } else {
                //Per e-learn docs, if ommited, then membership is gradable
                if (isset($enrolment->midtermgrademode) || isset($enrolment->finalgrademode)) {
                    $enrolment->gradable = 1;
                }
            }
            
            $recstatus = ($this->get_recstatus($member, 'role'));
            if($recstatus==3){
                $enrolment->status = 0;
            }
            
            if(preg_match('{<role.*?\>.*?<status>(.+?)</status>.*?</role>}is', $member, $matches)){
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
            if ($oldenrolment = $DB->get_record('enrol_lmb_enrolments', array('coursesourcedid' => $enrolment->coursesourcedid, 'personsourcedid' => $enrolment->personsourcedid))) {
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
        
        $status = $status && $this->process_enrolment_log($enrolment, $logline, $config);
        
        
        if ($status) {        
            if (!$config->logerrors) {
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
    public function process_properties_tag($tagcontents){
        
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
    public function log_line($string){
        //$config = $this->get_config();
        
        $silent = get_config('enrol_lmb', 'silent');
        
        $message = '';
        
        if ($this->islmb) {
            $message = 'LMB Message:';
        }
        
        if (!$silent) {
            mtrace($string);
        }
        
        if(isset($this->logfp) && $this->logfp) {
            fwrite($this->logfp, date('Y-m-d\TH:i:s - ') . $message . $string . "\n");
        }
    }
    
    
    /**
     * Open the lof file and store the pointer in this object.
     */
    public function open_log_file () {
        $config = $this->get_config();
    
    
        $this->logfp = false; // File pointer for writing log data to
        if(!empty($config->logtolocation)) {
            $this->logfp = fopen($config->logtolocation, 'a');
        }
    }
    
    
    /**
     * Execute the check of the last time a message was received 
     * on the liveimport interface (luminis message broker). If
     * last message time falls outside parameters, then email
     * error messages.
     */
    public function check_last_luminis_event(){
        $config = $this->get_config();
        global $CFG;
        
        $this->log_line("Checking LMB last message sent time.");
        
    
    
        if(isset($config->lastlmbmessagetime) && $config->lastlmbmessagetime) {
            $lastTime = $config->lastlmbmessagetime;
        
            $startTime = make_timestamp(date("Y"),date("m"),date("d"),( $config->startbiztimehr ? $config->startbiztimehr : 9),$config->startbiztimemin);
            $endTime = make_timestamp(date("Y"),date("m"),date("d"),( $config->endbiztimehr ? $config->endbiztimehr : 9),$config->endbiztimemin);
        
            $currentTime = time();
            
            $diffTime = $currentTime - $lastTime;
    
            //If it's mon-fri, and inside of biz hours
            if((date("w") > 0) && (date("w") < 6) && ($currentTime > $startTime && $currentTime < $endTime)) {
                //if longer then grace
                if(($config->bizgrace) && ($diffTime > ($config->bizgrace * 60))) {
                    $this->log_line('Last luminis message received '.floor($diffTime/60).' minutes ago.');
                    $emails = explode(',', $config->emails);
                    
                    foreach ($emails as $email) {
                        $this->email_luminis_error(floor($diffTime/60), trim($email));
                        //print trim($email);
                    }
                }
            } else {
                //if longer then grace
                if(($config->nonbizgrace) && ($diffTime > ($config->nonbizgrace * 60))) {
                    $this->log_line('Last luminis message received '.floor($diffTime/60).' minutes ago.');
                    
                    $emails = explode(',', $config->emails);
                    
                    foreach ($emails as $email) {
                        $this->email_luminis_error(floor($diffTime/60), trim($email));
                        //print trim($email);
                    }
                }
            }
        }
        
    }
    
    
    /**
     * Email a luminis downtime message to the provided email
     * 
     * @param int $minutes the number of minutes since the last message was received
     * @param string $emailAddress the email address to send the message to
     * @return bool success of failure of the email send
     */
    public function email_luminis_error($minutes, $emailAddress) {
        $config = $this->get_config();
        global $CFG, $FULLME;
        include_once($CFG->libdir .'/phpmailer/class.phpmailer.php');
        
        $messagetext = get_string('nomessagefull', 'enrol_lmb').$minutes.get_string('minutes');
        $subject = get_string("nomessage", "enrol_lmb");
    
        $mail = new phpmailer;
        
        $mail->Version = 'Moodle '. $CFG->version;           // mailer version
        $mail->PluginDir = $CFG->libdir .'/phpmailer/';      // plugin directory (eg smtp plugin)
    
    
        if (current_language() != 'en') {
            $mail->CharSet = get_string('thischarset');
        }
    
        if ($CFG->smtphosts == 'qmail') {
            $mail->IsQmail();                              // use Qmail system
    
        } else if (empty($CFG->smtphosts)) {
            $mail->IsMail();                               // use PHP mail() = sendmail
    
        } else {
            $mail->IsSMTP();                               // use SMTP directly
            if ($CFG->debug > 7) {
                echo '<pre>' . "\n";
                $mail->SMTPDebug = true;
            }
            $mail->Host = $CFG->smtphosts;               // specify main and backup servers
    
            if ($CFG->smtpuser) {                          // Use SMTP authentication
                $mail->SMTPAuth = true;
                $mail->Username = $CFG->smtpuser;
                $mail->Password = $CFG->smtppass;
            }
        }
    
        
        $adminuser = get_admin();
    
        // make up an email address for handling bounces
        if (!empty($CFG->handlebounces)) {
            $modargs = 'B'.base64_encode(pack('V',$user->id)).substr(md5($user->email),0,16);
            $mail->Sender = generate_email_processing_address(0,$modargs);
        }
        else {
            $mail->Sender   = $adminuser->email;
        }
    
        if (is_string($from)) { // So we can pass whatever we want if there is need
            $mail->From     = $CFG->noreplyaddress;
            $mail->FromName = $from;
        } else if ($usetrueaddress and $from->maildisplay) {
            $mail->From     = $from->email;
            $mail->FromName = fullname($from);
        } else {
            $mail->From     = $CFG->noreplyaddress;
            $mail->FromName = fullname($from);
            if (empty($replyto)) {
                $mail->AddReplyTo($CFG->noreplyaddress,get_string('noreplyname'));
            }
        }
    
        if (!empty($replyto)) {
            $mail->AddReplyTo($replyto,$replytoname);
        }
    
        $mail->Subject = $subject;
    
        $mail->AddAddress($emailAddress, "" );
        
            $mail->WordWrap = 79;                               // set word wrap
    
        if (!empty($from->customheaders)) {                 // Add custom headers
            if (is_array($from->customheaders)) {
                foreach ($from->customheaders as $customheader) {
                    $mail->AddCustomHeader($customheader);
                }
            } else {
                $mail->AddCustomHeader($from->customheaders);
            }
        }
    
        if (!empty($from->priority)) {
            $mail->Priority = $from->priority;
        }
        
        $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";
        
        if ($mail->Send()) {
            set_send_count($user);
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
    public function process_extract_drops()
    {
        global $CFG, $DB;
        $config = $this->get_config();
        $status = true;
        
        foreach ($this->terms as $termid => $count) {
            $this->log_line('Processing drops for term '.$termid);

            $sqlparams = array('term' => $termid, 'status' => 1);
            $termcnt = $DB->count_records('enrol_lmb_enrolments', $sqlparams);
            
            
            $sqlparams = array('processid' => $this->processid, 'termid' => $termid, 'status' => 1);
            $dropcnt = $DB->count_records_select('enrol_lmb_enrolments', 'extractstatus < :processid AND term = :termid AND status = :status', $sqlparams);
            
                        
            $percent = (int)ceil(($dropcnt/$termcnt)*100);
            $this->log_line('Dropping '.$dropcnt.' out of '.$termcnt.' ('.$percent.'%) enrolments.');
            
            if ($percent > $config->dropprecentlimit) {
                $this->log_line('Exceeds the drop percent limit, skipping term.');
                continue;
            }
                        
            $sqlparams = array('extractstatus' => $this->processid, 'termid' => $termid);
            
            
            if ($enrols = $DB->get_records_select('enrol_lmb_enrolments', 'extractstatus < :extractstatus AND term = :termid', $sqlparams, 'coursesourcedid ASC')) {
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
                        $this->log_line($percent.'% complete');
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
                            if (!$config->logerrors) {
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
     * Returns the config object. Uses a cache so that the object only has to 
     * be loaded from the database once.
     *
     * @param bool $flush if true, flush and reload the cache from the db
     * @return object an object that contains all of the plugin config options
     */
    public function get_config($flush = false) {
        if ($flush || (!isset($configcache) || !$configcache)) {
            if (isset($configcache)) {
                unset($configcache);
            }
            $configcache = enrol_lmb_get_config();
        }
    
        
        return $configcache;
    }
    
    
    /**
     * Processes an enrol object, executing the associated assign or
     * unassign and update the lmb entry for success or failure
     * 
     * @param object $enrol an enrol object representing a record in enrol_lmb_enrolments
     * @param string $logline passed logline object to append log entries to
     * @param object $config plugin config object passed optionally passes for caching speed
     * @return bool success or failure of the role assignments
     */ //TODO2
    public function process_enrolment_log($enrol, &$logline, $config=NULL) {
        global $DB;
        $status = true;
        
        if (!$config) {
            $config = $this->get_config();
        }
        
        $newcoursedid = enrol_lmb_get_course_id($enrol->coursesourcedid);
        
        if ($config->xlsmergegroups && $xlist = $DB->get_record('enrol_lmb_crosslists', array('status' => 1, 'coursesourcedid' => $enrol->coursesourcedid))) {
            $groupid = enrol_lmb_get_crosslist_groupid($enrol->coursesourcedid, $xlist->crosslistsourcedid);
        } else {
            $groupid = false;
        }
        
        
        
        $enrolup = new object();
        $enrolup->id = $enrol->id;
        
        if ($newcoursedid) {
            if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                    if ($enrol->status) {
                        $status = $this->lmb_assign_role_log($roleid, $newcoursedid, $userid, &$logline);
                        if ($status && $groupid && !groups_is_member($groupid, $userid)) {
                            global $CFG;
                            require_once($CFG->dirroot.'/group/lib.php');
                            groups_add_member($groupid, $userid);
                            $logline .= 'added user to group:';
                        }
                    } else {
                        $status = $this->lmb_unassign_role_log($roleid, $newcoursedid, $userid, &$logline);
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
    function restore_user_enrolments($idnumber) {
        global $DB;
        $config = $this->get_config();
    
        $status = true;
        
        
    
        if ($enrols = $DB->get_records('enrol_lmb_enrolments', array('personsourcedid' => $idnumber))) {
            foreach ($enrols as $enrol) {
                $logline = '';
                $status = $this->process_enrolment_log($enrol, $logline, $config) && $status;
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
     * @return bool success or failure of the role assignment
     */
    function lmb_assign_role_log($roleid, $courseid, $userid, &$logline) {
        if (!$courseid) {
            $logline .= 'missing courseid:';
        }
        
        $instance = $this->get_instance($courseid);
        
        //TODO catch exceptions thrown
        $this->enrol_user($instance, $userid, $roleid);
        
        $logline .= 'enrolled:';
        return true;
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
    function lmb_unassign_role_log($roleid, $courseid, $userid, &$logline) {
        if (!$courseid) {
            $logline .= 'missing courseid:';
            return false;
        }
        
        $instance = $this->get_instance($courseid);
        
        //TODO catch exceptions thrown
        $this->unenrol_user($instance, $userid, $roleid);
        $logline .= 'unenrolled:';
        return true;
    }
    
    
    /**
     * Returns enrolment instance in given course.
     * @param int $courseid
     * @return object of enrol instances, or false
     */
    function get_instance($courseid) {
        global $DB;

        
        $instance = $DB->get_record('enrol',
                                array('courseid' => $courseid, 'enrol' => 'lmb'));
                                
        return $instance;
    }

} // end of class

