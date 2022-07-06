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
  // Call the data url and parse out the template.
  $.each(widgetData, function(key, widget) {
    // Load the template
    const template = $('#hbs-' + widget.templateId);
    const hbs = Handlebars.compile(template.html());
    const $content = $('#content');
    $.ajax({
      method: 'GET',
      url: widget.url,
      success: function(data) {
        $.each(data, function(key, item) {
          // TODO: parse through the data parser if one exists for this widget.
          $content.append(hbs(item));
        });

        // TODO: layout scaler
      },
    });
  });
});
