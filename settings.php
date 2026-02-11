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
 * Admin settings for local_parce
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_parce', get_string('pluginname', 'local_parce'));

    // Enable/disable the chat widget.
    $settings->add(new admin_setting_configcheckbox(
        'local_parce/enabled',
        get_string('setting_enabled', 'local_parce'),
        get_string('setting_enabled_desc', 'local_parce'),
        1
    ));

    // Chat title.
    $settings->add(new admin_setting_configtext(
        'local_parce/chat_title',
        get_string('setting_chat_title', 'local_parce'),
        get_string('setting_chat_title_desc', 'local_parce'),
        get_string('defaulttitle', 'local_parce')
    ));

    // Enable for guests.
    $settings->add(new admin_setting_configcheckbox(
        'local_parce/enable_guests',
        get_string('setting_enable_guests', 'local_parce'),
        get_string('setting_enable_guests_desc', 'local_parce'),
        0
    ));

    // AI Action System Instructions.
    $settings->add(new admin_setting_heading(
        'local_parce/ai_instructions_heading',
        get_string('setting_ai_instructions_heading', 'local_parce'),
        get_string('setting_ai_instructions_heading_desc', 'local_parce')
    ));

    // Question plan system instruction.
    $settings->add(new admin_setting_configtextarea(
        'local_parce/question_plan_prompt',
        get_string('setting_question_plan_prompt', 'local_parce'),
        get_string('setting_question_plan_prompt_desc', 'local_parce'),
        get_string('default_question_plan_prompt', 'local_parce')
    ));

    // Answer question system instruction.
    $settings->add(new admin_setting_configtextarea(
        'local_parce/answer_question_prompt',
        get_string('setting_answer_question_prompt', 'local_parce'),
        get_string('setting_answer_question_prompt_desc', 'local_parce'),
        get_string('default_answer_question_prompt', 'local_parce')
    ));

    $ADMIN->add('localplugins', $settings);
}
