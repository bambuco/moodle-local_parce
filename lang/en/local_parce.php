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
 * English language strings for local_parce
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aria_close_chat'] = 'Close chat';
$string['aria_loading'] = 'Loading';
$string['aria_message_input'] = 'Message input';
$string['chat_bubble_label'] = 'Open chat';
$string['chat_error'] = 'An error occurred. Please try again later.';
$string['chat_error_processing'] = 'Sorry, I could not process your question. Please try again.';
$string['chat_loading'] = 'Processing your question...';
$string['chat_send'] = 'Send';
$string['chat_welcome'] = 'Hello! How can we help you today?';
$string['default_answer_question_prompt'] = 'Answer the user\'s question accurately, clearly, and concisely. Using only the text of "content" and considering the history of "previous". Provide only information suitable for an educational context.';
$string['default_question_plan_prompt'] = 'Based on the user\'s JSON, respond with valid JSON containing:\n"type": must be one of: greeting, content, dates\n"params": For type "content" the keywords that define the content to search for, for dates the date ranges, for "greeting" a random+respectful+cordial\nRespond only the pure JSON, without code blocks, without markdown and without additional text.';
$string['defaulttitle'] = 'Questions & Answers';
$string['error_ai_failed'] = 'Failed to generate a response';
$string['error_ai_unavailable'] = 'AI service is not available at this moment.';
$string['error_empty_question'] = 'Please enter a question.';
$string['error_no_content'] = 'No response content was generated.';
$string['error_processing_question'] = 'An error occurred while processing your question. Please try again.';
$string['placeholder'] = 'Type your question...';
$string['pluginname'] = 'Parce - Q&A Chat Widget';
$string['setting_ai_instructions_heading'] = 'AI Action System Instructions';
$string['setting_ai_instructions_heading_desc'] = 'Configure the system instructions for each AI action. These instructions guide the AI model behavior for specific question types.';
$string['setting_answer_question_prompt'] = 'Answer Question System Instruction';
$string['setting_answer_question_prompt_desc'] = 'The system instruction that guides the AI when answering user questions directly. This instruction is sent to the AI model with every answer_question request.';
$string['setting_chat_title'] = 'Chat Window Title';
$string['setting_chat_title_desc'] = 'The title displayed in the chat window header';
$string['setting_enable_guests'] = 'Enable for Guest Users';
$string['setting_enable_guests_desc'] = 'Allow guest users to access the chat widget';
$string['setting_enabled'] = 'Enable Parce Chat Widget';
$string['setting_enabled_desc'] = 'When enabled, the floating chat widget will appear on all pages for logged-in users';
$string['setting_question_plan_prompt'] = 'Question Plan System Instruction';
$string['setting_question_plan_prompt_desc'] = 'The system instruction that guides the AI when creating a structured plan or outline for a question or topic. This instruction is sent to the AI model with every question_plan request.';
