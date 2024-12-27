/**
 * String | Number transformer
 */
const transformer = function() {
  return {
    getExtendedDataKey: function(value, prefix = 'data.') {
      if (typeof value === 'undefined' || String(value).length === 0) {
        return null;
      }

      const dataKeyPrefix = prefix;
      const dataKey = String(value);

      if (!dataKey.includes(dataKeyPrefix)) {
        return dataKey;
      }

      return dataKey.replaceAll(dataKeyPrefix, '');
    },
  };
};

module.exports = new transformer();
