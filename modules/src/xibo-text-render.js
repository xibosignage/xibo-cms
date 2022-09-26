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
    xiboTextRender: function(options, items) {

        // Default options
        var defaults = {
            "effect": "none",
            "duration": "50",
            "durationIsPerItem": false,
            "numItems": 0,
            "takeItemsFrom": "start",
            "reverseOrder": 0,
            "itemsPerPage": 0,
            "speed": "2",
            "previewWidth": 0,
            "previewHeight": 0,
            "scaleOverride": 0,
            "randomiseItems": 0,
            "marqueeInlineSelector": ".item, .item p",
            "alignmentV": "top"
        };

        options = $.extend({}, defaults, options);

        // Calculate the dimensions of this item based on the preview/original dimensions
        var width = height = 0;
        if (options.previewWidth === 0 || options.previewHeight === 0) {
            width = options.originalWidth;
            height = options.originalHeight;
        }
        else {
            width = options.previewWidth;
            height = options.previewHeight;
        }

        if (options.scaleOverride !== 0) {
            width = width / options.scaleOverride;
            height = height / options.scaleOverride;
        }

        var paddingBottom = paddingRight = 0;
        if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
            if(options.itemsPerPage > 0){
                if($(window).width() > $(window).height()){
                    //Landscape or square size plus padding
                    options.widgetDesignWidth = (options.itemsPerPage * options.widgetDesignWidth) + (options.widgetDesignPadding * (options.itemsPerPage - 1));
                    options.widgetDesignHeight = options.widgetDesignHeight;
                    width = options.widgetDesignWidth;
                    height = options.widgetDesignHeight;
                    paddingRight = options.widgetDesignPadding;
                } else {
                    //Portrait size plus padding
                    options.widgetDesignHeight = (options.itemsPerPage * options.widgetDesignHeight) + (options.widgetDesignPadding * (options.itemsPerPage - 1));
                    options.widgetDesignWidth = options.widgetDesignWidth;
                    width = options.widgetDesignWidth;
                    height = options.widgetDesignHeight;
                    paddingBottom = options.widgetDesignPadding;
                }
            }
        }

        // For each matched element
        this.each(function() {

            //console.log("[Xibo] Selected: " + this.tagName.toLowerCase());
            //console.log("[Xibo] Options: " + JSON.stringify(options));

            // 1st Objective - filter the items array we have been given
            // settings involved:
            //  items,
            //  numItems (ticker number of items from the start/end),
            //  takeItemsFrom (ticker sort or reverse sort the array)
            //  randomiseItems (randomise the items)
            if (options.randomiseItems === 1) {
                // Sort the items in a random order (considering the entire list)
                // Durstenfeld shuffle
                // https://en.wikipedia.org/wiki/Fisher%E2%80%93Yates_shuffle#The_modern_algorithm
                // https://stackoverflow.com/questions/2450954/how-to-randomize-shuffle-a-javascript-array
                for (var i = items.length - 1; i > 0; i--) {
                    var j = Math.floor(Math.random() * (i + 1));
                    var temp = items[i];
                    items[i] = items[j];
                    items[j] = temp;
                }
            }

            if (options.takeItemsFrom === "end") {
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
            if ( (options.takeItemsFrom === "end" && options.reverseOrder === 0) || (options.takeItemsFrom === "start" && options.reverseOrder === 1)) {
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
                if (options.effect != "none" &&
                    options.effect != "marqueeLeft" &&
                    options.effect != "marqueeRight" &&
                    options.effect != "marqueeUp" &&
                    options.effect != "marqueeDown") {

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

            if (options.effect == "none") {
                // Do nothing
            }
            else if (options.effect != "marqueeLeft" && options.effect != "marqueeRight" && options.effect != "marqueeUp" && options.effect != "marqueeDown") {

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
                $(this).css("height", height);

                // Set the width on the cycled slides
                $(slides, this).css({
                    width: width,
                    height: height,
                });

                var timeout = duration * 1000;
                var noTransitionSpeed = 10;

                if (options.effect !== "noTransition") {
                    timeout = timeout - options.speed;
                } else {
                    timeout = timeout - noTransitionSpeed;
                }

                // Cycle handles this for us
                $(this).cycle({
                    fx: (options.effect === "noTransition") ? "none" : options.effect,
                    speed: (options.effect === "noTransition") ? noTransitionSpeed : options.speed,
                    timeout: timeout,
                    slides: "> " + slides
                });
            }
            else if (options.effect == "marqueeLeft" || options.effect == "marqueeRight") {
                marquee = true;
                options.direction = ((options.effect == "marqueeLeft") ? "left" : "right");

                // Make sure the speed is something sensible
                options.speed = (options.speed == 0) ? 1 : options.speed;

                // Stack the articles up and move them across the screen
                $(options.marqueeInlineSelector, this).css({
                    display: "inline",
                    "padding-left": "10px"
                });
            }
            else if (options.effect == "marqueeUp" || options.effect == "marqueeDown") {
                // We want a marquee
                marquee = true;
                options.direction = ((options.effect == "marqueeUp") ? "up" : "down");

                // Make sure the speed is something sensible
                options.speed = (options.speed == 0) ? 1 : options.speed;
            }

            if (marquee) {
                // Which marquee to use?
                var nua = navigator.userAgent;
                /* The intention was to allow Chrome based android to benefit from the new marquee
                   unfortunately though, it doesn't appear to work.
                   Maybe this is due to Chrome verison? Android tends to have quite an old version.
                var is_android = ((nua.indexOf('Mozilla/5.0') > -1
                    && nua.indexOf('Android') > -1
                    && nua.indexOf('AppleWebKit') > -1)
                    && !(nua.indexOf('Chrome') > -1));*/
                var is_android = nua.indexOf('Android') > -1;

                // Create a DIV to scroll, and put this inside the body
                var scroller = $("<div/>")
                    .addClass("scroll");

                if (!is_android) {
                    // in old marquee scroll delay is 85 milliseconds
                    // options.speed is the scrollamount which is the number of pixels per 85 milliseconds
                    // our new plugin speed is pixels per second
                    scroller.attr({
                        "data-is-legacy": false,
                        "data-speed": options.speed / 25 * 1000,
                        "data-direction": options.direction,
                        scaleFactor: options.scaleFactor
                    });
                } else {
                    scroller.attr({
                        "data-is-legacy": true,
                        scrollamount: options.speed,
                        scaleFactor: options.scaleFactor,
                        behaviour: "scroll",
                        direction: options.direction,
                        height: height,
                        width: width
                    });
                }

                $(this).wrapInner(scroller);

                // Correct for up / down
                if (options.effect === "marqueeUp" || options.effect === "marqueeDown") {
                    $(this).css('height', '100%');
                    $(this).find('.scroll').css('height', '100%').children().css({"white-space": "normal", float: "none"});
                }

                // Set some options on the extra DIV and make it a marquee
                if (!is_android) {
                    $(this).find('.scroll').marquee();
                } else {
                    $(this).find('.scroll').overflowMarquee();
                }
            }

            // Add aditional padding to the items
            if (paddingRight > 0 || paddingBottom > 0) {
                // Add padding to all item elements
                $(".item").css("padding", "0px " + paddingRight + "px " + paddingBottom  + "px 0px");

                // Exclude the last item on the page and the last on the content ( if there is no pages )
                $(".page .item:last-child").css("padding", 0);
                $("#content .item:last-child").css("padding", 0);
            }

          // Align the whole thing according to vAlignment
          if (options.type && options.type === 'text') {
            var $textContent = $(this);
            // The timeout just yields a bit to let our content get rendered
            setTimeout(function () {
              if (options.alignmentV === 'bottom') {
                $textContent.css('margin-top', $(window).height() - ($textContent.height() * $('body').data().ratio));
              } else if (options.alignmentV === 'middle') {
                $textContent.css('margin-top', ($(window).height() - ($textContent.height() * $('body').data().ratio)) / 2);
              }
            }, 500);
          }
        });

        return $(this);
    }
});
