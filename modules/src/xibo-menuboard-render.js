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
    menuBoardRender: function(options) {
        function createPage(pageNum, container) {
            var $newPage = $('<div>').addClass('menu-board-product-page').attr('data-page', pageNum);
            $(container).append($newPage);
            return $newPage;
        }

        $(this).each(function() {
            var deltaDuration;
            var maxPages = 0;

            // Hide all elements
            $('.menu-board-product').css('opacity', 0);

            // Get height of each products container
            $('.menu-board-products-container').each(function() {
                var pageNum = 1;
                var containerHeight = $(this).height();
                var elementsTotalHeight = 0;
                var $productContainer = $(this);

                // Create first page
                var $currentPage = createPage(pageNum, $productContainer);

                // Create pages dynamically
                $(this).find('.menu-board-product').each(function() {
                    var $product = $(this);
                    var productHeight = $product.outerHeight();

                    // If the current page is full, create a new page
                    if (productHeight + elementsTotalHeight > containerHeight) {
                        pageNum++;
                        elementsTotalHeight = 0;

                        // Create a new page
                        $currentPage = createPage(pageNum, $productContainer);
                    }

                    // Increase the total height
                    elementsTotalHeight += productHeight;

                    // Add element to the current page
                    $currentPage.append($product);
                });

                // Fill the last page with first elements
                if (pageNum > 1 && elementsTotalHeight < containerHeight) {
                    $(this).find('.menu-board-product').each(function() {
                        var $product = $(this);
                        var productHeight = $product.outerHeight();

                        // If the current page is full, stop adding elements
                        if (productHeight + elementsTotalHeight > containerHeight) {
                            return false;
                        } else {
                            // Add cloned element to the current page
                            $currentPage.append($product.clone());

                            // Increase the total height
                            elementsTotalHeight += productHeight;
                        }
                    });
                }

                // Save maxPages if pageNum is higher
                if (pageNum > maxPages) {
                    maxPages = pageNum;
                }
            });

            // Calculate the delta duration ( duration / number of max pages )
            deltaDuration = options.duration / maxPages;

            // Cycle handles this for us
            $('.menu-board-products-container').cycle({
                fx: "fade",
                timeout: deltaDuration * 1000,
                "slides": "> div.menu-board-product-page"
            });

            // Re-show elements
            $('.menu-board-product').css('opacity', 1);

            return $(this);
        });
    }
});
