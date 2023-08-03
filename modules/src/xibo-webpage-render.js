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
  xiboIframeScaler: function(options) {
    let width;
    let height;
    const iframeWidth = parseInt(options.iframeWidth);
    const iframeHeight = parseInt(options.iframeHeight);

    // All we worry about is the item we have been working on ($(this))
    $(this).each(function(_idx, el) {
      // Mode
      if (options.modeid == 1) {
        // Open Natively
        // We shouldn't ever get here, because the
        // Layout Designer will not show a preview for mode 1, and
        // the client will not call GetResource at all for mode 1
        $(el).css({
          width: options.originalWidth,
          height: options.originalHeight,
        });
      } else if (options.modeid == 3) {
        // Best fit, set the scale so that the web-page fits inside the region

        // If there is a preview width and height
        //  then we want to reset the original width and height in the
        // ratio calculation so that it represents the
        //  preview width/height * the scale override
        let originalWidth = options.originalWidth;
        let originalHeight = options.originalHeight;

        if (options.scaleOverride !== 0) {
          // console.log("Iframe: Scale Override is set,
          // meaning we want to scale according to the provided
          // scale of " + options.scaleOverride + ". Provided Width is " +
          // options.previewWidth + ". Provided Height is " +
          // options.previewHeight + ".");
          ratio = options.scaleOverride;
          originalWidth = options.previewWidth / ratio;
          originalHeight = options.previewHeight / ratio;
        }

        options.scale = Math.min(
          originalWidth / iframeWidth,
          originalHeight / iframeHeight,
        );

        // Remove the offsets
        options.offsetTop = 0;
        options.offsetLeft = 0;

        // Set frame to the full size and scale it back to fit inside the window
        if ($('body').hasClass('ie7') || $('body').hasClass('ie8')) {
          $(el).css({
            filter: 'progid:DXImageTransform.Microsoft.Matrix(M11=' +
              options.scale + ', M12=0, M21=0, M22=' + options.scale +
              ', SizingMethod=\'auto expand\'',
          });
        } else {
          $(el).css({
            transform: 'scale(' + options.scale + ')',
            'transform-origin': '0 0',
            width: iframeWidth,
            height: iframeHeight,
          });
        }
      } else {
        // Manual Position. This is the default.
        // We want to set its margins and scale
        // according to the provided options.

        // Offsets
        const offsetTop = parseInt(options.offsetTop) ?
          parseInt(options.offsetTop) : 0;
        const offsetLeft = parseInt(options.offsetLeft) ?
          parseInt(options.offsetLeft) : 0;

        // Dimensions
        width = iframeWidth + offsetLeft;
        height = iframeHeight + offsetTop;

        // Margins on frame
        $(el).css({
          'margin-top': -1 * offsetTop,
          'margin-left': -1 * offsetLeft,
          width: width,
          height: height,
        });

        // Do we need to scale?
        if (options.scale !== 1 && options.scale !== 0) {
          if ($('body').hasClass('ie7') || $('body').hasClass('ie8')) {
            $(el).css({
              filter: 'progid:DXImageTransform.Microsoft.Matrix(M11=' +
                options.scale + ', M12=0, M21=0, M22=' +
                options.scale + ', SizingMethod=\'auto expand\'',
            });
          } else {
            $(el).css({
              transform: 'scale(' + options.scale + ')',
              'transform-origin': '0 0',
              width: width / options.scale,
              height: height / options.scale,
            });
          }
        }
      }
    });
  },
});
