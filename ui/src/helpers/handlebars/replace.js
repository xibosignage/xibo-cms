module.exports = function(str, search, replacement) {
  if (typeof str !== 'string') {
    return str; // Not a string, return original
  }

  return str.replace(new RegExp(search, 'g'), replacement);
};
