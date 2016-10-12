/**
* Xibo - Digital Signage - http://www.xibo.org.uk
* Copyright (C) 2009-2016 Daniel Garner
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
        var width; var height; var newWidth; var newHeight;
        
        // Default options
        var defaults = {
          "originalWidth": 0,
          "originalHeight": 0,
          "previewWidth": 0,
          "previewHeight": 0,
          "scaleOverride": 0,
          "widgetDesignWidth": 0,
          "widgetDesignHeight": 0,
          "widgetDesignPadding": 0,
          "itemsPerPage": 0
        };

        options = $.extend({}, defaults, options);

        // Region original width and height
        var originalWidth = options.originalWidth;
        var originalHeight = options.originalHeight;

        if (options.previewWidth === 0 || options.previewHeight === 0) {
            width = $(window).width();
            height = $(window).height();
        }
        else {
            width = options.previewWidth;
            height = options.previewHeight;
        }

        // Calculate the ratio to apply as a scale transform
        var ratio = Math.min(width / options.originalWidth, height / options.originalHeight);

        // The layout designer might provide a scale override, which we need to use because we cannot tell whether
        // the region has been resized or not. If it has been resized in the designer, but not saved, the original
        // width and height will be incorrect (the old saved values). Scale override provides the correct
        // scale in that case.
        if (options.scaleOverride !== 0) {
            ratio = options.scaleOverride;
        }

        newWidth = width / ratio;
        newHeight = height / ratio;

        // The widget is scaled according to the layout dimensions

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
            newWidth = width / ratio;
            newHeight = height / ratio;
        }

        // Apply these details
        $(this).each(function() {

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
            }
            else {
                $(this).css({
                    "transform": "scale(" + ratio + ")",
                    "transform-origin": "0 0"
                });
            }
        });

        return $(this);
    }
});