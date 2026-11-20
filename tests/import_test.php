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

namespace enrol_lmb;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/lmb/lib.php');

/**
 * Smoke tests for importing LMB/Banner XML.
 *
 * @package    enrol_lmb
 * @copyright  2026 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_lmb_plugin::class)]
final class import_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,lmb');
        set_config('logtolocation', '', 'enrol_lmb');
        set_config('createnewusers', 1, 'enrol_lmb');
    }

    /**
     * Get a quiet plugin instance.
     *
     * @return \enrol_lmb_plugin
     */
    private function get_plugin(): \enrol_lmb_plugin {
        $plugin = new \enrol_lmb_plugin();
        $plugin->silent = true;
        return $plugin;
    }

    public function test_process_file_smoke(): void {
        global $DB;
        $this->get_plugin()->process_file(__DIR__ . '/fixtures/smoke.xml', true);

        $this->assertTrue($DB->record_exists('enrol_lmb_terms', ['sourcedid' => '202610']));
        $this->assertTrue($DB->record_exists('enrol_lmb_courses', ['sourcedid' => '12345.202610']));
        $course = $DB->get_record('course', ['idnumber' => '12345.202610'], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('enrol_lmb_people', ['sourcedid' => '9000001']));
        $user = $DB->get_record('user', ['idnumber' => '9000001'], '*', MUST_EXIST);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user));
    }

    public function test_process_xml_line_person(): void {
        global $DB;
        $xml = file_get_contents(__DIR__ . '/fixtures/smoke.xml');
        $this->assertSame(1, preg_match('{<person>.*?</person>}s', $xml, $matches));
        $this->assertTrue($this->get_plugin()->process_xml_line($matches[0]));
        $this->assertTrue($DB->record_exists('user', ['idnumber' => '9000001']));
    }
}
