const DateFormatHelper = function(options) {
  this.timezone = null;
  this.systemFormat = 'Y-m-d H:i:s';
  this.macroRegex = /^%(\+|\-)[0-9]([0-9])?(d|h|m|s)%$/gi;

  this.convertPhpToMomentFormat = function(format) {
    if (String(format).length === 0) {
      return '';
    }

    const replacements = {
      d: 'DD',
      D: 'ddd',
      j: 'D',
      l: 'dddd',
      N: 'E',
      S: 'o',
      w: 'e',
      z: 'DDD',
      W: 'W',
      F: 'MMMM',
      m: 'MM',
      M: 'MMM',
      n: 'M',
      t: '', // no equivalent
      L: '', // no equivalent
      o: 'YYYY',
      Y: 'YYYY',
      y: 'YY',
      a: 'a',
      A: 'A',
      B: '', // no equivalent
      g: 'h',
      G: 'H',
      h: 'hh',
      H: 'HH',
      i: 'mm',
      s: 'ss',
      u: 'SSS',
      e: 'zz', // deprecated since version 1.6.0 of moment.js
      I: '', // no equivalent
      O: '', // no equivalent
      P: '', // no equivalent
      T: '', // no equivalent
      Z: '', // no equivalent
      c: '', // no equivalent
      r: '', // no equivalent
      U: 'X',
    };
    let convertedFormat = '';

    String(format).split('').forEach(function(char) {
      if (Object.keys(replacements).indexOf(char) === -1) {
        convertedFormat += char;
      } else {
        convertedFormat += replacements[char];
      }
    });

    return convertedFormat;
  };

  this.composeUTCDateFromMacro = function(macroStr) {
    const utcFormat = 'YYYY-MM-DDTHH:mm:ssZ';
    const dateNow = moment().utc();
    // Check if input has the correct format
    const dateStr = String(macroStr);

    if (dateStr.length === 0 ||
        dateStr.match(this.macroRegex) === null
    ) {
      return dateNow.format(utcFormat);
    }

    // Trim the macro date string
    const dateOffsetStr = dateStr.replaceAll('%', '');
    const params = (op) => dateOffsetStr.replace(op, '')
      .split(/(\d+)/).filter(Boolean);
    const addRegex = /^\+/g;
    const subtractRegex = /^\-/g;

    // Check if it's add or subtract offset and return composed date
    if (dateOffsetStr.match(addRegex) !== null) {
      return dateNow.add(...params(addRegex)).format(utcFormat);
    } else if (dateOffsetStr.match(subtractRegex) !== null) {
      return dateNow.subtract(...params(subtractRegex)).format(utcFormat);
    }
  };

  this.formatDate = function(dateStr, format) {
    return moment(dateStr).format(format ? format : this.systemFormat);
  };

  return this;
};

module.exports = new DateFormatHelper();
