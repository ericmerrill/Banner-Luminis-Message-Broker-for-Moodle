<?php
/**
 * This is a support library for the enrol-lmb module and its tools
 *
 * @author Eric Merrill (merrill@oakland.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package enrol-lmb
 * Based on enrol_imsenterprise from Dan Stowell.
 */





//function enrol_lmb_process_extract_drops($termid)

/**
 * Returns the id of the moodle role for a provided ims/xml role
 * 
 * @param numeric $imsrole the ims/xml role number
 * @param object  $config the default value to return if nothing is found
 * @return int
 */
function enrol_lmb_get_roleid($imsrole, $config=NULL) {
    if (!$config) {
        $config = enrol_lmb_get_config();
    }
    
    $imsrole = intval($imsrole);
    
    $imsrole = sprintf("%02d",$imsrole);
    
    $imsrole = 'imsrolemap'.$imsrole;
    
    if (isset($config->$imsrole)) {
        return $config->$imsrole;
    }
    
    return false;
}



/**
 * Assigns a moodle role to a user in the provided context
 * 
 * @param int $roleid id of the moodle role to assign
 * @param int $rolecontextid id of the context to assign
 * @param int $userid id of the moodle user
 * @param string $logline passed logline object to append log entries to
 * @return bool success or failure of the role assignment
 */ //TODO2 remove
function enrol_lmb_assign_role_log($roleid, $rolecontextid, $userid, &$logline) {
    if (!$rolecontextid) {
        $logline .= 'missing rolecontextid:';
    }
    
    if (role_assign($roleid, $userid, $rolecontextid, 'enrol_lmb')) {
        $logline .= 'enrolled:';
        return true;
    }
    
    $logline .= 'error enrolling:';
    return false;
}


/**
 * Used to call enrol_lmb_unassign_role_log() without passing a 
 * logline variable. See enrol_lmb_unassign_role_log()
 * 
 * @param int $roleid id of the moodle role to assign
 * @param int $rolecontextid id of the context to assign
 * @param int $userid id of the moodle user
 * @param object $config passed plugin config object
 * @return bool success or failure of the role assignment
 */ //TODO2 remove
function enrol_lmb_unassign_role($roleid, $rolecontextid, $userid) {
    $logline = '';
    
    $status = enrol_lmb_unassign_role_log($roleid, $rolecontextid, $userid, &$logline);
    
    unset($logline);
    
    return $status;
}


/**
 * Unassigns a moodle role to a user in the provided context
 * 
 * @param int $roleid id of the moodle role to assign
 * @param int $rolecontextid id of the context to assign
 * @param int $userid id of the moodle user
 * @param string $logline passed logline object to append log entries to
 * @return bool success or failure of the role assignment
 */ //TODO2 remove
function enrol_lmb_unassign_role_log($roleid, $rolecontextid, $userid, &$logline) {
    if (!$rolecontextid) {
        $logline .= 'missing rolecontextid:';
        return false;
    }
    
    if (role_unassign($roleid, $userid, $rolecontextid, 'enrol_lmb')) {
        $logline .= 'unenrolled:';
        return true;
    }
    
    $logline .= 'error unenrolling:';
    return false;
}


/**
 * Returns the course level context for the provided idnumber
 * 
 * @param string $idnumber the ims/xml course id to find
 * @param bool $original if true, ignore crosslists when finding course
 * @return int|bool the context id for the given idnumber, false if not found
 */
function enrol_lmb_get_course_contextid($idnumber, $original = false) {
    if ($courseid = enrol_lmb_get_course_id($idnumber, $original)) {
        if ($rolecontext = get_context_instance(CONTEXT_COURSE, $courseid)) {
            return $rolecontext->id;
        }
    }

    return false;
}





function enrol_lmb_get_crosslist_groupid($coursesourcedid, $crosslistsourcedid = null) {
    global $DB;
    if ($crosslistsourcedid) {
        if (!$crosslist = $DB->get_record('lmb_crosslist', array('coursesourcedid' => $coursesourcedid, 'crosslistsourcedid' => $crosslistsourcedid))) {
            return false;
        }
    } else {
        if (!$crosslist = $DB->get_record('lmb_crosslist', array('coursesourcedid' => $coursesourcedid))) {
            return false;
        }
    }
    
    if ($crosslist->crosslistgroupid) {
        return $crosslist->crosslistgroupid;
    }
    return enrol_lmb_create_crosslist_group($crosslist);
}


function enrol_lmb_create_crosslist_group($lmbcrosslist) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/group/lib.php');

    
    if ($lmbcrosslist->crosslistgroupid) {
        return $crosslist->crosslistgroupid;
    }
    
    if (!$mdlcourse = $DB->get_record('course', array('idnumber' => $lmbcrosslist->crosslistsourcedid))) {
        return false;
    }
    
    if (!$lmbcourse = $DB->get_record('lmb_courses', array('sourcedid' => $lmbcrosslist->coursesourcedid))) {
        return false;
    }
    
    $config = enrol_lmb_get_config();
    
        
    $data = new Object();
    $data->courseid = $mdlcourse->id;
    $data->name = enrol_lmb_expand_course_title($lmbcourse, $config->coursetitle);
    $data->timecreated = time();
    $data->timemodified = time();
    if (!$groupid = groups_create_group($data)) {
        return false;
    }
    
    $crossup = new Object();
    $crossup->id = $lmbcrosslist->id;
    $crossup->crosslistgroupid = $groupid;
    $DB->update_record('lmb_crosslist', $crossup);
    
    return $groupid;
    
}

/**
 * Creates a title from the given lmb_course object and title definition
 * 
 * @param object $lmbcourse a object that is a row from lmb_course table
 * @param string $titledef the title definition
 * @return string the determined string
 */
function enrol_lmb_expand_course_title($lmbcourse, $titledef) {
    global $DB;
    
    $title = str_replace('[SOURCEDID]', $lmbcourse->sourcedid, $titledef);
    $title = str_replace('[CRN]', $lmbcourse->coursenumber, $title);
    $title = str_replace('[TERM]', $lmbcourse->term, $title);
    if ($lmbterm = $DB->get_record('lmb_terms', array('sourcedid' => $lmbcourse->term))) {
        $title = str_replace('[TERMNAME]', $lmbterm->title, $title);
    } else {
        $title = str_replace('[TERMNAME]', $lmbcourse->term, $title);
    }
    $title = str_replace('[LONG]', $lmbcourse->longtitle, $title);
    $title = str_replace('[FULL]', $lmbcourse->fulltitle, $title);
    $title = str_replace('[RUBRIC]', $lmbcourse->rubric, $title);
    $title = str_replace('[DEPT]', $lmbcourse->dept, $title);
    $title = str_replace('[NUM]', $lmbcourse->num, $title);
    $title = str_replace('[SECTION]', $lmbcourse->section, $title);
    
    return $title;
}




/**
 * Run all the enrolments for a given course
 * 
 * @param string $idnumber the ims/xml id of the course
 * @return bool success or failure of the role assignments
 */
function enrol_lmb_restore_users_to_course($idnumber) {
    global $DB;
    $config = enrol_lmb_get_config();
    $status = true;

    if (!class_exists('enrol_lmb_plugin')) {
        require_once('./lib.php');
    }
    
    $enrolmod = new enrol_lmb_plugin();
    

    if ($enrols = $DB->get_records('lmb_enrolments', array('status' => 1, 'coursesourcedid' => $idnumber))) {
        $rolecontext = enrol_lmb_get_course_contextid($idnumber);

        foreach ($enrols as $enrol) {
            //$status = enrol_lmb_process_enrolment($enrol, $config, $rolecontext) && $status;
            $logline = '';
            $status = $enrolmod->process_enrolment_log($enrol, $logline, $config, $rolecontext) && $status;
        }
        
        unset($enrols);
    }
    
    return $status;
}

/**
 * Drop a courses worth of users from a crosslist
 * 
 * @param object $xlist Crosslist object for the course being dropped from the crosslist
 * @return bool sucess of failure of the drops
 */
function enrol_lmb_drop_crosslist_users($xlist) {
    global $DB;
    $status = true;
    
    if ($enrols = $DB->get_records('lmb_enrolments', array('status' => 1, 'coursesourcedid' => $xlist->coursesourcedid))) {
        if ($rolecontext = enrol_lmb_get_course_contextid($xlist->crosslistsourcedid)) {
            foreach ($enrols as $enrol) {
                if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                    if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                        $status = enrol_lmb_unassign_role_log($roleid, $rolecontext, $userid) && $status;
                    }
                }
                
            }
        }
    }
    
    return $status;
}

/**
 * Drops some or all lmb enrolments from a course
 * 
 * @param string $idnumber the ims/xml id of the course
 * @param int $role optional ims/xml role id to limit the drops to
 * @param bool $original if true, will ignore merged crosslisting and drop from original course
 * @return bool success or failure of the drops
 */
function enrol_lmb_drop_all_users($idnumber, $role = NULL, $original = FALSE) {
    global $DB;
    
    $status = true;
    $config = enrol_lmb_get_config();

    if ($role) {
        $enrols = $DB->get_records('lmb_enrolments', array('status' => 1, 'role' => $role, 'coursesourcedid'=> $idnumber));
    } else {
        $enrols = $DB->get_records('lmb_enrolments', array('status' => 1, 'coursesourcedid' => $idnumber));
    }

    if ($enrols) {
        if ($rolecontext = enrol_lmb_get_course_contextid($idnumber, $original)) {
        
            foreach ($enrols as $enrol) {
                $enrolup = new object();
    
                $enrolup->succeeded = 0;
                
                if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                    if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                        $status = enrol_lmb_unassign_role($roleid, $rolecontext, $userid) && $status;
                    } else {
                        $status = false;
                    }
                }
                
                $enrolup->id = $enrol->id;
                $DB->update_record('lmb_enrolments', $enrolup);
                unset($enrolup);
            }
        }
    }

    return $status;
}


/**
 * Run enrol_lmb_force_course_to_db against all courses in a term
 * 
 * @param string $termid the ims/xml id of the term
 * @param bool $print if true, print course info
 * @param bool $printverbose if true print status of each enrol/unenrol
 * @return bool success or failure of the enrolments
 */
function enrol_lmb_force_all_courses($termid, $print = false, $printverbose = false) {
    global $DB;
    $status = true;
    
    
    $courses = $DB->get_records('lmb_courses', array('term' => $termid));
    
    $count = count($courses);
    $i = 1;
    
    foreach ($courses as $course) {
        $line = $i.' of '.$count.':'.$course->sourcedid.':';
        
        $coursestatus = enrol_lmb_force_course_to_db($course->sourcedid, $printverbose);
        //$coursestatus = true;
        if ($coursestatus) {
            $line .= "suc<br>\n";
        } else {
            $line .= "fail<br>\n";
            $status = false;
        }
        
        if ($print) {
            print $line;
        }
        
        $i++;
    }
    
    return $status;
}


/**
 * For a given course id number, run all enrol and unenrol records in
 * the local lmb database
 * 
 * @param string $idnumber the ims/xml id of the course
 * @param bool $print if true, print enrolment info
 * @return bool success or failure of the enrolments
 */
function enrol_lmb_force_course_to_db($idnumber,$print = false) {
    global $DB;
    $status = true;

    if ($enrols = $DB->get_records('lmb_enrolments', array('coursesourcedid' => $idnumber))) {
        $rolecontext = enrol_lmb_get_course_contextid($idnumber);
        
        if ($print) {
            print $idnumber."<br>\n";
        }
        
        if (!class_exists('enrol_lmb_plugin')) {
            require_once('./lib.php');
        }
        
        $enrolmod = new enrol_lmb_plugin();
        
        
        foreach ($enrols as $enrol) {
            $logline = $enrol->personsourcedid.':';
            
            //$status = enrol_lmb_process_enrolment_log($enrol, $logline, $config, $rolecontext) && $status;
            $status = $enrolmod->process_enrolment_log($enrol, $logline, $config, $rolecontext) && $status;
            
            $logline .= "<br>\n";
            if ($print) {
                print $logline;
            }
            unset($logline);
        }
    } else {
        if ($print) {
            print 'No enrolments for this course'."<br>\n";
        }
    }
    
    
    return $status;
}


/**
 * Return the effective moodle course id for a given course id number
 * 
 * @param string $idnumber the ims/xml id of the course
 * @param bool $original if true ignore any crosslisting and find the original course
 * @return int|bool moodle course id or false if not found
 */
function enrol_lmb_get_course_id($idnumber, $original = FALSE) {
    global $DB;
    
    $newidnumber = $idnumber;
    
    if (!$original && $xlist = $DB->get_record('lmb_crosslist', array('status' => 1, 'coursesourcedid' => $idnumber))) {
        if ($xlist->type == 'merge') {
            $newidnumber = $xlist->crosslistsourcedid;
        }
    }

    
    return $DB->get_field('course', 'id', array('idnumber' => $newidnumber));
}

function enrol_lmb_get_course_ids($idnumber, $original = FALSE) {
    global $DB;
    $out = array();
    
    if (!$original && $xlists = $DB->get_records('lmb_crosslist', array('status' => 1, 'coursesourcedid' => $idnumber))) {
        foreach ($xlists as $xlist) {
            if ($xlist->type == 'merge') {
                $courseid = $DB->get_field('course', 'id', array('idnumber' => $xlist->crosslistsourcedid));
                if ($courseid) {
                    $out[] = $courseid;
                }
            }
        }
    } else {
        $out[] = $DB->get_field('course', 'id', array('idnumber' => $idnumber));
    }
    
    return $out;
}


/**
 * Compare two objects and see if they are different, ignoring 
 * the object keys and timemodified field.
 * 
 * @param object $new first object
 * @param object $old second object to compare against the first
 * @return bool true if they are different, false if they are not
 */
function enrol_lmb_compare_objects($new, $old) {    
    if (!$new || !$old) {
        return false;
    }

    foreach ($new as $key => $val) {
        if ($key != 'timemodified') {
            if (!isset($old->$key) || ($new->$key != $old->$key)) {
                return true;
            }
        }
    }
    
    
    return false;

}


/**
 * For a given person id number, run all enrol and unenrol records in
 * the local lmb database
 * 
 * @param string $idnumber the ims/xml id of a person
 * @return bool success or failure of the enrolments
 */
function enrol_lmb_restore_user_enrolments($idnumber) {
    global $DB;
    $config = enrol_lmb_get_config();

    $status = true;
    
    if (!class_exists('enrol_lmb_plugin')) {
        require_once('./lib.php');
    }
    
    $enrolmod = new enrol_lmb_plugin();
    

    if ($enrols = $DB->get_records('lmb_enrolments', array('personsourcedid' => $idnumber))) {
        foreach ($enrols as $enrol) {
            //$status = enrol_lmb_process_enrolment($enrol, $config) && $status;
            $logline = '';
            $status = $enrolmod->process_enrolment_log($enrol, $logline, $config) && $status;
        }

    }
    
    return $status;
}


/**
 * For a given term, retry all unsuccessful enrolments
 * 
 * @param string $termid the ims/xml id of the term
 * @return bool success or failure of the enrolments
 */
function enrol_lmb_reset_all_term_enrolments($termid) {
    global $DB;

    $status = true;
    $sqlparams = array('termid' => $termid, 'succeeded' => 0);
    $sql = "SELECT personsourcedid FROM {lmb_enrolments} WHERE term = :termid AND succeeded = :succeeded GROUP BY personsourcedid";

    if ($enrols = $DB->get_records_sql($sql, $sqlparams)) {
        $count = sizeof($enrols);
        $i = 1;
        foreach ($enrols as $enrol) {
            print $i." of ".$count.":".$enrol->personsourcedid;
            $status = enrol_lmb_restore_user_enrolments($enrol->personsourcedid) && $status;
            print "<br>\n";
            $i++;
        }
    }
    
    return $status;
}


/**
 * Generate a new internal crosslist id number
 * 
 * @param string $term term ims/xml id
 * @return string the generated id
 */
function enrol_lmb_create_new_crosslistid($term = '') {
    if (!$count = get_config('enrol/lmb', 'last_internal_crosslist_num')) {
        $count = 0;
    }
    
    $count++;

    $did = 'XLSi'.$count.$term;
    
    set_config('last_internal_crosslist_num', $count, 'enrol/lmb');
    
    return $did;

}

/**
 * Return an array of terms in the lmb tables.
 * 
 * @return returns an array of term sourcedids
 */
function enrol_lmb_get_terms() {
    global $DB;
    $out = array();
    
    if ($terms = $DB->get_records('lmb_terms', null, 'id DESC')) {
        foreach ($terms as $term) {
            $out[] = $term->sourcedid;
        }
    }
    
    return $out;
}

/**
 * Return an array of terms to be used with the choose menu functions.
 * 
 * @return returns an array of term sourcedids
 */
function enrol_lmb_make_terms_menu_array() {
    global $DB;
    $out = array();
    
    if ($terms = $DB->get_records('lmb_terms', null, 'id DESC')) {
        foreach ($terms as $term) {
            $out[$term->sourcedid] = $term->title.' ('.$term->sourcedid.')';
        }
    }
    
    return $out;
}


/**
 * Return an object containing the config options for the LMB module
 * 
 * @return object the config object
 */
function enrol_lmb_get_config() {
    return get_config('enrol_lmb');
}


/**
 * Create a has from the provided sourcedid and sourcedid source. This
 * allows both to be stored in the idnumber field. But the drawback is that
 * you can't select courses from moodle table based on something like
 * '%.200840'
 * 
 * @param string $sourcedid the xml/ims id of the item
 * @param string $source the xml/ims source of the item
 * @return string the hash of the combo
 */
function enrol_lmb_hash_idnumber($sourcedid, $source = '') {
    $str = strval($sourcedid).strval($source);
    $hash = sha1($str);
    
    return $hash;
}

//create_shell_course
//get_category_id
//expand_crosslist_title
//expand_course_title


?>