Luminis Message Broker enrollment Module.
Version: 2.1.7
Moodle version: 2.0.6 throught 2.3.x
Maintainer: Eric Merrill (merrill@oakland.edu)



Project Maintained at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/

Documentation at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/wiki


RECENT CHANGES
--------------
View full change log at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/wiki/Change-Log

Release 2.1.7
Significant performance increase when processing on large site. Added indexes to common columns.
Add option force password, or set it only on user creation.
Fix to cron file processing (CONTRIB-3702)
Possible problem with crosslist groupings. (CONTRIB-3698)
Problem during call process_enrolment_log in enrol_lmb_force_course_to_db. (CONTRIB-3699)
Removed calls to print_header (depreciated). (CONTRIB-3701)
Fix various missing variable errors. (CONTRIB-3700)


Release 2.1.6
Option to restore old user grades during re-enrollements.
Fixed problem where settings may be lost during upgrade from Moodle 1.9.x and below to Moodle 2.x (CONTRIB-3626).


Release 2.1.5
Fixed problem where users may be dropped from cross lists when dropped from one member course (CONTRIB-1728).
Option to set domain comparison to case-insensitive.
Added option to ignore capitalization for email domains. (Thanks to Charles Fulton)
Option to disable enrolments instead of deleting them.
Tools moved into the settings block hierarchy (Site Administration>Plugins>Enrolments>Banner/Luminis Message Broker>Tools).


Release 2.1.1
Changed code to match moodle style guidelines.
Changed storage of raw XML to serialize format instead of print_r
Fixed possible fetal error with add and drop. Fixed bug that cause skipping of cross lists.
Fixed error with handeling of course section count calculation.
Adds lmb instance to courses found without it.
Added code to process folder on cron.
Add option to disable % logging for batch operations.
Logging made more efficient.
Added tools to process folders and files from the command line. See enrol/lmb/cli.


Release 2.1.0
IMPORTANT!:
Renamed tables to match Moodle conventions, adding enrol_ to the front of table names:
lmb_courses    => enrol_lmb_courses
lmb_people     => enrol_lmb_people
lmb_enrolments => enrol_lmb_enrolments
lmb_raw_xml    => enrol_lmb_raw_xml
lmb_crosslist  => enrol_lmb_crosslists   (NOTE: added 's' to the end also)
lmb_terms      => enrol_lmb_terms
lmb_categories => enrol_lmb_categories

If you use any additional scripts that access these tables, they will need to be updated. You can find them
by using the regular expression: (?<!enrol_)\b(?:OLDNAME) and then replace with the new name for that table.

Removed unused functions - check code compatibility:
enrol_lmb->lmb_assign_role
enrol_lmb->lmb_unassign_role
enrol_lmb->process_enrolment
enrol_lmb->lmb_assign_role (use lmb_assign_role_log)
enrol_lmb->lmb_unassign_role (use lmb_unassign_role_log)

enrol_lmb_assign_role_log
enrol_lmb_unassign_role
enrol_lmb_unassign_role_log
enrol_lmb_get_course_contextid
enrol_lmb_reset_all_term_enrolments renamed to enrol_lmb_retry_term_enrolments


Fixed Version display and link to tools on LMB settings page.
Fixed error in drop percent calculation for bulk processing.
Fix get string errors in tools.
Fix errors in tools breadcrumbs.
Fix set_url() error on tool pages.
Fixed context errors on tool pages.
Enrollment processing during course update does not show creation/update error.
Set some missing defaults in upgrade.php.
Fixes to some settings using the incorrect enrol/lmb plugin name. Settings migrated to enrol_lmb.
Fixed Buisness hours minute fields not working correctly.
Fixed defaults in various setting items.
Defaults now shown for ims role mapping.
Sets new users default country to whatever the sitewide config is.
Completed prune raw xml in "Prune LMB Tables" tool.
Decresed insert count on enrolment update.
Added optional_param to importnow.php to skip filetime check (add ?force=1 to url).
Added options to skip parsing of different types (person, course, crosslist, enrolments)









GENERAL
-------
This enrollment plugin can digest XML from the Luminis Message Broker, allowing realtime Banner to Moodle integration,
as well as full XML extractions from Banner.

You can use this module with or with Luminis Message Broker. If you do not use Luminis Message Broker, you can instead use
this module to import XML files from banner on a manual or automated basis.

This is a heavily modified version of the IMS Enterprise plugin.

Unlike the Moodle 1.5 version of this module, the current can be almost completely customized from with the standard Moodle
configuration pages. If you need to make changes for your specific install, please let me know, so I can look into making it
into a preference item (also take a look at the todo list below).


INSTALLATION
------------
1. Copy enrol/lmb into the enrol/ directory on your Moodle server.

2. Login to your Moodle server as an admin user, and visit the 'Notifications' page. Moodle will automatically setup the
tables for this module.

3. Under Course->Enrolments edit the settings for Luminis Message Broker.
NOTE: You must save the setting at least once, even if you don't make any changes, before you use this module. This is a
bug that will be fixed later.


UPDATING
________
If you are updating from a version of the Banner/LMB module before 0.8.1, please delete moodle/lang/en_utf8/enrol_lmb.php
and moodle/lang/en_utf8/help/enrol/lmb


LMB
---
You can configure, through the Luminis Message Broker interface, the LMB module to be a HTTP consumer. You should point it to
enrol/lmb/secure/liveimport.php on your Moodle server, and enter the LMB Security Username and Password that you entered in the
module settings. You should only use SSL (HTTPS) to ensure security of the interface.


USAGE
-----
When used with the Luminis Message Broker, you will generally import a complete XML from banner before the start of a
term/semester, and the Luminis Message Broker will continuously send messages that will keep Moodle up-to-date throughout the term. 

If you are using this module without Luminis Message Broker, you can configure the module to import a full XML file on a regular
basis. To do this, call importnow.php from a script or cron, much in the same way that the Moodle cron is polled.

You should disable users from being able to change the course and user idnumber fields, these are used to reference courses.

When doing mass imports, terms should come before courses and then crosslisted courses, users should come before enrolments (which
need to come courses and crosslistsing). There are tools in work that will make this order less important.

MULTI-FILE EXTRACTS
-------------------
Place a directory at the XML Folder path that you have specified in the settings. In this folder, place any XML files to be
processed (must end in .xml), as well as a empty file called 'start'. The 'start' file and directory must be writable by
the webserver.

When this is complete, you call extractprocess.php to preform the extract. 

When the processing starts, the module will remove the file 'start', and create a file called 'processing'. After extract processing
has completed, the 'processing' will be removed, and a 'done' file created. These status files allow people/scripts to check the
state of the run. It is important that the XML files are not modified/replaced while the extract is processing, or inconsistent
results may occur. Should a problem occur during extract, such as the processor dies, or the XML files are inadvertently
modified/removed, it is safe to re-process the extract with a consistent set of XML. 

In the event an incomplete XML set is processed, students/instructors may be inadvertently removed from their courses. Re-processing
with a complete XML set will reinstate users into their courses and no data is lost.


CHANGES
_______
View github for newly changed items
https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/commits/Release
