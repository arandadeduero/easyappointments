/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Strings utility.
 *
 * This module implements the functionality of strings.
 */
window.App.Utils.String = (function () {
    /**
     * Upper case the first letter of the provided string.
     *
     * Old Name: GeneralFunctions.upperCaseFirstLetter
     *
     * @param {String} value
     *
     * @returns {string}
     */
    function upperCaseFirstLetter(value) {
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    /**
     * Escape HTML content with the use of jQuery.
     *
     * Old Name: GeneralFunctions.escapeHtml
     *
     * @param {String} content
     *
     * @return {String}
     */
    function escapeHtml(content) {
        return $('<div/>').text(content).html();
    }

    /**
     * Sanitize HTML content before rendering it in customer-facing views.
     *
     * @param {String} content
     *
     * @return {String}
     */
    function sanitizeHtml(content) {
        const template = document.createElement('template');
        const allowedTags = new Set([
            'A',
            'B',
            'BLOCKQUOTE',
            'BR',
            'DIV',
            'EM',
            'H1',
            'H2',
            'H3',
            'H4',
            'H5',
            'H6',
            'HR',
            'I',
            'LI',
            'OL',
            'P',
            'SMALL',
            'SPAN',
            'STRONG',
            'SUB',
            'SUP',
            'TABLE',
            'TBODY',
            'TD',
            'TH',
            'THEAD',
            'TR',
            'U',
            'UL',
        ]);
        const blockedTags = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED']);
        const allowedAttributes = {
            A: new Set(['href', 'rel', 'target', 'title']),
        };

        template.innerHTML = String(content || '');

        function cleanNode(node) {
            Array.from(node.childNodes).forEach((childNode) => {
                if (childNode.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                const tagName = childNode.tagName;

                if (blockedTags.has(tagName)) {
                    childNode.remove();
                    return;
                }

                cleanNode(childNode);

                if (!allowedTags.has(tagName)) {
                    childNode.replaceWith(...Array.from(childNode.childNodes));
                    return;
                }

                Array.from(childNode.attributes).forEach((attribute) => {
                    const attributeName = attribute.name.toLowerCase();
                    const tagAllowedAttributes = allowedAttributes[tagName] || new Set();

                    if (!tagAllowedAttributes.has(attributeName)) {
                        childNode.removeAttribute(attribute.name);
                        return;
                    }

                    if (attributeName === 'href') {
                        const href = attribute.value.trim();

                        if (!/^(https?:|mailto:|tel:|\/|#)/i.test(href)) {
                            childNode.removeAttribute(attribute.name);
                        }
                    }
                });

                if (tagName === 'A') {
                    childNode.setAttribute('rel', 'noopener noreferrer');
                }
            });
        }

        cleanNode(template.content);

        return template.innerHTML;
    }

    return {
        upperCaseFirstLetter,
        escapeHtml,
        sanitizeHtml,
    };
})();
