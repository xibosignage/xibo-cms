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
};
