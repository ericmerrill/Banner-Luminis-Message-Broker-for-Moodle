<?php
// This file is part of Moodle - http://moodle.org/
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
 * An enrolment module that imports from Banner.
 *
 * @package   enrol_lmb
 * @copyright Eric Merrill (merrill@oakland.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lmb;

class xml_parser {
    //private logger = null;

    public function __construct() {
        //$this->logger = new logger();
    }

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


}
