# Changes

## 4.0.0

- Requires Moodle 4.5. Supports Moodle 4.5 through 5.3, on PHP 8.1 through 8.4.
  Sites on older Moodle versions should stay on the `Release_32` branch.
- Moodle no longer runs the old plugin cron, so its work is now done by three
  scheduled tasks. Each runs every 10 minutes by default, can be changed under
  Site administration > Server > Tasks > Scheduled tasks, and only runs while
  the LMB enrolment plugin is enabled:
  - **Import LMB/Banner XML files** (`\enrol_lmb\task\import_files`) — the
    "cron XML file" and "cron XML folder" imports.
  - **Check for recent LMB messages** (`\enrol_lmb\task\check_lmb`) — the LMB
    downtime email check.
  - **Unhide upcoming LMB courses** (`\enrol_lmb\task\unhide_courses`).
- Fixed the LMB downtime alert email, which failed on current Moodle.
- The file import no longer errors when the configured XML file is missing.
- Added a privacy metadata provider describing the personal data the plugin
  stores. Privacy export and deletion are not yet supported.

### Upgrade warning

On Moodle 4.x the plugin's cron has not been running, so its saved state may be
old. The new scheduled tasks pick up exactly where the old cron stopped. Before
upgrading, or by disabling the three tasks until you have checked, review:

- **Course unhiding:** the first run unhides every LMB course whose start date
  falls between the last time cron ran (`prevunhideendtime`) and today plus the
  "Unhide this many days before course start" setting. Courses you have hidden by hand in that window will be
  made visible again.
- **File import blocked:** if a file import was interrupted, the
  `processingfile` setting stays set and the file import will silently skip
  every run until it is cleared (set it to 0). The folder import has a similar
  `processingfolder` setting; check that it is also 0.
- **Old extracts:** any extract still in the XML folder will be processed on the
  first run. If "Treat the Banner XML folder as comprehensive" (`bannerxmlfoldercomp`) is on,
  enrolments missing from that extract will be dropped.
