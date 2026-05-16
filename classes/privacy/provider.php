<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_quicknote\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_quicknote.
 *
 * @package    local_quicknote
 * @category   privacy
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_quicknote_notes', [
            'userid' => 'privacy:metadata:local_quicknote_notes:userid',
            'courseid' => 'privacy:metadata:local_quicknote_notes:courseid',
            'content' => 'privacy:metadata:local_quicknote_notes:content',
            'quote' => 'privacy:metadata:local_quicknote_notes:quote',
            'quoteurl' => 'privacy:metadata:local_quicknote_notes:quoteurl',
            'url' => 'privacy:metadata:local_quicknote_notes:url',
            'timecreated' => 'privacy:metadata:local_quicknote_notes:timecreated',
            'timemodified' => 'privacy:metadata:local_quicknote_notes:timemodified',
        ], 'privacy:metadata:local_quicknote_notes');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Notes are associated with courses. If courseid is 0, they are associated with the system context.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_quicknote_notes} qn ON (c.instanceid = qn.courseid AND c.contextlevel = :courselevel)
                                                  OR (qn.courseid = 0 AND c.id = :systemcontextid)
                 WHERE qn.userid = :userid";

        $params = [
            'courselevel' => CONTEXT_COURSE,
            'systemcontextid' => context_system::instance()->id,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = ['userid' => $userid] + $contextparams;

        $sql = "SELECT qn.*, c.id AS contextid
                  FROM {local_quicknote_notes} qn
                  JOIN {context} c ON (c.instanceid = qn.courseid AND c.contextlevel = :courselevel)
                                   OR (qn.courseid = 0 AND c.id = :systemcontextid)
                 WHERE qn.userid = :userid AND c.id $contextsql";

        $params['courselevel'] = CONTEXT_COURSE;
        $params['systemcontextid'] = context_system::instance()->id;

        $notes = $DB->get_recordset_sql($sql, $params);

        foreach ($notes as $note) {
            $context = context::instance_by_id($note->contextid);
            $data = (object) [
                'content' => $note->content,
                'quote' => $note->quote,
                'quoteurl' => $note->quoteurl,
                'url' => $note->url,
                'timecreated' => transform::datetime($note->timecreated),
                'timemodified' => transform::datetime($note->timemodified),
            ];

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_quicknote'), $note->id],
                $data
            );
        }
        $notes->close();
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('local_quicknote_notes', ['courseid' => $context->instanceid]);
        } else if ($context->id == context_system::instance()->id) {
            $DB->delete_records('local_quicknote_notes', ['courseid' => 0]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $systemcontextid = context_system::instance()->id;
        $courseids = [];
        $deletesystem = false;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseids[] = $context->instanceid;
            } else if ($context->id == $systemcontextid) {
                $deletesystem = true;
            }
        }

        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $params = ['userid' => $userid] + $inparams;
            $select = "userid = :userid AND courseid $insql";
            $DB->delete_records_select('local_quicknote_notes', $select, $params);
        }

        if ($deletesystem) {
            $DB->delete_records('local_quicknote_notes', ['userid' => $userid, 'courseid' => 0]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_COURSE) {
            $sql = "SELECT userid
                      FROM {local_quicknote_notes}
                     WHERE courseid = :courseid";
            $params = ['courseid' => $context->instanceid];
            $userlist->add_from_sql('userid', $sql, $params);
        } else if ($context->id == context_system::instance()->id) {
            $sql = "SELECT userid
                      FROM {local_quicknote_notes}
                     WHERE courseid = 0";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        if ($context->contextlevel == CONTEXT_COURSE) {
            $params = ['courseid' => $context->instanceid] + $userparams;
            $select = "courseid = :courseid AND userid $usersql";
            $DB->delete_records_select('local_quicknote_notes', $select, $params);
        } else if ($context->id == context_system::instance()->id) {
            $params = ['courseid' => 0] + $userparams;
            $select = "courseid = :courseid AND userid $usersql";
            $DB->delete_records_select('local_quicknote_notes', $select, $params);
        }
    }
}
