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

namespace enrol_lmb\privacy;

use core_privacy\local\metadata\collection;

/**
 * Tests for the enrol_lmb privacy provider.
 *
 * @package    enrol_lmb
 * @copyright  2026 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_lmb\privacy\provider
 */
final class provider_test extends \advanced_testcase {

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('enrol_lmb'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        sort($names);
        $this->assertSame(['enrol_lmb_enrolments', 'enrol_lmb_people', 'enrol_lmb_raw_xml'], $names);
    }
}
