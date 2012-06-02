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
 * Unit tests for (some of) mod/quiz/locallib.php.
 *
 * @package    enrol_lmb
 * @category   phpunit
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/lmb/lib.php');


/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @group enrol_lmb
 */
class enrol_lmb_lib_testcase extends advanced_testcase {
    public function test_lmb_xml_to_person() {
        $this->resetAfterTest(true);
        $xml = '<person>
    <sourcedid>
        <source>Test SCT Banner</source>
        <id>usersourcedid</id>
    </sourcedid>
    <userid useridtype="Logon ID" password="loginpass">loginuserid</userid>
    <userid useridtype="SCTID" password="sctpass">sctuserid</userid>
    <userid useridtype="CustomUserId" password="custpass">custuserid</userid>
    <name>
        <fn>First M Last</fn>
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
        $expected = new stdClass;
        $expected->sourcedidsource = 'Test SCT Banner';
        $expected->sourcedid = 'usersourcedid';
        $expected->fullname = 'First M Last';
        $expected->familyname = 'Last';
        $expected->givenname = 'First';
        $expected->email = 'test@example.edu';
        $expected->academicmajor = 'Undeclared';
        $expected->username = 'test';
        $expected->auth = 'manual';

        $lmb = new enrol_lmb_plugin();
        $lmb->set_config('auth', 'manual');

        // Password settings.
        $lmb->set_config('passwordnamesource', 'none');
        $this->assertEquals($lmb->xml_to_person($xml), $expected);

        $lmb->set_config('passwordnamesource', 'sctid');
        $expected->password = 'sctpass';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);

        $lmb->set_config('passwordnamesource', 'loginid');
        $expected->password = 'loginpass';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);
        
        $lmb->set_config('passwordnamesource', 'other');
        $lmb->set_config('passworduseridtypeother', 'CustomUserId');
        $expected->password = 'custpass';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);
        
        // Username Settings.
        $lmb->set_config('usernamesource', 'email');
        $expected->username = 'test@example.edu';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);

        $lmb->set_config('usernamesource', 'emailname');
        $expected->username = 'test';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);

        $lmb->set_config('usernamesource', 'other');
        $lmb->set_config('useridtypeother', 'CustomUserId');
        $expected->username = 'custuserid';
        $this->assertEquals($lmb->xml_to_person($xml), $expected);

        }
    /*public function test_quiz_has_grades() {
        $quiz = new stdClass();
        $quiz->grade = '100.0000';
        $quiz->sumgrades = '100.0000';
        $this->assertTrue(quiz_has_grades($quiz));
        $quiz->sumgrades = '0.0000';
        $this->assertFalse(quiz_has_grades($quiz));
        $quiz->grade = '0.0000';
        $this->assertFalse(quiz_has_grades($quiz));
        $quiz->sumgrades = '100.0000';
        $this->assertFalse(quiz_has_grades($quiz));
    }

    public function test_quiz_format_grade() {
        $quiz = new stdClass();
        $quiz->decimalpoints = 2;
        $this->assertEquals(quiz_format_grade($quiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(quiz_format_grade($quiz, 0), format_float(0, 2));
        $this->assertEquals(quiz_format_grade($quiz, 1.000000000000), format_float(1, 2));
        $quiz->decimalpoints = 0;
        $this->assertEquals(quiz_format_grade($quiz, 0.12345678), '0');
    }

    public function test_quiz_format_question_grade() {
        $quiz = new stdClass();
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = 2;
        $this->assertEquals(quiz_format_question_grade($quiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(quiz_format_question_grade($quiz, 0), format_float(0, 2));
        $this->assertEquals(quiz_format_question_grade($quiz, 1.000000000000), format_float(1, 2));
        $quiz->decimalpoints = 3;
        $quiz->questiondecimalpoints = -1;
        $this->assertEquals(quiz_format_question_grade($quiz, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(quiz_format_question_grade($quiz, 0), format_float(0, 3));
        $this->assertEquals(quiz_format_question_grade($quiz, 1.000000000000), format_float(1, 3));
        $quiz->questiondecimalpoints = 4;
        $this->assertEquals(quiz_format_question_grade($quiz, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(quiz_format_question_grade($quiz, 0), format_float(0, 4));
        $this->assertEquals(quiz_format_question_grade($quiz, 1.000000000000), format_float(1, 4));
    }*/
}
