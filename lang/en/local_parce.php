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
$string['chat_empty'] = 'There are no messages in this conversation yet.';
$string['chat_error'] = 'An error occurred. Please try again later.';
$string['chat_error_processing'] = 'Sorry, I could not process your question. Please try again.';
$string['chat_history_error'] = 'The active conversation could not be loaded.';
$string['chat_loading'] = 'Processing your question...';
$string['chat_retry'] = 'Try again';
$string['chat_send'] = 'Send';
$string['chat_welcome'] = 'Hello! How can we help you today?';
$string['content_suggestions'] = 'I could not extract a direct answer, but I found these related resources:';
$string['conversation_started'] = 'A new conversation was started because the previous one reached its limit.';
$string['conversation_usage'] = 'Conversation usage';
$string['conversation_usage_aria'] = 'Estimated conversation limit used';
$string['course_reference'] = 'Found in [{$a->coursename}]({$a->courseurl})';
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
$string['default_question_plan_prompt'] = 'Respond with valid JSON containing "type" and "params".

"type" must be one of: greeting, content, resource, dates, grades, progress, help.
- "resource": explicit requests to search for, show, find, locate, or access Moodle courses, activities, or resources. This intent returns links directly and does not answer questions about their content.
- "content": questions that require an explanation or answer based on retrieved content.
- "dates": questions about events or date ranges.
- "grades": questions about the current user\'s own grades, scores or grading feedback. Do not use it to request another user\'s grades.
- "progress": questions about the current user\'s own course progress or completed, pending, passed or failed activities. Do not use it to request another user\'s progress.
- "greeting": greetings.
- "help": questions about using the system or what can be asked.

"params":
- For "resource", "resourcetype" is required. Module types used in the course appear as a JSON object between RESOURCE_TYPES_START and RESOURCE_TYPES_END: each key is an allowed short name and its boolean indicates whether the component declares that it can produce grades. Use ["core_course"] for courses, ["*"] for all available module types, or an array of specific object keys for the requested types. Use "content" with only terms from the current question that distinguish the resource name within the context; use an empty array when there are none. Use history only to resolve explicit references in the current question.
- For "content", use "content" with the subject and terms that distinguish the requested answer, while omitting generic syntactic wording. Preserve concepts such as advantages, causes, or definition. For example, "good things about social networks" should search for "advantages social networks".
- For "dates", use "dates" with date ranges or terms.
- For "grades", use "grades" with only a distinctive course or grade-item name when supplied. Use an empty array for a general question about all grades.
- For "progress", use "progress" with only a distinctive course or activity name when supplied. Optionally use "status" with one of: incomplete, completed, passed, failed. Use "status": "incomplete" for pending activities.
- For "greeting", include a respectful and cordial greeting.

Respond only with pure JSON, without code blocks, Markdown, or additional text.';
$string['defaulttitle'] = 'Assistant Parce';
$string['error_ai_failed'] = 'Failed to generate a response';
$string['error_ai_unavailable'] = 'AI service is not available at this moment.';
$string['error_empty_question'] = 'Please enter a question.';
$string['error_guest_history'] = 'Guest users cannot access conversation history.';
$string['error_no_content'] = 'No response content was generated.';
$string['error_processing_question'] = 'An error occurred while processing your question. Please try again.';
$string['error_question_too_long'] = 'The question is too long. The maximum length is 4000 characters.';
$string['error_rate_limited'] = 'Too many AI requests have been made. Please try again later.';
$string['error_search_unavailable'] = 'Content search functionality is currently unavailable.';
$string['eventconversationhistoryviewed'] = 'Conversation history viewed';
$string['historyadminlink'] = 'Parce conversation history';
$string['historyback'] = 'Back to the previous history level';
$string['historybreadcrumb'] = 'Conversation history navigation';
$string['historycompletiontokens'] = 'Response tokens';
$string['historycontexts'] = 'Contexts with history';
$string['historyconversation'] = 'Conversation turns';
$string['historyconversationlabel'] = 'Conversation';
$string['historyconversations'] = 'Conversations';
$string['historyempty'] = 'No historical conversations were found.';
$string['historyend'] = 'End of results.';
$string['historyerror'] = 'The history could not be loaded. Please try again.';
$string['historyguestsession'] = 'Guest session';
$string['historyloading'] = 'Loading history…';
$string['historyloadmore'] = 'Load more';
$string['historyprompttokens'] = 'Prompt tokens';
$string['historyresultslimited'] = 'Only the {$a} most recent results are shown. There may be more.';
$string['historysearch'] = 'Search conversation history';
$string['historysearchnoresults'] = 'No conversations match this phrase.';
$string['historysearchplaceholder'] = 'Search conversations';
$string['historyselectconversation'] = 'Select a conversation to view it.';
$string['historytitle'] = 'My Parce conversation history';
$string['historytokens'] = 'Token usage';
$string['historyturns'] = 'Turns';
$string['historyunavailablecontext'] = '[Unavailable context]';
$string['historyunavailableuser'] = 'Unavailable user';
$string['intent_content_default'] = 'I\'m here to help! Please provide some keywords or topics you\'re interested in, and I\'ll do my best to find relevant information for you.';
$string['intent_content_notfound'] = 'Sorry, I couldn\'t find any content related to your request. Please try different keywords or check back later.';
$string['intent_dates_default'] = 'I\'m here to help! Please let me know which dates or events you\'re interested in, and I\'ll look for the relevant information.';
$string['intent_dates_notfound'] = 'Sorry, I couldn\'t find any events or dates related to your query. Please try different terms or check back later.';
$string['intent_grades_notfound'] = 'I couldn\'t find any visible grades related to your question.';
$string['intent_greeting_default'] = 'Hello! How can I assist you today?';
$string['intent_help_course'] = 'You are in a course context. You can ask me questions related to the course content, assignments, or any other course-related topics. Just type your question and I\'ll do my best to help!';
$string['intent_help_default'] = 'Welcome to the help section! You can ask me questions about the content you are viewing, and I will do my best to provide relevant information. Just type your question and I\'ll be here to assist you!';
$string['intent_help_module'] = 'You are currently in a module context. You can ask me questions related to the specific module content, dates, or any other module-related topics. Just type your question and I\'ll do my best to assist you!';
$string['intent_progress_notfound'] = 'I couldn\'t find any visible completion progress related to your question.';
$string['intent_resource_notfound'] = 'Sorry, I could not find resources related to your search. Try using the resource name or other distinctive words.';
$string['msg_no_content'] = 'Sorry, I couldn\'t find any relevant information to answer your question. Please try asking in a different way or check back later.';
$string['parce:usechat'] = 'Use the Parce chat widget';
$string['parce:viewallchats'] = 'View all chat conversations';
$string['placeholder'] = 'Type your question...';
$string['pluginname'] = 'Parce - Q&A Chat Widget';
$string['privacy:metadata:ai_actions'] = 'Technical traces of AI requests made by the chat.';
$string['privacy:metadata:ai_actions:contextid'] = 'The context from which the AI request was made.';
$string['privacy:metadata:ai_actions:conversationentryid'] = 'The related conversation turn.';
$string['privacy:metadata:ai_actions:conversationkey'] = 'The conversation session identifier.';
$string['privacy:metadata:ai_actions:generatedcontent'] = 'The raw content generated by the AI provider.';
$string['privacy:metadata:ai_actions:prompt'] = 'The system instructions sent to the AI provider.';
$string['privacy:metadata:ai_actions:prompttext'] = 'The question, recent conversation, and retrieved course, grade or completion data sent to the AI provider.';
$string['privacy:metadata:ai_actions:technical'] = 'Technical correlation, lifecycle, timing, response, error, model, provider, and token usage information.';
$string['privacy:metadata:ai_actions:timecreated'] = 'When the AI request was created.';
$string['privacy:metadata:ai_actions:userid'] = 'The user who made the request.';
$string['privacy:metadata:aiprovider'] = 'The configured AI provider receives questions, recent conversation context, and relevant course, visible grade or completion data to generate responses.';
$string['privacy:metadata:conversation_entries'] = 'Completed chat conversation turns.';
$string['privacy:metadata:conversation_entries:chatid'] = 'The Moodle context in which the conversation took place.';
$string['privacy:metadata:conversation_entries:conversationkey'] = 'The conversation session identifier.';
$string['privacy:metadata:conversation_entries:question'] = 'The question submitted by the user.';
$string['privacy:metadata:conversation_entries:response'] = 'The response shown to the user.';
$string['privacy:metadata:conversation_entries:timecreated'] = 'When the conversation turn was created.';
$string['privacy:metadata:conversation_entries:userid'] = 'The user who participated in the conversation.';
$string['resource_results'] = 'Based on your request, I found these resources:';
$string['setting_ai_instructions_heading'] = 'AI Action System Instructions';
$string['setting_ai_instructions_heading_desc'] = 'Configure the system instructions for each AI action. These instructions guide the AI model behavior for specific question types.';
$string['setting_allowopenanswer'] = 'Allow Open Answering';
$string['setting_allowopenanswer_desc'] = 'Allow the answer to be openly sought in AI, without relying on content search results. This may provide more direct answers but can be less accurate and more likely to produce irrelevant responses. Use with caution.';
$string['setting_answer_question_prompt'] = 'Answer Question System Instruction';
$string['setting_answer_question_prompt_desc'] = 'The system instruction that guides the AI when answering user questions directly. This instruction is sent to the AI model with every answer_question request.';
$string['setting_cache_heading'] = 'Conversation Cache Settings';
$string['setting_cache_heading_desc'] = 'Configure the active conversation stored in session cache. Persistent history is not loaded into this cache or into AI prompts.';
$string['setting_cache_maxentries'] = 'Maximum turns per conversation';
$string['setting_cache_maxentries_desc'] = 'Safety limit for complete question-and-answer turns in one active conversation. Enter a value from 1 to the hard maximum of 40. Reaching it starts a new conversation.';
$string['setting_chat_title'] = 'Chat Window Title';
$string['setting_chat_title_desc'] = 'The title displayed in the chat window header';
$string['setting_conversation_maxtokens'] = 'Estimated-token limit per conversation';
$string['setting_conversation_maxtokens_desc'] = 'Primary size limit for an active conversation. Enter a value from 1 to the hard maximum of 16,000. The conservative estimate uses one token per three Unicode characters.';
$string['setting_enable_guests'] = 'Enable for Guest Users';
$string['setting_enable_guests_desc'] = 'Allow guest users to access the chat widget';
$string['setting_enabled'] = 'Enable Parce Chat Widget';
$string['setting_enabled_desc'] = 'When enabled, the floating chat widget will appear on all pages for logged-in users';
$string['setting_history_context_limit'] = 'Maximum history contexts';
$string['setting_history_context_limit_desc'] = 'Maximum number of contexts shown in the history browser. Enter a value from 1 to 100.';
$string['setting_history_conversation_limit'] = 'Maximum conversations per context';
$string['setting_history_conversation_limit_desc'] = 'Maximum number of conversations loaded when a context is expanded. Enter a value from 1 to 100.';
$string['setting_history_heading'] = 'Persistent history browser';
$string['setting_history_heading_desc'] = 'Configure bounded result sizes for history navigation and search.';
$string['setting_history_search_limit'] = 'Maximum search results';
$string['setting_history_search_limit_desc'] = 'Maximum number of matching conversations returned by one search. Enter a value from 1 to 100.';
$string['setting_openanswer_prompt'] = 'Open Answer Instruction';
$string['setting_openanswer_prompt_desc'] = 'The system instruction that guides the AI when answering questions directly without relying on content search results. This instruction is sent to the AI model with every open_answer request. Use this to provide guidance to the AI on how to answer questions in a more open-ended way, which can be useful when content search results are not sufficient to answer the question.';
$string['setting_question_plan_prompt'] = 'Question Plan System Instruction';
$string['setting_question_plan_prompt_desc'] = 'The system instruction that guides the AI when creating a structured plan or outline for a question or topic. This instruction is sent to the AI model with every question_plan request.';
$string['static_help'] = 'help';
$string['yesterday'] = 'Yesterday {$a}';
