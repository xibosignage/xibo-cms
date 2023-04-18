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
  xiboElementsRender: function(options) {
    const $this = $(this);
    const defaults = {
      width: 0,
      height: 0,
      layer: 0,
      top: 0,
      left: 0,
      elementId: '',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
    };

    options = $.extend({}, defaults, options);

    // Calculate the dimensions of this item
    let width = options.width;
    let height = options.height;
    if (options.scaleOverride !== 0) {
      width = options.width / options.scaleOverride;
      height = options.height / options.scaleOverride;
    }

    $this
      .attr('id', options.elementId)
      .css({
        height: height,
        width: width,
        position: 'absolute',
        top: options.top,
        left: options.left,
        'z-index': options.layer,
      });

    return $this;
  },
});
