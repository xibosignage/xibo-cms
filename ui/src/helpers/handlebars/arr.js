module.exports = function(...args) {
  return Array.from(args).slice(0, arguments.length - 1);
};
