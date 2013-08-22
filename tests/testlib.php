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
 * Functions to support test cases.
 *
 * @package    enrol_lmb
 * @category   phpunit
 * @copyright  2013 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

function lmb_tests_person_xml($params = array()) {
    lmb_tests_person_merge_defaults($params);

    $output = '<person recstatus="'.$params['recstatus'].'">
        <sourcedid>
            <source>'.$params['sourcedidsource'].'</source>
            <id>'.$params['sourcedid'].'</id>
        </sourcedid>
        <userid useridtype="Logon ID" password="'.$params['loginpass'].'">'.$params['loginuserid'].'</userid>
        <userid useridtype="SCTID" password="'.$params['sctpass'].'">'.$params['sctuserid'].'</userid>
        <userid useridtype="Email ID" password="'.$params['emailpass'].'">'.$params['emailuserid'].'</userid>
        <userid useridtype="CustomUserId" password="'.$params['custpass'].'">'.$params['custuserid'].'</userid>
        <name>
            <fn>'.$params['fullname'].'</fn>
            <nickname>'.$params['nickname'].'</nickname>
            <n>
                <family>'.$params['lastname'].'</family>
                <given>'.$params['firstname'].'</given>
                <partname partnametype="MiddleName">'.$params['middlename'].'</partname>
            </n>
        </name>
        <demographics>
            <gender>'.$params['gender'].'</gender>
        </demographics>
        <email>'.$params['email'].'</email>
        <tel teltype="1">'.$params['phone'].'</tel>
        <adr>
            <street>'.$params['street'].'</street>
            <locality>'.$params['city'].'</locality>
            <region>'.$params['region'].'</region>
            <pcode>'.$params['pcode'].'</pcode>
            <country>'.$params['country'].'</country>
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

    return $output;
    
}

function lmb_tests_person_merge_defaults(&$params) {
    $defaults = array();
    $defaults['recstatus'] = '0';
    $defaults['sourcedidsource'] = 'Test SCT Banner';
    $defaults['sourcedid'] = 'usersourcedid';
    $defaults['loginpass'] = 'loginpass';
    $defaults['loginuserid'] = 'loginuserid';
    $defaults['sctpass'] = 'sctpass';
    $defaults['sctuserid'] = 'sctuserid';
    $defaults['emailpass'] = 'emailpass';
    $defaults['emailuserid'] = 'emailuserid';
    $defaults['custpass'] = 'custpass';
    $defaults['custuserid'] = 'custuserid';
    $defaults['fullname'] = 'First M Last';
    $defaults['nickname'] = 'Nick Last';
    $defaults['lastname'] = 'Last';
    $defaults['firstname'] = 'First';
    $defaults['middlename'] = 'M';
    $defaults['gender'] = '2';
    $defaults['email'] = 'test@example.edu';
    $defaults['phone'] = '555-555-5555';
    $defaults['street'] = '430 Kresge';
    $defaults['city'] = 'Rochester';
    $defaults['region'] = 'MI';
    $defaults['pcode'] = '48309';
    $defaults['country'] = 'USA';

    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}

function lmb_tests_term_xml($params = array()) {
    lmb_tests_term_merge_defaults($params);

    $output = '<group>
    	<sourcedid>
            <source>'.$params['sourcedidsource'].'</source>
            <id>'.$params['sourcedid'].'</id>
    	</sourcedid>
    	<grouptype>
    		<scheme>Luminis</scheme>
    		<typevalue level="1">Term</typevalue>
    	</grouptype>
    	<description>
    		<short>'.$params['shortname'].'</short>
    		<long>'.$params['longname'].'</long>
    	</description>
    	<timeframe>
    		<begin restrict="'.$params['beginrestrict'].'">'.$params['beignrestrictdate'].'</begin>
    		<end restrict="'.$params['endrestrict'].'">'.$params['endrestrictdate'].'</end>
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

    return $output;
}

function lmb_tests_term_merge_defaults(&$params) {
    $defaults = array();
    $defaults['sourcedidsource'] = 'Test SCT Banner';
    $defaults['sourcedid'] = '201310';
    $defaults['shortname'] = 'Short Term 201310';
    $defaults['longname'] = 'Long Term 201310';
    $defaults['beginrestrict'] = '0';
    $defaults['beignrestrictdate'] = '2013-01-01';
    $defaults['endrestrict'] = '0';
    $defaults['endrestrictdate'] = '2013-06-30';

    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}

function lmb_tests_course_xml($params = array()) {
    lmb_tests_course_merge_defaults($params);

    $output = '<group>
        <sourcedid>
            <source>'.$params['sourcedidsource'].'</source>
            <id>'.$params['sourcedid'].'</id>
        </sourcedid>
        <grouptype>
            <scheme>Luminis</scheme>
            <typevalue level="1">CourseSection</typevalue>
        </grouptype>
        <description>
            <short>'.$params['crn'].'</short>
            <long>'.$params['longname'].'</long>
            <full>'.$params['fulldescription'].'</full>
        </description>
            <org>
                <orgunit>'.$params['orgunit'].'</orgunit>
            </org>
        <timeframe>
    		<begin restrict="'.$params['beginrestrict'].'">'.$params['beignrestrictdate'].'</begin>
    		<end restrict="'.$params['endrestrict'].'">'.$params['endrestrictdate'].'</end>
        </timeframe>
        <enrollcontrol>
            <enrollaccept>1</enrollaccept>
            <enrollallowed>0</enrollallowed>
        </enrollcontrol>
        <relationship relation="1">
        <sourcedid>
            <source>'.$params['termsource'].'</source>
            <id>'.$params['term'].'</id>
        </sourcedid>
        <label>Term</label>
        </relationship>
        <relationship relation="1">
        <sourcedid>
            <source>'.$params['coursesource'].'</source>
            <id>'.$params['course'].'</id>
        </sourcedid>
        <label>Course</label>
        </relationship>
        <extension>
            <luminisgroup>
                <deliverysystem>WEBCT</deliverysystem>
            </luminisgroup>
        </extension>
    </group>';

    return $output;
}

function lmb_tests_course_merge_defaults(&$params) {
    $defaults = array();
    $defaults['sourcedidsource'] = 'Test SCT Banner';
    $defaults['sourcedid'] = '10001.201310';
    $defaults['crn'] = '10001';
    $defaults['longname'] = 'DEP-101-001';
    $defaults['fulldescription'] = 'Full Course Description';
    $defaults['orgunit'] = 'Department Unit';
    $defaults['beginrestrict'] = '0';
    $defaults['beignrestrictdate'] = '2013-01-01';
    $defaults['endrestrict'] = '0';
    $defaults['endrestrictdate'] = '2013-06-30';
    $defaults['termsource'] = 'Test SCT Banner';
    $defaults['term'] = '201310';
    $defaults['coursesource'] = 'Test SCT Banner';
    $defaults['course'] = 'CRSPA-691';

    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}

function lmb_tests_person_member_xml($params = array()) {
    lmb_tests_person_member_merge_defaults($params);

    $output = '<membership>
		<sourcedid>
            <source>'.$params['sourcedidsource'].'</source>
            <id>'.$params['sourcedid'].'</id>
		</sourcedid>';

    foreach($params['members'] as $member) {
		$output .= '<member>
    		<sourcedid>
    			<source>'.$member['sourcedidsource'].'</source>
    			<id>'.$member['sourcedid'].'</id>
    		</sourcedid>
    		<idtype>1</idtype>
    		<role roletype = "'.$member['role'].'">
        		<status>'.$member['status'].'</status>';
		if (isset($member['beginrestrict'])) {
            $output .= '<timeframe>
            		<begin restrict="'.$member['beginrestrict'].'">'.$member['beignrestrictdate'].'</begin>
            		<end restrict="'.$member['endrestrict'].'">'.$member['endrestrictdate'].'</end>
        		</timeframe>';
        }
        if (isset($member['gradable'])) {
		    $output .= '<interimresult resulttype = "MidTerm">
        			<mode>'.$member['midtermmode'].'</mode>
        		</interimresult>
        		<finalresult>
        			<mode>'.$member['finalmode'].'</mode>
        		</finalresult>
        		<extension>
        			<gradable>'.$member['gradable'].'</gradable>
        		</extension>';
        }
        $output .= '</role>
		</member>';
    }
	$output .= '</membership>';

    return $output;
}

function lmb_tests_person_member_merge_defaults(&$params) {
    $defaults = array();
    $defaults['sourcedidsource'] = 'Test SCT Banner';
    $defaults['sourcedid'] = '10001.201310';
    $defaults['members'] = array();
    $defaults['members'][0] = array();
    $defaults['members'][0]['sourcedidsource'] = 'Test SCT Banner';
    $defaults['members'][0]['sourcedid'] = 'usersourcedid';
    $defaults['members'][0]['role'] = '01';
    $defaults['members'][0]['status'] = '1';
    $defaults['members'][0]['beginrestrict'] = '0';
    $defaults['members'][0]['beignrestrictdate'] = '2013-05-06';
    $defaults['members'][0]['endrestrict'] = '0';
    $defaults['members'][0]['endrestrictdate'] = '2013-06-26';
    $defaults['members'][0]['midtermmode'] = 'Standard Numeric';
    $defaults['members'][0]['finalmode'] = 'Standard Numeric';
    $defaults['members'][0]['gradable'] = '1';

    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}



function lmb_tests_xlist_member_xml($params = array()) {
    lmb_tests_xlist_member_merge_defaults($params);

    $output = '<membership>
		<sourcedid>
            <source>'.$params['sourcedidsource'].'</source>
            <id>'.$params['sourcedid'].'</id>
		</sourcedid>';
    if (isset($params['type'])) {
        $output .= '<type>merge</type>';
    }
    foreach($params['members'] as $member) {
        $output .= '<member>
		<sourcedid>
            <source>'.$member['sourcedidsource'].'</source>
            <id>'.$member['sourcedid'].'</id>
		</sourcedid>
		<idtype>2</idtype>
		<role roletype = "02">
		<status>'.$member['status'].'</status>
		</role>
		</member>';
    }
	$output .= '</membership>';

    return $output;
}

function lmb_tests_xlist_member_merge_defaults(&$params) {
    $defaults = array();
    $defaults['sourcedidsource'] = 'Test SCT Banner';
    $defaults['sourcedid'] = 'XLSAA201310';
    $defaults['members'] = array();
    $defaults['members'][0] = array();
    $defaults['members'][0]['sourcedidsource'] = 'Test SCT Banner';
    $defaults['members'][0]['sourcedid'] = '10001.201310';
    $defaults['members'][0]['status'] = '1';
    $defaults['members'][1] = array();
    $defaults['members'][1]['sourcedidsource'] = 'Test SCT Banner';
    $defaults['members'][1]['sourcedid'] = '10002.201310';
    $defaults['members'][1]['status'] = '1';


    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}

/*
function lmb_tests_course_xml($params = array()) {
    lmb_tests_course_merge_defaults($params);

    $output = '';

    return $output;
}

function lmb_tests_course_merge_defaults(&$params) {
    $defaults = array();


    foreach($defaults as $key => $value) {
        if (!isset($params[$key])) {
            $params[$key] = $value;
        }
    }
}
*/
