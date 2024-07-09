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
  xiboFinanceRender: function(options, items, body) {
    // Default options
    const defaults = {
      effect: 'none',
      pauseEffectOnStart: true,
      speed: '2',
      duration: '30',
      durationIsPerItem: false,
      numItems: items.length,
      itemsPerPage: 5,
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
    };

    options = $.extend({}, defaults, options);

    if (!options.itemsPerPage) {
      options.itemsPerPage = 1;
    }

    // Calculate the dimensions of this itemoptions.numItems
    // based on the preview/original dimensions
    let width = height = 0;
    if (options.previewWidth === 0 || options.previewHeight === 0) {
      width = options.originalWidth;
      height = options.originalHeight;
    } else {
      width = options.previewWidth;
      height = options.previewHeight;
    }

    if (options.scaleOverride !== 0) {
      width = width / options.scaleOverride;
      height = height / options.scaleOverride;
    }

    if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
      options.widgetDesignWidth = options.widgetDesignWidth;
      options.widgetDesignHeight = options.widgetDesignHeight;
      width = options.widgetDesignWidth;
      height = options.widgetDesignHeight;
    }

    const isEditor = xiboIC.checkIsEditor();

    // For each matched element
    this.each(function(_idx, _elem) {
      // How many pages to we need?
      const numberOfPages =
        (options.numItems > options.itemsPerPage) ?
          Math.ceil(options.numItems / options.itemsPerPage) : 1;
      const $mainContainer = $(_elem);

      // Destroy any existing cycle
      $mainContainer.find('.anim-cycle')
        .cycle('destroy');

      // Remove previous content
      $mainContainer.find('.container-main:not(.template-container)').remove();

      // Clone the main HTML
      // and remove template-container class when we are on the editor
      const $mainHTML = isEditor ? $(body).clone()
        .removeClass('template-container')
        .show() : $(body);

      // Hide main HTML if isEditor = true
      if (isEditor) {
        $(body).hide();
      }

      // Create the pages
      for (let i = 0; i < numberOfPages; i++) {
        // Create a page
        const $itemsHTML = $('<div />').addClass('page');
        for (let j = 0; j < options.itemsPerPage; j++) {
          if (((i * options.itemsPerPage) + j) < options.numItems) {
            const $item = $(items[(i * options.itemsPerPage) + j]);
            // Clone and append the item to the page
            // and remove template-item class when isEditor = true
            (isEditor ? $item.clone() : $item).appendTo($itemsHTML)
              .show().removeClass('template-item');

            // Hide the original item when isEditor = true
            if (isEditor) {
              $item.hide();
            }
          }
        }

        // Append the page to the item container
        $mainHTML.find('.items-container').append($itemsHTML);
      }

      // Append the main HTML to the container
      $mainContainer.append($mainHTML);

      const duration =
        (options.durationIsPerItem) ?
          options.duration :
          options.duration / numberOfPages;

      // Make sure the speed is something sensible
      options.speed = (options.speed <= 200) ? 1000 : options.speed;

      // Timeout is the duration in ms
      const timeout = (duration * 1000) - (options.speed * 0.7);

      const slides = (numberOfPages > 1) ? '.page' : '.item';

      const $cycleContainer = $mainContainer.find('#cycle-container');

      // Set the content div to the height of the original window
      $cycleContainer.css('height', height);

      // Set the width on the cycled slides
      $cycleContainer.find(slides).css({
        width: width,
        height: height,
      });

      // Cycle handles this for us
      $cycleContainer.addClass('anim-cycle')
        .cycle({
          fx: options.effect,
          speed: options.speed,
          timeout: timeout,
          slides: '> ' + slides,
          paused: options.pauseEffectOnStart,
          log: false,
        });

      // Protect against images that don't load
      $mainContainer.find('img').on('error', function(ev) {
        $(ev.currentTarget).off('error')
          // eslint-disable-next-line max-len
          .attr('src', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNiYAAAAAkAAxkR2eQAAAAASUVORK5CYII=');
      });
    });

    return $(this);
  },
});
