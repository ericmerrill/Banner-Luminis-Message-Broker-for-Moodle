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

    private $personxmlserial = 'a:1:{s:6:"person";a:2:{s:1:"@";a:1:{s:9:"recstatus";s:1:"0";}s:1:"#";a:9:{s:9:"sourcedid";a:1:{i:0;a:1:{s:1:"#";a:2:{s:6:"source";a:1:{i:0;a:1:{s:1:"#";s:15:"Test SCT Banner";}}s:2:"id";a:1:{i:0;a:1:{s:1:"#";s:13:"usersourcedid";}}}}}s:6:"userid";a:4:{i:0;a:2:{s:1:"#";s:11:"loginuserid";s:1:"@";a:2:{s:10:"useridtype";s:8:"Logon ID";s:8:"password";s:9:"loginpass";}}i:1;a:2:{s:1:"#";s:9:"sctuserid";s:1:"@";a:2:{s:10:"useridtype";s:5:"SCTID";s:8:"password";s:7:"sctpass";}}i:2;a:2:{s:1:"#";s:11:"emailuserid";s:1:"@";a:2:{s:10:"useridtype";s:8:"Email ID";s:8:"password";s:9:"emailpass";}}i:3;a:2:{s:1:"#";s:10:"custuserid";s:1:"@";a:2:{s:10:"useridtype";s:12:"CustomUserId";s:8:"password";s:8:"custpass";}}}s:4:"name";a:1:{i:0;a:1:{s:1:"#";a:3:{s:2:"fn";a:1:{i:0;a:1:{s:1:"#";s:12:"First M Last";}}s:8:"nickname";a:1:{i:0;a:1:{s:1:"#";s:9:"Nick Last";}}s:1:"n";a:1:{i:0;a:1:{s:1:"#";a:3:{s:6:"family";a:1:{i:0;a:1:{s:1:"#";s:4:"Last";}}s:5:"given";a:1:{i:0;a:1:{s:1:"#";s:5:"First";}}s:8:"partname";a:1:{i:0;a:2:{s:1:"#";s:1:"M";s:1:"@";a:1:{s:12:"partnametype";s:10:"MiddleName";}}}}}}}}}s:12:"demographics";a:1:{i:0;a:1:{s:1:"#";a:1:{s:6:"gender";a:1:{i:0;a:1:{s:1:"#";s:1:"2";}}}}}s:5:"email";a:1:{i:0;a:1:{s:1:"#";s:16:"test@example.edu";}}s:3:"tel";a:1:{i:0;a:2:{s:1:"#";s:12:"555-555-5555";s:1:"@";a:1:{s:7:"teltype";s:1:"1";}}}s:3:"adr";a:1:{i:0;a:1:{s:1:"#";a:5:{s:6:"street";a:1:{i:0;a:1:{s:1:"#";s:10:"430 Kresge";}}s:8:"locality";a:1:{i:0;a:1:{s:1:"#";s:9:"Rochester";}}s:6:"region";a:1:{i:0;a:1:{s:1:"#";s:2:"MI";}}s:5:"pcode";a:1:{i:0;a:1:{s:1:"#";s:5:"48309";}}s:7:"country";a:1:{i:0;a:1:{s:1:"#";s:3:"USA";}}}}}s:15:"institutionrole";a:3:{i:0;a:2:{s:1:"#";s:0:"";s:1:"@";a:2:{s:11:"primaryrole";s:2:"No";s:19:"institutionroletype";s:18:"ProspectiveStudent";}}i:1;a:2:{s:1:"#";s:0:"";s:1:"@";a:2:{s:11:"primaryrole";s:2:"No";s:19:"institutionroletype";s:5:"Staff";}}i:2;a:2:{s:1:"#";s:0:"";s:1:"@";a:2:{s:11:"primaryrole";s:2:"No";s:19:"institutionroletype";s:7:"Student";}}}s:9:"extension";a:1:{i:0;a:1:{s:1:"#";a:1:{s:13:"luminisperson";a:1:{i:0;a:1:{s:1:"#";a:2:{s:13:"academicmajor";a:1:{i:0;a:1:{s:1:"#";s:10:"Undeclared";}}s:10:"customrole";a:2:{i:0;a:1:{s:1:"#";s:15:"ApplicantAccept";}i:1;a:1:{s:1:"#";s:9:"BannerINB";}}}}}}}}}}}';

    private $personxmlarray;

    private $termxml = '<group>
	<sourcedid>
		<source>Test SCT Banner</source>
		<id>201310</id>
	</sourcedid>
	<grouptype>
		<scheme>Luminis</scheme>
		<typevalue level="1">Term</typevalue>
	</grouptype>
	<description>
		<short>Short Term 201310</short>
		<long>Long Term 201310</long>
	</description>
	<timeframe>
		<begin restrict="0">2013-01-01</begin>
		<end restrict="0">2013-06-30</end>
	</timeframe>
	<enrollcontrol>
		<enrollaccept>1</enrollaccept>
		<enrollallowed>0</enrollallowed>
	</enrollcontrol>
	<extension>
	<luminisgroup>
		<sort>201310</sort>
	</luminisgroup>
	</extension>
	</group>';

    public function test_lmb_xml_to_array() {
        $this->resetAfterTest(false);

        $this->personxmlarray = unserialize($this->personxmlserial);

        $this->assertEquals(enrol_lmb_xml_to_array($this->personxml), $this->personxmlarray);
    }

    public function test_lmb_xml_to_person() {
        $this->resetAfterTest(true);
        $this->personxmlarray = unserialize($this->personxmlserial);

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

        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'sctid');
        $expected->password = 'sctpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'loginid');
        $expected->password = 'loginpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('passwordnamesource', 'other');
        $lmb->set_config('passworduseridtypeother', 'CustomUserId');
        $expected->password = 'custpass';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        // Username Settings.
        // Custom field Settings.
        $lmb->set_config('usernamesource', 'loginid');
        $expected->username = 'loginuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'sctid');
        $expected->username = 'sctuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'emailid');
        $expected->username = 'emailuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'email');
        $expected->username = 'test@example.edu';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'emailname');
        $expected->username = 'test';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('usernamesource', 'other');
        $lmb->set_config('useridtypeother', 'CustomUserId');
        $expected->username = 'custuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        // Custom field Settings.
        $lmb->set_config('customfield1mapping', 1);

        $lmb->set_config('customfield1source', 'loginid');
        $expected->customfield1 = 'loginuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('customfield1source', 'sctid');
        $expected->customfield1 = 'sctuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);

        $lmb->set_config('customfield1source', 'emailid');
        $expected->customfield1 = 'emailuserid';
        $result = $this->clean_person_result($lmb->xml_to_person($this->personxmlarray));
        $this->assertEquals($expected, $result);
    }

    public function test_lmb_lmbperson_to_moodleuser() {
        // TODO expand for settings, conflicts, etc.
        global $CFG;
        $this->resetAfterTest(true);
        $this->personxmlarray = unserialize($this->personxmlserial);

        $lmb = new enrol_lmb_plugin();
        $lmbperson = $lmb->xml_to_person($this->personxmlarray);

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

    public function test_lmb_xml_to_term() {
        $this->resetAfterTest(true);
        $lmb = new enrol_lmb_plugin();
        $termxmlarray = enrol_lmb_xml_to_array($this->termxml);

        $expected = new stdClass();
        $expected->sourcedidsource = 'Test SCT Banner';
        $expected->sourcedid = '201310';
        $expected->title = 'Long Term 201310';
        $expected->starttime = '1357027200';
        $expected->endtime = '1357027200';
        $expected->timemodified = 1;
        $expected->id = 1;


        $result = $this->clean_lmb_object($lmb->xml_to_term($termxmlarray));
        $this->assertEquals($expected, $result);
        
    }

    public function test_membershiparray_to_xlsmembers() {
        global $CFG;
        $this->resetAfterTest(true);
    }

    private function clean_user_result($user) {

        return $this->clean_lmb_object($user);
    }

    private function clean_person_result($person) {

        return $this->clean_lmb_object($person);
    }

    private function clean_lmb_object($obj) {
        if (isset($obj->id)) {
            $obj->id = 1;
        }
        if (isset($obj->timemodified)) {
            $obj->timemodified = 1;
        }

        return $obj;
    }
}
