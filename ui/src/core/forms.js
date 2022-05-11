/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2022 Xibo Signage Ltd
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

// Common funtions/tools
const Common = require('../editor-core/common.js');

window.forms = {
    /**
     * Create form inputs from an array of elements
     * @param {object} properties - The properties to set on the form
     * @param {object} targetContainer - The container to add the properties to
     */
    createFields: function(properties, targetContainer) {
        for (var key in properties) {
            var property = properties[key];

            // Handle default value
            if (property.value === null && property.default !== undefined) {
                property.value = property.default;
            }

            // Append the property to the target container
            $(templates.forms[property.type](property)).appendTo($(targetContainer));
        }

        // Initialise tooltips
        Common.reloadTooltips($(targetContainer));
    },
    /**
     * Handle form field replacements
     * @param {*} container - The form container
     */
    handleFormReplacements: function(container, baseObject) {

        const replaceHTML = function(htmlString) {
            htmlString = htmlString.replace(/\%\S*\%/g, function(match) {
                const trimmedMatch = match.slice(1, -1);

                // Repplace trimmed match with the value of the base object
                return trimmedMatch.split('.').reduce((a, b) => a[b], baseObject);
            });

            return htmlString
        }

        // Replace title and alternative title for the elements that have them
        $(container).find('.xibo-form-input > [title], .xibo-form-btn[title]').each(function() {
            const $element = $(this);
            const elementTitle = $element.attr('title');
            const elementAlternativeTitle = $element.attr('data-original-title');

            // If theres title and it contains a replacement special character
            if(elementTitle && elementTitle.indexOf('%') > -1) {
                $element.attr('title', replaceHTML(elementTitle));
            }

            // If theres an aletrnative title and it contains a replacement special character
            if(elementAlternativeTitle && elementAlternativeTitle.indexOf('%') > -1) {
                $element.attr('data-original-title', replaceHTML(elementAlternativeTitle));
            }
        });

        // Replace inner html for input direct children
        $(container).find('.xibo-form-input > *, .xibo-form-btn').each(function() {
            const $element = $(this);
            const elementInnerHTML = $element.html();

            // If theres inner html and it contains a replacement special character
            if(elementInnerHTML && elementInnerHTML.indexOf('%') > -1) {
                $element.html(replaceHTML(elementInnerHTML));
            }
        });
    },
    /**
     * Set the form conditions
     * @param {object} container - The form container
     */
    setConditions: function(container) {
        $(container).find('.xibo-form-input[data-render-condition]').each(function() {
            var condition = $(this).data('renderCondition');
            var $conditionTarget = $(container).find(condition.target)
            const $self = $(this);

            const checkCondition = function() {
                // Get condition target value based on type
                const conditionTargetValue = ($conditionTarget.attr('type') == 'checkbox') ? $conditionTarget.is(":checked") : $conditionTarget.val();

                // If the condition is true
                if (condition.condition === '==' && conditionTargetValue != condition.value) {
                    $self.hide();
                }
                else if (condition.condition === '!=' && conditionTargetValue == condition.value) {
                    $self.hide();
                }
                else if (condition.condition === '>' && conditionTargetValue <= condition.value) {
                    $self.hide();
                }
                else if (condition.condition === '<' && conditionTargetValue >= condition.value) {
                    $self.hide();
                }
                else if (condition.condition === '>=' && conditionTargetValue < condition.value) {
                    $self.hide();
                }
                else if (condition.condition === '<=' && conditionTargetValue > condition.value) {
                    $self.hide();
                }
                else {
                    $self.show();
                }
            };

            // If condition isn't null or empty
            if (condition !== null && condition !== '') {
                // Check on change
                $conditionTarget.on('change', checkCondition);

                // Check on load
                checkCondition();
            }
        });
    },
    /**
     * Check for spacing issues on the form inputs
     * @param {object} container - The form container
     */
     checkForSpacingIssues: function($container) {
        $container.find('input[type=text]').each(function(index, el) {
            formRenderDetectSpacingIssues(el);

            $(el).on("keyup", _.debounce(function() {
                formRenderDetectSpacingIssues(el);
            }, 500));
        });
    }
};
