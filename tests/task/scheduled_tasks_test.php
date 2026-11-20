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

namespace enrol_lmb\task;

/**
 * Tests for the enrol_lmb scheduled tasks.
 *
 * @package    enrol_lmb
 * @copyright  2026 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_lmb\task\import_files::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_lmb\task\check_lmb::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_lmb\task\unhide_courses::class)]
final class scheduled_tasks_test extends \advanced_testcase {

    /** @var string LMB course sourcedid used in tests. */
    const SOURCEDID = '12345.202610';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,lmb');
        set_config('logtolocation', '', 'enrol_lmb');
    }

    /**
     * Create a hidden Moodle course backed by an LMB course starting tomorrow,
     * with unhide config set so the unhide window covers it.
     *
     * @param int $nextunhiderun value for the nextunhiderun config
     * @return \stdClass the course
     */
    private function setup_unhide(int $nextunhiderun): \stdClass {
        global $DB;
        $DB->insert_record('enrol_lmb_courses', (object) [
            'sourcedid' => self::SOURCEDID,
            'coursenumber' => '12345',
            'term' => '202610',
            'startdate' => time() + DAYSECS,
            'enddate' => time() + 100 * DAYSECS,
            'timemodified' => time(),
        ]);
        $course = $this->getDataGenerator()->create_course(['idnumber' => self::SOURCEDID, 'visible' => 0]);
        set_config('cronunhidecourses', 1, 'enrol_lmb');
        set_config('cronunhidedays', 2, 'enrol_lmb');
        set_config('nextunhiderun', $nextunhiderun, 'enrol_lmb');
        set_config('prevunhideendtime', time() - DAYSECS, 'enrol_lmb');
        return $course;
    }

    /**
     * Set config so the LMB check would email if enabled.
     *
     * @param int $enabled lmbcheck value
     */
    private function setup_lmbcheck(int $enabled): void {
        set_config('lmbcheck', $enabled, 'enrol_lmb');
        set_config('lastlmbmessagetime', 1, 'enrol_lmb');
        set_config('bizgrace', 1, 'enrol_lmb');
        set_config('nonbizgrace', 1, 'enrol_lmb');
        set_config('emails', 'a@example.com', 'enrol_lmb');
    }

    public function test_tasks_registered(): void {
        foreach (['import_files', 'check_lmb', 'unhide_courses'] as $name) {
            $task = \core\task\manager::get_scheduled_task('\\enrol_lmb\\task\\' . $name);
            $this->assertNotNull($task, $name);
            $this->assertSame('*/10', $task->get_minute(), $name);
        }
    }

    public function test_unhide_courses_unhides_course_in_window(): void {
        global $DB;
        $course = $this->setup_unhide(time() - 1);
        $this->expectOutputRegex('/cron unhide/');
        (new unhide_courses())->execute();
        $this->assertEquals(1, $DB->get_field('course', 'visible', ['id' => $course->id]));
        // Next run is moved to the end of today.
        $this->assertGreaterThan(time(), (int) get_config('enrol_lmb', 'nextunhiderun'));
    }

    public function test_unhide_courses_waits_for_next_run(): void {
        global $DB;
        $course = $this->setup_unhide(time() + HOURSECS);
        (new unhide_courses())->execute();
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $course->id]));
    }

    public function test_unhide_courses_off(): void {
        global $DB;
        $course = $this->setup_unhide(time() - 1);
        set_config('cronunhidecourses', 0, 'enrol_lmb');
        (new unhide_courses())->execute();
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $course->id]));
    }

    public function test_check_lmb_off_sends_nothing(): void {
        $this->setup_lmbcheck(0);
        $sink = $this->redirectEmails();
        (new check_lmb())->execute();
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_check_lmb_on_sends_email(): void {
        $this->setup_lmbcheck(1);
        $sink = $this->redirectEmails();
        $this->expectOutputRegex('/Last luminis message received/');
        (new check_lmb())->execute();
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame(get_string('nomessage', 'enrol_lmb'), $messages[0]->subject);
    }

    public function test_import_files_no_location(): void {
        set_config('cronxmlfile', 1, 'enrol_lmb');
        unset_config('bannerxmllocation', 'enrol_lmb');
        $this->expectOutputRegex('/XML file not found/');
        (new import_files())->execute();
        $this->assertDebuggingNotCalled();
        $this->assertFalse(get_config('enrol_lmb', 'xmlfiletime'));
    }

    public function test_import_files_missing_file(): void {
        set_config('cronxmlfile', 1, 'enrol_lmb');
        set_config('bannerxmllocation', '/nonexistent/lmb.xml', 'enrol_lmb');
        $this->expectOutputRegex('/XML file not found/');
        (new import_files())->execute();
        $this->assertDebuggingNotCalled();
        $this->assertFalse(get_config('enrol_lmb', 'xmlfiletime'));
    }

    public function test_tasks_skip_when_disabled(): void {
        global $DB;
        set_config('enrol_plugins_enabled', 'manual');
        $course = $this->setup_unhide(time() - 1);
        $this->setup_lmbcheck(1);
        $sink = $this->redirectEmails();
        (new unhide_courses())->execute();
        (new check_lmb())->execute();
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $course->id]));
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_folder_import_respects_stale_flag(): void {
        global $DB;
        $dir = make_request_directory();
        copy(__DIR__ . '/../fixtures/smoke.xml', $dir . '/extract.xml');
        set_config('cronxmlfolder', 1, 'enrol_lmb');
        set_config('bannerxmlfolder', $dir, 'enrol_lmb');
        set_config('processingfolder', 1, 'enrol_lmb');
        (new import_files())->execute();
        $this->assertSame(0, $DB->count_records('enrol_lmb_people'));
        $this->assertEquals(1, get_config('enrol_lmb', 'processingfolder'));
    }
}
