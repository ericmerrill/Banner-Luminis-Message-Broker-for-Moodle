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
 * Privacy provider for enrol_lmb.
 *
 * Declares the personal data received from Banner/LMB and stored by this plugin.
 *
 * @package    enrol_lmb
 * @copyright  2026 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /** @var array Fields stored per table. */
    const TABLES = [
        'enrol_lmb_people' => ['sourcedid', 'sctid', 'fullname', 'familyname', 'givenname', 'nickname', 'email',
            'username', 'telephone', 'adrstreet', 'locality', 'region', 'country', 'academicmajor', 'customfield1',
            'timemodified'],
        'enrol_lmb_enrolments' => ['coursesourcedid', 'personsourcedid', 'term', 'role', 'status', 'beginrestricttime',
            'endrestricttime', 'timemodified'],
        'enrol_lmb_raw_xml' => ['headers', 'xml', 'timereceived'],
    ];

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection the collection to add to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $table => $fields) {
            $fieldstrings = [];
            foreach ($fields as $field) {
                $fieldstrings[$field] = "privacy:metadata:{$table}:{$field}";
            }
            $collection->add_database_table($table, $fieldstrings, "privacy:metadata:{$table}");
        }
        return $collection;
    }
}
