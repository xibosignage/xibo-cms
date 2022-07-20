/**
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
        var defaults = {
          "originalWidth": 0,
          "originalHeight": 0,
          "widgetDesignWidth": 0,
          "widgetDesignHeight": 0,
          "widgetDesignPadding": 0,
          "itemsPerPage": 0,
          "alignmentH": "center",
          "alignmentV": "middle"
        };

        options = $.extend({}, defaults, options);

        // Region original width and height
        var originalWidth = options.originalWidth;
        var originalHeight = options.originalHeight;

        // Width and Height of the window we're in
        var width = $(window).width();
        var height = $(window).height();

        // Calculate the ratio to apply as a scale transform
        var ratio = Math.min(width / options.originalWidth, height / options.originalHeight);

        // Calculate a new width/height based on the ratio
        var newWidth = width / ratio;
        var newHeight = height / ratio;

        // Does the widget have an original design width/height
        // if so, we need to further scale the widget        
        if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
            
            if(options.itemsPerPage > 0){
              if(newWidth > newHeight){
                //Landscape or square size plus padding
                options.widgetDesignWidth = (options.itemsPerPage * options.widgetDesignWidth) + (options.widgetDesignPadding * (options.itemsPerPage - 1));
                options.widgetDesignHeight = options.widgetDesignHeight;
              } else {
                //Portrait size plus padding
                options.widgetDesignHeight = (options.itemsPerPage * options.widgetDesignHeight) + (options.widgetDesignPadding * (options.itemsPerPage - 1));
                options.widgetDesignWidth = options.widgetDesignWidth;
              }
            }
            
            // Calculate the ratio between the new
            var widgetRatio = Math.min(newWidth / options.widgetDesignWidth, newHeight / options.widgetDesignHeight);

            ratio = ratio * widgetRatio;
            newWidth = options.widgetDesignWidth;
            newHeight = options.widgetDesignHeight;
        }

        // Multiple element options
        var mElOptions = {};

        // Multiple elements per page
        if(options.numCols != undefined || options.numRows != undefined) {
            // Content dimensions and scale ( to create multiple elements based on the body scale fomr the xibo scaler )
            mElOptions.contentWidth = (options.numCols > 1) ? (options.widgetDesignWidth * options.numCols) : options.widgetDesignWidth;
            mElOptions.contentHeight = (options.numRows > 1) ? (options.widgetDesignHeight * options.numRows) : options.widgetDesignHeight;

            mElOptions.contentScaleX = width / mElOptions.contentWidth;
            mElOptions.contentScaleY = height / mElOptions.contentHeight;
        }

        // Apply these details
        $(this).each(function() {
            if(!$.isEmptyObject(mElOptions)) {
                // calculate/update ratio
                ratio = Math.min(mElOptions.contentScaleX, mElOptions.contentScaleY);

                $(this).css('transform-origin', '0 0');
                $(this).css('transform', 'scale(' + ratio + ')');
                $(this).width(mElOptions.contentWidth);
                $(this).height(mElOptions.contentHeight);

                $(this).find('.multi-element').css({
                    overflow: 'hidden',
                    float: 'left',
                    width: options.widgetDesignWidth,
                    height: options.widgetDesignHeight
                });
            } else {
                $(this).css({
                    width: newWidth,
                    height: newHeight
                });

                // Handle the scaling
                // What IE are we?
                if ($("body").hasClass("ie7") || $("body").hasClass("ie8")) {
                    $(this).css({
                        "filter": "progid:DXImageTransform.Microsoft.Matrix(M11=" + ratio + ", M12=0, M21=0, M22=" + ratio + ", SizingMethod=\'auto expand\'"
                    });
                } else {

                    $(this).css({
                        "transform": "scale(" + ratio + ")",
                        "transform-origin": "0 0"
                    });
                }
            }

            // Set ratio on the body incase we want to get it easily
            $(this).data('ratio', ratio);

            // Handle alignment (do not add position absolute unless needed)
            if (!options.type || options.type !== 'text') {
              $(this).css('position', 'absolute');

              //  Horizontal alignment
              if (options.alignmentH === 'right') {
                $(this).css('left', width - ($(this).width() * ratio));
              } else if (options.alignmentH === 'center') {
                $(this).css('left', (width / 2) - ($(this).width() * ratio) / 2);
              }

              //  Vertical alignment
              if (options.alignmentV === 'bottom') {
                $(this).css('top', height - ($(this).height() * ratio));
              } else if (options.alignmentV === 'middle') {
                $(this).css('top', (height / 2) - ($(this).height() * ratio) / 2);
              }
            }
        });

        return $(this);
    }
});