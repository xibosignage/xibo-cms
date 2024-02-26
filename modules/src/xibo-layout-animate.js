/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
  xiboLayoutAnimate: function(options) {
    // Default options
    const defaults = {
      effect: 'none',
    };
    options = $.extend({}, defaults, options);

    this.each(function(_key, element) {
      const isAndroid = navigator.userAgent.indexOf('Android') > -1;
      const $contentDiv = $(element);
      // Marquee effect
      if (
        options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown'
      ) {
        $contentDiv.find('.scroll:not(.animating)').marquee();
      } else if (
        options.effect === 'marqueeLeft' ||
        options.effect === 'marqueeRight'
      ) {
        if (isAndroid) {
          $contentDiv.find('.scroll:not(.animating)').overflowMarquee();
        } else {
          $contentDiv.find('.scroll:not(.animating)').marquee();
        }
      } else if (options.effect !== 'none' ||
        options.effect === 'noTransition'
      ) { // Cycle effect
        // Resume effect
        const $target = $contentDiv.is('.anim-cycle') ?
          $contentDiv : $contentDiv.find('.anim-cycle');

        $target.cycle('resume');
      }
    });

    return $(this);
  },
});
