/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
jQuery.fn.extend({
  xiboSubstitutesParser: function(
    template,
    dateFormat,
    dateFields = [],
    mapping = {},
  ) {
    const items = [];
    const parser = new RegExp('\\[.*?\\]', 'g');
    const pipeParser = new RegExp('\\|{1}', 'g');
    this.each(function(_idx, data) {
      // Parse the template for a list of things to substitute, and match those
      // with content from items.
      let replacement = template;
      let match = parser.exec(template);
      while (match != null) {
        // Matched text: match[0], match start: match.index,
        // capturing group n: match[n]
        // Remove the [] from the match
        let variable = match[0]
          .replace('[', '')
          .replace(']', '');
        variable = variable.charAt(0).toLowerCase() + variable.substring(1);

        // Check if variable has its own formatting
        // Then, parse it and use later as dateFormat
        let formatFromTemplate = null;

        if (variable.match(pipeParser) !== null &&
          variable.match(pipeParser).length === 1) {
          const variableWithFormat = variable.split('|');
          formatFromTemplate = variableWithFormat[1];
          variable = variableWithFormat[0];
        }

        if (mapping[variable]) {
          variable = mapping[variable];
        }
        let value = '';

        // Does this variable exist? or is it one of the ones in our map
        if (data.hasOwnProperty(variable)) {
          // Use it
          value = data[variable];

          // Is it a date field?
          dateFields.forEach((field) => {
            if (field === variable) {
              value = moment(value).format(formatFromTemplate !== null ?
                formatFromTemplate : dateFormat);
            }
          });
        }

        // If value is null, set it as empty string
        (value === null) && (value = '');

        // Finally set the replacement in the template
        replacement = replacement.replace(match[0], value);

        // Get the next match
        match = parser.exec(template);
      }

      // Add to our items
      items.push(replacement);
    });
    return items;
  },
});
