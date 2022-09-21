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
  xiboSubstitutesParser: function(template) {
    var items = [];
    this.each(function() {
      // Parse the template for a list of things to substitute, and match those
      // with content from items.
      var replacement = template;
      var parser = new RegExp('\[.*?\]', 'g');
      var match = parser.exec(template);
      while (match != null) {
        // matched text: match[0]
        // match start: match.index
        // capturing group n: match[n]
        match = parser.exec(template);
      }
    });
    return items;
  },
});
