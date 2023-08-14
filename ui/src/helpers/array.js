const ArrayHelper = function() {
  return {
    /**
     * Function to move item of an array by index
     * @param arr {Array} - Input array
     * @param from {number} - Original array index
     * @param to {number} - New array index
     * @return {Array}
     */
    move: function(arr, from, to) {
      if (arr === undefined) {
        console.warn('Please provide a valid array parameter');
        return [];
      }

      if (arr.length === 0) {
        return arr;
      }
      // Check if indexes: from and to are within the array
      if (from >= arr.length || to >= arr.length) {
        return arr;
      }

      // Store arr[from]
      const temp = arr[from];

      arr.splice(from, 1);
      arr.splice(to, 0, temp);

      return arr;
    },
  };
};

module.exports = new ArrayHelper([]);
