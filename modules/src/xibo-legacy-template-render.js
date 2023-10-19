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

// Based on https://github.com/octalmage/phptomoment/tree/master
const PHP_TO_MOMENT = {
  d: 'DD',
  D: 'ddd',
  j: 'D',
  l: 'dddd',
  N: 'E',
  S: 'o',
  w: 'e',
  z: 'DDD',
  W: 'W',
  F: 'MMMM',
  m: 'MM',
  M: 'MMM',
  n: 'M',
  t: '',
  L: '',
  o: 'YYYY',
  Y: 'YYYY',
  y: 'YY',
  a: 'a',
  A: 'A',
  B: '',
  g: 'h',
  G: 'H',
  h: 'hh',
  H: 'HH',
  i: 'mm',
  s: 'ss',
  u: 'SSS',
  e: 'zz',
  I: '',
  O: '',
  P: '',
  T: '',
  Z: '',
  c: '',
  r: '',
  U: 'X',
  '\\': '',
};

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
        const match = elementHTML.match(/\[dailyForecast.*?\]/g);

        if (match) {
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

        // Check if we have a time placeholder
        $(element).html(
          $(element).html().replace(/\[time\|.*?\]/g, function(match) {
            const oldFormat = match.split('|')[1].replace(']', '');
            const newFormat = PHP_TO_MOMENT[oldFormat];

            return '[time|' + newFormat + ']';
          }),
        );
      }

      // Social Media
      if (options.moduleType == 'social-media') {
        // Template HTML
        let templateHTML = $(element).find('.item-template').html();

        // If we have NameTrimmed, replace it with a trimmed Name
        const matches = templateHTML.match(/\[(.*?)\]/g);

        if (Array.isArray(matches)) {
          for (let index = 0; index < matches.length; index++) {
            const match = matches[index];
            const matchCropped = match.substring(1, match.length - 1);
            let replacement = '';

            switch (matchCropped) {
              case 'Tweet':
                replacement = '{{text}}';
                break;
              case 'User':
                replacement = '{{user}}';
                break;
              case 'ScreenName':
                replacement = '{{screenName}}';
                break;
              case 'Date':
                replacement = '{{date}}';
                break;
              case 'Location':
                replacement = '{{location}}';
                break;
              case 'ProfileImage':
                replacement = '<img src="{{userProfileImage}}" />';
                break;
              case 'ProfileImage|normal':
                replacement = '<img src="{{userProfileImage}}" />';
                break;
              case 'ProfileImage|mini':
                replacement = '<img src="{{userProfileImageMini}}" />';
                break;
              case 'ProfileImage|bigger':
                replacement = '<img src="{{userProfileImageBigger}}" />';
                break;
              case 'Photo':
                replacement = '<img src="{{photo}}" />';
                break;
              case 'TwitterLogoWhite':
                replacement =
                  $(element).find('.twitter-blue-logo').data('url');
                break;
              case 'TwitterLogoBlue':
                replacement =
                  $(element).find('.twitter-white-logo').data('url');
              default:
                break;
            }

            // Replace HTML on the element
            templateHTML = templateHTML.replace(
              match,
              replacement,
            );
          }
        }

        // Compile template for item
        const itemTemplate = Handlebars.compile(
          templateHTML,
        );

        // Apply template to items and add them to content
        for (let i = 0; i < widget.items.length; i++) {
          const item = widget.items[i];

          // Create new media item
          const $mediaItem =
            $('<div class="social-media-item"></div>');

          // Add template content to media item
          $mediaItem.html(itemTemplate(item));

          // Add to content
          $mediaItem.appendTo($(element).find('#content'));
        }
      }

      // Currencies and stocks
      if (
        options.moduleType == 'currencies' ||
        options.moduleType == 'stocks'
      ) {
        // Property to trim names
        const trimmedNames = [];

        const makeTemplateReplacements = function($template) {
          let templateHTML = $template.html();
          // Replace [itemsTemplate] with a new div element
          templateHTML = templateHTML.replace(
            '[itemsTemplate]',
            '<div class="items-container-helper"></div>',
          );

          // If we have NameTrimmed, replace it with a trimmed Name
          const matches = templateHTML.match(/\[NameTrimmed.*?\]/g);

          if (Array.isArray(matches)) {
            for (let index = 0; index < matches.length; index++) {
              const match = matches[index];

              // Get the string length
              trimmedNames.push(match.split('|')[1].replace(']', ''));

              // Replace HTML on the element
              templateHTML = templateHTML.replace(
                match,
                '[NameTrimmed' + (trimmedNames.length - 1) + ']',
              );
            }
          }

          // Add html back to container
          $template.html(templateHTML);

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
          const item = widget.items[i];
          // if we have trimmedNames, add those proterties to each item
          for (let index = 0; index < trimmedNames.length; index++) {
            const trimmedLength = trimmedNames[index];

            item['NameTrimmed' + index] = item.Name.substring(0, trimmedLength);
          }

          $(itemTemplate(item)).addClass('template-item')
            .appendTo($(element).find('#content'));
        }
      }

      // Article
      if (options.moduleType == 'article') {
        widget.properties.template = widget.properties.template
          .replaceAll('[Link|image]', '<img src="[Image]"></div>');
      }
    });

    return {
      target: $(this),
      options: newOptions,
    };
  },
});
