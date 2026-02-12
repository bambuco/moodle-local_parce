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
                    (type == 'bot' ? this.sanitizeBotMessage(message) : this.escapeHtml(message)) +
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
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        },

        /**
         * Sanitize bot messages to prevent XSS attacks
         *
         * Removes dangerous HTML tags and event handlers while preserving safe HTML
         * markup (bold, italic, links, code blocks, etc.) from markdown rendering.
         * This provides a second layer of protection after backend sanitization.
         *
         * @param {string} message - The message to sanitize
         * @return {string} Sanitized message
         */
        sanitizeBotMessage: function(message) {
            if (!message || typeof message !== 'string') {
                return '';
            }

            var sanitized = message;

            // Remove script tags and their content
            sanitized = sanitized.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');

            // Remove event handlers (onclick, onerror, onload, onmouseover, etc.)
            sanitized = sanitized.replace(/\s*on\w+\s*=\s*["\']?[^\s"\'>`]*["\']?/gi, '');

            // Remove iframe tags (potential security risk)
            sanitized = sanitized.replace(/<iframe[^>]*>[\s\S]*?<\/iframe>/gi, '');

            // Remove object and embed tags (potential security risk)
            sanitized = sanitized.replace(/<(object|embed|applet)[^>]*>[\s\S]*?<\/(object|embed|applet)>/gi, '');

            // Remove javascript: protocol from href and src attributes
            sanitized = sanitized.replace(/javascript\s*:/gi, '');

            // Remove style tags and their content
            sanitized = sanitized.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');

            return sanitized;
        }
    };

    return ChatUI;
});
