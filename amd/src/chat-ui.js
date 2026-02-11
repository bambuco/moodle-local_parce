/**
 * Local Parce - Chat UI Module
 *
 * Handles rendering, showing, and hiding the chat interface components.
 * Manages the visual state of the floating chat window.
 *
 * @module     local_parce/chat-ui
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log'], function($, Log) {
    'use strict';

    var ChatUI = {
        isWindowOpen: false,

        /**
         * Initialize the chat UI.
         * Creates and injects the chat window template if not already present.
         */
        init: function() {
            // The chat bubble should already be injected via the template.
            // We just need to show the chat window container.
            Log.debug('Parce: Chat UI initialized');
        },

        /**
         * Toggle the chat window visibility.
         */
        toggleWindow: function() {
            if (this.isWindowOpen) {
                this.closeWindow();
            } else {
                this.openWindow();
            }
        },

        /**
         * Open the chat window.
         */
        openWindow: function() {
            var container = $('#local_parce-chat-window-container');
            if (container.length > 0) {
                container.addClass('local_parce-visible');
                this.isWindowOpen = true;
                this.focusInputField();
            }
        },

        /**
         * Close the chat window.
         */
        closeWindow: function() {
            var container = $('#local_parce-chat-window-container');
            if (container.length > 0) {
                container.removeClass('local_parce-visible');
                this.isWindowOpen = false;
            }
        },

        /**
         * Focus the message input field.
         */
        focusInputField: function() {
            setTimeout(function() {
                $('.local_parce-message-input').focus();
            }, 100);
        },

        /**
         * Add a message to the chat window.
         *
         * @param {string} message - The message text
         * @param {string} type - The message type: 'user' or 'bot'
         */
        addMessage: function(message, type) {
            var container = $('#local_parce-messages-container');
            if (container.length === 0) {
                return;
            }

            // Remove welcome message if present.
            container.find('.local_parce-message-welcome').remove();

            var messageClass = 'local_parce-message-' + (type || 'bot');
            var messageHtml = '<div class="local_parce-message ' + messageClass + '">' +
                '<div class="local_parce-message-content">' +
                    this.escapeHtml(message) +
                '</div>' +
            '</div>';

            container.append(messageHtml);

            // Scroll to the bottom.
            this.scrollToBottom();
        },

        /**
         * Show loading indicator in the chat window.
         */
        showLoading: function() {
            const container = $('#local_parce-messages-container');
            if (container.length === 0) {
                return;
            }

            const loadingHtml = $($('.local_parce-message-loading').prop('outerHTML'));
            loadingHtml.removeClass('hidden');
            container.append(loadingHtml);
            this.scrollToBottom();
        },

        /**
         * Remove the loading indicator.
         */
        hideLoading: function() {
            $('#local_parce-messages-container .local_parce-message-loading').remove();
        },

        /**
         * Clear all messages from the chat window.
         */
        clearMessages: function() {
            const container = $('#local_parce-messages-container');
            if (container.length > 0) {
                const welcomeMessage = container.find('.local_parce-message-welcome').prop('outerHTML');
                container.html(welcomeMessage.removeClass('hidden'));
            }
        },

        /**
         * Scroll the message container to the bottom.
         */
        scrollToBottom: function() {
            var container = $('#local_parce-messages-container');
            if (container.length > 0) {
                setTimeout(function() {
                    container.scrollTop(container[0].scrollHeight);
                }, 100);
            }
        },

        /**
         * Escape HTML special characters.
         *
         * @param {string} text - The text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    return ChatUI;
});
