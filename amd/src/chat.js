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
 * Local Parce - Main chat module
 *
 * This module manages the initialization and overall lifecycle of the floating chat widget.
 * It coordinates between the UI, event handling, and backend services.
 *
 * @module     local_parce/chat
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
'use strict';

import $ from 'jquery';
import Log from 'core/log';
import ChatUI from 'local_parce/chat-ui';
import ChatHandler from 'local_parce/chat-handler';

/**
 * Component initialization.
 */
export const init = async() => {
    // Initialize the UI component.
    ChatUI.init();

    // Initialize event handlers.
    ChatHandler.init();

    const chat = {
        /**
         * Send a message using the handler.
         * @param {string} message The message to send
         */
        sendMessage: function(message) {
            ChatHandler.sendMessage(message);
        },

        /**
         * Setup event listeners for chat interactions.
         * This coordinates between the UI and the handler.
         */
        setupEventListeners: function() {
            var self = this;

            // Listen for chat bubble click.
            $(document).on('click', '.local_parce-chat-bubble', function(e) {
                e.preventDefault();
                e.stopPropagation();
                ChatUI.toggleWindow();
            });

            // Listen for send message button click.
            $(document).on('click', '.local_parce-send-btn', function(e) {
                e.preventDefault();
                var message = $('.local_parce-message-input').val().trim();
                if (message.length > 0) {
                    self.sendMessage(message);
                    $('.local_parce-message-input').val('');
                }
            });

            // Listen for enter key in message input.
            $(document).on('keypress', '.local_parce-message-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    var message = $(this).val().trim();
                    if (message.length > 0) {
                        self.sendMessage(message);
                        $(this).val('');
                    }
                }
            });

            // Auto-resize message input.
            $(document).on('input', '.local_parce-message-input', function() {
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Listen for close button click.
            $(document).on('click', '.local_parce-close-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                ChatUI.closeWindow();
            });

            // Close window when clicking outside.
            $(document).on('click', function(e) {
                var chatWindow = $('.local_parce-chat-window');
                var chatBubble = $('.local_parce-chat-bubble');
                if (chatWindow.is(':visible') &&
                    !chatWindow.has(e.target).length &&
                    !chatBubble.has(e.target).length &&
                    !$(e.target).hasClass('local_parce-chat-bubble')) {
                    ChatUI.closeWindow();
                }
            });

            Log.debug('Local Parce chat module initialized');
        }
    };

    // Set up the event listeners.
    chat.setupEventListeners();
};
