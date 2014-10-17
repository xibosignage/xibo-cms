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
    xiboIframeScaler: function(options) {
        var width; var height;

        // All we worry about is the item we have been working on ($(this))
        // We want to set its margins and scale according to the provided options.
        width = options.iframeWidth + options.offsetLeft;
        height = options.iframeHeight + options.offsetTop;

        $(this).each(function() {
            // Mode
            if (options.modeId == 1) {
                // We shouldn't ever get here.
                $(this).css({
                    "width": options.originalWidth,
                    "height": options.originalHeight
                });
            }
            else if (options.modeId == 3) {
                // Best fit, set the scale so that the web-page fits inside the region
                options.scale = Math.min(options.originalWidth / options.iframeWidth, options.originalHeight / options.iframeHeight);

                // Remove the offsets
                options.offsetTop = 0;
                options.offsetLeft = 0;

                // Set frame to the full size and scale it back to fit inside the window
                if ($("body").hasClass("ie7") || $("body").hasClass("ie8")) {
                        $(this).css({
                            "filter": "progid:DXImageTransform.Microsoft.Matrix(M11=" + options.scale + ", M12=0, M21=0, M22=" + options.scale + ", SizingMethod=\'auto expand\'"
                        });
                    }
                    else {
                        $(this).css({
                            "transform": "scale(" + options.scale + ")",
                            "transform-origin": "0 0",
                            "width": options.iframeWidth,
                            "height": options.iframeHeight
                        });
                    }
            }
            else {
                // Manual Position. This is the default.
            
                // Margins on frame
                $(this).css({
                    "margin-top": -1 * options.offsetTop,
                    "margin-left": -1 * options.offsetLeft,
                    "width": width,
                    "height": height
                });

                // Do we need to scale?
                if (options.scale !== 1 && options.scale !== 0) {

                    if ($("body").hasClass("ie7") || $("body").hasClass("ie8")) {
                        $(this).css({
                            "filter": "progid:DXImageTransform.Microsoft.Matrix(M11=" + options.scale + ", M12=0, M21=0, M22=" + options.scale + ", SizingMethod=\'auto expand\'"
                        });
                    }
                    else {
                        $(this).css({
                            "transform": "scale(" + options.scale + ")",
                            "transform-origin": "0 0",
                            "width": width / options.scale,
                            "height": height / options.scale
                        });
                    }
                }
            }
        });
    }
});