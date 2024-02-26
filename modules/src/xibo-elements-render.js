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
      selector: null,
      effect: 'none',
      pauseEffectOnStart: true,
      duration: 50,
      durationIsPerItem: false,
      numItems: 1,
      itemsPerPage: 1,
      speed: 2,
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      marqueeInlineSelector: '.elements-render-item',
      alignmentV: 'top',
      displayDirection: 0,
      parentId: '',
      layer: 0,
      seamless: true,
      gap: 50,
    };
    const $content = $('#content');
    const isAndroid = navigator.userAgent.indexOf('Android') > -1;

    // Is marquee effect
    const isMarquee =
        options.effect === 'marqueeLeft' ||
        options.effect === 'marqueeRight' ||
        options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown';

    const isUseNewMarquee = options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown' ||
        !isAndroid;

    options = $.extend({}, defaults, options);

    if (!isMarquee) {
      options.speed = 1000;
    } else {
      options.speed = 1;
    }

    const cycleElement = `.${options.id}`;

    if (isMarquee && isUseNewMarquee) {
      $this.marquee('destroy');
    } else if ($content.find(cycleElement + '.cycle-slideshow').length) {
      $(cycleElement + '.cycle-slideshow').cycle('destroy');
    }

    let marquee = false;

    if (options.effect === 'none') {
      // Do nothing
    } else if (!isMarquee && $content.find(cycleElement).length) {
      const numberOfSlides = items?.length || 1;
      const duration = (options.durationIsPerItem) ?
        options.duration :
        options.duration / numberOfSlides;
      const timeout = duration * 1000;
      const noTransitionSpeed = 200;
      let cycle2Config = {
        'data-cycle-fx': (options.effect === 'noTransition' ||
          options.effect === 'none') ? 'none' : options.effect,
        'data-cycle-speed': (
          options.effect === 'noTransition' || options.effect === 'none'
        ) ? noTransitionSpeed : options.speed,
        'data-cycle-timeout': timeout,
        'data-cycle-slides': `> .${options.id}--item`,
        'data-cycle-auto-height': false,
        'data-cycle-paused': options.pauseEffectOnStart,
      };

      if (options.effect === 'scrollHorz') {
        $(cycleElement).find(`> .${options.id}--item`)
          .each(function(idx, elem) {
            $(elem).css({width: '-webkit-fill-available'});
          });
      } else {
        cycle2Config = {
          ...cycle2Config,
          'data-cycle-sync': false,
        };
      }

      $(cycleElement).addClass('cycle-slideshow anim-cycle')
        .attr(cycle2Config).cycle();

      // Add some margin for each slide when options.effect === scrollHorz
      if (options.effect === 'scrollHorz') {
        $(cycleElement).css({width: options.width + (options.gap / 2)});
        $(cycleElement).find('.cycle-slide').css({
          marginLeft: options.gap / 4,
          marginRight: options.gap / 4,
        });
      }
    } else if (
      options.effect === 'marqueeLeft' ||
      options.effect === 'marqueeRight'
    ) {
      marquee = true;
      options.direction =
        ((options.effect === 'marqueeLeft') ? 'left' : 'right');

      // Make sure the speed is something sensible
      // This speed calculation gives as 80 pixels per second
      options.speed = (options.speed === 0) ? 1 : options.speed;

      // Add gap between
      if ($this.find('.scroll').length > 0) {
        $this.find('.scroll').css({
          paddingLeft: !options.seamless ? options.gap : 0,
          paddingRight: !options.seamless ? options.gap : 0,
          columnGap: options.gap,
        });
      }
    } else if (
      options.effect === 'marqueeUp' ||
      options.effect === 'marqueeDown'
    ) {
      // We want a marquee
      marquee = true;
      options.direction = ((options.effect === 'marqueeUp') ? 'up' : 'down');

      // Make sure the speed is something sensible
      // This speed calculation gives as 80 pixels per second
      options.speed = (options.speed === 0) ?
        1 : options.speed;

      if ($this.find('.scroll').length > 0) {
        $this.find('.scroll').css({
          flexDirection: 'column',
          height: 'auto',
        });
      }
    }

    if (marquee) {
      if (isUseNewMarquee) {
        // in old marquee scroll delay is 85 milliseconds
        // options.speed is the scrollamount
        // which is the number of pixels per 85 milliseconds
        // our new plugin speed is pixels per second
        $this.attr({
          'data-is-legacy': false,
          'data-speed': options.speed / 25 * 1000,
          'data-direction': options.direction,
          'data-duplicated': options.seamless,
          'data-gap': options.gap,
        }).marquee().addClass('animating');
      } else {
        let $scroller = $this.find('.scroll:not(.animating)');

        if ($scroller.length !== 0) {
          $scroller.attr({
            'data-is-legacy': true,
            scrollamount: options.speed,
            behaviour: 'scroll',
            direction: options.direction,
            height: options.height,
            width: options.width,
          }).overflowMarquee().addClass('animating scroll');

          $scroller = $this.find('.scroll.animating');
          // Correct items alignment as $scroller styles are overridden
          // after initializing overflowMarquee
          if (options.effect === 'marqueeLeft' ||
              options.effect === 'marqueeRight'
          ) {
            $scroller.find('> div').css({
              display: 'flex',
              flexDirection: 'row',
            });
          }
        }
      }

      // Correct for up / down
      if (
        options.effect === 'marqueeUp' ||
        options.effect === 'marqueeDown'
      ) {
        $this.find('.js-marquee').css({marginBottom: 0});
      }
    }

    return $this;
  },
});
