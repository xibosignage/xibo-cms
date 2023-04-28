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
    const glideConfig = {
      type: 'carousel',
      perView: 1,
      autoplay: 500,
      animationDuration: 1500,
    };
    const defaults = {
      effect: 'none',
      pauseEffectOnStart: true,
      duration: '50',
      durationIsPerItem: false,
      numItems: 0,
      takeItemsFrom: 'start',
      reverseOrder: 0,
      itemsPerPage: 1,
      speed: '2',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      randomiseItems: 0,
      marqueeInlineSelector: '.elements-render-item',
      alignmentV: 'top',
      displayDirection: 0,
      parentId: '',
      glideConfig,
    };

    options = $.extend({}, defaults, options);

    const elementWrapper = $('<div class="element-wrapper"></div>');
    const glide = $('<div class="glide"></div>');
    const glideTrack = $('' +
      '<div class="glide__track" data-glide-el="track"></div>');
    const glideSlides = $('<div class="glide__slides"></div>');

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
      glide.addClass(options.parentId);
    }

    if (items?.length > 0) {
      $.each(items, function(_idx, _item) {
        $(_item).addClass('glide__slide');
      });
      glideSlides.append(items);
      glideTrack.append(glideSlides);
    }

    if ($this.find(`#content .glide.${options.parentId}`).length === 0) {
      glide.append(glideTrack);
      elementWrapper.append(glide);
      $this.find('#content').prepend(elementWrapper);
    }

    const glideElement = `#content .glide.${options.parentId}`;

    // if (options.numItems) {
    //   options.glideConfig.animationDuration =
    //     options.duration * 1000 / options.numItems;
    // }

    if ($this.find(glideElement).length) {
      const glideSlide = new Glide(glideElement, options.glideConfig);

      glideSlide.mount();
    }

    return $this;
  },
});
