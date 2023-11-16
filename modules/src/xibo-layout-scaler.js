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
  xiboLayoutScaler: function(options) {
    // Default options
    const defaults = {
      originalWidth: 0,
      originalHeight: 0,
      widgetDesignWidth: 0,
      widgetDesignHeight: 0,
      widgetDesignGap: 0,
      itemsPerPage: 0,
      alignmentH: 'center',
      alignmentV: 'middle',
      displayDirection: 0,
      // 0 = undefined (default), 1 = horizontal, 2 = vertical
    };

    options = $.extend({}, defaults, options);

    // Width and Height of the window we're in
    const width = $(window).width();
    const height = $(window).height();

    // Calculate the ratio to apply as a scale transform
    let ratio =
      Math.min(width / options.originalWidth, height / options.originalHeight);

    // Calculate a new width/height based on the ratio
    let newWidth = width / ratio;
    let newHeight = height / ratio;

    // Does the widget have an original design width/height
    // if so, we need to further scale the widget
    if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
      if (options.itemsPerPage > 0) {
        if (
          (newWidth >= newHeight && options.displayDirection == '0') ||
          (options.displayDirection == '1')
        ) {
          // Landscape or square size plus padding
          // display direction is horizontal
          options.widgetDesignWidth =
            (options.itemsPerPage * options.widgetDesignWidth) +
            (options.widgetDesignGap * (options.itemsPerPage - 1));
          options.widgetDesignHeight = options.widgetDesignHeight;
        } else if (
          (newWidth < newHeight && options.displayDirection == '0') ||
          (options.displayDirection == '2')
        ) {
          // Portrait size plus padding
          // display direction is vertical
          options.widgetDesignHeight =
            (options.itemsPerPage * options.widgetDesignHeight) +
            (options.widgetDesignGap * (options.itemsPerPage - 1));
          options.widgetDesignWidth = options.widgetDesignWidth;
        }
      }

      // Calculate the ratio between the new
      const widgetRatio =
        Math.min(
          newWidth / options.widgetDesignWidth,
          newHeight / options.widgetDesignHeight);

      ratio = ratio * widgetRatio;
      newWidth = options.widgetDesignWidth;
      newHeight = options.widgetDesignHeight;
    }

    // Multiple element options
    const mElOptions = {};

    // Multiple elements per page
    if (options.numCols != undefined || options.numRows != undefined) {
      // Content dimensions and scale ( to create
      // multiple elements based on the body scale fomr the xibo scaler )
      mElOptions.contentWidth =
        (options.numCols > 1) ?
          (options.widgetDesignWidth * options.numCols) :
          options.widgetDesignWidth;
      mElOptions.contentHeight =
        (options.numRows > 1) ?
          (options.widgetDesignHeight * options.numRows) :
          options.widgetDesignHeight;

      mElOptions.contentScaleX = width / mElOptions.contentWidth;
      mElOptions.contentScaleY = height / mElOptions.contentHeight;

      // calculate/update ratio
      ratio = Math.min(mElOptions.contentScaleX, mElOptions.contentScaleY);
    }

    // Do nothing and return $(this) when ratio = 1
    if (ratio == 1) {
      return $(this);
    }

    // Apply these details
    $(this).each(function(_idx, el) {
      if (!$.isEmptyObject(mElOptions)) {
        $(el).css('transform-origin', '0 0');
        $(el).css('transform', 'scale(' + ratio + ')');
        $(el).width(mElOptions.contentWidth);
        $(el).height(mElOptions.contentHeight);

        $(el).find('.multi-element').css({
          overflow: 'hidden',
          float: 'left',
          width: options.widgetDesignWidth,
          height: options.widgetDesignHeight,
        });
      } else {
        $(el).css({
          width: newWidth,
          height: newHeight,
        });

        // Handle the scaling
        // What IE are we?
        if ($('body').hasClass('ie7') || $('body').hasClass('ie8')) {
          $(el).css({
            filter: 'progid:DXImageTransform.Microsoft.Matrix(M11=' +
            ratio +
            ', M12=0, M21=0, M22=' +
             ratio +
            ', SizingMethod=\'auto expand\'',
          });
        } else {
          $(el).css({
            transform: 'scale(' + ratio + ')',
            'transform-origin': '0 0',
          });
        }
      }

      // Set ratio on the body incase we want to get it easily
      $(el).attr('data-ratio', ratio);

      // Handle alignment (do not add position absolute unless needed)
      if (!options.type || options.type !== 'text') {
        $(el).css('position', 'absolute');

        //  Horizontal alignment
        if (options.alignmentH === 'right') {
          $(el).css('left', width - ($(el).width() * ratio));
        } else if (options.alignmentH === 'center') {
          $(el).css('left', (width / 2) - ($(el).width() * ratio) / 2);
        }

        //  Vertical alignment
        if (options.alignmentV === 'bottom') {
          $(el).css('top', height - ($(el).height() * ratio));
        } else if (options.alignmentV === 'middle') {
          $(el).css('top', (height / 2) - ($(el).height() * ratio) / 2);
        }
      }
    });

    return $(this);
  },
});
