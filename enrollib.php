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

/**
 * Returns the id of the moodle role for a provided ims/xml role
 *
 * @param numeric $imsrole the ims/xml role number
 * @param object  $config the default value to return if nothing is found
 * @return int
 */
function enrol_lmb_get_roleid($imsrole, $config=null) {
    if (!$config) {
        $config = enrol_lmb_get_config();
    }

    $imsrole = intval($imsrole);

    $imsrole = sprintf("%02d", $imsrole);

    $imsrole = 'imsrolemap'.$imsrole;

    if (isset($config->$imsrole)) {
        return $config->$imsrole;
    }

    return false;
}



function enrol_lmb_get_crosslist_children($xlsid, $merged = true) {
    global $DB;

    $params = array('crosslistsourcedid' => $xlsid);
    if ($merged) {
        $params['type'] = 'merge';
    }

    return $DB->get_records('enrol_lmb_crosslists', $params);
}

function enrol_lmb_check_enrolled_in_xls_merged($userid, $courseid) {
    global $DB;

    if (!$xlsid = $DB->get_field('course', 'idnumber', array('id' => $courseid))) {
        return false;
    }

    if (!$personsourcedid = $DB->get_field('user', 'idnumber', array('id' => $userid))) {
        return false;
    }

    $subsql = "SELECT coursesourcedid FROM {enrol_lmb_crosslists} WHERE crosslistsourcedid = :xlsid AND type = 'merge'";
    $sql = "SELECT * FROM {enrol_lmb_enrolments} WHERE status = 1 "
            ."AND personsourcedid = :personsourcedid AND coursesourcedid IN (".$subsql.")";

    $params = array('personsourcedid' => $personsourcedid, 'xlsid' => $xlsid);

    if ($enrols = $DB->get_records_sql($sql, $params)) {
        return true;
    }

    return false;
}

function enrol_lmb_get_crosslist_groupid($coursesourcedid, $crosslistsourcedid = null) {
    global $DB;
    if ($crosslistsourcedid) {
        $params = array('coursesourcedid' => $coursesourcedid, 'crosslistsourcedid' => $crosslistsourcedid);
        if (!$crosslist = $DB->get_record('enrol_lmb_crosslists', $params)) {
            return false;
        }
    } else {
        if (!$crosslist = $DB->get_record('enrol_lmb_crosslists', array('coursesourcedid' => $coursesourcedid))) {
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
        return $lmbcrosslist->crosslistgroupid;
    }

    if (!$mdlcourse = $DB->get_record('course', array('idnumber' => $lmbcrosslist->crosslistsourcedid))) {
        return false;
    }

    if (!$lmbcourse = $DB->get_record('enrol_lmb_courses', array('sourcedid' => $lmbcrosslist->coursesourcedid))) {
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
    $DB->update_record('enrol_lmb_crosslists', $crossup);

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
    if ($lmbterm = $DB->get_record('enrol_lmb_terms', array('sourcedid' => $lmbcourse->term))) {
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
    global $DB, $CFG;
    $config = enrol_lmb_get_config();
    $status = true;

    if (!class_exists('enrol_lmb_plugin')) {
        require_once($CFG->dirroot.'/enrol/lmb/lib.php');
    }

    $enrolmod = new enrol_lmb_plugin();

    if ($enrols = $DB->get_records('enrol_lmb_enrolments', array('status' => 1, 'coursesourcedid' => $idnumber))) {

        foreach ($enrols as $enrol) {
            $logline = '';
            $status = $enrolmod->process_enrolment_log($enrol, $logline, $config) && $status;
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
    global $DB, $CFG;
    $status = true;

    if ($enrols = $DB->get_records('enrol_lmb_enrolments', array('status' => 1, 'coursesourcedid' => $xlist->coursesourcedid))) {
        if (!class_exists('enrol_lmb_plugin')) {
            require_once($CFG->dirroot.'/enrol/lmb/lib.php');
        }

        $enrolmod = new enrol_lmb_plugin();

        if ($courseid = enrol_lmb_get_course_id($xlist->crosslistsourcedid)) {
            foreach ($enrols as $enrol) {
                if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                    if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                        $logline = '';
                        $status = $enrolmod->lmb_unassign_role_log($roleid, $courseid, $userid, $logline) && $status;
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
function enrol_lmb_drop_all_users($idnumber, $role = null, $original = false) {
    global $DB, $CFG;

    $status = true;
    $config = enrol_lmb_get_config();

    if ($role) {
        $enrols = $DB->get_records('enrol_lmb_enrolments', array('status' => 1, 'role' => $role, 'coursesourcedid'=> $idnumber));
    } else {
        $enrols = $DB->get_records('enrol_lmb_enrolments', array('status' => 1, 'coursesourcedid' => $idnumber));
    }

    if ($enrols) {
        if ($courseid = enrol_lmb_get_course_id($idnumber, $original)) {
            if (!class_exists('enrol_lmb_plugin')) {
                require_once($CFG->dirroot.'/enrol/lmb/lib.php');
            }

            $enrolmod = new enrol_lmb_plugin();

            foreach ($enrols as $enrol) {
                $enrolup = new object();

                $enrolup->succeeded = 0;

                if ($userid = $DB->get_field('user', 'id', array('idnumber' => $enrol->personsourcedid))) {
                    if ($roleid = enrol_lmb_get_roleid($enrol->role)) {
                        $logline = '';
                        $status = $enrolmod->lmb_unassign_role_log($roleid, $courseid, $userid, $logline) && $status;
                    } else {
                        $status = false;
                    }
                }

                $enrolup->id = $enrol->id;
                $DB->update_record('enrol_lmb_enrolments', $enrolup);
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

    $courses = $DB->get_records('enrol_lmb_courses', array('term' => $termid));

    $count = count($courses);
    $i = 1;

    foreach ($courses as $course) {
        $line = $i.' of '.$count.':'.$course->sourcedid.':';

        $coursestatus = enrol_lmb_force_course_to_db($course->sourcedid, $printverbose);

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
function enrol_lmb_force_course_to_db($idnumber, $print = false) {
    global $DB, $CFG;
    $status = true;

    if ($enrols = $DB->get_records('enrol_lmb_enrolments', array('coursesourcedid' => $idnumber))) {

        if ($print) {
            print $idnumber."<br>\n";
        }

        if (!class_exists('enrol_lmb_plugin')) {
            require_once($CFG->dirroot.'/enrol/lmb/lib.php');
        }

        $enrolmod = new enrol_lmb_plugin();

        foreach ($enrols as $enrol) {
            $logline = $enrol->personsourcedid.':';

            $status = $enrolmod->process_enrolment_log($enrol, $logline) && $status;

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
function enrol_lmb_get_course_id($idnumber, $original = false) {
    global $DB;

    $newidnumber = $idnumber;

    if (!$original && $xlist = $DB->get_record('enrol_lmb_crosslists', array('status' => 1, 'coursesourcedid' => $idnumber))) {
        if ($xlist->type == 'merge') {
            $newidnumber = $xlist->crosslistsourcedid;
        }
    }

    return $DB->get_field('course', 'id', array('idnumber' => $newidnumber));
}

function enrol_lmb_get_course_ids($idnumber, $original = false) {
    global $DB;
    $out = array();

    if (!$original && $xlists = $DB->get_records('enrol_lmb_crosslists', array('status' => 1, 'coursesourcedid' => $idnumber))) {
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
 * For a given term, retry all unsuccessful enrolments
 *
 * @param string $termid the ims/xml id of the term
 * @return bool success or failure of the enrolments
 */
function enrol_lmb_retry_term_enrolments($termid) {
    global $DB, $CFG;

    $status = true;
    $sqlparams = array('termid' => $termid, 'succeeded' => 0);
    $sql = "SELECT personsourcedid FROM {enrol_lmb_enrolments}
            WHERE term = :termid AND succeeded = :succeeded GROUP BY personsourcedid";

    if ($persons = $DB->get_records_sql($sql, $sqlparams)) {
        if (!class_exists('enrol_lmb_plugin')) {
            require_once($CFG->dirroot.'/enrol/lmb/lib.php');
        }

        $enrolmod = new enrol_lmb_plugin();

        $count = count($persons);
        $i = 1;
        foreach ($persons as $person) {
            print $i." of ".$count.":".$person->personsourcedid;
            $status = $enrolmod->restore_user_enrolments($person->personsourcedid) && $status;
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
    if (!$count = get_config('enrol_lmb', 'last_internal_crosslist_num')) {
        $count = 0;
    }

    $count++;

    $did = 'XLSi'.$count.$term;

    set_config('last_internal_crosslist_num', $count, 'enrol_lmb');

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

    if ($terms = $DB->get_records('enrol_lmb_terms', null, 'id DESC')) {
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

    if ($terms = $DB->get_records('enrol_lmb_terms', null, 'id DESC')) {
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

// TODO create_shell_course?
// TODO get_category_id?
// TODO expand_crosslist_title?
// TODO expand_course_title?
