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


/* This file keeps track of upgrades to
 * the authorize enrol plugin
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installtion to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the functions defined in lib/ddllib.php
 */

// TODO update older upgrade code?

function xmldb_enrol_lmb_upgrade($oldversion=0) {

    global $CFG, $THEME, $DB;

    $dbman = $DB->get_manager();

    $result = true;

    if ($result && $oldversion < 2007072501) {
        $table = new xmldb_table('lmb_enrolments');
        if (!$dbman->field_exists($table, new xmldb_field('succeeded'))) {
            $field = new xmldb_field('succeeded');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'status');

            $result = $result && $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('lmb_crosslist');
        if (!$dbman->field_exists($table, new xmldb_field('timemodified'))) {
            $field = new xmldb_field('timemodified');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'crosslistsourcedid');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('coursesourcedidsource'))) {
            $field = new xmldb_field('coursesourcedidsource');
            $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'id');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('crosssourcedidsource'))) {
            $field = new xmldb_field('crosssourcedidsource');
            $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'coursesourcedid');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('status'))) {
            $field = new xmldb_field('status');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '-1', 'crosslistsourcedid');

            $result = $result && $dbman->add_field($table, $field);
        }
    }

    if ($result && $oldversion < 2007101701) {
        unset_config('enrol_lmb_bannercron');
        unset_config('enrol_lmb_bannercronhr');
        unset_config('enrol_lmb_bannercronmin');
        set_config('enrol_lmb_storexml', 'always');
    }

    if ($result && $oldversion < 2008050501) {
        $table = new xmldb_table('lmb_crosslist');
        if (!$dbman->field_exists($table, new xmldb_field('manual'))) {
            $field = new xmldb_field('manual');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'status');

            $result = $result && $dbman->add_field($table, $field);
        }
    }

    if ($result && $oldversion < 2008073101) {
        $table = new xmldb_table('lmb_enrolments');
        if (!$dbman->field_exists($table, new xmldb_field('midtermgrademode'))) {
            $field = new xmldb_field('midtermgrademode');
            $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'succeeded');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('midtermsubmitted'))) {
            $field = new xmldb_field('midtermsubmitted');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'midtermgrademode');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('finalgrademode'))) {
            $field = new xmldb_field('finalgrademode');
            $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'midtermsubmitted');

            $result = $result && $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists($table, new xmldb_field('finalsubmitted'))) {
            $field = new xmldb_field('finalsubmitted');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'finalgrademode');

            $result = $result && $dbman->add_field($table, $field);
        }
    }

    if ($result && $oldversion < 2008073102) {
        $table = new xmldb_table('lmb_enrolments');

        if (!$dbman->field_exists($table, new xmldb_field('gradable'))) {
            $field = new xmldb_field('gradable');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'succeeded');

            // Launch add field gradable.
            $result = $result && $dbman->add_field($table, $field);
        }
    }

    if ($result && $oldversion < 2008073104) {
        $table = new xmldb_table('lmb_terms');
        $field = new xmldb_field('active');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'studentshowtime');

        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2008081302) {
        $table = new xmldb_table('lmb_crosslist');
        $field = new xmldb_field('type');
        $field->set_attributes(XMLDB_TYPE_CHAR, '8', null, null, null, $CFG->enrol_lmb_xlstype, 'manual');

        $result = $result && $dbman->add_field($table, $field);

        $field->set_attributes(XMLDB_TYPE_CHAR, '8', null, null, null, null, 'manual');

        $result = $result && $dbman->change_field_default($table, $field);
    }

    if ($result && $oldversion < 2008082001) {
        set_config('silent', 1, 'enrol/lmb');
    }

    if ($result && $oldversion < 2008082201) {
        set_config('logtolocation', $CFG->enrol_lmb_logtolocation, 'enrol/lmb');
        unset_config('enrol_lmb_logtolocation');

        set_config('performlmbcheck', $CFG->enrol_lmb_performlmbcheck, 'enrol/lmb');
        unset_config('enrol_lmb_performlmbcheck');

        set_config('startbiztimehr', $CFG->enrol_lmb_startbiztimehr, 'enrol/lmb');
        unset_config('enrol_lmb_startbiztimehr');

        set_config('startbiztimemin', $CFG->enrol_lmb_startbiztimemin, 'enrol/lmb');
        unset_config('enrol_lmb_startbiztimemin');

        set_config('endbiztimehr', $CFG->enrol_lmb_endbiztimehr, 'enrol/lmb');
        unset_config('enrol_lmb_endbiztimehr');

        set_config('endbiztimemin', $CFG->enrol_lmb_endbiztimemin, 'enrol/lmb');
        unset_config('enrol_lmb_endbiztimemin');

        set_config('bizgrace', $CFG->enrol_lmb_bizgrace, 'enrol/lmb');
        unset_config('enrol_lmb_bizgrace');

        set_config('nonbizgrace', $CFG->enrol_lmb_nonbizgrace, 'enrol/lmb');
        unset_config('enrol_lmb_nonbizgrace');

        set_config('emails', $CFG->enrol_lmb_emails, 'enrol/lmb');
        unset_config('enrol_lmb_emails');

        set_config('bannerxmllocation', $CFG->enrol_lmb_bannerxmllocation, 'enrol/lmb');
        unset_config('enrol_lmb_bannerxmllocation');

        set_config('bannercron', $CFG->enrol_lmb_bannercron, 'enrol/lmb');
        unset_config('enrol_lmb_bannercron');

        set_config('bannercronhr', $CFG->enrol_lmb_bannercronhr, 'enrol/lmb');
        unset_config('enrol_lmb_bannercronhr');

        set_config('bannercronmin', $CFG->enrol_lmb_bannercronmin, 'enrol/lmb');
        unset_config('enrol_lmb_bannercronmin');

        set_config('coursetitle', $CFG->enrol_lmb_coursetitle, 'enrol/lmb');
        unset_config('enrol_lmb_coursetitle');

        set_config('courseshorttitle', $CFG->enrol_lmb_courseshorttitle, 'enrol/lmb');
        unset_config('enrol_lmb_courseshorttitle');

        set_config('cattype', $CFG->enrol_lmb_cattype, 'enrol/lmb');
        unset_config('enrol_lmb_cattype');

        set_config('catselect', $CFG->enrol_lmb_catselect, 'enrol/lmb');
        unset_config('enrol_lmb_catselect');

        set_config('xlstitle', $CFG->enrol_lmb_xlstitle, 'enrol/lmb');
        unset_config('enrol_lmb_xlstitle');

        set_config('xlsshorttitle', $CFG->enrol_lmb_xlsshorttitle, 'enrol/lmb');
        unset_config('enrol_lmb_xlsshorttitle');

        set_config('xlstype', $CFG->enrol_lmb_xlstype, 'enrol/lmb');
        unset_config('enrol_lmb_xlstype');

        set_config('createnewusers', $CFG->enrol_lmb_createnewusers, 'enrol/lmb');
        unset_config('enrol_lmb_createnewusers');

        set_config('createusersemaildomain', $CFG->enrol_lmb_createusersemaildomain, 'enrol/lmb');
        unset_config('enrol_lmb_createusersemaildomain');

        set_config('imsdeleteusers', $CFG->enrol_lmb_imsdeleteusers, 'enrol/lmb');
        unset_config('enrol_lmb_imsdeleteusers');

        set_config('usernamesource', $CFG->enrol_lmb_usernamesource, 'enrol/lmb');
        unset_config('enrol_lmb_usernamesource');

        set_config('useridtypeother', $CFG->enrol_lmb_useridtypeother, 'enrol/lmb');
        unset_config('enrol_lmb_useridtypeother');

        set_config('auth', $CFG->enrol_lmb_auth, 'enrol/lmb');
        unset_config('enrol_lmb_auth');

        set_config('passwordnamesource', $CFG->enrol_lmb_passwordnamesource, 'enrol/lmb');
        unset_config('enrol_lmb_passwordnamesource');

        set_config('passworduseridtypeother', $CFG->enrol_lmb_passworduseridtypeother, 'enrol/lmb');
        unset_config('enrol_lmb_passworduseridtypeother');

        set_config('defaultcity', $CFG->enrol_lmb_defaultcity, 'enrol/lmb');
        unset_config('enrol_lmb_defaultcity');

        set_config('standardcity', $CFG->enrol_lmb_standardcity, 'enrol/lmb');
        unset_config('enrol_lmb_standardcity');

        set_config('imsrolemap01', $CFG->enrol_lmb_imsrolemap01, 'enrol/lmb');
        unset_config('enrol_lmb_imsrolemap01');
        set_config('imsrolemap02', $CFG->enrol_lmb_imsrolemap02, 'enrol/lmb');
        unset_config('enrol_lmb_imsrolemap02');

        set_config('unenrolmember', $CFG->enrol_lmb_unenrolmember, 'enrol/lmb');
        unset_config('enrol_lmb_unenrolmember');
    }

    if ($result && $oldversion < 2008082301) {
        set_config('xlstitlerepeat', $CFG->enrol_lmb_xlstitlerepeat, 'enrol/lmb');
        unset_config('enrol_lmb_xlstitlerepeat');

        set_config('xlstitledivider', $CFG->enrol_lmb_xlstitledivider, 'enrol/lmb');
        unset_config('enrol_lmb_xlstitledivider');

        set_config('xlsshorttitlerepeat', $CFG->enrol_lmb_xlsshorttitlerepeat, 'enrol/lmb');
        unset_config('enrol_lmb_xlsshorttitlerepeat');

        set_config('xlsshorttitledivider', $CFG->enrol_lmb_xlsshorttitledivider, 'enrol/lmb');
        unset_config('enrol_lmb_xlsshorttitledivider');

        set_config('storexml', $CFG->enrol_lmb_storexml, 'enrol/lmb');
        unset_config('enrol_lmb_storexml');

        set_config('sourcedidfallback', $CFG->enrol_lmb_sourcedidfallback, 'enrol/lmb');
        unset_config('enrol_lmb_sourcedidfallback');

        unset_config('enrol_lmb_movinglogs');

        set_config('logerrors', $CFG->enrol_lmb_logerrors, 'enrol/lmb');
        unset_config('enrol_lmb_logerrors');

        set_config('includetelephone', $CFG->enrol_lmb_includetelephone, 'enrol/lmb');
        unset_config('enrol_lmb_includetelephone');

        set_config('includeaddress', $CFG->enrol_lmb_includeaddress, 'enrol/lmb');
        unset_config('enrol_lmb_includeaddress');

        set_config('forcetitle', $CFG->enrol_lmb_forcetitle, 'enrol/lmb');
        unset_config('enrol_lmb_forcetitle');

        set_config('forcetelephone', $CFG->enrol_lmb_forcetelephone, 'enrol/lmb');
        unset_config('enrol_lmb_forcetelephone');

        set_config('forceshorttitle', $CFG->enrol_lmb_forceshorttitle, 'enrol/lmb');
        unset_config('enrol_lmb_forceshorttitle');

        set_config('forcename', $CFG->enrol_lmb_forcename, 'enrol/lmb');
        unset_config('enrol_lmb_forcename');

        set_config('forceemail', $CFG->enrol_lmb_forceemail, 'enrol/lmb');
        unset_config('enrol_lmb_forceemail');

        set_config('forcecat', $CFG->enrol_lmb_forcecat, 'enrol/lmb');
        unset_config('enrol_lmb_forcecat');

        set_config('forceaddress', $CFG->enrol_lmb_forceaddress, 'enrol/lmb');
        unset_config('enrol_lmb_forceaddress');

        set_config('coursehidden', $CFG->enrol_lmb_coursehidden, 'enrol/lmb');
        unset_config('enrol_lmb_coursehidden');

        set_config('consolidateusernames', $CFG->enrol_lmb_consolidateusernames, 'enrol/lmb');
        unset_config('enrol_lmb_consolidateusernames');
    }

    if ($result && $oldversion < 2008082402) {
        $sql = 'SELECT MAX(timereceived) FROM '.$CFG->prefix.'lmb_raw_xml';

        if ($lasttime = get_field_sql($sql)) {
            set_config('lastlmbmessagetime', $lasttime, 'enrol/lmb');
        }

    }

    if ($result && $oldversion < 2008082402) {
        set_config('ignoreusernamecase', 0, 'enrol/lmb');
    }

    if ($result && $oldversion < 2008082601) {
        $table = new xmldb_table('lmb_enrolments');
        $field = new xmldb_field('extractstatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'status');

        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2009081302) {
        set_config('lastunhidetime', time(), 'enrol/lmb');
        set_config('cronunhidecourses', 0, 'enrol/lmb');

        if (isset($config->coursehidden) && ($config->coursehidden == 1)) {
            set_config('coursehidden', 'always', 'enrol/lmb');
        } else {
            set_config('coursehidden', 'never', 'enrol/lmb');
        }
    }

    if ($result && $oldversion < 2009082001) {
        $table = new xmldb_table('lmb_enrolments');
        $field = new xmldb_field('extractstatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'status');

        // Launch change of precision for field extractstatus.
        $result = $result && $dbman->change_field_precision($table, $field);
    }

    if ($result && $oldversion < 2009082201) {

        // Define field crosslistgroupid to be added to lmb_crosslist.
        $table = new xmldb_table('lmb_crosslist');
        $field = new xmldb_field('crosslistgroupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, 'crosslistsourcedid');

        // Launch add field crosslistgroupid.
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2010012001) {

        // Define table lmb_categories to be created.
        $table = new xmldb_table('lmb_categories');

        // Adding fields to table lmb_categories.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('termsourcedid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('sourcedidsource', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('dept', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('cattype', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);

        // Adding keys to table lmb_categories.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Launch create table for lmb_categories.
        $result = $result && $dbman->create_table($table);

        $depts = get_records_sql('SELECT MIN(id), depttitle FROM '.$CFG->prefix.'lmb_courses GROUP BY depttitle');
        $xl = new Object();
        $xl->depttitle = 'Crosslisted';
        $depts[] = $xl;
        if ($terms = get_records('lmb_terms')) {
            foreach ($terms as $term) {
                if ($term->categoryid) {
                    $cat = new Object();
                    $cat->categoryid = $term->categoryid;
                    $cat->termsourcedid = $term->sourcedid;
                    $cat->sourcedidsource = $term->sourcedidsource;
                    $cat->cattype = 'term';

                    insert_record('lmb_categories', addslashes_object($cat));
                    unset($cat);

                    if ($depts) {
                        foreach ($depts as $dept) {
                            if ($catid = get_field('course_categories', 'id', 'name',
                                        addslashes($dept->depttitle), 'parent', $term->categoryid)) {
                                $cat = new Object();
                                $cat->categoryid = $catid;
                                $cat->termsourcedid = $term->sourcedid;
                                $cat->sourcedidsource = $term->sourcedidsource;
                                $cat->dept = $dept->depttitle;
                                $cat->cattype = 'termdept';

                                insert_record('lmb_categories', addslashes_object($cat));
                                unset($cat);
                            }
                        }
                    }
                }
            }
        }

        if ($depts) {
            foreach ($depts as $dept) {
                if ($catid = get_field('course_categories', 'id', 'name', addslashes($dept->depttitle), 'parent', 0)) {
                    $cat = new Object();
                    $cat->categoryid = $catid;
                    $cat->dept = $dept->depttitle;
                    $cat->cattype = 'dept';

                    insert_record('lmb_categories', addslashes_object($cat));
                    unset($cat);
                }
            }
        }

        // Define field categoryid to be dropped from lmb_terms.
        $table = new xmldb_table('lmb_terms');
        $field = new xmldb_field('categoryid');

        // Launch drop field categoryid.
        $result = $result && $dbman->drop_field($table, $field);

    }

    if ($result && $oldversion < 2010082701) {
        $config = get_config('enrol/lmb');

        $curtime = time();
        $endtoday = mktime(23, 59, 59, date('n', $curtime), date('j', $curtime), date('Y', $curtime));

        set_config('nextunhiderun', $endtoday, 'enrol/lmb');

        if (isset($config->lastunhidetime) && ($lasttime = $config->lastunhidetime)) {
            $lastdaytime = mktime(23, 59, 59, date('n', $lasttime), date('j', $lasttime), date('Y', $lasttime));
            $lastendtime = $lastdaytime + ($config->cronunhidedays * 86400);

            set_config('prevunhideendtime', $lastendtime, 'enrol/lmb');
        }

        unset_config('lastunhidetime', 'enrol/lmb');
    }

    if ($oldversion < 2011012501) {
        $config_bad = get_config('enrol/lmb');

        $objarray = get_object_vars($config_bad);

        foreach ($objarray as $key => $val) {
            if (get_config('enrol_lmb', $key) === false) {
                set_config($key, $val, 'enrol_lmb');
            }
            unset_config($key, 'enrol/lmb');
        }

        // Changing type of field sourcedidsource on table lmb_terms to char.
        $table = new xmldb_table('lmb_terms');
        $field = new xmldb_field('sourcedidsource', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'sourcedid');

        // Launch change of type for field sourcedidsource.
        $dbman->change_field_type($table, $field);

        // Changing type of field sourcedid on table lmb_courses to char.
        $table = new xmldb_table('lmb_courses');
        $field = new xmldb_field('sourcedid', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'id');

        // Launch change of type for field sourcedid.
        $dbman->change_field_type($table, $field);

        // Changing type of field sourcedidsource on table lmb_courses to char.
        $table = new xmldb_table('lmb_courses');
        $field = new xmldb_field('sourcedidsource', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'sourcedid');

        // Launch change of type for field sourcedidsource.
        $dbman->change_field_type($table, $field);

        // Changing type of field cattype on table lmb_categories to char.
        $table = new xmldb_table('lmb_categories');
        $field = new xmldb_field('cattype', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'categoryid');

        // Launch change of type for field cattype.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011012501, 'enrol', 'lmb');
    }

    if ($oldversion < 2011012502) {
        // Changing type of field coursesourcedid on table lmb_enrolments to char.
        $table = new xmldb_table('lmb_enrolments');
        $field = new xmldb_field('coursesourcedid', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'id');

        // Launch change of type for field coursesourcedid.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011012502, 'enrol', 'lmb');
    }

    if ($oldversion < 2011012503) {
        // Changing type of field coursesourcedidsource on table lmb_crosslist to char.
        $table = new xmldb_table('lmb_crosslist');
        $field = new xmldb_field('coursesourcedidsource', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'id');

        // Launch change of type for field coursesourcedidsource.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('coursesourcedid', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'coursesourcedidsource');

        // Launch change of type for field coursesourcedid.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('crosssourcedidsource', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'coursesourcedid');

        // Launch change of type for field crosssourcedidsource.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('crosslistsourcedid', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'crosssourcedidsource');

        // Launch change of type for field crosslistsourcedid.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011012503, 'enrol', 'lmb');
    }

    if ($oldversion < 2011020301) {
        $table = new xmldb_table('lmb_enrolments');
        $field = new xmldb_field('personsourcedid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '0', 'coursesourcedid');

        // Launch change of type for field personsourcedid.
        $dbman->change_field_type($table, $field);

        // Changing type of field sourcedid on table lmb_people to char.
        $table = new xmldb_table('lmb_people');
        $field = new xmldb_field('sourcedid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '0', 'id');

        // Launch change of type for field sourcedid.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011020301, 'enrol', 'lmb');
    }

    if ($oldversion < 2011030801) {
        // Changing type of field sourcedid on table lmb_terms to char.
        $table = new xmldb_table('lmb_terms');
        $field = new xmldb_field('sourcedid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '0', 'id');

        // Launch change of type for field sourcedid.
        $dbman->change_field_type($table, $field);

        // Changing type of field term on table lmb_courses to char.
        $table = new xmldb_table('lmb_courses');
        $field = new xmldb_field('term', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '0', 'coursenumber');

        // Launch change of type for field term.
        $dbman->change_field_type($table, $field);

        // Changing type of field term on table lmb_enrolments to char.
        $table = new xmldb_table('lmb_enrolments');
        $field = new xmldb_field('term', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '0', 'personsourcedid');

        // Launch change of type for field term.
        $dbman->change_field_type($table, $field);

        // Changing type of field termsourcedid on table lmb_categories to char.
        $table = new xmldb_table('lmb_categories');
        $field = new xmldb_field('termsourcedid', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'id');

        // Launch change of type for field termsourcedid.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011030801, 'enrol', 'lmb');
    }

    if ($oldversion < 2011122501) {

        // Changing type of field sourcedidsource on table lmb_categories to char.
        $table = new xmldb_table('lmb_categories');
        $field = new xmldb_field('sourcedidsource', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'termsourcedid');

        // Launch change of type for field sourcedidsource.
        $dbman->change_field_type($table, $field);

        // Changing type of field dept on table lmb_categories to char.
        $field = new xmldb_field('dept', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'sourcedidsource');

        // Launch change of type for field dept.
        $dbman->change_field_type($table, $field);

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2011122501, 'enrol', 'lmb');
    }

    if ($oldversion < 2012032701) {

        // Define field nickname to be added to lmb_people.
        $table = new xmldb_table('lmb_people');
        $field = new xmldb_field('nickname', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'givenname');

        // Conditionally launch add field nickname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2012032701, 'enrol', 'lmb');
    }

    if ($oldversion < 2012032901) {

        // Define table lmb_courses to be renamed to enrol_lmb_courses.
        $table = new xmldb_table('lmb_courses');

        // Launch rename table for lmb_courses.
        $dbman->rename_table($table, 'enrol_lmb_courses');

        // Define table lmb_people to be renamed to enrol_lmb_people.
        $table = new xmldb_table('lmb_people');

        // Launch rename table for lmb_people.
        $dbman->rename_table($table, 'enrol_lmb_people');

        // Define table lmb_enrolments to be renamed to enrol_lmb_enrolments.
        $table = new xmldb_table('lmb_enrolments');

        // Launch rename table for lmb_enrolments.
        $dbman->rename_table($table, 'enrol_lmb_enrolments');

        // Define table lmb_raw_xml to be renamed to enrol_lmb_raw_xml.
        $table = new xmldb_table('lmb_raw_xml');

        // Launch rename table for lmb_courses.
        $dbman->rename_table($table, 'enrol_lmb_raw_xml');

        // Define table lmb_crosslist to be renamed to enrol_lmb_crosslists.
        $table = new xmldb_table('lmb_crosslist');

        // Launch rename table for lmb_crosslist.
        $dbman->rename_table($table, 'enrol_lmb_crosslists');

        // Define table lmb_courses to be renamed to enrol_lmb_terms.
        $table = new xmldb_table('lmb_terms');

        // Launch rename table for lmb_terms.
        $dbman->rename_table($table, 'enrol_lmb_terms');

        // Define table lmb_categories to be renamed to enrol_lmb_categories.
        $table = new xmldb_table('lmb_categories');

        // Launch rename table for lmb_categories.
        $dbman->rename_table($table, 'enrol_lmb_categories');

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2012032901, 'enrol', 'lmb');
    }

    if ($oldversion < 2012033004) {
        $config_bad = get_config('enrol/lmb');

        $objarray = get_object_vars($config_bad);

        foreach ($objarray as $key => $val) {
            if (get_config('enrol_lmb', $key) === false) {
                set_config($key, $val, 'enrol_lmb');
            }
            unset_config($key, 'enrol/lmb');
        }

        upgrade_plugin_savepoint(true, 2012033004, 'enrol', 'lmb');
    }

    if ($oldversion < 2012033005) {
        if (($config = get_config('enrol_lmb', 'enrol_lmb/endbiztimemin')) !== false) {
            set_config('endbiztimemin', $config, 'enrol_lmb');
            unset_config('enrol_lmb/endbiztimemin', 'enrol_lmb');
        }

        if (($config = get_config('enrol_lmb', 'enrol_lmb/startbiztimemin')) !== false) {
            set_config('startbiztimemin', $config, 'enrol_lmb');
            unset_config('enrol_lmb/startbiztimemin', 'enrol_lmb');
        }

        upgrade_plugin_savepoint(true, 2012033005, 'enrol', 'lmb');
    }

    if ($oldversion < 2012040101) {
        $config = get_config('enrol_lmb');

        if (!isset($config->parsecoursexml)) {
            set_config('parsecoursexml', 1, 'enrol_lmb');
        }

        if (!isset($config->parsexlsxml)) {
            set_config('parsexlsxml', 1, 'enrol_lmb');
        }

        if (!isset($config->parsepersonxml)) {
            set_config('parsepersonxml', 1, 'enrol_lmb');
        }

        if (!isset($config->parseenrolxml)) {
            set_config('parseenrolxml', 1, 'enrol_lmb');
        }

        upgrade_plugin_savepoint(true, 2012040101, 'enrol', 'lmb');
    }

    if ($oldversion < 2012060702) {
        // Define index enrolmbcour_sou_ix (not unique) to be added to enrol_lmb_courses.
        $table = new xmldb_table('enrol_lmb_courses');
        $index = new xmldb_index('enrolmbcour_sou_ix', XMLDB_INDEX_NOTUNIQUE, array('sourcedid'));

        // Conditionally launch add index enrolmbcour_sou_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbpeop_sou_ix (not unique) to be added to enrol_lmb_people.
        $table = new xmldb_table('enrol_lmb_people');
        $index = new xmldb_index('enrolmbpeop_sou_ix', XMLDB_INDEX_NOTUNIQUE, array('sourcedid'));

        // Conditionally launch add index enrolmbpeop_sou_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcour_per_ix (not unique) to be added to enrol_lmb_enrolments.
        $table = new xmldb_table('enrol_lmb_enrolments');
        $index = new xmldb_index('enrolmbenro_per_ix', XMLDB_INDEX_NOTUNIQUE, array('personsourcedid'));

        // Conditionally launch add index enrolmbcour_per_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcour_cou_ix (not unique) to be added to enrol_lmb_enrolments.
        $table = new xmldb_table('enrol_lmb_enrolments');
        $index = new xmldb_index('enrolmbenro_cou_ix', XMLDB_INDEX_NOTUNIQUE, array('coursesourcedid'));

        // Conditionally launch add index enrolmbcour_cou_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcros_cou_ix (not unique) to be added to enrol_lmb_crosslists.
        $table = new xmldb_table('enrol_lmb_crosslists');
        $index = new xmldb_index('enrolmbcros_cou_ix', XMLDB_INDEX_NOTUNIQUE, array('coursesourcedid'));

        // Conditionally launch add index enrolmbcros_cou_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcros_cro_ix (not unique) to be added to enrol_lmb_crosslists.
        $table = new xmldb_table('enrol_lmb_crosslists');
        $index = new xmldb_index('enrolmbcros_cro_ix', XMLDB_INDEX_NOTUNIQUE, array('crosslistsourcedid'));

        // Conditionally launch add index enrolmbcros_cro_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbterm_sou_ix (not unique) to be added to enrol_lmb_terms.
        $table = new xmldb_table('enrol_lmb_terms');
        $index = new xmldb_index('enrolmbterm_sou_ix', XMLDB_INDEX_NOTUNIQUE, array('sourcedid'));

        // Conditionally launch add index enrolmbterm_sou_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcate_ter_ix (not unique) to be added to enrol_lmb_categories.
        $table = new xmldb_table('enrol_lmb_categories');
        $index = new xmldb_index('enrolmbcate_ter_ix', XMLDB_INDEX_NOTUNIQUE, array('termsourcedid'));

        // Conditionally launch add index enrolmbcate_ter_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index enrolmbcate_dep_ix (not unique) to be added to enrol_lmb_categories.
        $table = new xmldb_table('enrol_lmb_categories');
        $index = new xmldb_index('enrolmbcate_dep_ix', XMLDB_INDEX_NOTUNIQUE, array('dept'));

        // Conditionally launch add index enrolmbcate_dep_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2012060702, 'enrol', 'lmb');
    }

    if ($oldversion < 2012091201) {
        // Define field customfield1 to be added to enrol_lmb_people.
        $table = new xmldb_table('enrol_lmb_people');
        $field = new xmldb_field('customfield1', XMLDB_TYPE_TEXT, null, null, null, null, null, 'academicmajor');

        // Conditionally launch add field customfield1.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // LMB savepoint reached.
        upgrade_plugin_savepoint(true, 2012091201, 'enrol', 'lmb');
    }

    if ($oldversion < 2013071601) {
        // Define field beginrestrict to be added to enrol_lmb_enrolments.
        $table = new xmldb_table('enrol_lmb_enrolments');
        $field = new xmldb_field('beginrestrict', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field beginrestrict.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('beginrestricttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'beginrestrict');

        // Conditionally launch add field beginrestricttime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('endrestrict', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'beginrestricttime');

        // Conditionally launch add field endrestrict.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('endrestricttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'endrestrict');

        // Conditionally launch add field endrestricttime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Lmb savepoint reached.
        upgrade_plugin_savepoint(true, 2013071601, 'enrol', 'lmb');
    }

    if ($oldversion < 2013080202) {
        if (enrol_is_enabled('lmb')) {
            $config = get_config('enrol_lmb');
            if (((!isset($config->lmbusername)) || empty($config->lmbusername)) && ((!isset($config->lmbpasswd)) || empty($config->lmbpasswd))) {
                set_config('disablesecurity', 1, 'enrol_lmb');
            }
        }

        // Lmb savepoint reached.
        upgrade_plugin_savepoint(true, 2013080202, 'enrol', 'lmb');
    }

    if ($oldversion < 2014121402) {
        $config = get_config('enrol_lmb');
        if (isset($config->includeaddress)) {
            set_config('includecity', $config->includeaddress, 'enrol_lmb');
            unset_config('includeaddress', 'enrol_lmb');
        }
        if (isset($config->forceaddress)) {
            set_config('forcecity', $config->forceaddress, 'enrol_lmb');
            unset_config('forceaddress', 'enrol_lmb');
        }

        // Lmb savepoint reached.
        upgrade_plugin_savepoint(true, 2014121402, 'enrol', 'lmb');
    }

    if ($oldversion < 2016020901) {

        // Define field sctid to be added to enrol_lmb_people.
        $table = new xmldb_table('enrol_lmb_people');
        $field = new xmldb_field('sctid', XMLDB_TYPE_CHAR, '127', null, null, null, null, 'sourcedidsource');

        // Conditionally launch add field sctid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Lmb savepoint reached.
        upgrade_plugin_savepoint(true, 2016020901, 'enrol', 'lmb');
    }

    return $result;
}
