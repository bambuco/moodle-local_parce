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
 * Optional administration page for stored Solr file text.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_parce\local\search\file_content_schema;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login(null, false);
require_capability('moodle/site:config', $context);

$pageurl = new moodle_url('/local/parce/filecontent.php');
if (!file_content_schema::is_solr_configured()) {
    $PAGE->set_context($context);
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('filecontent_page', 'local_parce'));
    $PAGE->set_heading(get_string('filecontent_page', 'local_parce'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('filecontent_page', 'local_parce'));
    echo $OUTPUT->notification(get_string('filecontent_solr_required', 'local_parce'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

admin_externalpage_setup('local_parce_filecontent');

$action = optional_param('action', '', PARAM_ALPHA);
if (data_submitted() && confirm_sesskey() && ($action === 'check' || $action === 'prepare')) {
    $schema = new file_content_schema();
    if ($action === 'prepare') {
        $SESSION->local_parce_filecontent_status = $schema->prepare();
    } else {
        $SESSION->local_parce_filecontent_status = $schema->check();
    }
    redirect($pageurl);
}

$status = null;
if (!empty($SESSION->local_parce_filecontent_status) && is_array($SESSION->local_parce_filecontent_status)) {
    $status = $SESSION->local_parce_filecontent_status;
    unset($SESSION->local_parce_filecontent_status);
}

$links = (object) [
    'fileindexing' => (new moodle_url('/admin/search.php', ['query' => 'fileindexing']))->out(false),
    'searchareas' => (new moodle_url('/admin/searchareas.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('filecontent_page', 'local_parce'));

if ($status !== null) {
    foreach (file_content_schema::notices($status) as $notice) {
        echo $OUTPUT->notification($notice['message'], $notice['type']);
    }
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('filecontent_check', 'local_parce'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'check',
    'class' => 'btn btn-secondary me-2',
]);
echo html_writer::tag('button', get_string('filecontent_prepare', 'local_parce'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'prepare',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo html_writer::div(get_string('filecontent_steps', 'local_parce', $links), 'mt-4');

echo $OUTPUT->footer();
