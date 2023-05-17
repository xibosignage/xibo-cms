/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
  xiboLegacyTemplateRender: function(options, widget) {
    // Default options
    const defaults = {
      moduleType: 'none',
    };

    const newOptions = {};

    options = $.extend({}, defaults, options);

    // For each matched element
    this.each(function(_idx, element) {
      // Forecast
      if (options.moduleType == 'forecast') {
        // Check if we have a dailyForecast placeholder
        const elementHTML = $(element).html();
        const match = elementHTML.match(/\[dailyForecast.*?\]/);

        // Get the number of days
        const numDays = match[0].split('|')[1];
        const offset = match[0].split('|')[2].replace(']', '');

        // Replace HTML on the element
        $(element).html(elementHTML.replace(
          match[0],
          '<div class="forecast-container" ' +
          'data-days-num="' + numDays + '" ' +
          'data-days-offset="' + offset + '"></div>',
        ));
      }

      // Currencies
      if (options.moduleType == 'currencies') {
        const makeTemplateReplacements = function($template) {
          // Replace [itemsTemplate] with a new div element
          $template.html(
            $template.html().replace(
              '[itemsTemplate]',
              '<div class="items-container-helper"></div>',
            ),
          );

          // Change new element parent to be the
          // item-container class and clear it
          $template.find('.items-container-helper')
            .parent().addClass('items-container').empty();

          // Replace image
          let $templateImage = $template.find('img[src="[CurrencyFlag]"]');
          if ($templateImage.length > 0) {
            const imageTemplate = $(element).find('.sample-image').html();

            // Replace HTML with the image template
            $templateImage[0].outerHTML = imageTemplate;

            // Get new image object
            $templateImage = $($templateImage[0]);
          }

          // Replace curly brackets with double brackets
          $template.html(
            $template.html().replaceAll('[', '{{').replaceAll(']', '}}'),
          );

          // Return template
          return $template;
        };

        // Make replacements for item template
        $(element).find('.item-template').replaceWith(
          makeTemplateReplacements(
            $(element).find('.item-template'),
          ),
        );

        // Make replacements for container template
        $(element).find('.template-container').replaceWith(
          makeTemplateReplacements(
            $(element).find('.template-container'),
          ),
        );

        // Compile template for item
        const itemTemplate = Handlebars.compile(
          $(element).find('.item-template').html(),
        );

        // Apply template to items and add them to content
        for (let i = 0; i < widget.items.length; i++) {
          $(itemTemplate(widget.items[i])).addClass('template-item')
            .appendTo($(element).find('#content'));
        }
      }
    });

    return {
      target: $(this),
      options: newOptions,
    };
  },
});
