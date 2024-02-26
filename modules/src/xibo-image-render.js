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
  xiboImageRender: function(options) {
    // Default options
    const defaults = {
      reloadTime: 5000,
      maxTries: -1, // -1: Infinite # times
    };

    // Extend options
    options = $.extend({}, defaults, options);

    const $self = $(this);

    // Run all the selected elements individually
    if ($self.length > 1) {
      $self.each(function(i, el) {
        $(el).xiboImageRender(options);
      });
      return $self;
    }

    // Handle the image error by replacing the original image
    // with a transparent pixel and try to reload the original source again
    const handleImageError = function() {
      // Replace image with a single transparent pixel
      $self.off('error')
        .attr(
          'src',
          // eslint-disable-next-line max-len
          'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNiYAAAAAkAAxkR2eQAAAAASUVORK5CYII=');

      let reloadTimes = $self.data('reload-times');

      // Loop an infinite number of times ( maxTries == -1 )
      // or until the loop reach options.maxTries times
      if (reloadTimes < options.maxTries || options.maxTries == -1) {
        // Create a timeout using the options reload time
        setTimeout(function() {
          // Try to change source to the original
          $self.attr('src', $self.data('original-src'))
            .on('error', handleImageError);

          // Increase the control var and set it to the element
          reloadTimes++;
          $self.data('reload-times', reloadTimes);
        }, options.reloadTime);
      }
    };

    // Original image source
    $self.data('original-src', $self.attr('src'));

    // Initialise reload times var
    $self.data('reload-times', 0);

    // Bind handle image funtion to a error event
    if ($self.data('original-src') != undefined) {
      $self.bind('error', handleImageError);
    }

    return $self;
  },
});
