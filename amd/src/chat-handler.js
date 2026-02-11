/**
 * Local Parce - Chat Handler Module
 *
 * Handles the logic for sending messages and processing responses.
 * Manages AJAX communication with backend services.
 *
 * @module     local_parce/chat-handler
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log', 'core/str', 'core/ajax', 'local_parce/chat-ui'], function($, Log, Str, Ajax, ChatUI) {
    'use strict';

    // Load strings.
    var strings = [
        {key: 'chat_error', component: 'local_parce'},
        {key: 'chat_error_processing', component: 'local_parce'},
    ];
    var s = [];

    /**
     * Load strings from server.
     *
     * @return {Promise} Promise that is resolved when the strings are loaded.
     */
    function loadStrings() {
        strings.forEach(one => {
            s[one.key] = one.key;
        });

        return new Promise((resolve) => {
            Str.getStrings(strings).then(function(results) {
                var pos = 0;
                strings.forEach(one => {
                    s[one.key] = results[pos];
                    pos++;
                });

                resolve(true);
                return true;
            }).fail(function(e) {
                Log.debug('Error loading strings');
                Log.debug(e);
                return false;
            });
        });
    }

    loadStrings().catch(() => null);
    // End of Load strings.


    var ChatHandler = {
        isSending: false,

        /**
         * Initialize the chat handler.
         */
        init: function() {
            // Handler initialization if needed.
        },

        /**
         * Send a message to the backend service.
         *
         * @param {string} message - The user's message
         */
        sendMessage: function(message) {
            var self = this;

            if (this.isSending || message.length === 0) {
                return;
            }

            // Add user message to the UI.
            ChatUI.addMessage(message, 'user');

            // Show loading indicator.
            ChatUI.showLoading();

            // Set flag to prevent multiple submissions.
            this.isSending = true;

            // Call the backend service.
            this.submitQuestion(message).then(function(response) {
                ChatUI.hideLoading();
                if (response && response.answer) {
                    ChatUI.addMessage(response.answer, 'bot');
                } else {
                    ChatUI.addMessage(s.chat_error_processing, 'bot');
                }
                self.isSending = false;
            }).catch(function(error) {
                ChatUI.hideLoading();
                ChatUI.addMessage(s.chat_error, 'bot');
                Log.debug('Chat error:', error);
                self.isSending = false;
            });
        },

        /**
         * Submit a question to the backend web service.
         *
         * Sends the user's question to the local_parce_answer web service
         * which processes it and returns an answer.
         *
         * @param {string} question - The question to submit
         * @return {Promise} A promise that resolves with the answer
         */
        submitQuestion: function(question) {
            // Call the web service using Moodle's Ajax module.
            return Ajax.call([{
                methodname: 'local_parce_answer',
                args: {
                    question: question,
                    contextid: M.cfg.contextid || 1
                }
            }])[0].then(function(response) {
                // Success: return the response with the answer.
                return {
                    question: question,
                    answer: response.answer
                };
            }).catch(function(error) {
                // Error handling.
                Log.debug('Web service error:', error);
                throw error;
            });
        }
    };

    return ChatHandler;
});
