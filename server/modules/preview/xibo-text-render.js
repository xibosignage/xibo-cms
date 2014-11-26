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
    xiboTextRender: function(options, items) {

        // Default options
        var defaults = {
            "fx": "none",
            "duration": "50",
            "durationIsPerItem": false,
            "numItems": 0,
            "takeItemsFrom": "start",
            "itemsPerPage": 0,
            "speed": "2",
            "previewWidth": 0,
            "previewHeight": 0,
            "scaleOverride": 0
        };

        options = $.extend({}, defaults, options);

        // For each matched element
        this.each(function() {

            //console.log("[Xibo] Selected: " + this.tagName.toLowerCase());
            //console.log("[Xibo] Options: " + JSON.stringify(options));
            
            // 1st Objective - filter the items array we have been given
            // settings involved: 
            //  items, 
            //  numItems (ticker number of items from the start/end),
            //  takeItemsFrom (ticker sort or reverse sort the array)
            if (options.takeItemsFrom == "end") {
                //console.log("[Xibo] Reversing items");
                items.reverse();
            }

            // Make sure the num items is not greater than the actual number of items
            //console.log("[Xibo] Module requested " + options.numItems + " there are " + items.length + " in the array of items");

            if (options.numItems > items.length || options.numItems === 0)
                options.numItems = items.length;

            // Get a new array with only the first N elements
            items = items.slice(0, options.numItems);

            // Reverse the items again (so they are in the correct order)
            if (options.takeItemsFrom == "end") {
                //console.log("[Xibo] Reversing items");
                items.reverse();
            }
                
            // 2nd objective - put the items on the page
            // settings involved:
            //  fx (if we are single we might need to configure some pages for this)
            //  itemsPerPage (tells us how many items to put on per page)
            //console.log("[Xibo] Putting " + options.numItems + " Items on the page"); 

            // Store the number of items (we might change this to number of pages)
            var numberOfItems = options.numItems;

            // How many pages to we need?
            var numberOfPages = (options.itemsPerPage > 0) ? Math.ceil(options.numItems / options.itemsPerPage) : options.numItems;
            var itemsThisPage = 1;

            //console.log("[Xibo] We need to have " + numberOfPages + " pages");
            var appendTo = this;
            
            // Loop around each of the items we have been given and append them to this element (in a div)
            for (var i = 0; i < items.length; i++) {

                // We don't add any pages for marquee / none transitions.
                if (options.fx != "none" &&
                    options.fx != "marqueeLeft" &&
                    options.fx != "marqueeRight" &&
                    options.fx != "marqueeUp" &&
                    options.fx != "marqueeDown") {

                    // If we need to set pages, have we switched over to a new page?
                    if (options.itemsPerPage > 1 && (itemsThisPage >= options.itemsPerPage || i === 0)) {
                        // Append a new page to the body
                        appendTo = $("<div/>").addClass("page").appendTo(this);

                        // Reset the row count on this page
                        itemsThisPage = 0;
                    }
                }

                // For each item output a DIV
                $("<div/>")
                    .addClass("item")
                    .html(items[i]).appendTo(appendTo);

                itemsThisPage++;
            }
            
            // 4th objective - move the items around, start the timer
            // settings involved:
            //  fx (the way we are moving effects the HTML required)
            //  speed (how fast we need to move)
            var marquee = false;

            if (options.fx == "none") {
                // Do nothing
            }
            else if (options.fx != "marqueeLeft" && options.fx != "marqueeRight" && options.fx != "marqueeUp" && options.fx != "marqueeDown") {

                // Make sure the speed is something sensible
                options.speed = (options.speed <= 200) ? 1000 : options.speed;

                // Cycle slides are either page or item
                var slides = (options.itemsPerPage > 1) ? ".page" : ".item";

                // If we only have 1 item, then we are in trouble and need to duplicate it.
                if ($(slides).length <= 1 && options.type == 'text') {
                    // Change our slide tag to be the paragraphs inside
                    slides = slides + ' p';

                    // Change the number of items
                    numberOfItems = $(slides).length;
                }

                var numberOfSlides = (options.itemsPerPage > 1) ? numberOfPages : numberOfItems;
                var duration = (options.durationIsPerItem) ? options.duration : options.duration / numberOfSlides;

                //console.log("[Xibo] initialising the cycle2 plugin with " + numberOfSlides + " slides and selector " + slides + ". Duration per slide is " + duration + " seconds.");

                // Set the content div to the height of the original window
                $(this).css("height", options.originalHeight);

                // Set the width on the cycled slides
                $(slides, this).css({
                    width: options.originalWidth,
                    height: options.originalHeight
                });

                // Cycle handles this for us
                $(this).cycle({
                    fx: options.fx,
                    speed: options.speed,
                    timeout: (duration * 1000),
                    slides: "> " + slides
                });
            }
            else if (options.fx == "marqueeLeft" || options.fx == "marqueeRight") {
                marquee = true;
                options.direction = ((options.fx == "marqueeLeft") ? "left" : "right");

                // Make sure the speed is something sensible
                options.speed = (options.speed == 0) ? 1 : options.speed;
                
                // Stack the articles up and move them across the screen
                $('.item, .item p', this).css({
                    display: "inline",
                    "padding-left": "10px"
                });
            }
            else if (options.fx == "marqueeUp" || options.fx == "marqueeDown") {
                // We want a marquee
                marquee = true;
                options.direction = ((options.fx == "marqueeUp") ? "up" : "down");

                // Make sure the speed is something sensible
                options.speed = (options.speed == 0) ? 1 : options.speed;
            }

            if (marquee) {
                
                // Create a DIV to scroll, and put this inside the body
                var scroller = $("<div/>")
                    .addClass("scroll")
                    .attr({
                        scrollamount: options.speed,
                        scaleFactor: options.scaleFactor,
                        behaviour: "scroll",
                        direction: options.direction,
                        height: options.originalHeight,
                        width: options.originalWidth
                    });

                $(this).wrapInner(scroller);

                // Set some options on the extra DIV and make it a marquee
                $(this).find('.scroll').marquee();

                // Correct for up / down
                if (options.fx == "marqueeUp" || options.fx == "marqueeDown")
                    $(this).children().children().css({"white-space": "normal", float: "none"});
            }

            // Protect against images that don't load
            $(this).find("img").error(function () {
                $(this).unbind("error").attr("src", "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNiYAAAAAkAAxkR2eQAAAAASUVORK5CYII=");
            });
        });

        return $(this);
    }
});
