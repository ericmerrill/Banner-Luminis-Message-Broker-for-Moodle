Luminis Message Broker enrollment Module.
Version: 3.0.1
Moodle version: 2.6.0 through 3.0.x
Maintainer: Eric Merrill (merrill@oakland.edu)


Project Maintained at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/

Documentation at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/wiki


RECENT CHANGES
--------------
View full change log at https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/wiki/Change-Log

Release 3.0.1
Fix typo in setting up course default settings. Thanks to Michael Spall.


Release 3.0.0
Change default for "Use Moodle default course settings" to true.
Improve handling of Moodle default course settings.
Fix bug with missing/deleted categories.
Use the Moodle delete_user library function.
Only show enabled auth plugins on the settings page.
Add option to record SCT ID to the database. For upcoming grade exchange work.
Fix a bug that was causing unneeded database updates.
Add defaults for settings missing them (Thanks to Charles Fulton).


Release 2.9.0
Added option to filter live connections by hostnames and IP addresses.
Added options to whitelist or blacklist courses and enrollments by term.
Separated person city and address into separate settings.
Added live import URL to the Live Import settings area.
Number of strings updated, some settings renamed.
Fixed bug that would cause user addresses to be overwritten.
Fixed fatal DB error when phone, address, or city options are enabled in some cases.
Fixed false username collisions when username capitalization does not match.
Improved failure HTTP codes for live connections


Release 2.7.1
Get the term code from the XLS code if possible.
Prevent course names longer than 255 characters.
Moving minimum Moodle version to 2.4.


Release 2.7.0
Fix LMB down notification emails.


Release 2.6.2
Fix problem where users were not dropped from crosslists during an extract processing.
Improve performance in extract drops by using a recordset.
Define xmlcache variable.


Release 2.6.1
Replace all instances of mark_context_dirty.
Replace access to modinfo with course_modinfo for Moodle 2.6 and above.


Release 2.6.0
Converting all get_context_instance() to context_xxx::instance().


Release 2.5.3
Fix problem where categories were always created hidden with some settings. CONTRIB-4728.


Release 2.5.2
Fixing problem where clean install couldn't be done in Moodle version before 2.5 - Thanks to MDL-37726.
Actually make the list of terms to sort by id number - was supposed to happen in 2.5.1.


Release 2.5.1
Option to obey enrolment restriction dates.
Addition of 3 extra roletypes (03, 04, 05).
Fixing error when logging path location is blank.
Sort the menu of term in Reprocess Enrolments and Prune Tables.
LMB endpoint security must now be explicitly disabled. 
  For backwards compatibility, if LMB is enabled and no username or password is set, security is set as disabled during the upgrade.
Catch exception thrown when course shortname or idnumbers collide. (CONTRIB-4559)
Less strict status checking when making crosslists. Prevents partial completion.
Records are no longer saved in enrol_lmb_raw_xml when storexml is set to never. (https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/issues/3)
secure/xmlservice.php (which you probably shouldn't be using...) applies the same security as liveimport.php


If upgrading from a version before 2.5.1, be sure to read the full change log at:
https://github.com/merrill-oakland/Banner-Luminis-Message-Broker-for-Moodle/wiki/Change-Log



GENERAL
-------
This enrollment plugin can digest XML from the Luminis Message Broker , allowing realtime Banner to Moodle integration,
as well as full XML extractions from Banner.

You can use this module with or without Luminis Message Broker. If you do not use Luminis Message Broker, you can instead use
this module to import XML files from banner on a manual or automated basis.

I have tried to make this module as customizable as possible. If you need to make changes for your specific install, please let
me know, so I can look into making it into a preference item (also take a look at the todo list below).


INSTALLATION
------------
1. Make sure the folder is named lmb, then copy to the enrol/ directory on your Moodle server.

2. Login to your Moodle server as an admin user, and visit the 'Notifications' page. Moodle will run through the install process.

3. Under Site Administration > Plugins > Enrolments > Manage enrol plugins, enable Banner/Luminis Message Broker.

4. Click settings Banner/Luminis Message Broker, and configure as desired.
NOTE: You must save the setting at least once, even if you don't make any changes, before you use this module. This is a
bug that will be fixed later.


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
basis. To do this, call cli/fileprocess.php or cli/folderprocess.php from a script or cron, much in the same way that the Moodle
cron is polled.

You should disable users from being able to change the course and user idnumber fields, these are used by this module to track users
and courses.

When doing mass imports, terms should come before courses and then crosslisted courses, users should come before enrolments (which
need to come courses and crosslistsing). 


MULTI-FILE EXTRACTS
-------------------
Place a directory at the XML Folder path that you have specified in the settings. In this folder, place any XML files to be
processed (only files that end in .xml will be processed).

When this is complete, you call extractprocess.php or cli/folderprocess.php to preform the extract.

If you want to use status files, the folder and all files need to be writable by the web user. When the files are ready, add a file
named 'start' to the folder.

When the processing starts, the module will remove the file 'start', and create a file called 'processing'. After extract processing
has completed, the 'processing' will be removed, and a 'done' file created. These status files allow people/scripts to check the
state of the run. It is important that the XML files are not modified/replaced while the extract is processing, or inconsistent
results may occur. Should a problem occur during extract, such as the process dies, or the XML files are inadvertently
modified/removed, it is safe to re-process the extract with a consistent set of XML. 

In the event an incomplete XML set is processed, students/instructors may be inadvertently removed from their courses. Re-processing
with a complete XML set will reinstate users into their courses and no data is lost.
