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
 * Unit tests for Luminis Message Broker plugin
 *
 * @package    enrol_lmb
 * @category   phpunit
 * @copyright  2012 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/lmb/lib.php');


/**
 * @group enrol_lmb
 */
class enrol_lmb_lib_testcase extends advanced_testcase {
    // TODO expand XML to include more items, like address.
    private $personxml = '<person recstatus="0">
    <sourcedid>
        <source>Test SCT Banner</source>
        <id>usersourcedid</id>
    </sourcedid>
    <userid useridtype="Logon ID" password="loginpass">loginuserid</userid>
    <userid useridtype="SCTID" password="sctpass">sctuserid</userid>
    <userid useridtype="Email ID" password="emailpass">emailuserid</userid>
    <userid useridtype="CustomUserId" password="custpass">custuserid</userid>
    <name>
        <fn>First M Last</fn>
        <nickname>Nick Last</nickname>
        <n>
            <family>Last</family>
            <given>First</given>
            <partname partnametype="MiddleName">M</partname>
        </n>
    </name>
    <demographics>
        <gender>2</gender>
    </demographics>
    <email>test@example.edu</email>
    <tel teltype="1">555-555-5555</tel>
    <adr>
        <street>430 Kresge</street>
        <locality>Rochester</locality>
        <region>MI</region>
        <pcode>48309</pcode>
        <country>USA</country>
    </adr>
    <institutionrole primaryrole="No" institutionroletype="ProspectiveStudent"/>
    <institutionrole primaryrole="No" institutionroletype="Staff"/>
    <institutionrole primaryrole="No" institutionroletype="Student"/>
    <extension>
        <luminisperson>
            <academicmajor>Undeclared</academicmajor>
            <customrole>ApplicantAccept</customrole>
            <customrole>BannerINB</customrole>
        </luminisperson>
    </extension>
    </person>';

    public function test_lmb_xml_to_person() {
        $this->resetAfterTest(true);

        $expected = new stdClass();
        $expected->sourcedidsource = 'Test SCT Banner';
        $expected->sourcedid = 'usersourcedid';
        $expected->recstatus = '0';
        $expected->fullname = 'First M Last';
        $expected->familyname = 'Last';
        $expected->givenname = 'First';
        $expected->email = 'test@example.edu';
        $expected->academicmajor = 'Undeclared';
        $expected->username = 'test';
        $expected->auth = 'manual';
        $expected->nickname = 'Nick Last';
        $expected->telephone = '555-555-5555';
        $expected->adrstreet = '430 Kresge';
        $expected->locality = 'Rochester';
        $expected->region = 'MI';
        $expected->country = 'USA';
        $expected->timemodified = 1;
        $expected->id = 1;

        $lmb = new enrol_lmb_plugin();
        $lmb->set_config('auth', 'manual');

        // Password settings.
        $lmb->set_config('passwordnamesource', 'none');

        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'sctid');
        $expected->password = 'sctpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'loginid');
        $expected->password = 'loginpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'other');
        $lmb->set_config('passworduseridtypeother', 'CustomUserId');
        $expected->password = 'custpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        // Username Settings.
        // Custom field Settings.
        $lmb->set_config('usernamesource', 'loginid');
        $expected->username = 'loginuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'sctid');
        $expected->username = 'sctuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'emailid');
        $expected->username = 'emailuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'email');
        $expected->username = 'test@example.edu';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'emailname');
        $expected->username = 'test';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'other');
        $lmb->set_config('useridtypeother', 'CustomUserId');
        $expected->username = 'custuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        // Custom field Settings.
        $lmb->set_config('customfield1mapping', 1);

        $lmb->set_config('customfield1source', 'loginid');
        $expected->customfield1 = 'loginuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('customfield1source', 'sctid');
        $expected->customfield1 = 'sctuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);

        $lmb->set_config('customfield1source', 'emailid');
        $expected->customfield1 = 'emailuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxml));
        $this->assertEquals($expected, $result);
    }

    public function test_lmb_lmbperson_to_moodleuser() {
        // TODO expand for settings, conflicts, etc.
        global $CFG;
        $this->resetAfterTest(true);

        $lmb = new enrol_lmb_plugin();
        $lmbperson = $lmb->xml_to_person($this->personxml);

        $expected = new stdClass();
        $expected->idnumber = 'usersourcedid';
        $expected->auth = $lmb->get_config('auth');
        $expected->id = 1;
        $expected->timemodified = 1;
        $expected->mnethostid = $CFG->mnet_localhost_id;
        $expected->country = $CFG->country;
        $expected->firstname = 'First';
        $expected->lastname = 'Last';
        $expected->email = 'test@example.edu';
        $expected->username = 'test';
        $expected->confirmed = 1;
        $expected->address = '';

        $lmb->set_config('includetelephone', 0);
        $lmb->set_config('includeaddress', 0);
        $lmb->set_config('includecity', 0);
        $lmb->set_config('nickname', 0);

        $result = $this->clean_user_result($lmb->person_to_moodleuser($lmbperson));
        $this->assertEquals($expected, $result, 'Error in moodle user creation test - without options');

        $this->resetAllData();

        $lmb->set_config('includetelephone', 1);
        $expected->phone1 = '555-555-5555';
        $lmb->set_config('includeaddress', 1);
        $expected->address = '430 Kresge';
        $lmb->set_config('includecity', 1);
        $expected->city = 'Rochester';
        $lmb->set_config('nickname', 1);
        $expected->firstname = 'Nick';

        $result = $this->clean_user_result($lmb->person_to_moodleuser($lmbperson));
        $this->assertEquals($expected, $result, 'Error in moodle user creation test - with options');

        unset($expected->mnethostid);
        unset($expected->country);
        unset($expected->confirmed);

        $lmb->set_config('forcename', 1);
        $lmb->set_config('forceemail', 1);
        $lmb->set_config('forcetelephone', 1);
        $lmb->set_config('forceaddress', 1);
        $lmb->set_config('defaultcity', 'standard');
        $lmb->set_config('standardcity', 'Standard City');
        $expected->city = 'Standard City';

        $result = $this->clean_user_result($lmb->person_to_moodleuser($lmbperson));
        $this->assertEquals($expected, $result, 'Error in forceed user tests');

        $lmb->set_config('forcename', 0);
        unset($expected->firstname);
        unset($expected->lastname);
        $lmb->set_config('forceemail', 0);
        unset($expected->email);
        $lmb->set_config('forcetelephone', 0);
        unset($expected->phone1);
        $lmb->set_config('forceaddress', 0);
        $expected->address = '';;
        unset($expected->city);

        $result = $this->clean_user_result($lmb->person_to_moodleuser($lmbperson));
        $this->assertEquals($expected, $result, 'Error in do-not-force user tests');
    }

    public function test_membershiparray_to_xlsmembers() {
        global $CFG;
        $this->resetAfterTest(true);
    }

    private function clean_user_result($user) {
        if (isset($user->id)) {
            $user->id = 1;
        }
        if (isset($user->timemodified)) {
            $user->timemodified = 1;
        }

        return $user;
    }

    private function clean_person_result($person) {
        if (isset($person->id)) {
            $person->id = 1;
        }
        if (isset($person->timemodified)) {
            $person->timemodified = 1;
        }

        return $person;
    }
}
