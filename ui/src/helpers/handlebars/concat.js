module.exports = function(...args) {
  // if last argument is handlebars option, pop it
  if (typeof args[args.length - 1] === 'object') {
    args.pop();
  }
  return args.join('');
};
