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
 * Local Parce - Markdown Renderer Module
 *
 * Handles markdown parsing and conversion to HTML.
 * Uses the marked.js library for markdown processing.
 *
 * marked.js is loaded globally via output.php hook as window.marked
 *
 * @module     local_parce/markdown-renderer
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log'], function(Log) {
    'use strict';

    /**
     * MarkdownRenderer object
     * Provides methods to render markdown content to HTML
     */
    var MarkdownRenderer = {

        /**
         * Configuration for marked renderer
         */
        config: {
            breaks: true,
            gfm: true,
            pedantic: false,
            smartLists: true,
            smartypants: false
        },

        /**
         * Initialize the markdown renderer with custom configuration
         *
         * @param {object} customConfig - Custom configuration options
         */
        init: function(customConfig) {
            if (customConfig) {
                this.config = Object.assign(this.config, customConfig);
            }
        },

        /**
         * Parse and render markdown content to HTML
         *
         * @param {string} markdown - The markdown content to render
         * @return {string} HTML rendered content
         */
        render: function(markdown) {
            if (!markdown || typeof markdown !== 'string') {
                return '';
            }

            try {
                // Check if marked is available globally (loaded via output.php)
                if (typeof window.marked === 'undefined') {
                    Log.debug('marked.js library not loaded');
                    return markdown;
                }
                return window.marked.parse(markdown, this.config);
            } catch (error) {
                Log.debug('Error rendering markdown:', error);
                return markdown; // Fallback: return original content if rendering fails
            }
        },

        /**
         * Parse and render markdown content to HTML (inline version)
         * Useful for processing inline markdown without block elements
         *
         * @param {string} markdown - The markdown content to render
         * @return {string} HTML rendered content
         */
        renderInline: function(markdown) {
            if (!markdown || typeof markdown !== 'string') {
                return '';
            }

            try {
                // Check if marked is available globally (loaded via output.php)
                if (typeof window.marked === 'undefined') {
                    Log.debug('marked.js library not loaded');
                    return markdown;
                }
                return window.marked.parseInline(markdown, this.config);
            } catch (error) {
                Log.debug('Error rendering inline markdown:', error);
                return markdown; // Fallback: return original content if rendering fails
            }
        },

        /**
         * Check if content appears to be markdown
         * Simple heuristic to detect markdown syntax
         *
         * @param {string} content - Content to check
         * @return {boolean} True if content likely contains markdown
         */
        isMarkdown: function(content) {
            if (!content || typeof content !== 'string') {
                return false;
            }

            // Check for common markdown patterns
            var markdownPatterns = [
                /#+\s/, // Headers: # ## ###.
                /\*\*.*?\*\*|__.*?__/, // Bold.
                /\*.*?\*|_.*?_/, // Italic.
                /\[.*?\]\(.*?\)/, // Links.
                /^[-*_]\s/m, // Lists.
                /`.*?`/, // Inline code.
                /```/, // Code blocks.
                /^>/m // Blockquotes.
            ];

            return markdownPatterns.some(function(pattern) {
                return pattern.test(content);
            });
        }
    };

    return MarkdownRenderer;
});
