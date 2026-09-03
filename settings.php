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

    // Allow the answer to be openly sought in AI.
    $settings->add(new admin_setting_configcheckbox(
        'local_parce/allowopenanswer',
        get_string('setting_allowopenanswer', 'local_parce'),
        get_string('setting_allowopenanswer_desc', 'local_parce'),
        0
    ));

    // The additional prompt when openly searching for the answer is enabled.
    $settings->add(new admin_setting_configtextarea(
        'local_parce/openanswer_prompt',
        get_string('setting_openanswer_prompt', 'local_parce'),
        get_string('setting_openanswer_prompt_desc', 'local_parce'),
        ''
    ));

    // Conversation cache settings heading.
    $settings->add(new admin_setting_heading(
        'local_parce/cache_heading',
        get_string('setting_cache_heading', 'local_parce'),
        get_string('setting_cache_heading_desc', 'local_parce')
    ));

    // Conversation cache maximum entries per conversation.
    $settings->add(new admin_setting_configtext(
        'local_parce/cache_maxentries',
        get_string('setting_cache_maxentries', 'local_parce'),
        get_string('setting_cache_maxentries_desc', 'local_parce'),
        40,
        '/^(?:[1-9]|[1-3][0-9]|40)$/'
    ));

    // Estimated-token limit for an active conversation.
    $settings->add(new admin_setting_configtext(
        'local_parce/conversation_maxtokens',
        get_string('setting_conversation_maxtokens', 'local_parce'),
        get_string('setting_conversation_maxtokens_desc', 'local_parce'),
        16000,
        '/^(?:[1-9][0-9]{0,3}|1[0-5][0-9]{3}|16000)$/'
    ));

    // Persistent history browser limits.
    $settings->add(new admin_setting_heading(
        'local_parce/history_heading',
        get_string('setting_history_heading', 'local_parce'),
        get_string('setting_history_heading_desc', 'local_parce')
    ));
    $historylimitvalidation = '/^(?:[1-9]|[1-9][0-9]|100)$/';
    $settings->add(new admin_setting_configtext(
        'local_parce/history_context_limit',
        get_string('setting_history_context_limit', 'local_parce'),
        get_string('setting_history_context_limit_desc', 'local_parce'),
        20,
        $historylimitvalidation
    ));
    $settings->add(new admin_setting_configtext(
        'local_parce/history_conversation_limit',
        get_string('setting_history_conversation_limit', 'local_parce'),
        get_string('setting_history_conversation_limit_desc', 'local_parce'),
        20,
        $historylimitvalidation
    ));
    $settings->add(new admin_setting_configtext(
        'local_parce/history_search_limit',
        get_string('setting_history_search_limit', 'local_parce'),
        get_string('setting_history_search_limit_desc', 'local_parce'),
        50,
        $historylimitvalidation
    ));

    $ADMIN->add('localplugins', $settings);
}
