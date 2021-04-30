/*
 * Copyright (C) 2021 Xibo Signage Ltd
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
        $(this).each(function() {
            var maxPages = options.maxPages;
            var duration =  options.duration / maxPages;

            if (options.productsPerPage > 0) {
                // Cycle handles this for us
                $('.ProductsContainer').cycle({
                    fx: "fade",
                    timeout: duration * 1000,
                    "slides": "> div.page"
                });
            }

            return $(this);
        });
    }
});
