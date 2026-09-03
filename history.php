<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Persistent conversation history portal.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$chatid = optional_param('chatid', 0, PARAM_INT);
$mode = optional_param('mode', 'own', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$conversationkey = optional_param('conversationkey', '', PARAM_ALPHANUM);

require_login();
if (isguestuser()) {
    throw new moodle_exception('error_guest_history', 'local_parce');
}
if (!in_array($mode, ['own', 'admin'], true)) {
    throw new invalid_parameter_exception('Invalid history request.');
}

$admin = $mode === 'admin';
$context = $chatid ? context::instance_by_id($chatid, IGNORE_MISSING) : null;
if ($admin) {
    if (!$context || !in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSE], true)) {
        throw new invalid_parameter_exception('Invalid history request.');
    }
    require_capability('local/parce:viewallchats', $context);
} else {
    $userid = $USER->id;
}
if ($conversationkey !== '' && (!$chatid || !$userid)) {
    throw new invalid_parameter_exception('Invalid history request.');
}

$pagecontext = $context ?: context_system::instance();
$params = ['mode' => $mode];
if ($chatid) {
    $params['chatid'] = $chatid;
}
if ($admin && $userid) {
    $params['userid'] = $userid;
}
if ($conversationkey !== '') {
    $params['conversationkey'] = $conversationkey;
}
$PAGE->set_context($pagecontext);
$PAGE->set_url(new core\url('/local/parce/history.php', $params));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('historytitle', 'local_parce'));
$PAGE->set_heading(get_string('historytitle', 'local_parce'));
$contextname = $context ? $context->get_context_name(false) : '';
$PAGE->requires->js_call_amd('local_parce/history', 'init', [[
    'chatid' => $chatid,
    'userid' => $userid,
    'conversationkey' => $conversationkey,
    'mode' => $mode,
    'contextname' => $contextname,
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_parce/history', []);
echo $OUTPUT->footer();
