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
 * Local Parce chat request handler.
 *
 * @module     local_parce/chat-handler
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log', 'core/str', 'core/ajax', 'local_parce/chat-ui'], function(Log, Str, Ajax, ChatUI) {
    'use strict';

    const text = {
        chat_error: 'chat_error',
        chat_error_processing: 'chat_error_processing'
    };

    Str.getStrings([
        {key: 'chat_error', component: 'local_parce'},
        {key: 'chat_error_processing', component: 'local_parce'}
    ]).then(function(strings) {
        text.chat_error = strings[0];
        text.chat_error_processing = strings[1];
        return strings;
    }).catch(function(error) {
        Log.debug('Parce: error strings could not be loaded', error);
    });

    const ChatHandler = {
        isSending: false,
        pending: null,

        /** Initialise handler state. */
        init: function() {
            this.isSending = false;
            this.pending = null;
            ChatUI.setSending(false);
        },

        /**
         * Queue one message. Loading and sending are serialised to prevent races.
         *
         * @param {string} message User question
         * @return {Promise|boolean} False if another turn is active
         */
        sendMessage: function(message) {
            if (this.isSending || this.pending || message.length === 0) {
                return false;
            }
            this.pending = {
                message: message,
                userNode: null
            };
            return this.processPending();
        },

        /**
         * Continue the current turn after initial load or retry.
         *
         * @return {Promise}
         */
        processPending: function() {
            const pending = this.pending;
            if (!pending || this.isSending) {
                return Promise.resolve(false);
            }

            this.isSending = true;
            ChatUI.setSending(true);
            ChatUI.removeOperationFeedback();

            return ChatUI.ensureHistoryLoaded().then(() => {
                if (!pending.userNode) {
                    pending.userNode = ChatUI.addMessage(pending.message, 'user');
                }
                ChatUI.clearStatus();
                ChatUI.showLoading();
                return this.submitQuestion(pending.message);
            }).then((response) => {
                ChatUI.hideLoading();
                ChatUI.updateUsage(response.usagepercentage);

                if (response.successful) {
                    if (response.newconversation) {
                        ChatUI.startNewConversation(pending.userNode);
                    }
                    ChatUI.addMessage(response.answer || text.chat_error_processing, 'system');
                    this.pending = null;
                    return true;
                }

                ChatUI.showOperationError(
                    response.answer || text.chat_error_processing,
                    response.status,
                    response.retryable,
                    () => this.retryPending()
                );
                if (!response.retryable) {
                    this.pending = null;
                }
                return false;
            }).catch((error) => {
                ChatUI.hideLoading();
                if (pending.userNode) {
                    ChatUI.showOperationError(text.chat_error, 'error', true, () => this.retryPending());
                } else {
                    ChatUI.showStatus('error', ChatUI.getText('historyerror'), () => this.retryPending());
                }
                Log.debug('Parce: chat operation failed', error);
                return false;
            }).then((result) => {
                this.isSending = false;
                ChatUI.setSending(false);
                return result;
            });
        },

        /** Retry the retained operation without adding its question again. */
        retryPending: function() {
            if (!this.pending || this.isSending) {
                return;
            }
            this.processPending();
        },

        /**
         * Submit one backend operation.
         *
         * @param {string} question User question
         * @return {Promise}
         */
        submitQuestion: function(question) {
            return Ajax.call([{
                methodname: 'local_parce_answer',
                args: {
                    question: question,
                    contextid: M.cfg.contextid || 1
                }
            }])[0];
        }
    };

    return ChatHandler;
});
