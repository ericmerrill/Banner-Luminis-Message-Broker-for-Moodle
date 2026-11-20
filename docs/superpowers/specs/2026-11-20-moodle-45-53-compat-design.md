# enrol_lmb: Moodle 4.5–5.3 compatibility (minimal port)

## Intent

enrol_lmb (Banner/Luminis Message Broker enrolment) was last fully supported on
Moodle 3.9. This is a general update so that one plugin release installs and
runs correctly on every Moodle version from 4.5 through 5.3, on current PHP.

The change is deliberately **minimal**: only what is needed to work on current
Moodle and PHP. A deeper modernisation (restructuring `lib.php` into classes,
new parsing, broad tests) may come later and is out of scope here.

## Goals

- One release supports Moodle 4.5, 5.0, 5.1, 5.2, 5.3.
- Runs on PHP 8.1–8.4 (4.5: 8.1–8.3; 5.0/5.1: 8.2–8.4; 5.2/5.3: 8.3–8.4).
- Everything that used to run on cron runs again.
- Automated checks (moodle-plugin-ci on GitHub Actions) prove install/upgrade
  and basic behaviour across the range.

## Non-goals

- Supporting Moodle older than 4.5. That stays on the `Release_32` branch.
- Refactoring, restyling, renaming, or reorganising existing code.
- New features or behaviour changes.
- Full privacy export/delete support.

## Anti-goals

- Any change in how an existing site's LMB/Banner data is processed (course
  creation, enrolments, crosslists, categories, users). A site upgrading from
  3.8.0 must behave the same, apart from cron now actually running.
- Breaking the existing HTTP endpoints in `secure/` that LMB posts to.

## Constraints

- PHP syntax must stay 8.1-compatible (lowest PHP for Moodle 4.5).
- No back-compat code for Moodle < 4.5 is needed; it may be removed where it
  is in the way.
- Moodle 5.1+ moves plugins under `public/`; a `public/config.php` stub loads
  the real config, so the existing relative `config.php` requires keep working.
  No special handling needed.
- Upgrade path from existing installs (plugin version 2018042501 / 2018042502)
  must work; existing `db/upgrade.php` steps are kept as-is.

## Approaches

1. **Single release requiring 4.5, CI-verified across 4.5–5.3** (chosen).
   Simplest, matches "one version for the whole range", lets us delete
   pre-4.5 branches.
2. Separate branch per Moodle version — rejected: user wants one release.
3. One release that also keeps 3.9–4.4 working via version checks —
   rejected: older support stays on `Release_32`; it would keep dead code
   and conflict with the PHP 8.1 floor.

## Decisions

- **Branches:** `master` is first fast-forwarded to `DEF-4830` (which is
  `Release_32` + `d037f90`, the PHP 8 fix in `add_to_metacourse()`; a clean
  fast-forward, no merge commit). This work then targets `master`.
  `Release_32` is left as-is and keeps pre-4.5 support.
- **Stale cron state on upgrade: resume where it stopped.** The tasks pick up
  existing config (`prevunhideendtime`, `nextunhiderun`, `processingfolder`,
  `xmlfiletime`) unchanged — no upgrade step resets it. This matches 3.8.0
  behaviour. The release notes must warn admins that, on first run after
  upgrade: the unhide task will unhide LMB courses starting between the old
  `prevunhideendtime` and now (+ `cronunhidedays`); a stale `processingfolder`
  flag blocks folder import until cleared; and a leftover extract in the
  folder will be processed (with drops, if `bannerxmlfoldercomp` is on).
  Admins should review those settings/folders before enabling cron.
- **version.php:** `requires = 2024100700` (4.5.0), `supported = [405, 503]`,
  `version = 2018042503` (DEF-4830's 2018042502 + 1, not rolled to a current
  date for now), `release = '4.0.0'`,
  `maturity = MATURITY_STABLE`, remove `$plugin->cron`.
- **Cron → scheduled tasks.** Moodle 4.5 no longer runs legacy plugin
  `cron()` methods. Replace with scheduled tasks in `classes/task/` plus
  `db/tasks.php`. Each task just instantiates `enrol_lmb_plugin` and calls the
  existing method(s), keeping current config gates (`lmbcheck`, `cronxmlfile`,
  `cronxmlfolder`, `cronunhidecourses`). *(Builder's call: three tasks —
  import file/folder, LMB-alive check, unhide courses — each defaulting to
  every 10 minutes, matching the old `$plugin->cron = 600`.)* The old `cron()`
  method is removed or reduced so nothing calls dead code.
- **PHP fixes:** fix only actual errors and deprecations on PHP 8.1–8.4
  (e.g. dynamic properties, null passed to string functions, `count()` on
  non-countables, implicitly nullable parameters deprecated in 8.4, removed
  functions). No style-only edits.
- **Old Moodle compatibility branches:** remove only where they're dead or
  break, e.g. the `coursecat` fallback in `settings.php` becomes
  `core_course_category` only. `numsections` and direct `user` table
  inserts/updates are left alone unless they demonstrably fail on 4.5+.
- **Privacy:** add `classes/privacy/provider.php` implementing a
  metadata-only provider: declares the `enrol_lmb_people`,
  `enrol_lmb_enrolments` and `enrol_lmb_raw_xml` tables and that data comes
  from an external system (Banner/LMB). Language strings added accordingly.
- **CI:** `.github/workflows/ci.yml` using moodle-plugin-ci, matrix of
  each Moodle branch 4.5–5.3 at its lowest and highest supported PHP, on
  PostgreSQL and MariaDB/MySQL. Runs: phplint, validate, savepoints,
  phpdoc/codechecker, PHPUnit. *(Builder's call: since this is a minimal
  port of old code, codechecker/phpdoc run with `--max-warnings` relaxed or
  as non-blocking so pre-existing style debt doesn't fail the build; real
  errors still fail.)*
- **Tests:** small PHPUnit tests for the new scheduled tasks (each runs and
  respects its config gate) and the privacy provider metadata. Plus a smoke
  test that processes a small sample LMB XML file end-to-end (person,
  course, enrolment) so CI exercises the core path on every version.

## Left to the builder

- Exact task class names and schedules (within the above).
- Exact CI matrix rows and moodle-plugin-ci version.
- How to find PHP problems (running CI, phpcompatibility sniffs, manual
  review) and the individual fixes.
- Sample XML content for the smoke test.
- Release notes / README update describing the new minimum, the tasks,
  and the upgrade warning above (the warning itself is required).
