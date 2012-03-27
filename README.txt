Luminis Message Broker enrollment Module.
Version: 2.0.5
Moodle version: 2.x
Maintainer: Eric Merrill (merrill@oakland.edu)







CHANGES
-------
Since 2.0.1
Added option to compute number of sections based on course duration.
Removed make enrollable option - not supported in Moodle 2.
Use Moodle's internal course creation tool, enrollment methods added based on default settings.
Now uses new Moodle 2 meta course system and should work properly.
Fixed problem with auth names throwing errors and not loading and setting pages.


Since 2.0.0
Changes of sourcedidsource and dept columns in lmb_categories to char(128) and char(255) respectively.
Fixes problem could cause failure when included in a new Moodle install.
Added nickname processing


Since 2.0.0b1
Minor fix to email domain limitation


Since 2.0.0a5
Minor fix to xmlservice file


Since 2.0.0a3
Fixes to errors when use has no password
Fixes to errors when use has no email
Fixes to enrol/unenroll errors
Tools fixes
Fix error when no department found
Fixed error when no username
Help strings added to config page


Since 2.0.0a2
No email address now caught
term sourcedid converted to 128 varchar









TODO
----
Catch exceptions for update_course and similar
Use moodle delete functions
Prune XML table
-Reprocess enrolments (enrol/unenrol need to be done in plugin now)
Check into term length dependancies
Load config descriptions
Filter Terms
meta sync changes (when to sync)





GENERAL
-------
This enrollment plugin can digest XML from the Luminis Message Broker, allowing realtime Banner to Moodle integration, as well as full XML extractions from Banner.

You can use this module with or with Luminis Message Broker. If you do not use Luminis Message Broker, you can instead use this module to import XML files from banner on a manual or automated basis.

This is a heavily modified version of the IMS Enterprise plugin.

Unlike the Moodle 1.5 version of this module, the current can be almost completely customized from with the standard Moodle configuration pages. If you need to make changes for your specific install, please let me know, so I can look into making it into a preference item (also take a look at the todo list below).


INSTALLATION
------------
1. Copy enrol/lmb into the enrol/ directory on your Moodle server.

2. Login to your Moodle server as an admin user, and visit the 'Notifications' page. Moodle will automatically setup the tables for this module.

3. Under Course->Enrolments edit the settings for Luminis Message Broker.
NOTE: You must save the setting at least once, even if you don't make any changes, before you use this module. This is a bug that will be fixed later.


UPDATING
________
If you are updating from a version of the Banner/LMB module before 0.8.1, please delete moodle/lang/en_utf8/enrol_lmb.php and moodle/lang/en_utf8/help/enrol/lmb


LMB
---
You can configure, through the Luminis Message Broker interface, the LMB module to be a HTTP consumer. You should point it to enrol/lmb/secure/liveimport.php on your Moodle server, and enter the LMB Security Username and Password that you entered in the module settings. You should only use SSL (HTTPS) to ensure security of the interface.


USAGE
-----
When used with the Luminis Message Broker, you will generally import a complete XML from banner before the start of a term/semester, and the Luminis Message Broker will continuously send messages that will keep Moodle up-to-date throughout the term. 

If you are using this module without Luminis Message Broker, you can configure the module to import a full XML file on a regular basis. To do this, call importnow.php from a script or cron, much in the same way that the Moodle cron is polled.

You should disable users from being able to change the course and user idnumber fields, these are used to reference courses. This may change in the future.

When doing mass imports, terms should come before courses and then crosslisted courses, users should come before enrolments (which need to come courses and crosslistsing). There are tools in work that will make this order less important.

MULTI-FILE EXTRACTS
-------------------
Place a directory at the XML Folder path that you have specified in the settings. In this folder, place any XML files to be processed (must end in .xml), as well as a empty file called 'start'. The 'start' file and directory must be writable by the webserver.

When this is complete, you call extractprocess.php to preform the extract. 

When the processing starts, the module will remove the file 'start', and create a file called 'processing'. After extract processing has completed, the 'processing' will be removed, and a 'done' file created. These status files allow people/scripts to check the state of the run. It is important that the XML files are not modified/replaced while the extract is processing, or inconsistent results may occur. Should a problem occur during extract, such as the processor dies, or the XML files are inadvertently modified/removed, it is safe to re-process the extract with a consistent set of XML. 

In the event an incomplete XML set is processed, students/instructors may be inadvertently removed from their courses. Re-processing with a complete XML set will reinstate users into their courses and no data is lost.


CHANGES
_______
View tracker for newly changed items
http://tracker.moodle.org/secure/IssueNavigator.jspa?reset=true&&pid=10033&component=10593&sorter/field=status&sorter/order=DESC&sorter/field=updated&sorter/order=DESC&sorter/field=issuekey&sorter/order=ASC


Changes since 0.8.0
-------
Now displays the module version number on the config page.
LMB import security can now be applied in the module, instead of needing to modify server settings.
New option for setting a course as enrollable or not.
LMB Check grace period is now a text field to allow an arbitrary number of minutes.
All lang and help files moved into the module directory.
Added [TERMNAME] option to course title options, which uses the full name of the term.
Tracking categories in a table now. Because of this, a categories can be renamed once they are created.







TODO
----

Coding:
Term id code may not be an int?
Use Moodle logging system instead of text file?


Config:
Default settings
Required settings
Verify config inputs
Delete courses when directed


