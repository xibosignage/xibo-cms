/**
* Xibo - Digital Signage - http://www.xibo.org.uk
* Copyright (C) 2009-2014 Daniel Garner
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
        var width; var height;

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

        var ratio = Math.min(width / options.originalWidth, height / options.originalHeight);

        if (options.scaleOverride !== 0) {
            //console.log("Scale Override is set, meaning we want to scale according to the provided scale of " + options.scaleOverride + ". Provided Width is " + width + ". Provided Height is " + height + ".");
            ratio = options.scaleOverride;
            originalWidth = width / ratio;
            originalHeight = height / ratio;
        }

        $(this).each(function() {

            $(this).css({
                width: originalWidth,
                height: originalHeight
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
