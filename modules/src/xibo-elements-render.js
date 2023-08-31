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
  xiboElementsRender: function(options, items) {
    const $this = $(this);
    const defaults = {
      effect: 'none',
      pauseEffectOnStart: true,
      duration: 50,
      durationIsPerItem: false,
      numItems: 1,
      takeItemsFrom: 'start',
      reverseOrder: 0,
      itemsPerPage: 1,
      speed: 1000,
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      marqueeInlineSelector: '.elements-render-item',
      alignmentV: 'top',
      displayDirection: 0,
      parentId: '',
      layer: 0,
    };
    const $content = $('#content');
    let isGroup = false;

    options = $.extend({}, defaults, options);

    const elementWrapper = $('<div class="element-wrapper"></div>');

    if (String(options.parentId).length > 0) {
      if (options.parentId === options.id) {
        isGroup = true;
      }

      if (!isGroup) {
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
    }

    if (!isGroup && items?.length > 0) {
      elementWrapper.append(items);
    }

    if (!isGroup && $this.find(`.element-wrapper--${options.parentId}`)
      .length === 0) {
      $this.prepend(elementWrapper);
    }

    const cycleElement = isGroup ?
      `.${options.id}` :
      `.element-wrapper--${options.parentId}`;

    if ($content.find(cycleElement).length) {
      // Make sure the speed is something sensible
      options.speed = (options.speed <= 200) ? 1000 : options.speed;

      const numberOfSlides = items?.length || 1;

      const duration = (options.durationIsPerItem) ?
        options.duration :
        options.duration / numberOfSlides;
      const timeout = duration * 1000;
      const noTransitionSpeed = 200;

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
