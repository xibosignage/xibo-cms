// eslint-disable-next-line valid-jsdoc
/**
 * String | Number transformer
 */
const transformer = function() {
  return {
    getExtendedDataKey: function(value) {
      if (typeof value === 'undefined' || String(value).length === 0) {
        return null;
      }

      const dataKeyPrefix = 'data.';
      const dataKey = String(value);

      if (!dataKey.includes(dataKeyPrefix)) {
        return dataKey;
      }

      return dataKey.replaceAll(dataKeyPrefix, '');
    },
  };
};

// eslint-disable-next-line new-cap
module.exports = new transformer();
