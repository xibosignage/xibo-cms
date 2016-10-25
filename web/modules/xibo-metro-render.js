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
    xiboMetroRender: function(options, items, colors) {

        // Default options
        var defaults = {
            "fx": "none",
            "duration": "10",
            "numItems": 0,
            "takeItemsFrom": "start",
            "speed": "2",
            "previewWidth": 0,
            "previewHeight": 0,
            "scaleOverride": 0,
            "cellsPerRow": 6,
            "cellsPerPage": 18,
            "numberItemsLarge": 1,
            "numberItemsMedium": 3,
            "smallItemSize": 1,
            "mediumItemSize": 2,
            "largeItemSize": 3
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
        
        // Set the cells per row according to the widgets original orientation
        options.cellsPerRow = (options.widgetDesignWidth < options.widgetDesignHeight) ? 3 : 6;

        // For each matched element
        this.each(function() {

            // 1st Objective - filter the items array we have been given
            // settings involved: 
            //  items, 
            //  numItems (ticker number of items from the start/end),
            //  takeItemsFrom (ticker sort or reverse sort the array)
            if (options.takeItemsFrom == "end") {
                items.reverse();
            }
            // Make sure the num items is not greater than the actual number of items
            if (options.numItems > items.length || options.numItems === 0)
                options.numItems = items.length;

            // Get a new array with only the first N elements
            items = items.slice(0, options.numItems);

            // Reverse the items again (so they are in the correct order)
            if (options.takeItemsFrom == "end") {
                //console.log("[Xibo] Reversing items");
                items.reverse();
            }


            // 2nd objective - create an array that defines the positions of the items on the layout
            // settings involved:
            //  positionsArray (the array that stores the positions of the items according to size)
            //  largeItems (number of large items to appear on the layout)
            //  mediumItems (number of medium items to appear on the layout)
            //  cellsPerPage (number of cells for each page)
            //  cellsPerRow (number of cells for each row)

            // Create the positions array with size equal to the number of cells per page, and each positions starts as undefined
            var positionsArray = new Array(options.cellsPerPage);

            //Randomize values so each one can have values from default to default+X
            var largeItems = options.numberItemsLarge + Math.floor(Math.random() * 2);
            var mediumItems = options.numberItemsMedium + Math.floor(Math.random() * 3);

            // Number of items displayed in each page
            var numberOfItems = 0;

            // Var to prevent the placement loop to run indefinitley 
            var loopMaxValue = 100;

            // Try to place the large and medium items until theres none of those left
            while (mediumItems + largeItems > 0 && loopMaxValue > 0) {
                // Calculate a random position inside the array
                var positionRandom = Math.floor(Math.random() * options.cellsPerPage);

                // I f we still have large items to place
                if (largeItems > 0) {
                    if (checkFitPosition(positionsArray, positionRandom, options.largeItemSize, options.cellsPerRow) && checkCellEmpty(positionsArray, positionRandom, options.largeItemSize)) {
                        // Set the array positions to the pretended item type
                        for (var i = 0; i < options.largeItemSize; i++) {
                            positionsArray[positionRandom + i] = options.largeItemSize;
                        }
                        numberOfItems++;
                        // Decrease the items to place var
                        largeItems--;
                    }
                } else if (mediumItems > 0) {
                    if (checkFitPosition(positionsArray, positionRandom, options.mediumItemSize, options.cellsPerRow) && checkCellEmpty(positionsArray, positionRandom, options.mediumItemSize)) {
                        // Set the array positions to the pretended item type
                        for (var i = 0; i < options.mediumItemSize; i++) {
                            positionsArray[positionRandom + i] = options.mediumItemSize;
                        }

                        // Decrease the items to place var
                        numberOfItems++;
                        mediumItems--;
                    }
                }

                loopMaxValue--;
            }

            // Fill the rest of the array with small size items
            for (var i = 0; i < positionsArray.length; i++) {
                if (positionsArray[i] == undefined) {
                    numberOfItems++;
                    positionsArray[i] = options.smallItemSize;
                }
            }

            // 3rd objective - put the items on the respective rows, add the rows to each page and build the resulting html
            // settings involved:
            //  positionsArray (the array that stores the positions of the items according to size)
            //  largeItems (number of large items to appear on the layout)
            //  mediumItems (number of medium items to appear on the layout)
            //  cellsPerPage (number of cells for each page)
            //  cellsPerRow (number of cells for each row)
            //  rowCellSum

            // How many pages to we need?
            var numberOfPages = (options.numItems > numberOfItems) ? Math.floor(options.numItems / numberOfItems) : 1;
            var rowCellSum = 0;
            var rowNumber = 0;
            var itemId = 0;
            var pageId = 0;


            // If we dont have enough items to fill a page, change the items array to have dummy position between items
            if (items.length < numberOfItems) {
                // Create a new array
                var newItems = new Array();

                // Distance between items so they can be spread in the page 
                var distance = Math.round(numberOfItems / items.length);

                var idAux = 0;
                for (var i = 0; i < numberOfItems; i++) {

                    if (i % distance == 0) {
                        // Place a real item
                        newItems.push(items[idAux]);
                        idAux++;
                    } else {
                        // Place a dummy item
                        newItems.push(undefined);
                    }
                }
                items = newItems;
            }

            // Cycle through all the positions on the positionsArray 
            for (var i = 0; i < positionsArray.length; i++) {

                // If we are on the first cell position, create a row
                if (i % options.cellsPerRow == 0) {
                    rowNumber += 1;
                    $("#content").append("<div class='row-1' id='idrow-" + rowNumber + "'></div>");
                }

                // Create a page and add it to the content div
                $("#content").append("<div id='page-" + itemId + "'></div>");

                for (var j = 0; j < numberOfPages; j++) {

                    // Get the items that should be on this position
                    var itemIdNow = (numberOfItems * j) + itemId;

                    // Pass the item to a variable and replace some tags, if there's no item we create a dummy item
                    var stringHTML;
                    if (items[itemIdNow] != undefined) {
                        stringHTML = items[itemIdNow];
                    } else {
                        var randomColorNumber = Math.floor(Math.random() * colors.length);
                        stringHTML = "<div class='cell-[itemType]'><div class='item-container' style='background-color:" + colors[randomColorNumber] + "'><div class='item-text'></div><div class='userData'></div></div></div>";
                    }

                    stringHTML = stringHTML.replace('[itemId]', itemIdNow);
                    stringHTML = stringHTML.replace('[itemType]', positionsArray[i]);

                    // Append item to the current page
                    $("#page-" + itemId).append(stringHTML).addClass("page");
                }

                // Move the created page into the respective row
                $("#idrow-" + rowNumber).append($("#page-" + itemId));

                // Increase the item ID var
                itemId++;

                // Increase the iterator so it can move forward the number of cells that the current item occupies
                i += positionsArray[i] - 1;
            }



            // 4th objective - move the items around, start the timer
            // settings involved:
            //  fx (the way we are moving effects the HTML required)
            //  speed (how fast we need to move

            // Make sure the speed is something sensible
            options.speed = (options.speed <= 200) ? 1000 : options.speed;

            var slides = ".cell";

            var numberOfSlides = (numberOfItems > 1) ? numberOfPages : numberOfItems;
            
            // Duration of each page, adding one page to the operation for the delay, 
            // and one half so that the page have the same items that when it started the loop
            var duration = options.duration/(numberOfPages+1.5);

            // Use cycle in all pages of items ( to cycle individually )
            // The timeout is calculated using the duration minus the delay
            for (var i = 0; i < numberOfItems; i++) {
                // Timeout is the duration in ms
                var timeout = (duration * 1000);
                
                // The delay is calulated usign the distance between items ( random from 0 to 5 )  
                // that animate at the same time, and a part of the timeout duration
                var delayDistance = Math.random() * 5;
                var delay = (timeout/delayDistance) * (i%delayDistance);
                $("#page-" + i).cycle({
                    fx: options.fx,
                    speed: options.speed,
                    delay: delay,
                    timeout: timeout,
                    slides: "> " + slides
                });
            }

            // Protect against images that don't load
            $(this).find("img").error(function() {
                $(this).unbind("error").attr("src", "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNiYAAAAAkAAxkR2eQAAAAASUVORK5CYII=");
            });
        });

        return $(this);
    }
});

/**
 * Check if a set of given cells of an array are empty (undefined) 
 */
function checkCellEmpty(array, index, size) {

    var check = true;
    for (var i = 0; i < size; i++) {
        if (array[index + i] != undefined)
            check = false;
    }
    return check;
}

/**
 * Check if a given position of an array is good to fit an item given it's size and position
 */
function checkFitPosition(array, index, size, cellsPerRow) {
    return (index % cellsPerRow <= cellsPerRow - size);
}