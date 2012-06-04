/**
* Xibo - Digital Signage - http://www.xibo.org.uk
* Copyright (C) 2009-2012 Daniel Garner
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
    xiboRender: function(options) {

        // Any options?
        if (options === undefined || options === null) {
            options = {
                direction: "none",
                width: 100,
                height: 100,
                scrollSpeed: 2
            };
        }

        this.each(function() {
            // Scale text to fit the box
            //$(this).fit2Box();

            // Set an interval
            if (options.direction != "none") {
                // Scroll speed is going to be wrong (30 was the default before)
                var scrollSpeed = (options.scrollSpeed > 15) ? 3 : options.scrollSpeed;

                // Set some options on the node, before calling marquee (behaviour, direction, scrollAmount, width, height)
                $(this).attr({
                    direction: options.direction,
                    width: options.width,
                    height: options.height,
                    scrollamount: scrollSpeed,
                    behaviour: "scroll"
                });

                // Create a marquee out of it
                $(this).marquee();
            }
        });
    }
});