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
  xiboTextRender: function(options, items) {
    // Default options
    const defaults = {
      effect: 'none',
      pauseEffectOnStart: true,
      duration: '50',
      durationIsPerItem: false,
      numItems: 1,
      itemsPerPage: 0,
      speed: '2',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      randomiseItems: 0,
      marqueeInlineSelector: '.text-render-item, .text-render-item p',
      alignmentV: 'top',
      widgetDesignWidth: 0,
      widgetDesignHeight: 0,
      widgetDesignGap: 0,
      displayDirection: 0,
      seamless: true,
    };

    options = $.extend({}, defaults, options);

    const resetRenderElements = function($contentDiv) {
      // Remove item classes
      $contentDiv.find('.text-render-item').removeClass('text-render-item');

      // Remove animation items
      $contentDiv.find('.text-render-anim-item').remove();

      // If options is seamless, remove second .scroll marquee div
      // so we don't have duplicated elements
      if (
        options.seamless &&
        $contentDiv.find('.scroll .js-marquee').length > 1
      ) {
        $contentDiv.find('.scroll .js-marquee')[1].remove();
      }

      // Show and reset the hidden elements
      const $originalElements =
        $contentDiv.find('.text-render-hidden-element');
      $originalElements.removeClass('text-render-hidden-element').show();

      // If we have a scroll container, move elements
      // to content and destroy container
      if ($contentDiv.find('.scroll').length > 0) {
        $originalElements.appendTo($contentDiv);
        $contentDiv.find('.scroll').remove();
      }
    };

    // If number of items is not defined, get it from the item count
    options.numItems = options.numItems ? options.numItems : items.length;

    // Calculate the dimensions of this item
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

    let paddingBottom = paddingRight = 0;
    if (options.widgetDesignWidth > 0 && options.widgetDesignHeight > 0) {
      if (options.itemsPerPage > 0) {
        if (
          (
            $(window).width() >= $(window).height() &&
            options.displayDirection == '0'
          ) ||
          (options.displayDirection == '1')
        ) {
          // Landscape or square size plus padding
          options.widgetDesignWidth =
            (options.itemsPerPage * options.widgetDesignWidth) +
            (options.widgetDesignGap * (options.itemsPerPage - 1));
          options.widgetDesignHeight = options.widgetDesignHeight;
          width = options.widgetDesignWidth;
          height = options.widgetDesignHeight;
          paddingRight = options.widgetDesignGap;
        } else if (
          (
            $(window).width() < $(window).height() &&
            options.displayDirection == '0'
          ) ||
          (options.displayDirection == '2')
        ) {
          // Portrait size plus padding
          options.widgetDesignHeight =
            (options.itemsPerPage * options.widgetDesignHeight) +
            (options.widgetDesignGap * (options.itemsPerPage - 1));
          options.widgetDesignWidth = options.widgetDesignWidth;
          width = options.widgetDesignWidth;
          height = options.widgetDesignHeight;
          paddingBottom = options.widgetDesignGap;
        }
      }
    }

    const isAndroid = navigator.userAgent.indexOf('Android') > -1;

    // For each matched element
    this.each(function(_key, element) {
      // console.log("[Xibo] Selected: " + this.tagName.toLowerCase());
      // console.log("[Xibo] Options: " + JSON.stringify(options));

      const $contentDiv = $(element).find('#content');

      // Is marquee effect
      const isMarquee =
        options.effect === 'marqueeLeft' ||
        options.effect === 'marqueeRight' ||
        options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown';

      const isUseNewMarquee = options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown' ||
        !isAndroid;

      // Reset the animation elements
      resetRenderElements($contentDiv);

      // Store the number of items (we might change this to number of pages)
      let numberOfItems = options.numItems;

      // How many pages to we need?
      // if there's no effect, we don't need any pages
      const numberOfPages =
        (options.itemsPerPage > 0 && options.effect !== 'none') ?
          Math.ceil(options.numItems / options.itemsPerPage) :
          options.numItems;

      let itemsThisPage = 1;

      // console.log("[Xibo] We need to have " + numberOfPages + " pages");
      let appendTo = $contentDiv;

      // Clear previous animation elements
      if (isMarquee && isUseNewMarquee) {
        $contentDiv.marquee('destroy');
      } else {
        // Destroy cycle plugin
        $(element).find('.anim-cycle').cycle('destroy');
      }

      // If we have animations
      // Loop around each of the items we have been given
      // and append them to this element (in a div)
      if (options.effect !== 'none') {
        for (let i = 0; i < items.length; i++) {
          // We don't add any pages for marquee
          if (!isMarquee) {
            // If we need to set pages, have we switched over to a new page?
            if (
              options.itemsPerPage > 1 &&
              (itemsThisPage >= options.itemsPerPage || i === 0)
            ) {
              // Append a new page to the body
              appendTo = $('<div/>')
                .addClass('text-render-page text-render-anim-item')
                .appendTo($contentDiv);

              // Reset the row count on this page
              itemsThisPage = 0;
            }
          }

          // For each item, create a DIV if element doesn't exist on the DOM
          // Or clone the element if it does
          // hide the original and show the clone
          let $newItem;
          let $oldItem;
          if ($.contains(element, items[i])) {
            $oldItem = $(items[i]);
            $newItem = $oldItem.clone();
          } else {
            $oldItem = null;
            $newItem = $('<div/>').html(items[i]);
          }

          // Hide and mark as hidden the original element
          ($oldItem) && $oldItem.hide().addClass('text-render-hidden-element');

          // Append the item to the page
          $newItem
            .addClass('text-render-item text-render-anim-item')
            .appendTo(appendTo);

          itemsThisPage++;
        }
      }

      // 4th objective - move the items around, start the timer
      // settings involved:
      //  fx (the way we are moving effects the HTML required)
      //  speed (how fast we need to move)
      let marquee = false;

      if (options.effect === 'none') {
        // Do nothing
      } else if (!isMarquee) {
        // Make sure the speed is something sensible
        options.speed = (options.speed <= 200) ? 1000 : options.speed;

        // Cycle slides are either page or item
        let slides =
          (options.itemsPerPage > 1) ?
            '.text-render-page' :
            '.text-render-item';

        // If we only have 1 item, then
        // we are in trouble and need to duplicate it.
        if ($(slides).length <= 1 && options.type === 'text') {
          // Change our slide tag to be the paragraphs inside
          slides = slides + ' p';

          // Change the number of items
          numberOfItems = $(slides).length;
        } else if (options.type === 'text') {
          numberOfItems = $(slides).length;
        }

        const numberOfSlides = (options.itemsPerPage > 1) ?
          numberOfPages :
          numberOfItems;
        const duration = (options.durationIsPerItem) ?
          options.duration :
          options.duration / numberOfSlides;

        // console.log("[Xibo] initialising the cycle2 plugin with "
        // + numberOfSlides + " slides and selector " + slides +
        // ". Duration per slide is " + duration + " seconds.");

        // Set the content div to the height of the original window
        $contentDiv.css('height', height);

        // Set the width on the cycled slides
        $(slides, $contentDiv).css({
          width: width,
          height: height,
        });

        let timeout = duration * 1000;
        const noTransitionSpeed = 10;

        if (options.effect !== 'noTransition') {
          timeout = timeout - options.speed;
        } else {
          timeout = timeout - noTransitionSpeed;
        }

        // Cycle handles this for us
        $contentDiv.addClass('anim-cycle').cycle({
          fx: (options.effect === 'noTransition') ? 'none' : options.effect,
          speed: (options.effect === 'noTransition') ?
            noTransitionSpeed : options.speed,
          timeout: timeout,
          slides: '> ' + slides,
          autoHeight: false, // To fix the rogue sentinel issue
          paused: options.pauseEffectOnStart,
          log: false,
        });
      } else if (
        options.effect === 'marqueeLeft' ||
        options.effect === 'marqueeRight'
      ) {
        marquee = true;
        options.direction =
          ((options.effect === 'marqueeLeft') ? 'left' : 'right');

        // Make sure the speed is something sensible
        options.speed = (options.speed === 0) ? 1 : options.speed;

        // Stack the articles up and move them across the screen
        $(
          options.marqueeInlineSelector + ':not(.text-render-hidden-element)',
          $contentDiv,
        ).each(function(_idx, _el) {
          if (!$(_el).hasClass('text-render-hidden-element')) {
            $(_el).css({
              display: 'inline-block',
              'padding-left': '10px',
            });
          }
        });
      } else if (
        options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown'
      ) {
        // We want a marquee
        marquee = true;
        options.direction = ((options.effect === 'marqueeUp') ? 'up' : 'down');

        // Make sure the speed is something sensible
        options.speed = (options.speed === 0) ? 1 : options.speed;

        // Set the content div height, if we don't do this when the marquee
        // plugin floats the content inside, this goes to 0 and up/down
        // marquees don't work
        $contentDiv.css('height', height);
      }

      if (marquee) {
        // Create a DIV to scroll, and put this inside the body
        const scroller = $('<div/>')
          .addClass('scroll');

        if (isUseNewMarquee) {
          // in old marquee scroll delay is 85 milliseconds
          // options.speed is the scrollamount
          // which is the number of pixels per 85 milliseconds
          // our new plugin speed is pixels per second
          scroller.attr({
            'data-is-legacy': false,
            'data-speed': options.speed / 25 * 1000,
            'data-direction': options.direction,
            'data-duplicated': options.seamless,
            scaleFactor: options.scaleFactor,
          });
        } else {
          scroller.attr({
            'data-is-legacy': true,
            scrollamount: options.speed,
            scaleFactor: options.scaleFactor,
            behaviour: 'scroll',
            direction: options.direction,
            height: height,
            width: width,
          });
        }

        $contentDiv.wrapInner(scroller);

        // Correct for up / down
        if (
          options.effect === 'marqueeUp' ||
          options.effect === 'marqueeDown'
        ) {
          // Set the height of the scroller to 100%
          $contentDiv.find('.scroll')
            .css('height', '100%')
            .children()
            .css({'white-space': 'normal', float: 'none'});
        }

        if (!options.pauseEffectOnStart) {
          // Set some options on the extra DIV and make it a marquee
          if (isUseNewMarquee) {
            $contentDiv.find('.scroll').marquee();
          } else {
            $contentDiv.find('.scroll').overflowMarquee();
          }

          // Add animating class to prevent multiple inits
          $contentDiv.find('.scroll').addClass('animating');
        }
      }

      // Add aditional padding to the items
      if (paddingRight > 0 || paddingBottom > 0) {
        // Add padding to all item elements
        $('.text-render-item').css(
          'padding',
          '0px ' + paddingRight + 'px ' + paddingBottom + 'px 0px',
        );

        // Exclude the last item on the page and
        // the last on the content ( if there are no pages )
        $('.text-render-page .text-render-item:last-child').css('padding', 0);
        $('#content .text-render-item:last').css('padding', 0);
      }

      // Align the whole thing according to vAlignment
      if (options.type && options.type === 'text') {
        // The timeout just yields a bit to let our content get rendered
        setTimeout(function() {
          if (options.alignmentV === 'bottom') {
            $contentDiv.css(
              'margin-top',
              $(window).height() -
              ($contentDiv.height() * $('body').data().ratio),
            );
          } else if (options.alignmentV === 'middle') {
            $contentDiv.css(
              'margin-top',
              (
                $(window).height() -
                ($contentDiv.height() * $('body').data().ratio)
              ) / 2,
            );
          }
        }, 500);
      }
    });

    return $(this);
  },
});
