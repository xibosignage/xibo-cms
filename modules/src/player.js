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
    const hbs = ($template.length > 0) ?
      Handlebars.compile($template.html()) :
      null;
    const $content = $('#content');
    $.ajax({
      method: 'GET',
      url: widget.url,
    }).done(function(data) {
      $.each(data, function(_key, item) {
        // If we have items per page, add only the first n items
        if (
          !widget.templateProperties.itemsPerPage ||
           _key < widget.templateProperties.itemsPerPage
        ) {
          // Parse the data if there is a parser function
          if (typeof window['dataParser_' + widget.widgetId] === 'function') {
            item = window[
              'dataParser_' + widget.widgetId
            ](item, widget.properties);
          }
          // Add the item to the content
          (hbs) && $content.append(hbs(item));
        }
      });

      // Save template height and width if exists to global options
      if ($template.length > 0) {
        globalOptions.widgetDesignWidth = $template.data('width');
        globalOptions.widgetDesignHeight = $template.data('height');
      }

      // Save template properties as global options for scaling
      for (const key in widget.templateProperties) {
        if (widget.templateProperties.hasOwnProperty(key)) {
          globalOptions[key] = widget.templateProperties[key];
        }
      }

      // Handle the scaling of the widget
      const $target = $('body');
      const targetId = widget.widgetId;
      if (
        typeof window['render_' + widget.templateId] === 'function'
      ) { // Custom scaler
        window.scaleContent =
          window['render_' + widget.templateId];
      } else { // Default scaler
        // Default scaler
        window.scaleContent = function(
          {
            id,
            item = $('body'),
            options = globalOptions,
          } = {},
        ) {
          // Scale the content
          $(item).xiboLayoutScaler(options);
        };
      }

      // Call the scale function on body
      window.scaleContent({
        id: targetId,
        target: $target,
        options: globalOptions,
      });
    }).fail(function(jqXHR, textStatus, errorThrown) {
      console.log( 'fail' );
      console.log(jqXHR, textStatus, errorThrown);
    });
  });
});
