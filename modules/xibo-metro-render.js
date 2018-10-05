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
            "duration": "60",
            "numItems": 0,
            "speed": "2",
            "previewWidth": 0,
            "previewHeight": 0,
            "scaleOverride": 0,
            "cellsPerRow": 6,
            "cellsPerPage": 18,
            "numberItemsLarge": 1,
            "numberItemsMedium": 2,
            "maxItemsLarge": 3,
            "maxItemsMedium": 4,
            "smallItemSize": 1,
            "mediumItemSize": 2,
            "largeItemSize": 3,
            "randomizeSizeRatio": false
        };

        options = $.extend({}, defaults, options);

        options.randomizeSizeRatio = false;

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

            // 1st objective - create an array that defines the positions of the items on the layout
            // settings involved:
            //  positionsArray (the array that stores the positions of the items according to size)
            //  largeItems (number of large items to appear on the layout)
            //  mediumItems (number of medium items to appear on the layout)
            //  cellsPerPage (number of cells for each page)
            //  cellsPerRow (number of cells for each row)

            // Create the positions array with size equal to the number of cells per page, and each positions starts as undefined
            var positionsArray = new Array(options.cellsPerPage);

            // Get the page small/medium/large Ratio ( by random or percentage )
            if (options.randomizeSizeRatio) {
                // START OPTION 1 - RANDOM
                //Randomize values so each one can have values from default to default+X
                var largeItems = options.numberItemsLarge + Math.floor(Math.random() * 2);
                var mediumItems = options.numberItemsMedium + Math.floor(Math.random() * 3);
            } else {
                // OPTION 2 - PERCENTAGE
                // Count image tweets ratio
                var tweetsWithImageCount = 0;
                for (var i = 0; i < items.length; i++) {
                    if (checkBackgroundImage(items, i))
                        tweetsWithImageCount++;
                }
                var imageTweetsRatio = tweetsWithImageCount / items.length;
                var imageTweetsCellsPerPage = Math.floor(options.cellsPerPage * imageTweetsRatio);

                // Calculate the large/medium quantity according to the ratio of withImage/all tweets
                // Try to get a number of large items that fit on the calculated cells per page 
                var largeItems = Math.floor(imageTweetsCellsPerPage / options.largeItemSize);
                
                // Get the number of medium items by the remaining cells per page "space" left by the large items
                var mediumItems = Math.floor((imageTweetsCellsPerPage - (largeItems*options.largeItemSize))/options.mediumItemSize);

                // If the reulting medium/large values are 0 give them the default option values
                if (largeItems == 0)
                    largeItems = options.numberItemsLarge;

                if (mediumItems == 0)
                    mediumItems = options.numberItemsMedium;
                    
                //  If the reulting medium/large values are over the maximum values set them to max
                if(largeItems > options.maxItemsLarge)
                    largeItems = options.maxItemsLarge;
                    
                if(mediumItems > options.maxItemsMedium)
                    mediumItems = options.maxItemsMedium;
            }

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


            // 2nd objective - put the items on the respective rows, add the rows to each page and build the resulting html
            //   settings involved:
            //   positionsArray (the array that stores the positions of the items according to size)

            // How many pages to we need?
            var numberOfPages = (options.numItems > numberOfItems) ? Math.floor(options.numItems / numberOfItems) : 1;

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

            // Create an auxiliary items array, so we can place the tweets at the same time we remove them from the new array
            var itemsAux = items;

            // Cycle through all the positions on the positionsArray 
            for (var i = 0; i < positionsArray.length; i++) {

                // If we are on the first cell position, create a row
                if (i % options.cellsPerRow == 0) {
                    rowNumber += 1;
                    $("#content").append("<div class='row-1' id='idrow-" + rowNumber + "'></div>");
                }

                // Create a page and add it to the content div
                $("#content").append("<div id='page-" + pageId + "'></div>");

                for (var j = 0; j < numberOfPages; j++) {

                    // Pass the item to a variable and replace some tags, if there's no item we create a dummy item
                    var stringHTML;

                    // Search for the item to remove regarding the type of the tweet (with/without image)
                    var indexToRemove = checkImageTweet(itemsAux, (positionsArray[i] > 1));

                    if (itemsAux[indexToRemove] != undefined) {
                        stringHTML = itemsAux[indexToRemove];
                    } else {
                        var randomColorNumber = Math.floor(Math.random() * colors.length);
                        stringHTML = "<div class='cell-[itemType]'><div class='item-container' style='background-color:" + colors[randomColorNumber] + "'><div class='item-text'></div><div class='userData'></div></div></div>";
                    }

                    // Remove the element that we used to create the new html
                    itemsAux.splice(indexToRemove, 1);

                    // Increase the item ID
                    itemId++;

                    // Replace the item ID and Type on its html
                    stringHTML = stringHTML.replace('[itemId]', itemId);
                    stringHTML = stringHTML.replace('[itemType]', positionsArray[i]);

                    // Append item to the current page
                    $("#page-" + pageId).append(stringHTML).addClass("page");
                }

                // Move the created page into the respective row
                $("#idrow-" + rowNumber).append($("#page-" + pageId));

                // Increase the page ID var
                pageId++;

                // Increase the iterator so it can move forward the number of cells that the current item occupies
                i += positionsArray[i] - 1;
            }


            // 3rd objective - move the items around, start the timer
            // settings involved:
            //   fx (the way we are moving effects the HTML required)
            //   speed (how fast we need to move

            // Make sure the speed is something sensible
            options.speed = (options.speed <= 200) ? 1000 : options.speed;

            var slides = ".cell";

            var numberOfSlides = (numberOfItems > 1) ? numberOfPages : numberOfItems;

            // Duration of each page
            var pageDuration = options.duration / numberOfPages;
                      
            // Use cycle in all pages of items ( to cycle individually )
            for (var i = 0; i < numberOfItems; i++) {
                // Timeout is the duration in ms
                var timeout = (pageDuration * 1000);

                // The delay is calulated usign the distance between items ( random from 1 to 5 )  
                // that animate almost at the same time, and a part of the timeout duration
                var delayDistance = 1 + Math.random() * 4;
                var delay = (timeout / delayDistance) * ((i+1) % delayDistance);
                
                $("#page-" + i).cycle({
                    fx: options.fx,
                    speed: options.speed,
                    delay: -delay,
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

/**
 * Check if a given item has background image
 */
function checkBackgroundImage(array, index) {
    return (array[index].indexOf("background-image") >= 0);
}

/**
 * Find a tweet with image (or one without image), if not return 0
 */
function checkImageTweet(array, withImage) {
    // Default return var
    var returnVar = 0;

    for (var i = 0; i < array.length; i++) {
        // Find a tweet with image
        if (withImage && checkBackgroundImage(array, i)) {
            returnVar = i;
            break;
        }

        // Find a tweet without image
        if (!withImage && !checkBackgroundImage(array, i)) {
            returnVar = i;
            break;
        }
    }
    return returnVar;
}