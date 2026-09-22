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
 * Site report of Parce conversations by user and context.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use local_parce\reportbuilder\local\systemreports\conversations;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_parce_conversations', '', [], '', ['pagelayout' => 'report']);

$context = context_system::instance();
require_capability('local/parce:viewallchats', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conversationsreport', 'local_parce'));

$report = system_report_factory::create(conversations::class, $context);
echo $report->output();

echo $OUTPUT->footer();
