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
  xiboWorldClockRender: function(options, template) {
    const worldClocks =
      (options.worldClocks != '' && options.worldClocks != null) ?
        JSON.parse(options.worldClocks) : [];
    const self = this;

    // Make replacements to the template
    const $templateHTML = $(template).html();
    $(template).html($templateHTML.replace(/\[.*?\]/g, function(match) {
      const matchContent = match.replace(/[\[\]]/g, '');
      if (matchContent == 'label') {
        return '<span class="world-clock-label"></span>';
      } else {
        return '<span class="momentClockTag" format="' +
          matchContent + '"></span>';
      }
    }));

    // Update clocks
    const updateClocks = function() {
      const timeNow = moment();

      for (let index = 0; index < worldClocks.length; index++) {
        // Get time according to timezone
        const t = timeNow.tz(worldClocks[index].clockTimezone);
        const $clockContainer = $(self).find('#clock' + index);

        $clockContainer.find('.year').html(t.year());
        $clockContainer.find('.month').html(t.month() + 1);
        $clockContainer.find('.week').html(t.week());
        $clockContainer.find('.day').html(t.date());
        $clockContainer.find('.hour').html(t.hours());
        $clockContainer.find('.minutes').html(t.minutes());
        $clockContainer.find('.seconds').html(t.seconds());

        $clockContainer.find('.momentClockTag').each(function(_k, el) {
          $(el).html(t.format($(el).attr('format')));
        });

        const secondAnalog = t.seconds() * 6;
        const minuteAnalog = t.minutes() * 6 + secondAnalog / 60;
        const hourAnalog =
          ((t.hours() % 12) / 12) * 360 + 90 + minuteAnalog / 12;

        $clockContainer.find('.analogue-clock-hour')
          .css('transform', 'rotate(' + hourAnalog + 'deg)');
        $clockContainer.find('.analogue-clock-minute')
          .css('transform', 'rotate(' + minuteAnalog + 'deg)');
        $clockContainer.find('.analogue-clock-second')
          .css('transform', 'rotate(' + secondAnalog + 'deg)');
      }
    };

    // Default options
    const defaults = {
      duration: '30',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
    };

    options = $.extend({}, defaults, options);

    // For each matched element
    this.each(function() {
      for (let index = 0; index < worldClocks.length; index++) {
        // Create new item and add a copy of the template html
        const $newItem =
          $('<div>').attr('id', 'clock' + index)
            .addClass('world-clock multi-element')
            .html($(template).html());

        // Add label or timezone name
        $newItem.find('.world-clock-label')
          .html(
            (worldClocks[index].clockLabel != '') ?
              worldClocks[index].clockLabel :
              worldClocks[index].clockTimezone,
          );

        // Check if clock has highlighted class
        if (worldClocks[index].clockHighlight) {
          $newItem.addClass('highlighted');
        }

        // Check if the element is outside the drawing area ( cols * rows )
        if ((index + 1) > (options.numCols * options.numRows)) {
          $newItem.css('display', 'none');
        }

        // Add content to the main container
        $(self).find('#content').append($newItem);
      }

      // Update clocks
      updateClocks(); // run function once at first to avoid delay

      // Update every second
      setInterval(updateClocks, 1000);
    });

    return $(this);
  },
});
