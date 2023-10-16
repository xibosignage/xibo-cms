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

  return bgImage;
});
