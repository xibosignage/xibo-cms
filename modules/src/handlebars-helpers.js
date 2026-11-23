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

/* eslint-disable no-invalid-this */
Handlebars.registerHelper('eq', function(v1, v2, opts) {
  if (v1 === v2) {
    return opts.fn(this);
  } else {
    return opts.inverse(this);
  }
});

Handlebars.registerHelper('neq', function(v1, v2, opts) {
  if (v1 !== v2) {
    return opts.fn(this);
  } else {
    return opts.inverse(this);
  }
});

Handlebars.registerHelper('set', function(varName, varValue, opts) {
  if (!opts.data.root) {
    opts.data.root = {};
  }

  opts.data.root[varName] = varValue;
});

Handlebars.registerHelper('parseJSON', function(varName, varValue, opts) {
  if (!opts.data.root) {
    opts.data.root = {};
  }

  try {
    opts.data.root[varName] = JSON.parse(varValue);
  } catch (error) {
    console.warn(error);
    opts.data.root = {};
  }
});

Handlebars.registerHelper('createGradientInSVG', function(
  gradient,
  uniqueId,
) {
  if (gradient == '') {
    return '';
  }

  const gradientObj = JSON.parse(gradient);

  if (gradientObj.type === 'linear') {
    // Convert angle to radians
    const radians = (gradientObj.angle - 90) * Math.PI / 180;

    // Calculate x and y components
    const x = Math.cos(radians);
    const y = Math.sin(radians);

    // Determine x1, x2, y1, y2 points
    const x1 = 0.5 - 0.5 * x;
    const x2 = 0.5 + 0.5 * x;
    const y1 = 0.5 - 0.5 * y;
    const y2 = 0.5 + 0.5 * y;

    return `<linearGradient id="gradLinear_${uniqueId}"
      x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}">
      <stop offset="0%" style="stop-color:${gradientObj.color1};" />
      <stop offset="100%" style="stop-color:${gradientObj.color2};" />
      </linearGradient>`;
  } else {
    // Radial
    return `<radialGradient id="gradRadial_${uniqueId}"
      cx="50%" cy="50%" r="50%" fx="50%" fy="50%">
      <stop offset="0%" style="stop-color:${gradientObj.color1};" />
      <stop offset="100%" style="stop-color:${gradientObj.color2};" />
      </radialGradient>`;
  }
});

Handlebars.registerHelper('weatherBackgroundImage', function(
  icon,
  cloudyImage,
  dayCloudyImage,
  dayClearImage,
  fogImage,
  hailImage,
  nightClearImage,
  nightPartlyCloudyImage,
  rainImage,
  snowImage,
  windImage,
  opts,
) {
  let bgImage = false;

  if ((typeof cloudyImage !== 'undefined' && cloudyImage !== '') &&
    icon === 'cloudy') {
    bgImage = cloudyImage;
  } else if ((typeof dayCloudyImage !== 'undefined' && dayCloudyImage !== '') &&
    icon === 'partly-cloudy-day') {
    bgImage = dayCloudyImage;
  } else if ((typeof dayClearImage !== 'undefined' && dayClearImage !== '') &&
    icon === 'clear-day') {
    bgImage = dayClearImage;
  } else if ((typeof fogImage !== 'undefined' && fogImage !== '') &&
    icon === 'fog') {
    bgImage = fogImage;
  } else if ((typeof hailImage !== 'undefined' && hailImage !== '') &&
    icon === 'sleet') {
    bgImage = hailImage;
  } else if ((typeof nightClearImage !== 'undefined' &&
    nightClearImage !== '') && icon === 'clear-night') {
    bgImage = nightClearImage;
  } else if ((typeof nightPartlyCloudyImage !== 'undefined' &&
    nightPartlyCloudyImage !== '') && icon === 'partly-cloudy-night') {
    bgImage = nightPartlyCloudyImage;
  } else if ((typeof rainImage !== 'undefined' && rainImage !== '') &&
    icon === 'rain') {
    bgImage = rainImage;
  } else if ((typeof snowImage !== 'undefined' && snowImage !== '') &&
    icon === 'snow') {
    bgImage = snowImage;
  } else if ((typeof windImage !== 'undefined' && windImage !== '') &&
    icon === 'wind') {
    bgImage = windImage;
  }

  // If it's the media id, replace with path to be rendered
  if (bgImage && !isNaN(bgImage) && imageDownloadUrl) {
    bgImage = imageDownloadUrl.replace(':id', bgImage);
  }

  return bgImage;
});
