/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
  xiboTextScaler: function(options) {
    // Default options
    const defaults = {
      fitTarget: '',
      fontFamily: '',
      fitScaleAxis: 'x',
      isIcon: false,
    };

    options = $.extend({}, defaults, options);

    // For each matched element
    this.each(function(_key, el) {
      const elWidth = $(el).width();
      const elHeight = $(el).height();

      // Continue only if we have a valid element
      if (elWidth == 0 || elHeight == 0) {
        return $(el);
      }

      const $fitTarget = (options.fitTarget != '') ?
        $(el).find(options.fitTarget) :
        $(el);

      const waitForFontToLoad = function(font, callback) {
        // Wait for font to load
        // but also make sure target has any dimensions
        if (
          $fitTarget.width() > 0 &&
          $fitTarget.height() > 0 &&
          document.fonts.check(font)
        ) {
          callback();
        } else {
          setTimeout(function() {
            waitForFontToLoad(font, callback);
          }, 100);
        }
      };

      if (options.isIcon) {
        const fontFamily = (options.fontFamily) ?
          options.fontFamily : $fitTarget.css('font-family');
        const maxFontSize = 1000;
        let fontSize = 1;

        $fitTarget.css('font-size', fontSize);

        // Wait for font to load, then run resize
        waitForFontToLoad(fontSize + 'px ' + fontFamily, function() {
          while (fontSize < maxFontSize) {
            const auxFontSize = fontSize + 2;

            // Increase font
            $fitTarget.css('font-size', fontSize);

            const doesItBreak = (options.fitScaleAxis === 'y') ?
              $fitTarget.height() > elHeight :
              $fitTarget.width() > elWidth;

            // When it breaks, use previous fontSize
            if (doesItBreak) {
              break;
            } else {
              // Increase font size and continue
              fontSize = auxFontSize;
            }
          }

          // Set font size to element
          $fitTarget.css('font-size', fontSize);
        });
      } else {
        const maxFontSize = 1000;
        let fontSize = 1;
        // Text
        const fontFamily = (options.fontFamily) ?
          options.fontFamily : 'sans-serif';

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const text = $fitTarget.html().trim();
        const fontStyle = $fitTarget.css('font-style');
        const fontWeight = $fitTarget.css('font-weight');

        // If text is empty, dont resize
        if (text.length === 0) {
          return $(el);
        }

        // Set a low font size to begin with
        $fitTarget.css('font-size', fontSize);
        $fitTarget.hide();

        // Wait for font to load, then run resize
        waitForFontToLoad(fontWeight + ' ' + fontStyle + ' ' +
          fontSize + 'px ' + fontFamily, function() {
          context.font =
            fontWeight + ' ' + fontStyle + ' ' +
            fontSize + 'px ' + fontFamily;

          while (fontSize < maxFontSize) {
            const auxFontSize = fontSize + 1;

            // Increase font
            context.font =
              fontWeight + ' ' + fontStyle + ' ' +
              auxFontSize + 'px ' + fontFamily;

            const doesItBreak = (options.fitScaleAxis === 'y') ?
              context.measureText(text).height > elHeight :
              context.measureText(text).width > elWidth;

            // When it breaks, use previous fontSize
            if (doesItBreak) {
              break;
            } else {
              // Increase font size and continue
              fontSize = auxFontSize;
            }
          }

          // Set font size to element
          $fitTarget.css('font-size', fontSize);
          $fitTarget.show();
        });
      }
    });

    return $(this);
  },
});
