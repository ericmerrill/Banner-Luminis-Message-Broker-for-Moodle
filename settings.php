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

defined('MOODLE_INTERNAL') || die();

$hidden = $settings->hidden;

$settings = new admin_category('enrolsettingscat', get_string('pluginname', 'enrol_lmb'), $hidden);

$settingslmb = new admin_settingpage('enrolsettingslmb', get_string('settings'), 'moodle/site:config');

if ($ADMIN->fulltree) {
    require_once('enrollib.php');

    $plugin = new stdClass();
    include($CFG->dirroot.'/enrol/lmb/version.php');

    $a = new stdClass();
    $a->version = $plugin->release.' ('.$plugin->version.')';
    $a->toolslink = $CFG->wwwroot.'/enrol/lmb/tools';

    $settingslmb->add(new admin_setting_heading('enrol_lmb_settings', '', get_string('header', 'enrol_lmb', $a)));



    // Log Settings --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_logsettings', get_string('logsettings', 'enrol_lmb'), ''));


    $settingslmb->add(new admin_setting_configfile('enrol_lmb/logtolocation', get_string('logtolocation', 'enrol_lmb'),
            get_string('logtolocationhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/logerrors', get_string('logerrors', 'enrol_lmb'),
            get_string('logerrorshelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/logpercent', get_string('logpercent', 'enrol_lmb'),
        '', 1));

    unset($options);
    $options = array();
    $options['never'] = get_string('never', 'enrol_lmb');
    $options['onerror'] = get_string('onerror', 'enrol_lmb');
    $options['always'] = get_string('always', 'enrol_lmb');

    $settingslmb->add(new admin_setting_configselect('enrol_lmb/storexml', get_string('storexml', 'enrol_lmb'),
            get_string('storexmlhelp', 'enrol_lmb'), 'never', $options));



    // LMB Security --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_lmbsecurity', get_string('livesettings', 'enrol_lmb'),
            get_string('liveinfo', 'enrol_lmb', $CFG->wwwroot.'/enrol/lmb/secure/liveimport.php')));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/disablesecurity', get_string('disablesecurity', 'enrol_lmb'),
            get_string('disablesecurityhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/lmbusername', get_string('lmbusername', 'enrol_lmb'),
            get_string('lmbusernamehelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configpasswordunmask('enrol_lmb/lmbpasswd', get_string('lmbpasswd', 'enrol_lmb'),
            get_string('lmbpasswdhelp', 'enrol_lmb'), ''));

    unset($options);
    $options = array();
    $options[ENROL_LMB_FILTER_OFF] = get_string('off', 'enrol_lmb');
    $options[ENROL_LMB_FILTER_WHITE] = get_string('whitelist', 'enrol_lmb');
    $options[ENROL_LMB_FILTER_BLACK] = get_string('blacklist', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/livefiltermode', get_string('livefiltermode', 'enrol_lmb'),
            get_string('livefiltermodehelp', 'enrol_lmb'), ENROL_LMB_FILTER_OFF, $options));

    $settingslmb->add(new admin_setting_configtextarea('enrol_lmb/livefilterlist', get_string('livefilterlist', 'enrol_lmb'),
            get_string('livefilterlisthelp', 'enrol_lmb'), ''));



    // LMB Status Check ----------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_lmbcheck', get_string('lmbcheck', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/lmbcheck', get_string('lmbcheck', 'enrol_lmb'),
            get_string('lmbcheckhelp', 'enrol_lmb'), 0));
    $settingslmb->add(new admin_setting_configtime('enrol_lmb/startbiztimehr', 'startbiztimemin',
            get_string('startbiztime', 'enrol_lmb'), get_string('startbiztimehelp', 'enrol_lmb'), array('h' => 9, 'm' => 0)));

    $settingslmb->add(new admin_setting_configtime('enrol_lmb/endbiztimehr', 'endbiztimemin', get_string('endbiztime', 'enrol_lmb'),
            get_string('endbiztimehelp', 'enrol_lmb'), array('h' => 17, 'm' => 0)));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/bizgrace', get_string('bizdowngrace', 'enrol_lmb'),
            get_string('bizdowngracehelp', 'enrol_lmb'), '30'));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/nonbizgrace', get_string('nonbizdowngrace', 'enrol_lmb'),
            get_string('minutes').get_string('nonbizdowngracehelp', 'enrol_lmb'), '0'));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/emails', get_string('lmbnotificationemails', 'enrol_lmb'),
            get_string('lmbnotificationemailshelp', 'enrol_lmb'), ''));



    // Banner Extract Import -----------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_bannerextractimport',
            get_string('bannerextractimport', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configfile('enrol_lmb/bannerxmllocation', get_string('bannerxmllocation', 'enrol_lmb'),
            get_string('bannerxmllocationhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/bannerxmllocationcomp',
            get_string('bannerxmllocationcomp', 'enrol_lmb'), get_string('bannerxmllocationcomphelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configdirectory('enrol_lmb/bannerxmlfolder', get_string('bannerxmlfolder', 'enrol_lmb'),
            get_string('bannerxmlfolderhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/bannerxmlfoldercomp',
            get_string('bannerxmlfoldercomp', 'enrol_lmb'), get_string('bannerxmlfoldercomphelp', 'enrol_lmb'), 0));

    unset($options);
    $options = array(0 => '0%', 5 => '5%', 10 => '10%', 20 => '20%', 30 => '30%', 40 => '40%',
            50 => '50%', 60 => '60%', 70 => '70%', 80 => '80%', 90 => '90%', 100 => '100%');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/dropprecentlimit', get_string('dropprecentlimit', 'enrol_lmb'),
            get_string('dropprecentlimithelp', 'enrol_lmb'), '10', $options));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/usestatusfiles', get_string('usestatusfiles', 'enrol_lmb'),
            get_string('usestatusfileshelp', 'enrol_lmb'), 0));



    // Cron Options --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_cron', get_string('cron', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/cronxmlfile', get_string('cronxmlfile', 'enrol_lmb'),
            get_string('cronxmlfilehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/cronxmlfolder', get_string('cronxmlfolder', 'enrol_lmb'),
            get_string('cronxmlfolderhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/cronunhidecourses', get_string('cronunhidecourses', 'enrol_lmb'),
            get_string('cronunhidecourseshelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/cronunhidedays', get_string('cronunhidedays', 'enrol_lmb'),
            get_string('cronunhidedayshelp', 'enrol_lmb'), '0'));


    // Term Filters --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_filter', get_string('termfilter', 'enrol_lmb'), ''));

    unset($options);
    $options = array();
    $options[ENROL_LMB_FILTER_OFF] = get_string('off', 'enrol_lmb');
    $options[ENROL_LMB_FILTER_WHITE] = get_string('whitelist', 'enrol_lmb');
    $options[ENROL_LMB_FILTER_BLACK] = get_string('blacklist', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/filtermode', get_string('filtermode', 'enrol_lmb'),
            get_string('filtermodehelp', 'enrol_lmb'), ENROL_LMB_FILTER_OFF, $options));

    $settingslmb->add(new admin_setting_configtextarea('enrol_lmb/filterlist', get_string('filterlist', 'enrol_lmb'),
            get_string('filterlisthelp', 'enrol_lmb'), ''));


    // Parse Course --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_parsecourse', get_string('parsecourse', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/parsecoursexml', get_string('parsecoursexml', 'enrol_lmb'),
            get_string('parsecoursexmlhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/coursetitle', get_string('coursetitle', 'enrol_lmb'),
            get_string('coursetitlehelp', 'enrol_lmb'), '[RUBRIC]-[CRN]-[FULL]'));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcetitle', get_string('forcetitle', 'enrol_lmb'),
            get_string('forcetitlehelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/courseshorttitle', get_string('courseshorttitle', 'enrol_lmb'),
            get_string('courseshorttitlehelp', 'enrol_lmb'), '[DEPT][NUM]-[CRN][TERM]'));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forceshorttitle', get_string('forceshorttitle', 'enrol_lmb'),
            get_string('forceshorttitlehelp', 'enrol_lmb'), 1));

    unset($options);
    $options = array();
    $options['never'] = get_string('coursehiddennever', 'enrol_lmb');
    $options['cron'] = get_string('coursehiddencron', 'enrol_lmb');
    $options['always'] = get_string('coursehiddenalways', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/coursehidden', get_string('coursehidden', 'enrol_lmb'),
            get_string('coursehiddenhelp', 'enrol_lmb'), 'never', $options));

    unset($options);
    $options = array();
    $options['term'] = get_string('termcat', 'enrol_lmb');
    $options['dept'] = get_string('deptcat', 'enrol_lmb');
    $options['deptcode'] = get_string('deptcodecat', 'enrol_lmb');
    $options['termdept'] = get_string('termdeptcat', 'enrol_lmb');
    $options['termdeptcode'] = get_string('termdeptcodecat', 'enrol_lmb');
    $options['other'] = get_string('selectedcat', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/cattype', get_string('categorytype', 'enrol_lmb'),
            get_string('categorytypehelp', 'enrol_lmb'), 'term', $options));

    // Check for coursecat::make_categories_list, new in 2.5.
    // Old make_categories_list() depricated in 2.5.
    if (method_exists('coursecat', 'make_categories_list')) {
        $displaylist = coursecat::make_categories_list();
    } else {
        $displaylist = array();
        $parentlist = array();
        make_categories_list($displaylist, $parentlist);
    }

    $settingslmb->add(new admin_setting_configselect('enrol_lmb/catselect', get_string('catselect', 'enrol_lmb'),
            get_string('catselecthelp', 'enrol_lmb'), 1, $displaylist));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/cathidden', get_string('cathidden', 'enrol_lmb'),
            get_string('cathiddenhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcecat', get_string('forcecat', 'enrol_lmb'),
            get_string('forcecathelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/usemoodlecoursesettings',
            get_string('usemoodlecoursesettings', 'enrol_lmb'), get_string('usemoodlecoursesettingshelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/computesections', get_string('computesections', 'enrol_lmb'),
            get_string('computesectionshelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcecomputesections',
            get_string('forcecomputesections', 'enrol_lmb'), get_string('forcecomputesectionshelp', 'enrol_lmb'), 0));



    // Parse XLS -----------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_parsexls', get_string('parsexls', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/parsexlsxml', get_string('parsexlsxml', 'enrol_lmb'),
            get_string('parsexlsxmlhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlstitle', get_string('xlstitle', 'enrol_lmb'),
            get_string('xlstitlehelp', 'enrol_lmb'), '[XLSID] - [REPEAT]'));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlstitlerepeat', get_string('xlstitlerepeat', 'enrol_lmb'),
            get_string('xlstitlerepeathelp', 'enrol_lmb'), '[CRN]'));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlstitledivider', get_string('xlstitledivider', 'enrol_lmb'),
            get_string('xlstitledividerhelp', 'enrol_lmb'), ' / '));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlsshorttitle', get_string('xlsshorttitle', 'enrol_lmb'),
            get_string('xlsshorttitlehelp', 'enrol_lmb'), '[XLSID]'));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlsshorttitlerepeat', get_string('xlsshorttitlerepeat', 'enrol_lmb'),
            get_string('xlsshorttitlerepeathelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/xlsshorttitledivider',
            get_string('xlsshorttitledivider', 'enrol_lmb'), get_string('xlsshorttitledividerhelp', 'enrol_lmb'), ''));

    unset($options);
    $options = array('merge' => get_string('xlsmergecourse', 'enrol_lmb'), 'meta' => get_string('xlsmetacourse', 'enrol_lmb'));
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/xlstype', get_string('xlstype', 'enrol_lmb'),
            get_string('xlstypehelp', 'enrol_lmb'), 'merge', $options));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/xlsmergegroups', get_string('xlsmergegroups', 'enrol_lmb'),
            get_string('xlsmergegroupshelp', 'enrol_lmb'), 1));



    // Parse Person --------------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_parseperson', get_string('parseperson', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/parsepersonxml', get_string('parsepersonxml', 'enrol_lmb'),
            get_string('parsepersonxmlhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/recordsctid', get_string('recordsctid', 'enrol_lmb'),
            get_string('recordsctidhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/createnewusers', get_string('createnewusers', 'enrol_lmb'),
            get_string('createnewusershelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/createusersemaildomain',
            get_string('createusersemaildomain', 'enrol_lmb'), get_string('createusersemaildomainhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/ignoredomaincase', get_string('ignoredomaincase', 'enrol_lmb'),
            get_string('ignoredomaincasehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/donterroremail', get_string('donterroremail', 'enrol_lmb'),
            get_string('donterroremailhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/imsdeleteusers', get_string('deleteusers', 'enrol_lmb'),
            get_string('deleteusershelp', 'enrol_lmb'), 0));

    unset($options);
    $options = array();
    $options['email'] = get_string('fullemail', 'enrol_lmb');
    $options['emailname'] = get_string('emailname', 'enrol_lmb');
    $options['loginid'] = get_string('useridtypelogin', 'enrol_lmb');
    $options['sctid'] = get_string('useridtypesctid', 'enrol_lmb');
    $options['emailid'] = get_string('useridtypeemail', 'enrol_lmb');
    $options['other'] = get_string('useridtypeother', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/usernamesource', get_string('usernamesource', 'enrol_lmb'),
            get_string('usernamesourcehelp', 'enrol_lmb'), 'emailname', $options));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/useridtypeother', get_string('otheruserid', 'enrol_lmb'),
            get_string('otheruseridhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/ignoreusernamecase',
            get_string('ignoreusernamecase', 'enrol_lmb'), get_string('ignoreusernamecasehelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/ignoreemailcase', get_string('ignoreemailcase', 'enrol_lmb'),
            get_string('ignoreemailcasehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/sourcedidfallback', get_string('sourdidfallback', 'enrol_lmb'),
            get_string('sourdidfallbackhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/consolidateusernames',
            get_string('consolidateusers', 'enrol_lmb'), get_string('consolidateusershelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/customfield1mapping', get_string('customfield1mapping', 'enrol_lmb'),
            get_string('customfield1help', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configselect('enrol_lmb/customfield1source', get_string('customfield1source', 'enrol_lmb'),
            get_string('customfield1sourcehelp', 'enrol_lmb'), 'email', $options));

    unset($options);
    $modules = \core\plugininfo\auth::get_enabled_plugins();
    $options = array();
    foreach ($modules as $module => $path) {
        $options[$module] = get_string("pluginname", "auth_".$module);
    }
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/auth', get_string('authmethod', 'enrol_lmb'),
            get_string('authmethodhelp', 'enrol_lmb'), 'manual', $options));

    unset($options);
    $options = array();
    $options['none'] = get_string('none', 'enrol_lmb');
    $options['loginid'] = get_string('useridtypelogin', 'enrol_lmb');
    $options['sctid'] = get_string('useridtypesctid', 'enrol_lmb');
    $options['emailid'] = get_string('useridtypeemail', 'enrol_lmb');
    $options['other'] = get_string('useridtypeother', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/passwordnamesource', get_string('passwordsource', 'enrol_lmb'),
            get_string('passwordsourcehelp', 'enrol_lmb'), 'none', $options));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/passworduseridtypeother', get_string('otherpassword', 'enrol_lmb'),
            get_string('otherpasswordhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcepassword', get_string('forcepassword', 'enrol_lmb'),
            get_string('forcepasswordhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/nickname', get_string('nickname', 'enrol_lmb'),
            get_string('nicknamehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcename', get_string('forcename', 'enrol_lmb'),
            get_string('forcenamehelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forceemail', get_string('forceemail', 'enrol_lmb'),
            get_string('forceemailhelp', 'enrol_lmb'), 1));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/includetelephone', get_string('includetele', 'enrol_lmb'),
            get_string('includetelehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcetelephone', get_string('forcetele', 'enrol_lmb'),
            get_string('forcetelehelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/includeaddress', get_string('includeadr', 'enrol_lmb'),
            get_string('includeadrhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forceaddress', get_string('forceadr', 'enrol_lmb'),
            get_string('forceadrhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/includecity', get_string('includecity', 'enrol_lmb'),
            get_string('includecityhelp', 'enrol_lmb'), 0));

    unset($options);
    $options = array();
    $options['xml'] = get_string('locality', 'enrol_lmb');
    $options['standardxml'] = get_string('usestandardcityxml', 'enrol_lmb');
    $options['standard'] = get_string('usestandardcity', 'enrol_lmb');
    $settingslmb->add(new admin_setting_configselect('enrol_lmb/defaultcity', get_string('defaultcity', 'enrol_lmb'),
            get_string('defaultcityhelp', 'enrol_lmb'), 'xml', $options));

    $settingslmb->add(new admin_setting_configtext('enrol_lmb/standardcity', get_string('standardcity', 'enrol_lmb'),
            get_string('standardcityhelp', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/forcecity', get_string('forcecity', 'enrol_lmb'),
            get_string('forcecityhelp', 'enrol_lmb'), 0));



    // Parse Enrollments ---------------------------------------------------------------------------.
    $settingslmb->add(new admin_setting_heading('enrol_lmb_parseenrol', get_string('parseenrol', 'enrol_lmb'), ''));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/parseenrolxml', get_string('parseenrolxml', 'enrol_lmb'),
            get_string('parseenrolxmlhelp', 'enrol_lmb'), 1));

    if (!during_initial_install()) {

        $coursecontext = context_course::instance(SITEID);
        $assignableroles = get_assignable_roles($coursecontext);
        $assignableroles = array('0' => get_string('ignore', 'enrol_imsenterprise')) + $assignableroles;

        $imsroles = array(
        '01'=>'Learner',
        '02'=>'Instructor',
        '03'=>'Extra 1',
        '04'=>'Extra 2',
        '05'=>'Extra 3'
        );

        $imsmappings = array(
        '01'=>'student',
        '02'=>'editingteacher',
        '03'=>'student',
        '04'=>'student',
        '05'=>'student'
        );

        foreach ($imsroles as $imsrolenum => $imsrolename) {
            $default = false;

            if ($role = get_archetype_roles($imsmappings[$imsrolenum])) {
                $role = reset($role);
                $default = $role->id;
            }


            $settingslmb->add(new admin_setting_configselect('enrol_lmb/imsrolemap'.$imsrolenum,
                    format_string('"'.$imsrolename.'" ('.$imsrolenum.')'), '', $default, $assignableroles));
        }
    }

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/userestrictdates', get_string('userestrictdates', 'enrol_lmb'),
            get_string('userestrictdateshelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/unenrolmember', get_string('unenrolmember', 'enrol_lmb'),
            get_string('unenrolmemberhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/disableenrol', get_string('disableenrol', 'enrol_lmb'),
            get_string('disableenrolhelp', 'enrol_lmb'), 0));

    $settingslmb->add(new admin_setting_configcheckbox('enrol_lmb/recovergrades', get_string('recovergrades', 'enrol_lmb'),
            get_string('recovergradeshelp', 'enrol_lmb'), 1));

}


$settings->add('enrolsettingscat', $settingslmb);

$settings->add('enrolsettingscat', new admin_category('enroltoolsscat',
        get_string('tools', 'enrol_lmb'), false));

$settings->add("enroltoolsscat", new admin_externalpage('enroltoollmbstatus', get_string('page_lmbstatus', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/tools/lmbstatus.php", "moodle/role:manage"));

$settings->add("enroltoolsscat", new admin_externalpage('enroltoolimportfile', get_string('page_importnow', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/importnow.php", "moodle/role:manage"));

$settings->add("enroltoolsscat", new admin_externalpage('enroltoolimportextract', get_string('page_extractprocess', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/tools/extractprocess.php", "moodle/role:manage"));

$settings->add("enroltoolsscat", new admin_externalpage('enroltoolreprocess', get_string('page_reprocessenrolments', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/tools/reprocessenrolments.php", "moodle/role:manage"));

$settings->add("enroltoolsscat", new admin_externalpage('enroltoolprune', get_string('page_prunelmbtables', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/tools/prunelmbtables.php", "moodle/role:manage"));

$settings->add("enroltoolsscat", new admin_externalpage('enrolcleanxlsdrops', get_string('page_cleanxlsdrops', 'enrol_lmb'),
        "$CFG->wwwroot/enrol/lmb/tools/cleanxlsdrops.php", "moodle/role:manage"));
