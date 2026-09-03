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
 * Local Parce chat UI.
 *
 * @module     local_parce/chat-ui
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log', 'core/ajax'], function($, Log, Ajax) {
    'use strict';

    const WINDOW_STATE_STORAGE_KEY = 'local_parce_chat_window_open';

    const ChatUI = {
        isWindowOpen: false,
        isLoadingHistory: false,
        hasLoadedHistory: false,
        historyPromise: null,
        chatid: null,
        opener: null,

        /**
         * Initialise the last persisted state for this page.
         *
         * @param {number} chatid Canonical chat context ID
         */
        init: function(chatid) {
            this.chatid = chatid;
            this.syncWindowState(this.getPersistedWindowState());
            if (this.isWindowOpen) {
                this.ensureHistoryLoaded().catch(() => null);
                this.focusInputField();
            }
            Log.debug('Parce: Chat UI initialized');
        },

        /**
         * Read the last window state shared by tabs on this browser.
         *
         * @return {boolean}
         */
        getPersistedWindowState: function() {
            try {
                return window.localStorage.getItem(WINDOW_STATE_STORAGE_KEY) === 'true';
            } catch (error) {
                Log.debug('Parce: Chat window state could not be read', error);
                return false;
            }
        },

        /**
         * Persist the window state for subsequent page loads in any tab.
         *
         * @param {boolean} open Whether the widget is open
         */
        persistWindowState: function(open) {
            try {
                window.localStorage.setItem(WINDOW_STATE_STORAGE_KEY, open ? 'true' : 'false');
            } catch (error) {
                Log.debug('Parce: Chat window state could not be persisted', error);
            }
        },

        /**
         * Keep CSS, hidden and ARIA state in sync.
         *
         * @param {boolean} open Whether the widget is open
         */
        syncWindowState: function(open) {
            const container = $('#local_parce-chat-window-container');
            const toggle = $('#local_parce-chat-toggle');
            this.isWindowOpen = open;
            container.toggleClass('local_parce-visible', open);
            container.prop('hidden', !open).attr('aria-hidden', open ? 'false' : 'true');
            toggle.attr('aria-expanded', open ? 'true' : 'false');
        },

        /**
         * Toggle window visibility.
         *
         * @param {HTMLElement} opener Element that opened the widget
         */
        toggleWindow: function(opener) {
            if (this.isWindowOpen) {
                this.closeWindow();
            } else {
                this.openWindow(opener);
            }
        },

        /**
         * Open, load the active conversation once, and focus the textarea.
         *
         * @param {HTMLElement} opener Element that opened the widget
         */
        openWindow: function(opener) {
            if ($('#local_parce-chat-window-container').length === 0) {
                return;
            }
            this.opener = opener || this.opener || $('#local_parce-chat-toggle')[0];
            this.syncWindowState(true);
            this.persistWindowState(true);
            this.ensureHistoryLoaded().catch(() => null);
            this.focusInputField();
        },

        /**
         * Close and restore focus to the opener.
         */
        closeWindow: function() {
            if (!this.isWindowOpen) {
                return;
            }
            this.syncWindowState(false);
            this.persistWindowState(false);
            if (this.opener && document.contains(this.opener)) {
                this.opener.focus();
            }
        },

        /** Focus the message textarea without creating a focus trap. */
        focusInputField: function() {
            window.setTimeout(() => {
                if (this.isWindowOpen) {
                    $('.local_parce-message-input').trigger('focus');
                }
            }, 0);
        },

        /**
         * Obtain a localised text embedded by the server template.
         *
         * @param {string} key Text key
         * @return {string}
         */
        getText: function(key) {
            return $('.local_parce-status-texts [data-key="' + key + '"]').text();
        },

        /**
         * Display an accessible operation status and optional retry action.
         *
         * @param {string} type loading, empty, error, or rate_limited
         * @param {string} message Visible status text
         * @param {Function|null} retry Retry callback
         */
        showStatus: function(type, message, retry) {
            const status = $('#local_parce-chat-status');
            status.empty().removeClass('hidden local_parce-status-loading local_parce-status-empty ' +
                'local_parce-status-error local_parce-status-rate_limited');
            status.addClass('local_parce-status-' + type);
            $('<span class="local_parce-status-message"></span>').text(message).appendTo(status);
            if (typeof retry === 'function') {
                $('<button type="button" class="btn btn-sm btn-link local_parce-retry-btn"></button>')
                    .text(this.getText('retry'))
                    .one('click', function(e) {
                        // Stop bubbling so the removal below never resembles an outside click.
                        e.stopPropagation();
                        retry();
                    })
                    .appendTo(status);
            }
        },

        /** Hide the current status. */
        clearStatus: function() {
            $('#local_parce-chat-status').addClass('hidden').empty();
        },

        /** Show loading and disable controls while an operation is active. */
        showLoading: function() {
            this.showStatus('loading', this.getText('loading'), null);
        },

        /** Remove loading state. */
        hideLoading: function() {
            if ($('#local_parce-chat-status').hasClass('local_parce-status-loading')) {
                this.clearStatus();
            }
        },

        /**
         * Enable or disable send controls.
         *
         * @param {boolean} sending Whether a send is active
         */
        setSending: function(sending) {
            $('.local_parce-send-btn').prop('disabled', sending);
            $('.local_parce-message-input').attr('aria-busy', sending ? 'true' : 'false');
        },

        /**
         * Create the one renderer used by live and restored entries.
         * System HTML has already crossed Moodle's server sanitisation boundary.
         *
         * @param {string} message Message content
         * @param {string} type user or system
         * @param {string} timestamp Display timestamp
         * @param {string} extraClass Optional state class
         * @return {jQuery}
         */
        createMessage: function(message, type, timestamp, extraClass) {
            const item = $('<div></div>').addClass('local_parce-message local_parce-message-' + type);
            if (extraClass) {
                item.addClass(extraClass);
            }
            const content = $('<div class="local_parce-message-content"></div>');
            if (type === 'user') {
                content.text(message);
            } else {
                content.html(message);
            }
            item.append(content);
            $('<span class="local_parce-message-timestamp"></span>').text(timestamp).appendTo(item);
            return item;
        },

        /**
         * Append one new message to the accessible log.
         *
         * @param {string} message Message content
         * @param {string} type user or system
         * @param {string} extraClass Optional state class
         * @return {jQuery|null}
         */
        addMessage: function(message, type, extraClass) {
            const container = $('#local_parce-messages-container');
            if (container.length === 0) {
                return null;
            }
            container.find('.local_parce-message-welcome').remove();
            const item = this.createMessage(message, type || 'system', this.formatTime(new Date()), extraClass);
            container.append(item);
            this.scrollToBottom();
            return item;
        },

        /**
         * Replace a failed operation message with a retryable status.
         *
         * @param {string} message Safe server-rendered error HTML
         * @param {string} status Result discriminator
         * @param {boolean} retryable Whether retry is allowed
         * @param {Function} retry Retry callback
         */
        showOperationError: function(message, status, retryable, retry) {
            this.removeOperationFeedback();
            const state = status === 'rate_limited' ? 'rate_limited' : 'error';
            const item = this.addMessage(message, 'system',
                'local_parce-operation-feedback local_parce-message-' + state);
            if (item && retryable) {
                $('<button type="button" class="btn btn-sm btn-link local_parce-retry-btn"></button>')
                    .text(this.getText('retry'))
                    .one('click', function(e) {
                        // Stop bubbling so the removal below never resembles an outside click.
                        e.stopPropagation();
                        retry();
                    })
                    .appendTo(item.find('.local_parce-message-content'));
            }
        },

        /** Remove feedback from the preceding failed attempt before retry. */
        removeOperationFeedback: function() {
            $('#local_parce-messages-container .local_parce-operation-feedback').remove();
        },

        /**
         * Commit a rollover without adding or announcing the pending question twice.
         *
         * @param {jQuery} userMessage Existing user message node
         */
        startNewConversation: function(userMessage) {
            const container = $('#local_parce-messages-container');
            const notice = $('#local_parce-chat-container > .local_parce-conversation-notice').first().clone();
            const live = container.attr('aria-live');
            container.attr('aria-live', 'off');
            userMessage.detach();
            container.empty();
            notice.removeClass('hidden').appendTo(container);
            userMessage.appendTo(container);
            container.attr('aria-live', live || 'polite');
        },

        /**
         * Load the complete active conversation exactly once.
         *
         * @return {Promise}
         */
        ensureHistoryLoaded: function() {
            if (this.hasLoadedHistory) {
                return Promise.resolve();
            }
            if (this.historyPromise) {
                return this.historyPromise;
            }

            this.isLoadingHistory = true;
            this.showLoading();
            this.historyPromise = Ajax.call([{
                methodname: 'local_parce_get_active_conversation',
                args: {chatid: this.chatid}
            }])[0].then((response) => {
                this.renderHistoryEntries(response.entries);
                this.updateUsage(response.usagepercentage);
                this.hasLoadedHistory = true;
                this.isLoadingHistory = false;
                this.historyPromise = null;
                if (response.entries.length === 0) {
                    this.showStatus('empty', this.getText('empty'), null);
                } else {
                    this.clearStatus();
                }
                this.scrollToBottom();
            }).catch((error) => {
                this.isLoadingHistory = false;
                this.historyPromise = null;
                this.hasLoadedHistory = false;
                this.showStatus('error', this.getText('historyerror'), () => this.ensureHistoryLoaded());
                Log.debug('Parce: active conversation load failed', error);
                throw error;
            });
            return this.historyPromise;
        },

        /**
         * Replace the initial view without announcing restored entries.
         *
         * @param {array} entries Chronological active conversation entries
         */
        renderHistoryEntries: function(entries) {
            const container = $('#local_parce-messages-container');
            const live = container.attr('aria-live');
            container.attr('aria-live', 'off').empty();
            if (entries.length === 0) {
                const welcome = $('#local_parce-chat-container > .local_parce-message-welcome').first().clone();
                welcome.removeClass('hidden').appendTo(container);
            } else {
                entries.forEach((entry) => {
                    container.append(this.createMessage(
                        entry.content,
                        entry.role,
                        entry.timestamp_formatted,
                        ''
                    ));
                });
            }
            container.attr('aria-live', live || 'polite');
        },

        /**
         * Update usage and retain the 70/85/100 bands.
         *
         * @param {number} percentage Active conversation usage
         */
        updateUsage: function(percentage) {
            percentage = Math.max(0, Math.min(100, Number(percentage) || 0));
            const progress = $('.local_parce-usage-progress');
            const bar = $('.local_parce-usage-bar');
            progress.attr('aria-valuenow', percentage);
            bar.css('width', percentage + '%').removeClass('bg-warning bg-danger');
            if (percentage >= 85) {
                bar.addClass('bg-danger');
            } else if (percentage >= 70) {
                bar.addClass('bg-warning');
            }
            $('.local_parce-usage-percentage').text(percentage + '%');
        },

        /** Scroll the log to its newest entry. */
        scrollToBottom: function() {
            const container = $('#local_parce-messages-container');
            if (container.length > 0) {
                window.setTimeout(function() {
                    container.scrollTop(container[0].scrollHeight);
                }, 0);
            }
        },

        /**
         * Format a date as HH:MM.
         *
         * @param {Date} date Date to format
         * @return {string}
         */
        formatTime: function(date) {
            return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }
    };

    return ChatUI;
});
