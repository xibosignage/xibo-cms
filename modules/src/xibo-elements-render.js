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
  xiboElementsRender: function(options, items) {
    const $this = $(this);
    const defaults = {
      effect: 'none',
      pauseEffectOnStart: true,
      duration: 50,
      durationIsPerItem: false,
      numItems: 0,
      takeItemsFrom: 'start',
      reverseOrder: 0,
      itemsPerPage: 1,
      speed: 2,
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      randomiseItems: 0,
      marqueeInlineSelector: '.elements-render-item',
      alignmentV: 'top',
      displayDirection: 0,
      parentId: '',
      groupId: null,
    };

    options = $.extend({}, defaults, options);

    const elementWrapper = $('<div class="element-wrapper"></div>');

    if (options.parentId) {
      elementWrapper
        .addClass(`element-wrapper--${options.parentId}`)
        .css({
          width: options.width,
          height: options.height,
          position: 'absolute',
          top: options.top,
          left: options.left,
          'z-index': options.layer,
        });
    }

    if (items?.length > 0) {
      elementWrapper.append(items);
    }

    if ($this.find(`#content .element-wrapper--${options.parentId}`)
      .length === 0) {
      $this.find('#content').prepend(elementWrapper);
    }

    const cycleElement = `#content .element-wrapper--${options.parentId}`;

    if ($this.find(cycleElement).length) {
      // Make sure the speed is something sensible
      options.speed = (options.speed <= 200) ? 1000 : options.speed;

      const numberOfSlides = options.numItems || items?.length || 1;
      const duration = (options.durationIsPerItem) ?
        options.duration :
        options.duration / numberOfSlides;
      let timeout = duration * 1000;
      const noTransitionSpeed = 10;

      if (options.effect !== 'noTransition' && options.effect !== 'none') {
        timeout = timeout - options.speed;
      } else {
        timeout = timeout - noTransitionSpeed;
      }

      $(cycleElement).addClass('cycle-slideshow').cycle({
        fx: (options.effect === 'noTransition' || options.effect === 'none') ?
          'none' : options.effect,
        speed: (
          options.effect === 'noTransition' || options.effect === 'none'
        ) ? noTransitionSpeed : options.speed,
        timeout: timeout,
        slides: `> .${options.parentId}--item`,
        autoHeight: false,
        sync: false,
      });
    }

    return $this;
  },
});
