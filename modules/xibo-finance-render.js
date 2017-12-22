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
    xiboFinanceRender: function(options, items, body) {

        // Default options
        var defaults = {
            "fx": "none",
            "speed": "2",
            "duration": "30",
            "durationIsPerPage": false,
            "numItems": 0,
            "maxItemsPerPage": 5,
            "previewWidth": 0,
            "previewHeight": 0,
            "scaleOverride": 0
        };

        options = $.extend({}, defaults, options);

        // Calculate the dimensions of this item based on the preview/original dimensions
        var width = height = 0;
        if (options.previewWidth === 0 || options.previewHeight === 0) {
            width = options.originalWidth;
            height = options.originalHeight;
        } else {
            width = options.previewWidth;
            height = options.previewHeight;
        }

        if (options.scaleOverride !== 0) {
            width = width / options.scaleOverride;
            height = height / options.scaleOverride;
        }

        if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
            options.widgetDesignWidth = options.widgetDesignWidth;
            options.widgetDesignHeight = options.widgetDesignHeight;
            width = options.widgetDesignWidth;
            height = options.widgetDesignHeight;
        }

        // For each matched element
        this.each(function() {
            // How many pages to we need?
            var numberOfPages = (options.numItems > options.maxItemsPerPage) ? Math.ceil(options.numItems / options.maxItemsPerPage) : 1;

            var mainHTML = body;
            var itemsHTML = '';

            // Create the pages
            for (var i = 0; i < numberOfPages; i++) {
                itemsHTML += "<div class='page'>";
                for (var j = 0; j < options.maxItemsPerPage; j++) {
                    if (((i * options.maxItemsPerPage) + j) < options.numItems)
                        itemsHTML += items[(i * options.maxItemsPerPage) + j];
                }
                itemsHTML += "</div>"
            }

            mainHTML = mainHTML.replace('[itemsTemplate]', itemsHTML);
            $("#content").append(mainHTML);

            var duration = (options.durationIsPerPage) ? options.duration : options.duration / numberOfPages;

            // Make sure the speed is something sensible
            options.speed = (options.speed <= 200) ? 1000 : options.speed;

            // Timeout is the duration in ms
            var timeout = (duration * 1000) - (options.speed * 0.7);

            var slides = (numberOfPages > 1) ? ".page" : ".item";

            // Set the content div to the height of the original window
            $("#cycle-container").css("height", height);

            // Set the width on the cycled slides
            $(slides, "#cycle-container").css({
                width: width,
                height: height
            });

            // Cycle handles this for us
            $("#cycle-container").cycle({
                fx: options.fx,
                speed: options.speed,
                timeout: timeout,
                slides: "> " + slides
            });

            // Protect against images that don't load
            $(this).find("img").error(function() {
                $(this).unbind("error").attr("src", "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNiYAAAAAkAAxkR2eQAAAAASUVORK5CYII=");
            });
        });

        return $(this);
    }
});