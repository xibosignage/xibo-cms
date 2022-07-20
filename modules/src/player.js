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
  $.each(widgetData, function(_key, widget) {
    // Load the template
    const $template = $('#hbs-' + widget.templateId);
    const hbs = Handlebars.compile($template.html());
    const $content = $('#content');

    // Set some variables
    // Get template height and width
    globalOptions.widgetDesignWidth = $template.data('width');
    globalOptions.widgetDesignHeight = $template.data('height');

    // Save template properties as global options for scaling
    for (const key in widget.templateProperties) {
      if (widget.templateProperties.hasOwnProperty(key)) {
        globalOptions[key] = widget.templateProperties[key];
      }
    }

    // Set scale flag to true
    window.scaleContent = true;

    $.ajax({
      method: 'GET',
      url: widget.url,
    }).done(function(data) {
      $.each(data, function(_key, item) {
        // TODO: parse through the data parser if one exists for this widget
        // Parse the data if there is a parser function
        if (typeof dataParser === 'function') {
          item = dataParser(item, widget.templateProperties);
        }
        if (!globalOptions.itemsPerPage || _key < globalOptions.itemsPerPage) {
          $content.append(hbs(item));
        }
      });

      // Scale the content
      $('body').xiboLayoutScaler(globalOptions);
    }).fail(function(jqXHR, textStatus, errorThrown) {
      console.log( 'fail' );
      console.log(jqXHR, textStatus, errorThrown);
    });
  });
});
