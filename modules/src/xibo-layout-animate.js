/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
