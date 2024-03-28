/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
  dataSetRender: function(options) {
    // Any options?
    if (options === undefined || options === null) {
      options = {
        duration: 5,
        transition: 'fade',
        rowsPerPage: 0,
        previewWidth: 0,
        previewHeight: 0,
        scaleOverride: 0,
      };
    }

    $(this).each(function(_idx, el) {
      const numberItems = $(el).data('totalPages');
      const duration =
        (options.durationIsPerItem) ?
          options.duration : options.duration / numberItems;

      if (options.rowsPerPage > 0) {
        // Cycle handles this for us
        if ($(el).prop('isCycle')) {
          $(el).cycle('destroy');
        }

        $(el).prop('isCycle', true).cycle({
          fx: options.transition,
          timeout: duration * 1000,
          slides: '> table',
        });
      }
    });

    return $(this);
  },
});
