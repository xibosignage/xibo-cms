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
$(function() {
  // Get a message from the parent window
  // RUN ON IFRAME
  window.onmessage = function(e) {
    if (
      e.data.method == 'renderContent'
    ) {
      // Update global options for the widget
      globalOptions.originalWidth = e.data.options.originalWidth;
      globalOptions.originalHeight = e.data.options.originalHeight;

      // Call render content if exists
      if (typeof renderContent === 'function') {
        window.renderContent(
          e.data.options.id, // id
          $('body'), // target
          widget.items, // items
          Object.assign(widget.properties, globalOptions), // properties
        );
      }
    }
  };
});
