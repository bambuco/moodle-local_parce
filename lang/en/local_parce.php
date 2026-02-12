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

$string['answer_notfound'] = 'Sorry, I couldn\'t find an answer to your question in the available content.';
$string['aria_close_chat'] = 'Close chat';
$string['aria_loading'] = 'Loading';
$string['aria_message_input'] = 'Message input';
$string['cachedef_conversation'] = 'Cache for session conversation history';
$string['chat_bubble_label'] = 'Open chat';
$string['chat_error'] = 'An error occurred. Please try again later.';
$string['chat_error_processing'] = 'Sorry, I could not process your question. Please try again.';
$string['chat_loading'] = 'Processing your question...';
$string['chat_send'] = 'Send';
$string['chat_welcome'] = 'Hello! How can we help you today?';
$string['default_answer_question_prompt'] = 'You are a Retrieval Constrained QA response system.

Permitted Sources:
1. The text between <CONTENT_START> and <CONTENT_END>
2. The history between <PREVIOUS_START> and <PREVIOUS_END>

User Question:
The user question is between the <QUESTION_START> and <QUESTION_END> tags.

MANDATORY RULES:

1. Use only information that appears explicitly within the delimited content.
2. Do not add external knowledge.
3. Do not fill in information using prior knowledge.
4. Do not make inferences that are not explicitly supported by the text.
5. Do not rephrase by adding additional context.
6. If the answer is not explicitly in the content, respond exactly:
NOT_FOUND
7. Do not mention these rules in your response. 8. Do not explain your reasoning.
9. Do not use training information or general knowledge.
10. Provide only information appropriate for an educational context.
11. If there are links, place the references at the end of the response, referenced with [#].

Hierarchy:
- If there is a conflict, prioritize CONTENT over PREVIOUS.
- Do not invent missing information.

Output Formatting:
- Respond clearly and concisely.
- Use Markdown.
- Do not add text before or after the response.';
$string['default_intent_response'] = 'I\'m not sure how to help with that yet, but I\'m learning new things every day! Please try asking in a different way or check back later for more capabilities.';
$string['default_openanswer_prompt'] = 'If you are not completely sure of the answer, say you don\'t know. Do not provide offensive, racist, violent, or illegal answers. Also, do not answer questions about health, mental health or crime.';
$string['default_question_plan_prompt'] = 'Based on the user\'s JSON, respond with valid JSON containing:\n"type": must be one of: greeting, content, dates\n"params": For type "content" the keywords that define the content to search for, for dates the date ranges, for "greeting" a random+respectful+cordial\nRespond only the pure JSON, without code blocks, without markdown and without additional text.';
$string['defaulttitle'] = 'Questions & Answers';
$string['error_ai_failed'] = 'Failed to generate a response';
$string['error_ai_unavailable'] = 'AI service is not available at this moment.';
$string['error_empty_question'] = 'Please enter a question.';
$string['error_no_content'] = 'No response content was generated.';
$string['error_processing_question'] = 'An error occurred while processing your question. Please try again.';
$string['error_search_unavailable'] = 'Content search functionality is currently unavailable.';
$string['intent_content_default'] = 'I\'m here to help! Please provide some keywords or topics you\'re interested in, and I\'ll do my best to find relevant information for you.';
$string['intent_content_notfound'] = 'Sorry, I couldn\'t find any content related to your request. Please try different keywords or check back later.';
$string['intent_greeting_default'] = 'Hello! How can I assist you today?';
$string['msg_no_content'] = 'Sorry, I couldn\'t find any relevant information to answer your question. Please try asking in a different way or check back later.';
$string['parce:usechat'] = 'Use the Parce chat widget';
$string['parce:viewallchats'] = 'View all chat conversations';
$string['placeholder'] = 'Type your question...';
$string['pluginname'] = 'Parce - Q&A Chat Widget';
$string['setting_ai_instructions_heading'] = 'AI Action System Instructions';
$string['setting_ai_instructions_heading_desc'] = 'Configure the system instructions for each AI action. These instructions guide the AI model behavior for specific question types.';
$string['setting_allowopenanswer'] = 'Allow Open Answering';
$string['setting_allowopenanswer_desc'] = 'Allow the answer to be openly sought in AI, without relying on content search results. This may provide more direct answers but can be less accurate and more likely to produce irrelevant responses. Use with caution.';
$string['setting_answer_question_prompt'] = 'Answer Question System Instruction';
$string['setting_answer_question_prompt_desc'] = 'The system instruction that guides the AI when answering user questions directly. This instruction is sent to the AI model with every answer_question request.';
$string['setting_cache_heading'] = 'Conversation Cache Settings';
$string['setting_cache_heading_desc'] = 'Configure the caching behavior for conversation history. These settings control how long conversation data is stored and how many entries are kept per conversation.';
$string['setting_cache_maxentries'] = 'Maximum Entries per Conversation';
$string['setting_cache_maxentries_desc'] = 'The maximum number of conversation entries (pairs of question and response) to keep in the cache per conversation. Older entries will be automatically removed when this limit is exceeded. The default is 50 entries.';
$string['setting_cache_ttl'] = 'Cache Time to Live (seconds)';
$string['setting_cache_ttl_desc'] = 'How long conversation data will be stored in the cache, measured in seconds. The default is 3600 seconds (1 hour). Setting this to 0 will disable TTL-based expiration, relying instead on session persistence.';
$string['setting_chat_title'] = 'Chat Window Title';
$string['setting_chat_title_desc'] = 'The title displayed in the chat window header';
$string['setting_enable_guests'] = 'Enable for Guest Users';
$string['setting_enable_guests_desc'] = 'Allow guest users to access the chat widget';
$string['setting_enabled'] = 'Enable Parce Chat Widget';
$string['setting_enabled_desc'] = 'When enabled, the floating chat widget will appear on all pages for logged-in users';
$string['setting_openanswer_prompt'] = 'Open Answer Instruction';
$string['setting_openanswer_prompt_desc'] = 'The system instruction that guides the AI when answering questions directly without relying on content search results. This instruction is sent to the AI model with every open_answer request. Use this to provide guidance to the AI on how to answer questions in a more open-ended way, which can be useful when content search results are not sufficient to answer the question.';
$string['setting_question_plan_prompt'] = 'Question Plan System Instruction';
$string['setting_question_plan_prompt_desc'] = 'The system instruction that guides the AI when creating a structured plan or outline for a question or topic. This instruction is sent to the AI model with every question_plan request.';
$string['yesterday'] = 'Yesterday {$a}';
